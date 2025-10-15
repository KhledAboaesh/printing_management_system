<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'sales') {
//     header("Location: login.php");
//     exit();
// }

define('INCLUDED', true);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// جلب قائمة العملاء والمنتجات
$customers = $db->query("SELECT customer_id, name FROM customers")->fetchAll();
$products = $db->query("SELECT item_id, name, selling_price FROM inventory")->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->beginTransaction(); // بدء المعاملة هنا
    
    try {
        $customer_id = $_POST['customer_id'] ?? null;
        $order_date = date('Y-m-d H:i:s');
        $required_date = $_POST['required_date'] ?? null;
        $notes = $_POST['notes'] ?? '';
        
        // تحقق من وجود عناصر الطلب وتنسيقها الصحيح
        $items = [];
        if (!empty($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['id']) && !empty($item['qty']) && $item['qty'] > 0) {
                    $items[] = [
                        'id' => (int)$item['id'],
                        'qty' => (int)$item['qty'],
                        'price' => (float)($item['price'] ?? 0)
                    ];
                }
            }
        }

        if (empty($customer_id)) {
            throw new Exception("حقل العميل مطلوب");
        }
        
        if (empty($items)) {
            throw new Exception("يجب إضافة منتجات على الأقل");
        }

        // إضافة الطلب
        $stmt = $db->prepare("INSERT INTO orders 
                            (customer_id, order_date, required_date, notes, created_by) 
                            VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$customer_id, $order_date, $required_date, $notes, $_SESSION['user_id']]);
        $order_id = $db->lastInsertId();

        // إضافة عناصر الطلب
        foreach ($items as $item) {
            // التحقق من توفر الكمية في المخزون
            $inventory = $db->query("SELECT current_quantity, selling_price FROM inventory 
                                    WHERE item_id = {$item['id']}")->fetch();
            
            if (!$inventory) {
                throw new Exception("المنتج المحدد غير موجود");
            }
            
            if ($inventory['current_quantity'] < $item['qty']) {
                throw new Exception("الكمية المطلوبة غير متوفرة في المخزون للمنتج ID: {$item['id']}");
            }
            
            $stmt = $db->prepare("INSERT INTO order_items 
                                (order_id, item_id, quantity, unit_price) 
                                VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $order_id, 
                $item['id'], 
                $item['qty'], 
                $inventory['selling_price'] // استخدام السعر من قاعدة البيانات
            ]);
            
            // تحديث المخزون
            $db->query("UPDATE inventory SET current_quantity = current_quantity - {$item['qty']} 
                       WHERE item_id = {$item['id']}");
        }

        $db->commit();
        $success = "تم إضافة الطلب رقم #$order_id بنجاح";
        logActivity($_SESSION['user_id'], 'add_order', "تم إنشاء طلب جديد رقم $order_id");

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "حدث خطأ: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة طلب جديد - نظام المطبعة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .form-container {
            max-width: 1000px;
            margin: 20px auto;
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .form-title {
            color: #4361ee;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 15px;
        }
        
        .btn-primary {
            background: #4361ee;
            color: white;
            border: none;
        }
        
        .btn-success {
            background: #4cc9f0;
            color: white;
            border: none;
        }
        
        .order-items {
            margin-top: 30px;
        }
        
        .item-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .item-select {
            flex: 2;
        }
        
        .item-qty {
            flex: 1;
        }
        
        .item-price {
            flex: 1;
        }
        
        .remove-item {
            color: #f72585;
            cursor: pointer;
        }
        
        #add-item {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <h1>إضافة طلب جديد</h1>
                <?php include 'includes/user-menu.php'; ?>
            </div>
            
            <div class="form-container">
                <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                
                <h2 class="form-title"><i class="fas fa-cart-plus"></i> بيانات الطلب الأساسية</h2>
                
                <form method="POST" id="order-form">
                   <div class="form-group" style="position: relative;">
    <label for="customer_search" class="form-label">العميل <span class="text-danger">*</span></label>
    <input type="text" id="customer_search" class="form-control" placeholder="اكتب الاسم، الهاتف أو البريد..." autocomplete="off" required>
    <input type="hidden" id="customer_id" name="customer_id">
    <div id="customer_results" class="search-results" 
         style="position: absolute; z-index: 999; background: #fff; width: 100%; max-height: 200px; overflow-y: auto; border: 1px solid #ccc;"></div>
</div>
                    
                    <div class="form-group">
                        <label for="required_date" class="form-label">تاريخ التسليم المطلوب</label>
                        <input type="date" id="required_date" name="required_date" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="notes" class="form-label">ملاحظات</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <h3 class="form-title"><i class="fas fa-boxes"></i> عناصر الطلب</h3>
                    
                    <div class="order-items" id="order-items">
                        <!-- العناصر ستضاف هنا عبر JavaScript -->
                    </div>
                    
                    <button type="button" id="add-item" class="btn btn-success">
                        <i class="fas fa-plus"></i> إضافة منتج
                    </button>
                    
                    <div class="form-group" style="margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ الطلب
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- قالب عنصر الطلب (يستخدم في JavaScript) -->
    <template id="item-template">
        <div class="item-row">
            <div class="item-select">
                <select name="items[][id]" class="form-control item-id" required>
                    <option value="">اختر منتجاً</option>
                    <?php foreach ($products as $product): ?>
                    <option value="<?= $product['item_id'] ?>" 
                            data-price="<?= $product['selling_price'] ?>">
                        <?= $product['name'] ?> (<?= $product['selling_price'] ?> د.ل)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="item-qty">
                <input type="number" name="items[][qty]" class="form-control" min="1" value="1" required>
            </div>
            <div class="item-price">
                <input type="text" class="form-control item-price-input" readonly>
            </div>
            <div class="remove-item">
                <i class="fas fa-trash-alt"></i>
            </div>
        </div>
    </template>

    <script>
        document.getElementById('order-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const items = [];
    
    document.querySelectorAll('.item-row').forEach((row, index) => {
        const id = row.querySelector('.item-id').value;
        const qty = row.querySelector('input[name$="[qty]"]').value;
        const price = row.querySelector('.item-price-input').value;
        
        if (id && qty) {
            items.push({
                id: id,
                qty: qty,
                price: price
            });
        }
    });
    
    // إضافة العناصر كبيانات FormData
    items.forEach((item, index) => {
        formData.append(`items[${index}][id]`, item.id);
        formData.append(`items[${index}][qty]`, item.qty);
        formData.append(`items[${index}][price]`, item.price);
    });
    
    // إرسال البيانات عبر fetch
    fetch(this.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        document.body.innerHTML = data;
    })
    .catch(error => console.error('Error:', error));
});
        document.addEventListener('DOMContentLoaded', function() {
        const orderItems = document.getElementById('order-items');
        const template = document.getElementById('item-template');
        
        // إضافة عنصر جديد
        document.getElementById('add-item').addEventListener('click', function() {
            const clone = template.content.cloneNode(true);
            const newItem = clone.querySelector('.item-row');
            orderItems.appendChild(clone);
            
            // تحديث السعر عند اختيار منتج
            const select = newItem.querySelector('.item-id');
            const priceInput = newItem.querySelector('.item-price-input');
            
            select.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const price = selectedOption.getAttribute('data-price') || '0';
                priceInput.value = price;
                
                // تحديث حقل price المخفي (إذا كنت تستخدمه)
                const qtyInput = newItem.querySelector('input[name$="[qty]"]');
                const priceHiddenInput = document.createElement('input');
                priceHiddenInput.type = 'hidden';
                priceHiddenInput.name = this.name.replace('[id]', '[price]');
                priceHiddenInput.value = price;
                newItem.appendChild(priceHiddenInput);
            });
            
            // تشغيل حدث change مباشرة لتعيين القيم الابتدائية
            select.dispatchEvent(new Event('change'));
        });

        // حذف عنصر
        orderItems.addEventListener('click', function(e) {
            if (e.target.closest('.remove-item')) {
                e.target.closest('.item-row').remove();
            }
        });

        // إضافة أول عنصر تلقائياً
        document.getElementById('add-item').click();
    });




    const searchInput = document.getElementById('customer_search');
const resultsDiv = document.getElementById('customer_results');
const hiddenCustomerId = document.getElementById('customer_id');

let debounceTimeout;

searchInput.addEventListener('input', function() {
    clearTimeout(debounceTimeout);
    const query = this.value.trim();
    if (query.length < 1) {
        resultsDiv.innerHTML = '';
        hiddenCustomerId.value = '';
        return;
    }

    debounceTimeout = setTimeout(() => {
        fetch('search_customers.php?term=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(data => {
                resultsDiv.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(customer => {
                        const div = document.createElement('div');
                        div.classList.add('search-result-item');
                        div.style.padding = '5px 10px';
                        div.style.cursor = 'pointer';
                        div.textContent = `${customer.name} | ${customer.phone} | ${customer.email}`;
                        div.addEventListener('click', () => {
                            searchInput.value = customer.name;
                            hiddenCustomerId.value = customer.customer_id;
                            resultsDiv.innerHTML = '';
                        });
                        resultsDiv.appendChild(div);
                    });
                } else {
                    resultsDiv.innerHTML = '<div style="padding:5px;">لا توجد نتائج</div>';
                    hiddenCustomerId.value = '';
                }
            });
    }, 300);
});

document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
        resultsDiv.innerHTML = '';
    }
});
    </script>
</body>
</html>