<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (المحاسبة)
if ($_SESSION['role'] != 'accounting' && $_SESSION['role'] != 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// جلب بيانات الفواتير والمنتجات
try {
    global $db;
    
    // جلب الفواتير
    $invoices_stmt = $db->query("
        SELECT i.invoice_id, i.invoice_number, i.created_at, c.name as customer_name 
        FROM invoices i 
        LEFT JOIN customers c ON i.customer_id = c.customer_id 
        ORDER BY i.created_at DESC
    ");
    $invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب المنتجات من المخزون
    $products_stmt = $db->query("
        SELECT item_id, name, price, stock_quantity 
        FROM inventory 
        WHERE is_active = 1 
        ORDER BY name
    ");
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب بنود الفاتورة إذا كان هناك معرف فاتورة في الرابط
    $invoice_items = [];
    $current_invoice = null;
    
    if (isset($_GET['invoice_id']) && is_numeric($_GET['invoice_id'])) {
        $invoice_id = $_GET['invoice_id'];
        
        // جلب بيانات الفاتورة
        $invoice_stmt = $db->prepare("
            SELECT i.*, c.name as customer_name, c.tax_number
            FROM invoices i 
            LEFT JOIN customers c ON i.customer_id = c.customer_id 
            WHERE i.invoice_id = ?
        ");
        $invoice_stmt->execute([$invoice_id]);
        $current_invoice = $invoice_stmt->fetch(PDO::FETCH_ASSOC);
        
        // جلب بنود الفاتورة
        $items_stmt = $db->prepare("
            SELECT ii.*, i.name as product_name, i.price as unit_price
            FROM invoice_items ii 
            LEFT JOIN inventory i ON ii.product_id = i.item_id 
            WHERE ii.invoice_id = ?
        ");
        $items_stmt->execute([$invoice_id]);
        $invoice_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // معالجة إضافة بند فاتورة جديد
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_item'])) {
        $invoice_id = $_POST['invoice_id'];
        $product_id = $_POST['product_id'];
        $quantity = $_POST['quantity'];
        $price = $_POST['price'];
        $discount = $_POST['discount'] ?? 0;
        $tax = $_POST['tax'] ?? 0;
        
        // حساب المبلغ الإجمالي
        $subtotal = $quantity * $price;
        $discount_amount = $subtotal * ($discount / 100);
        $tax_amount = ($subtotal - $discount_amount) * ($tax / 100);
        $total = $subtotal - $discount_amount + $tax_amount;
        
        // إدخال البند في قاعدة البيانات
        $stmt = $db->prepare("
            INSERT INTO invoice_items (invoice_id, product_id, quantity, unit_price, discount_percent, tax_percent, total_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$invoice_id, $product_id, $quantity, $price, $discount, $tax, $total]);
        
        // تحديث إجمالي الفاتورة
        $update_stmt = $db->prepare("
            UPDATE invoices 
            SET total_amount = (
                SELECT SUM(total_amount) 
                FROM invoice_items 
                WHERE invoice_id = ?
            ) 
            WHERE invoice_id = ?
        ");
        $update_stmt->execute([$invoice_id, $invoice_id]);
        
        // تسجيل النشاط
        logActivity($_SESSION['user_id'], 'add_invoice_item', 'إضافة بند جديد للفاتورة رقم ' . $invoice_id);
        
        // إعادة التوجيه لتجنب إعادة إرسال النموذج
        header("Location: invoice_items.php?invoice_id=" . $invoice_id);
        exit();
    }
    
    // معالجة حذف بند فاتورة
    if (isset($_GET['delete_item']) && is_numeric($_GET['delete_item'])) {
        $item_id = $_GET['delete_item'];
        $invoice_id = $_GET['invoice_id'];
        
        $stmt = $db->prepare("DELETE FROM invoice_items WHERE item_id = ?");
        $stmt->execute([$item_id]);
        
        // تحديث إجمالي الفاتورة
        $update_stmt = $db->prepare("
            UPDATE invoices 
            SET total_amount = (
                SELECT COALESCE(SUM(total_amount), 0) 
                FROM invoice_items 
                WHERE invoice_id = ?
            ) 
            WHERE invoice_id = ?
        ");
        $update_stmt->execute([$invoice_id, $invoice_id]);
        
        // تسجيل النشاط
        logActivity($_SESSION['user_id'], 'delete_invoice_item', 'حذف بند من الفاتورة رقم ' . $invoice_id);
        
        header("Location: invoice_items.php?invoice_id=" . $invoice_id);
        exit();
    }
    
} catch (PDOException $e) {
    error_log('Invoice Items Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - بنود الفواتير</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --bg-color: #f8f9fa;
            --text-color: #333;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tajawal', sans-serif;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .page-title {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--secondary-color);
            text-align: center;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
            color: var(--secondary-color);
        }
        
        .invoice-list {
            list-style: none;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .invoice-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .invoice-item:hover {
            background-color: #f5f5f5;
        }
        
        .invoice-item.active {
            background-color: #e3f2fd;
            border-right: 4px solid var(--primary-color);
        }
        
        .invoice-number {
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .invoice-customer {
            color: #666;
            font-size: 14px;
        }
        
        .invoice-date {
            color: #999;
            font-size: 12px;
        }
        
        .invoice-details {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .invoice-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .info-group label {
            font-weight: bold;
            color: #666;
            display: block;
            margin-bottom: 5px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .items-table th, .items-table td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        
        .items-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        
        .items-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .delete-btn {
            background-color: var(--danger-color);
            color: white;
        }
        
        .delete-btn:hover {
            background-color: #c0392b;
        }
        
        .add-item-form {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: var(--border-radius);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .submit-btn {
            background-color: var(--success-color);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            display: block;
            width: 100%;
        }
        
        .submit-btn:hover {
            background-color: #27ae60;
        }
        
        .summary-card {
            background-color: #e8f5e9;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 18px;
            color: var(--secondary-color);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #777;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ccc;
        }
        
        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .invoice-info {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">نظام إدارة الطباعة</div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo substr($_SESSION['username'] ?? 'U', 0, 1); ?>
                </div>
                <div>
                    <div><?php echo $_SESSION['username'] ?? 'مستخدم'; ?></div>
                    <div style="font-size: 12px; color: #777;">المحاسبة</div>
                </div>
            </div>
        </header>
        
        <h1 class="page-title">إدارة بنود الفواتير</h1>
        
        <?php if (isset($error)): ?>
        <div style="background-color: #ffebee; color: #d32f2f; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="content-grid">
            <div class="card">
                <h2 class="section-title">قائمة الفواتير</h2>
                <ul class="invoice-list">
                    <?php if (!empty($invoices)): ?>
                        <?php foreach ($invoices as $invoice): ?>
                        <li class="invoice-item <?php echo (isset($current_invoice) && $current_invoice['invoice_id'] == $invoice['invoice_id']) ? 'active' : ''; ?>"
                            onclick="window.location='invoice_items.php?invoice_id=<?php echo $invoice['invoice_id']; ?>'">
                            <div class="invoice-number">#<?php echo $invoice['invoice_number']; ?></div>
                            <div class="invoice-customer"><?php echo $invoice['customer_name']; ?></div>
                            <div class="invoice-date"><?php echo date('Y-m-d', strtotime($invoice['created_at'])); ?></div>
                        </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="empty-state">لا توجد فواتير</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div>
                <?php if (isset($current_invoice)): ?>
                <div class="card">
                    <div class="invoice-details">
                        <div class="invoice-header">
                            <h2>فاتورة #<?php echo $current_invoice['invoice_number']; ?></h2>
                            <div>التاريخ: <?php echo date('Y-m-d', strtotime($current_invoice['created_at'])); ?></div>
                        </div>
                        
                        <div class="invoice-info">
                            <div class="info-group">
                                <label>العميل:</label>
                                <div><?php echo $current_invoice['customer_name']; ?></div>
                            </div>
                            <div class="info-group">
                                <label>الرقم الضريبي:</label>
                                <div><?php echo $current_invoice['tax_number'] ?? 'غير محدد'; ?></div>
                            </div>
                            <div class="info-group">
                                <label>حالة الفاتورة:</label>
                                <div><?php echo $current_invoice['status']; ?></div>
                            </div>
                            <div class="info-group">
                                <label>الإجمالي:</label>
                                <div><?php echo number_format($current_invoice['total_amount'] ?? 0, 2); ?> د.ل</div>
                            </div>
                        </div>
                    </div>
                    
                    <h3 class="section-title">بنود الفاتورة</h3>
                    
                    <?php if (!empty($invoice_items)): ?>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المنتج/الخدمة</th>
                                <th>الكمية</th>
                                <th>سعر الوحدة</th>
                                <th>الخصم %</th>
                                <th>الضريبة %</th>
                                <th>المجموع</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoice_items as $index => $item): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo $item['product_name']; ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo number_format($item['unit_price'], 2); ?> د.ل</td>
                                <td><?php echo $item['discount_percent']; ?>%</td>
                                <td><?php echo $item['tax_percent']; ?>%</td>
                                <td><?php echo number_format($item['total_amount'], 2); ?> د.ل</td>
                                <td>
                                    <button class="action-btn delete-btn" 
                                            onclick="if(confirm('هل أنت متأكد من حذف هذا البند؟')) window.location='invoice_items.php?invoice_id=<?php echo $current_invoice['invoice_id']; ?>&delete_item=<?php echo $item['item_id']; ?>'">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice"></i>
                        <p>لا توجد بنود لهذه الفاتورة</p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="add-item-form">
                        <h3 class="section-title">إضافة بند جديد</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="invoice_id" value="<?php echo $current_invoice['invoice_id']; ?>">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="product_id">المنتج/الخدمة</label>
                                    <select id="product_id" name="product_id" required>
                                        <option value="">اختر منتج/خدمة</option>
                                        <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['item_id']; ?>" 
                                                data-price="<?php echo $product['price']; ?>">
                                            <?php echo $product['name']; ?> (<?php echo number_format($product['price'], 2); ?> د.ل)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="quantity">الكمية</label>
                                    <input type="number" id="quantity" name="quantity" min="1" value="1" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="price">سعر الوحدة (د.ل)</label>
                                    <input type="number" id="price" name="price" step="0.01" min="0" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="discount">الخصم (%)</label>
                                    <input type="number" id="discount" name="discount" step="0.01" min="0" max="100" value="0">
                                </div>
                                
                                <div class="form-group">
                                    <label for="tax">الضريبة (%)</label>
                                    <input type="number" id="tax" name="tax" step="0.01" min="0" max="100" value="15">
                                </div>
                            </div>
                            
                            <button type="submit" name="add_item" class="submit-btn">إضافة البند</button>
                        </form>
                    </div>
                    
                    <?php if (!empty($invoice_items)): ?>
                    <div class="summary-card">
                        <div class="summary-row">
                            <span>المجموع الفرعي:</span>
                            <span>
                                <?php 
                                $subtotal = array_sum(array_map(function($item) {
                                    return $item['quantity'] * $item['unit_price'];
                                }, $invoice_items));
                                echo number_format($subtotal, 2); 
                                ?> د.ل
                            </span>
                        </div>
                        
                        <div class="summary-row">
                            <span>الخصم:</span>
                            <span>
                                <?php 
                                $total_discount = array_sum(array_map(function($item) {
                                    return ($item['quantity'] * $item['unit_price']) * ($item['discount_percent'] / 100);
                                }, $invoice_items));
                                echo number_format($total_discount, 2); 
                                ?> د.ل
                            </span>
                        </div>
                        
                        <div class="summary-row">
                            <span>الضريبة:</span>
                            <span>
                                <?php 
                                $total_tax = array_sum(array_map(function($item) {
                                    return (($item['quantity'] * $item['unit_price']) - (($item['quantity'] * $item['unit_price']) * ($item['discount_percent'] / 100))) * ($item['tax_percent'] / 100);
                                }, $invoice_items));
                                echo number_format($total_tax, 2); 
                                ?> د.ل
                            </span>
                        </div>
                        
                        <div class="summary-row">
                            <span>الإجمالي:</span>
                            <span><?php echo number_format($current_invoice['total_amount'] ?? 0, 2); ?> د.ل</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="empty-state">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <p>يرجى اختيار فاتورة من القائمة لعرض بنودها</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // تحديث سعر الوحدة عند اختيار منتج
        document.getElementById('product_id').addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            if (selectedOption && selectedOption.dataset.price) {
                document.getElementById('price').value = selectedOption.dataset.price;
            }
        });
        
        // تهيئة سعر الوحدة إذا كان هناك منتج محدد مسبقًا
        window.addEventListener('load', function() {
            var productSelect = document.getElementById('product_id');
            if (productSelect.value) {
                var selectedOption = productSelect.options[productSelect.selectedIndex];
                if (selectedOption && selectedOption.dataset.price) {
                    document.getElementById('price').value = selectedOption.dataset.price;
                }
            }
        });
    </script>
</body>
</html>