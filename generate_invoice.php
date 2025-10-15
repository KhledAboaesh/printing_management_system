<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

define('INCLUDED', true);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// جلب بيانات العملاء
$customers = [];
try {
    $stmt = $db->query("SELECT customer_id, name, email FROM customers ORDER BY name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطأ في جلب بيانات العملاء: " . $e->getMessage();
}

// جلب المستخدمين
$users = [];
try {
    $stmt = $db->query("SELECT user_id, full_name, email, role FROM users WHERE is_active = 1");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطأ في جلب بيانات المستخدمين: " . $e->getMessage();
}

// جلب المنتجات
$products = [];
try {
    $stmt = $db->query("SELECT item_id, name, selling_price, current_quantity FROM inventory WHERE current_quantity > 0");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطأ في جلب المنتجات: " . $e->getMessage();
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $customer_id = $_POST['customer_id'] ?? null;
        $invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
        $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
        $items = $_POST['items'] ?? [];
        $notes = $_POST['notes'] ?? '';
        $send_to_customer = isset($_POST['send_to_customer']);
        $send_to_users = $_POST['send_to_users'] ?? [];
        $send_to_designers = $_POST['send_to_designers'] ?? [];
        $send_to_sections = $_POST['send_to_sections'] ?? [];

        $payment_method = $_POST['payment_method'] ?? 'cash';
        $deposit_amount = floatval($_POST['deposit_amount'] ?? 0);
        $amount_paid = floatval($_POST['amount_paid'] ?? 0);

        if (empty($customer_id)) throw new Exception("العميل مطلوب.");
        if (empty($items)) throw new Exception("يجب إضافة منتج واحد على الأقل.");
        if ($deposit_amount < 0) throw new Exception("قيمة العربون غير صالحة.");
        if ($amount_paid < 0) throw new Exception("المبلغ المدفوع غير صالح.");

        $db->beginTransaction();

        // إنشاء الفاتورة
        $stmt = $db->prepare("INSERT INTO invoices (customer_id, issue_date, due_date, notes, created_by, payment_method, deposit_amount, amount_paid) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$customer_id, $invoice_date, $due_date, $notes, $_SESSION['user_id'], $payment_method, $deposit_amount, $amount_paid]);
        $invoice_id = $db->lastInsertId();

        // توليد رقم الفاتورة
        $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($invoice_id, 4, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("UPDATE invoices SET invoice_number = ? WHERE invoice_id = ?");
        $stmt->execute([$invoice_number, $invoice_id]);

        // إضافة العناصر وحساب الإجمالي
        $total_amount = 0;
        foreach ($items as $item) {
            $item_id = $item['item_id'] ?? null;
            $quantity = (int)($item['quantity'] ?? 0);
            if (!$item_id || $quantity <= 0) continue;

            $stmt = $db->prepare("SELECT selling_price, current_quantity, name FROM inventory WHERE item_id = ?");
            $stmt->execute([$item_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) throw new Exception("المنتج غير موجود (ID: $item_id).");
            if ($quantity > $product['current_quantity']) throw new Exception("الكمية المطلوبة للمنتج '{$product['name']}' أكبر من المخزون الحالي.");

            $unit_price = $product['selling_price'];
            $subtotal = $unit_price * $quantity;
            $total_amount += $subtotal;

            $stmt = $db->prepare("INSERT INTO invoice_items (invoice_id, item_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
            $stmt->execute([$invoice_id, $item_id, $quantity, $unit_price]);

            $stmt = $db->prepare("UPDATE inventory SET current_quantity = current_quantity - ? WHERE item_id = ?");
            $stmt->execute([$quantity, $item_id]);
        }

        // تحديث الإجمالي وحالة الدفع
        $payment_status = 'pending';
        $payment_date = null;
        if ($amount_paid >= $total_amount) {
            $payment_status = 'paid';
            $payment_date = date('Y-m-d');
        } elseif ($amount_paid > 0) {
            $payment_status = 'partial';
            $payment_date = date('Y-m-d');
        }

        $stmt = $db->prepare("UPDATE invoices SET total_amount = ?, payment_status = ?, payment_date = ? WHERE invoice_id = ?");
        $stmt->execute([$total_amount, $payment_status, $payment_date, $invoice_id]);

        // إدراج الدفع الأولي بشكل صحيح مع created_by و received_by
        if ($amount_paid > 0) {
            $stmt = $db->prepare("
                INSERT INTO invoice_payments 
                (invoice_id, amount, payment_method, notes, created_by, received_by, payment_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$invoice_id, $amount_paid, $payment_method, "الدفع الأولي للفاتورة", $_SESSION['user_id'], $_SESSION['user_id'], date('Y-m-d')]);
        }

        // إرسال الفاتورة للمصممين والأقسام
        foreach ($send_to_designers as $designer_id) {
            $stmt = $db->prepare("INSERT INTO invoice_assignments (invoice_id, designer_id, assigned_by, notes, assigned_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$invoice_id, $designer_id, $_SESSION['user_id'], $notes]);
            logActivity($_SESSION['user_id'], 'assign_to_designer', "تم إرسال الفاتورة #$invoice_number إلى المصمم $designer_id");
        }

        foreach ($send_to_sections as $section) {
            $stmt = $db->prepare("SELECT user_id FROM users WHERE role = ? AND is_active = 1");
            $stmt->execute([$section]);
            $section_users = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($section_users as $user_id) {
                $stmt = $db->prepare("INSERT INTO invoice_assignments (invoice_id, designer_id, assigned_by, notes, assigned_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$invoice_id, $user_id, $_SESSION['user_id'], $notes]);
            }
        }

        // إرسال البريد الإلكتروني
        if ($send_to_customer || !empty($send_to_users)) {
            $stmt = $db->prepare("SELECT i.*, c.name as customer_name, c.email as customer_email, c.phone, c.company_name, u.full_name as created_by_name FROM invoices i JOIN customers c ON i.customer_id = c.customer_id JOIN users u ON i.created_by = u.user_id WHERE i.invoice_id = ?");
            $stmt->execute([$invoice_id]);
            $invoice_data = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("SELECT ii.*, inv.name as product_name FROM invoice_items ii JOIN inventory inv ON ii.item_id = inv.item_id WHERE ii.invoice_id = ?");
            $stmt->execute([$invoice_id]);
            $invoice_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendInvoiceEmail($invoice_data, $invoice_items, $send_to_customer, $send_to_users);
        }

        $db->commit();
        header("Location: view_invoice.php?id=$invoice_id&sent=" . ($send_to_customer || !empty($send_to_users) ? '1' : '0'));
        exit();

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = "حدث خطأ أثناء إنشاء الفاتورة: " . $e->getMessage();
    }
}

// دالة إرسال البريد الإلكتروني
function sendInvoiceEmail($invoice, $items, $sendToCustomer, $sendToUsers) {
    global $db;
    $subject = "فاتورة جديدة #{$invoice['invoice_number']} - نظام المطبعة";
    $message = "<html><body>";
    $message .= "<h2>فاتورة جديدة #{$invoice['invoice_number']}</h2>";
    $message .= "<p>تاريخ الإصدار: {$invoice['issue_date']}</p>";
    $message .= "<p>تاريخ الاستحقاق: {$invoice['due_date']}</p>";

    $payment_methods = ['cash'=>'نقداً','bank_transfer'=>'تحويل بنكي','credit_card'=>'بطاقة ائتمان','check'=>'شيك'];
    $payment_statuses = ['pending'=>'معلق','partial'=>'مدفوع جزئياً','paid'=>'مدفوع بالكامل'];

    $message .= "<h3>معلومات الدفع</h3>";
    $message .= "<p><strong>طريقة الدفع:</strong> " . ($payment_methods[$invoice['payment_method']] ?? $invoice['payment_method']) . "</p>";
    $message .= "<p><strong>حالة الدفع:</strong> " . ($payment_statuses[$invoice['payment_status']] ?? $invoice['payment_status']) . "</p>";
    $message .= "<p><strong>العربون:</strong> {$invoice['deposit_amount']} د.ل</p>";
    $message .= "<p><strong>المبلغ المدفوع:</strong> {$invoice['amount_paid']} د.ل</p>";

    $message .= "<h3>معلومات العميل</h3>";
    $message .= "<p><strong>الاسم:</strong> {$invoice['customer_name']}</p>";
    $message .= "<p><strong>الشركة:</strong> " . ($invoice['company_name'] ?: 'غير محدد') . "</p>";
    $message .= "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'><tr><th>المنتج</th><th>الكمية</th><th>سعر الوحدة</th><th>المجموع</th></tr>";
    foreach ($items as $item) {
        $message .= "<tr><td>{$item['product_name']}</td><td>{$item['quantity']}</td><td>{$item['unit_price']}</td><td>" . ($item['quantity'] * $item['unit_price']) . "</td></tr>";
    }
    $message .= "<tr><td colspan='3'><strong>الإجمالي</strong></td><td><strong>{$invoice['total_amount']}</strong></td></tr>";
    $message .= "<tr><td colspan='3'><strong>العربون</strong></td><td><strong>{$invoice['deposit_amount']}</strong></td></tr>";
    $message .= "<tr><td colspan='3'><strong>المبلغ المدفوع</strong></td><td><strong>{$invoice['amount_paid']}</strong></td></tr>";
    $message .= "<tr><td colspan='3'><strong>المبلغ المتبقي</strong></td><td><strong>" . ($invoice['total_amount'] - $invoice['amount_paid']) . "</strong></td></tr>";
    $message .= "</table>";
    $message .= "<p>ملاحظات: " . ($invoice['notes'] ?: 'لا توجد ملاحظات') . "</p>";
    $message .= "</body></html>";

    $headers = ['MIME-Version: 1.0','Content-type: text/html; charset=utf-8','From: نظام المطبعة <noreply@print-system.com>'];

    $recipients = [];
    if ($sendToCustomer && !empty($invoice['customer_email'])) $recipients[] = $invoice['customer_email'];

    if (!empty($sendToUsers)) {
        foreach ($sendToUsers as $userId) {
            $stmt = $db->prepare("SELECT email FROM users WHERE user_id = ? AND email IS NOT NULL");
            $stmt->execute([$userId]);
            if ($email = $stmt->fetchColumn()) $recipients[] = $email;
        }
    }

    foreach (array_unique($recipients) as $recipient) {
        if (!empty($recipient)) {
            mail($recipient, $subject, $message, implode("\r\n", $headers));
        }
    }

    logActivity($_SESSION['user_id'], 'send_invoice', "تم إرسال الفاتورة #{$invoice['invoice_number']} إلى " . count($recipients) . " مستلم");
}
?>



<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء فاتورة - نظام المطبعة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .invoice-container {
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            outline: none;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background-color: white;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-outline:hover {
            background-color: #f8f9fa;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .items-table th, .items-table td {
            padding: 12px;
            border: 1px solid #eee;
            text-align: center;
        }
        
        .items-table th {
            background-color: #f8f9fa;
        }
        
        .add-item-btn {
            background-color: var(--success);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .remove-item-btn {
            background-color: var(--danger);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .date-inputs {
            display: flex;
            gap: 15px;
        }
        
        .date-inputs .form-group {
            flex: 1;
        }
        
        .send-options {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .form-check {
            margin-bottom: 10px;
        }
        
        .form-check-input {
            margin-left: 8px;
        }
        
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }


         .search-results {
        position: absolute;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        margin-top: 2px;
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
        z-index: 9999;
    }
    .search-results div {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
    }
    .search-results div:hover {
        background: #f8f9fa;
    }


    .payment-section {
    border: 1px solid #e0e0e0;
}

#payment-summary {
    border: 1px solid #ddd;
}

#payment-summary p {
    margin: 5px 0;
    font-size: 14px;
}
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <h1>إنشاء فاتورة جديدة</h1>
                <?php include 'includes/user-menu.php'; ?>
            </div>
            
            <div class="invoice-container">
                <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
<form method="POST" id="invoice-form">
    <!-- ✅ البحث الذكي عن العميل -->
    <div class="form-group" style="position: relative;">
        <label for="customer_search" class="form-label">العميل <span class="text-danger">*</span></label>
        <input type="text" id="customer_search" class="form-control" placeholder="اكتب الاسم، الهاتف أو البريد..." autocomplete="off" required>
        <input type="hidden" id="customer_id" name="customer_id">
        <div id="customer_results" class="search-results"></div>
    </div>

   

    <div class="date-inputs">
        <div class="form-group">
            <label for="invoice_date" class="form-label">تاريخ الفاتورة</label>
            <input type="date" id="invoice_date" name="invoice_date" class="form-control" 
                   value="<?php echo date('Y-m-d'); ?>">
        </div>
        
        <div class="form-group">
            <label for="due_date" class="form-label">تاريخ الاستحقاق</label>
            <input type="date" id="due_date" name="due_date" class="form-control" 
                   value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
        </div>
    </div>
    
    <div class="form-group">
        <label class="form-label">عناصر الفاتورة <span class="text-danger">*</span></label>
        <table class="items-table" id="items-table">
            <thead>
                <tr>
                    <th width="40%">المنتج/الخدمة</th>
                    <th width="20%">الكمية</th>
                    <th width="20%">السعر</th>
                    <th width="20%">المجموع</th>
                    <th width="10%"></th>
                </tr>
            </thead>
            <tbody id="items-tbody">
                <!-- العناصر ستضاف هنا عبر JavaScript -->
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align: left;"><strong>الإجمالي:</strong></td>
                    <td id="total-amount">0.00</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <button type="button" id="add-item-btn" class="add-item-btn">
            <i class="fas fa-plus"></i> إضافة عنصر
        </button>
    </div>
    
    <div class="form-group">
        <label for="notes" class="form-label">ملاحظات</label>
        <textarea id="notes" name="notes" class="form-control" rows="3"></textarea>
    </div>


    <!-- قسم بيانات الدفع والعربون -->
 <div class="form-group">
    <label class="form-label">بيانات الدفع</label>
    <div class="payment-section" style="background: #f8f9fa; padding: 20px; border-radius: 10px;">
        <div class="payment-method">
            <label for="payment_method" class="form-label">طريقة الدفع</label>
            <select id="payment_method" name="payment_method" class="form-control">
                <option value="cash">نقداً</option>
                <option value="bank_transfer">تحويل بنكي</option>
                <option value="credit_card">بطاقة ائتمان</option>
                <option value="check">شيك</option>
            </select>
        </div>
        
        <div class="date-inputs" style="margin-top: 15px;">
            <div class="form-group">
                <label for="deposit_amount" class="form-label">الاجمالي (د.ل)</label>
                <input type="number" id="deposit_amount" name="deposit_amount" 
                       class="form-control" min="0" step="0.01" value="0" 
                       onchange="updatePaymentInfo()">
            </div>
            
            <div class="form-group">
                <label for="amount_paid" class="form-label">المبلغ المدفوع (د.ل)</label>
                <input type="number" id="amount_paid" name="amount_paid" 
                       class="form-control" min="0" step="0.01" value="0" 
                       onchange="updatePaymentInfo()">
            </div>
        </div>
        
        <div id="payment-summary" style="margin-top: 15px; padding: 10px; background: white; border-radius: 5px;">
            <p><strong>الإجمالي:</strong> <span id="total-display">0.00</span> د.ل</p>
            <p><strong>المبلغ المتبقي:</strong> <span id="remaining-amount">0.00</span> د.ل</p>
            <p><strong>حالة الدفع:</strong> <span id="payment-status">لم يتم الدفع</span></p>
        </div>
    </div>
 </div>
    
    <!-- قسم إرسال الفاتورة إلى الأقسام والمصممين -->
    <div class="form-group">
        <label class="form-label">إرسال الفاتورة إلى:</label>
        <div class="send-options">

            <!-- المصممين -->
            <label class="form-label">المصممون:</label>
            <div class="users-grid">
                <?php 
                $stmt = $db->query("SELECT user_id, full_name FROM users WHERE role = 'designer' AND is_active = 1");
                $designers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($designers as $designer): ?>
                    <div class="form-check">
                        <input type="checkbox" name="send_to_designers[]" value="<?php echo $designer['user_id']; ?>" 
                               class="form-check-input" id="designer_<?php echo $designer['user_id']; ?>">
                        <label for="designer_<?php echo $designer['user_id']; ?>" class="form-check-label">
                            <?php echo htmlspecialchars($designer['full_name']); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- أقسام النظام الأخرى -->
            <label class="form-label mt-3">أقسام أخرى:</label>
            <div class="users-grid">
                <?php 
                $stmt = $db->query("SELECT DISTINCT role FROM users WHERE role != 'designer' AND is_active = 1");
                $sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($sections as $section): ?>
                    <div class="form-check">
                        <input type="checkbox" name="send_to_sections[]" value="<?php echo htmlspecialchars($section); ?>" 
                               class="form-check-input" id="section_<?php echo htmlspecialchars($section); ?>">
                        <label for="section_<?php echo htmlspecialchars($section); ?>" class="form-check-label">
                            <?php echo ucfirst(htmlspecialchars($section)); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- خيار إرسال إلى العميل -->
            <div class="form-check mt-3">
                <input type="checkbox" name="send_to_customer" value="1" class="form-check-input" id="send_to_customer">
                <label for="send_to_customer" class="form-check-label">إرسال إلى العميل</label>
            </div>

            <!-- خيار إرسال إلى مستخدمين محددين -->
            <label class="form-label mt-3">مستخدمون محددون:</label>
            <div class="users-grid">
                <?php 
                $stmt = $db->query("SELECT user_id, full_name FROM users WHERE is_active = 1");
                $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($all_users as $user): ?>
                    <div class="form-check">
                        <input type="checkbox" name="send_to_users[]" value="<?php echo $user['user_id']; ?>" 
                               class="form-check-input" id="user_<?php echo $user['user_id']; ?>">
                        <label for="user_<?php echo $user['user_id']; ?>" class="form-check-label">
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

    <div class="btn-group">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> حفظ الفاتورة وإرسالها
        </button>
        <button type="button" id="preview-btn" class="btn btn-outline">
            <i class="fas fa-eye"></i> معاينة
        </button>
        <a href="invoices.php" class="btn btn-outline">
            <i class="fas fa-times"></i> إلغاء
        </a>
    </div>
</form>

<!-- ✅ JavaScript للبحث الذكي -->


            </div>
        </main>
    </div>

    <script>



        // بيانات المنتجات المتاحة
        const products = <?php echo json_encode($products); ?>;
        
        // متغير لعدة العناصر
        let itemCounter = 0;
        
        // إضافة عنصر جديد
        document.getElementById('add-item-btn').addEventListener('click', function() {
            addNewItem();
        });
        
        // دالة لإضافة عنصر جديد
        function addNewItem(item = null) {
            const tbody = document.getElementById('items-tbody');
            const tr = document.createElement('tr');
            tr.id = `item-${itemCounter}`;
            
            tr.innerHTML = `
                <td>
                    <select name="items[${itemCounter}][item_id]" class="form-control item-select" required>
                        <option value="">اختر منتجاً</option>
                        ${products.map(p => 
                            `<option value="${p.item_id}" 
                              data-price="${p.selling_price}"
                              ${item && item.item_id == p.item_id ? 'selected' : ''}>
                                ${p.name} (${p.selling_price} د.ل)
                            </option>`
                        ).join('')}
                    </select>
                </td>
                <td>
                    <input type="number" name="items[${itemCounter}][quantity]" 
                           class="form-control item-quantity" min="1" value="${item ? item.quantity : 1}" required>
                </td>
                <td class="item-price">${item ? item.price : '0.00'}</td>
                <td class="item-subtotal">${item ? (item.price * item.quantity).toFixed(2) : '0.00'}</td>
                <td>
                    <button type="button" class="remove-item-btn" onclick="removeItem(${itemCounter})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(tr);
            itemCounter++;
            
            // إضافة event listeners للعنصر الجديد
            const select = tr.querySelector('.item-select');
            const quantity = tr.querySelector('.item-quantity');
            
            select.addEventListener('change', updateItem);
            quantity.addEventListener('input', updateItem);
            
            updateTotal();
        }
        
        // تحديث العنصر عند التغيير
        function updateItem(e) {
            const tr = e.target.closest('tr');
            const select = tr.querySelector('.item-select');
            const quantity = tr.querySelector('.item-quantity');
            const priceCell = tr.querySelector('.item-price');
            const subtotalCell = tr.querySelector('.item-subtotal');
            
            const selectedOption = select.options[select.selectedIndex];
            const price = selectedOption ? parseFloat(selectedOption.dataset.price) : 0;
            const qty = parseFloat(quantity.value) || 0;
            
            priceCell.textContent = price.toFixed(2);
            subtotalCell.textContent = (price * qty).toFixed(2);
            
            updateTotal();
        }
        
        // إزالة العنصر
        function removeItem(id) {
            const item = document.getElementById(`item-${id}`);
            if (item) {
                item.remove();
                updateTotal();
            }
        }
        
        // تحديث الإجمالي
        function updateTotal() {
            let total = 0;
            document.querySelectorAll('.item-subtotal').forEach(cell => {
                total += parseFloat(cell.textContent) || 0;
            });
            
            document.getElementById('total-amount').textContent = total.toFixed(2);
        }
        
        // معاينة الفاتورة
        document.getElementById('preview-btn').addEventListener('click', function() {
            const form = document.getElementById('invoice-form');
            form.target = '_blank';
            form.action = 'preview_invoice.php';
            form.submit();
            form.action = '';
            form.target = '';
        });
        
        // إضافة عنصر واحد عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            addNewItem();
        });




        const searchInput = document.getElementById('customer_search');
const resultsDiv = document.getElementById('customer_results');
const hiddenId = document.getElementById('customer_id');

searchInput.addEventListener('input', function() {
    const query = this.value.trim();
    hiddenId.value = "";
    resultsDiv.innerHTML = "";

    if (query.length < 2) return;

    fetch("search_customers.php?term=" + encodeURIComponent(query))
        .then(res => res.json())
        .then(data => {
            if (data.length === 0) {
                resultsDiv.innerHTML = "<div>لا يوجد نتائج</div>";
                return;
            }
            data.forEach(c => {
                const div = document.createElement('div');
                div.textContent = `${c.name} | ${c.phone ?? ''} | ${c.email ?? ''}`;
                div.addEventListener('click', function() {
                    searchInput.value = c.name;
                    hiddenId.value = c.customer_id;
                    resultsDiv.innerHTML = "";
                });
                resultsDiv.appendChild(div);
            });
        });
});

document.addEventListener('click', function(e) {
    if (!resultsDiv.contains(e.target) && e.target !== searchInput) {
        resultsDiv.innerHTML = "";
    }
});




// تحديث معلومات الدفع
function updatePaymentInfo() {
    const total = parseFloat(document.getElementById('total-amount').textContent) || 0;
    const deposit = parseFloat(document.getElementById('deposit_amount').value) || 0;
    const paid = parseFloat(document.getElementById('amount_paid').value) || 0;
    
    // تحديث عرض الإجمالي
    document.getElementById('total-display').textContent = total.toFixed(2);
    
    // حساب المبلغ المتبقي
    const remaining = total - paid;
    document.getElementById('remaining-amount').textContent = remaining.toFixed(2);
    
    // تحديث حالة الدفع
    let status = 'لم يتم الدفع';
    if (paid >= total && total > 0) {
        status = 'مدفوع بالكامل';
    } else if (paid > 0) {
        status = 'مدفوع جزئياً';
    }
    
    document.getElementById('payment-status').textContent = status;
    
    // التأكد من أن المبلغ المدفوع لا يتجاوز الإجمالي
    if (paid > total) {
        document.getElementById('amount_paid').value = total.toFixed(2);
        updatePaymentInfo(); // تحديث مرة أخرى بالقيمة المصححة
    }
    
    // التأكد من أن العربون لا يتجاوز الإجمالي
    if (deposit > total) {
        document.getElementById('deposit_amount').value = total.toFixed(2);
        updatePaymentInfo(); // تحديث مرة أخرى بالقيمة المصححة
    }
}

// استدعاء updatePaymentInfo عند تحديث الإجمالي
function updateTotal() {
    let total = 0;
    document.querySelectorAll('.item-subtotal').forEach(cell => {
        total += parseFloat(cell.textContent) || 0;
    });
    
    document.getElementById('total-amount').textContent = total.toFixed(2);
    updatePaymentInfo(); // تحديث معلومات الدفع عند تغيير الإجمالي
}

// تحديث معلومات الدفع عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    updatePaymentInfo();
});
    </script>
</body>
</html>