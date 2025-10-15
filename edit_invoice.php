<?php
// في أعلى ملف edit_invoice.php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// تم تعطيل التحقق من تسجيل الدخول
// checkLogin();

// تم تعطيل التحقق من الصلاحية
// $user_role = $_SESSION['user_role'] ?? '';
// $user_id   = $_SESSION['user_id'] ?? 0;
// if (!in_array($user_role, ['admin', 'accountant', 'sales'])) {
//     header("Location: invoices.php");
//     exit();
// }

// التحقق من وجود معرف الفاتورة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: invoices.php");
    exit();
}

$invoice_id = intval($_GET['id']);

// جلب بيانات الفاتورة الحالية
$invoice = [];
$invoice_items = [];
$error = '';

try {
    // جلب بيانات الفاتورة الأساسية
    $stmt = $db->prepare("
        SELECT i.*, c.name as customer_name, c.customer_id, c.phone, c.email
        FROM invoices i
        JOIN customers c ON i.customer_id = c.customer_id
        WHERE i.invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        header("Location: invoices.php");
        exit();
    }

    // جلب عناصر الفاتورة
    $stmt = $db->prepare("
        SELECT ii.*, inv.name as item_name, inv.selling_price
        FROM invoice_items ii
        LEFT JOIN inventory inv ON ii.item_id = inv.item_id
        WHERE ii.invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "خطأ في جلب بيانات الفاتورة: " . $e->getMessage();
}

// جلب العملاء للقائمة المنسدلة
$customers = [];
try {
    $stmt = $db->query("SELECT customer_id, name FROM customers ORDER BY name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطأ في جلب بيانات العملاء: " . $e->getMessage();
}

// جلب المنتجات المتاحة
$products = [];
try {
    $stmt = $db->query("SELECT item_id, name, selling_price, current_quantity FROM inventory WHERE current_quantity > 0");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطأ في جلب المنتجات: " . $e->getMessage();
}

// معالجة تحديث الفاتورة
$errors = [];
$success = false;

// استخدم معرف المستخدم صفر لتسجيل النشاط لأي شخص
$user_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $customer_id = $_POST['customer_id'] ?? null;
        $invoice_date = $_POST['invoice_date'] ?? '';
        $due_date = $_POST['due_date'] ?? '';
        $status = $_POST['status'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $items = $_POST['items'] ?? [];

        // التحقق من البيانات الأساسية
        if (empty($customer_id)) $errors[] = "العميل مطلوب.";
        if (empty($items)) $errors[] = "يجب إضافة منتج واحد على الأقل.";

        if (empty($errors)) {
            $db->beginTransaction();

            // 1. تحديث بيانات الفاتورة الأساسية
            $stmt = $db->prepare("
                UPDATE invoices 
                SET customer_id = ?, issue_date = ?, due_date = ?, status = ?, notes = ?
                WHERE invoice_id = ?
            ");
            $stmt->execute([$customer_id, $invoice_date, $due_date, $status, $notes, $invoice_id]);

            // 2. حذف العناصر القديمة
            $stmt = $db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
            $stmt->execute([$invoice_id]);

            // 3. إضافة العناصر الجديدة وحساب المجموع الكلي
            $total_amount = 0;
            foreach ($items as $item) {
                $item_id = $item['item_id'] ?? null;
                $quantity = (int)($item['quantity'] ?? 0);
                $unit_price = (float)($item['unit_price'] ?? 0);

                if (!$item_id || $quantity <= 0 || $unit_price <= 0) continue;

                $subtotal = $unit_price * $quantity;
                $total_amount += $subtotal;

                // إدخال عنصر الفاتورة
                $stmt = $db->prepare("
                    INSERT INTO invoice_items (invoice_id, item_id, quantity, unit_price) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$invoice_id, $item_id, $quantity, $unit_price]);
            }

            // 4. تحديث المبلغ الإجمالي
            $stmt = $db->prepare("UPDATE invoices SET total_amount = ? WHERE invoice_id = ?");
            $stmt->execute([$total_amount, $invoice_id]);

            $db->commit();

            // تسجيل النشاط (أي شخص)
            logActivity($user_id, 'update_invoice', "تم تحديث الفاتورة رقم #$invoice_id");

            $success = true;
            $_SESSION['success_message'] = "تم تحديث الفاتورة بنجاح!";

            // إعادة تحميل البيانات بعد التحديث
            $stmt = $db->prepare("
                SELECT i.*, c.name as customer_name, c.customer_id, c.phone, c.email
                FROM invoices i
                JOIN customers c ON i.customer_id = c.customer_id
                WHERE i.invoice_id = ?
            ");
            $stmt->execute([$invoice_id]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("
                SELECT ii.*, inv.name as item_name, inv.selling_price
                FROM invoice_items ii
                LEFT JOIN inventory inv ON ii.item_id = inv.item_id
                WHERE ii.invoice_id = ?
            ");
            $stmt->execute([$invoice_id]);
            $invoice_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        $errors[] = "حدث خطأ أثناء تحديث الفاتورة: " . $e->getMessage();
    }
}

// أسماء حالات الفاتورة
$invoice_statuses = [
    'draft' => 'مسودة',
    'sent' => 'مرسلة',
    'paid' => 'مدفوعة',
    'overdue' => 'متأخرة',
    'cancelled' => 'ملغاة'
];
?>



<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الفاتورة - نظام المطبعة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: var(--dark);
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* الشريط الجانبي */
        .sidebar {
            width: 250px;
            background-color: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            padding: 20px 0;
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        
        .sidebar-header img {
            width: 100%;
            max-width: 150px;
            display: block;
            margin: 0 auto;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border-right: 3px solid var(--primary);
        }
        
        .sidebar-menu i {
            margin-left: 10px;
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        
        /* المحتوى الرئيسي */
        .main-content {
            flex: 1;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .header h1 {
            font-size: 24px;
            color: var(--dark);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
        }
        
        .user-menu img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-left: 10px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
        }
        
        .user-role {
            font-size: 12px;
            color: var(--gray);
        }
        
        /* معلومات الفاتورة */
        .invoice-header {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .invoice-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-group {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .info-value {
            color: var(--dark);
            font-size: 16px;
        }
        
        /* نموذج التعديل */
        .form-container {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .form-title {
            font-size: 22px;
            margin-bottom: 25px;
            color: var(--primary);
            display: flex;
            align-items: center;
        }
        
        .form-title i {
            margin-left: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        /* جدول العناصر */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .items-table th,
        .items-table td {
            padding: 12px;
            text-align: right;
            border: 1px solid #ddd;
        }
        
        .items-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #3aa8c9;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c2185b;
        }
        
        .btn-secondary {
            background-color: var(--gray);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn i {
            margin-left: 8px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .alert-danger {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--danger);
            border: 1px solid rgba(247, 37, 133, 0.2);
        }
        
        .alert-success {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success);
            border: 1px solid rgba(76, 201, 240, 0.2);
        }
        
        .alert i {
            margin-left: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- الشريط الجانبي -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="images/logo.png" alt="Logo">
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> لوحة التحكم</a></li>
                <li><a href="customers.php"><i class="fas fa-users"></i> العملاء</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> الطلبات</a></li>
                <li><a href="inventory.php"><i class="fas fa-boxes"></i> المخزون</a></li>
                <li><a href="hr.php"><i class="fas fa-user-tie"></i> الموارد البشرية</a></li>
                <li><a href="invoices.php" class="active"><i class="fas fa-file-invoice-dollar"></i> الفواتير</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> التقارير</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a></li>
            </ul>
        </aside>
        
        <!-- المحتوى الرئيسي -->
        <main class="main-content">
            <div class="header">
                <h1>تعديل الفاتورة</h1>
                
                <div class="user-menu">
                    <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['user_id']; ?></div>
                        <div class="user-role">
                            <?php echo ($_SESSION['user_role'] ?? 'موظف') == 'admin' ? 'مدير النظام' : 'موظف مبيعات'; ?>
                        </div>
                    </div>
                    <img src="images/user.png" alt="User">
                </div>
            </div>
            
            <!-- معلومات الفاتورة -->
            <div class="invoice-header">
                <div class="invoice-info">
                    <div>
                        <div class="info-group">
                            <div class="info-label">رقم الفاتورة</div>
                            <div class="info-value"><?php echo $invoice['invoice_number']; ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">تاريخ الإنشاء</div>
                            <div class="info-value"><?php echo date('Y/m/d', strtotime($invoice['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="info-group">
                            <div class="info-label">الحالة الحالية</div>
                            <div class="info-value"><?php echo $invoice_statuses[$invoice['status']] ?? $invoice['status']; ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">المبلغ الإجمالي</div>
                            <div class="info-value"><?php echo number_format($invoice['total_amount'], 2); ?> د.ل</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- الرسائل -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo $error; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    تم تحديث الفاتورة بنجاح!
                </div>
            <?php endif; ?>
            
            <!-- نموذج تعديل الفاتورة -->
            <form method="POST" action="">
                <div class="form-container">
                    <h2 class="form-title"><i class="fas fa-edit"></i> تعديل بيانات الفاتورة</h2>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="customer_id">العميل *</label>
                            <select class="form-control" id="customer_id" name="customer_id" required>
                                <option value="">اختر العميل</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['customer_id']; ?>" 
                                        <?php echo ($customer['customer_id'] == $invoice['customer_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="invoice_date">تاريخ الفاتورة *</label>
                            <input type="date" class="form-control" id="invoice_date" name="invoice_date" 
                                   value="<?php echo $invoice['issue_date']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="due_date">تاريخ الاستحقاق *</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" 
                                   value="<?php echo $invoice['due_date']; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="status">حالة الفاتورة *</label>
                            <select class="form-control" id="status" name="status" required>
                                <?php foreach ($invoice_statuses as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" 
                                        <?php echo ($value == $invoice['status']) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="notes">ملاحظات</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="ملاحظات إضافية حول الفاتورة"><?php echo htmlspecialchars($invoice['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <h3 style="margin: 30px 0 20px; color: var(--primary);">
                        <i class="fas fa-shopping-cart"></i> عناصر الفاتورة
                    </h3>
                    
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>المنتج</th>
                                <th>الكمية</th>
                                <th>سعر الوحدة</th>
                                <th>المجموع</th>
                                <th>الإجراء</th>
                            </tr>
                        </thead>
                        <tbody id="items-container">
                            <?php foreach ($invoice_items as $index => $item): ?>
                                <tr>
                                    <td>
                                        <select name="items[<?php echo $index; ?>][item_id]" class="form-control" required>
                                            <option value="">اختر المنتج</option>
                                            <?php foreach ($products as $product): ?>
                                                <option value="<?php echo $product['item_id']; ?>" 
                                                    <?php echo ($product['item_id'] == $item['item_id']) ? 'selected' : ''; ?>
                                                    data-price="<?php echo $product['selling_price']; ?>">
                                                    <?php echo htmlspecialchars($product['name']); ?> - 
                                                    <?php echo number_format($product['selling_price'], 2); ?> د.ل
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="items[<?php echo $index; ?>][quantity]" 
                                               class="form-control" min="1" value="<?php echo $item['quantity']; ?>" 
                                               required onchange="calculateTotal(this)">
                                    </td>
                                    <td>
                                        <input type="number" name="items[<?php echo $index; ?>][unit_price]" 
                                               class="form-control" min="0" step="0.01" 
                                               value="<?php echo $item['unit_price']; ?>" required 
                                               onchange="calculateTotal(this)">
                                    </td>
                                    <td>
                                        <span class="item-total">
                                            <?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?>
                                        </span> د.ل
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger" onclick="removeItem(this)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align: left; font-weight: bold;">الإجمالي:</td>
                                <td colspan="2" style="font-weight: bold; font-size: 18px;">
                                    <span id="total-amount"><?php echo number_format($invoice['total_amount'], 2); ?></span> د.ل
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <button type="button" class="btn btn-success" onclick="addItem()" style="margin-top: 15px;">
                        <i class="fas fa-plus"></i> إضافة منتج
                    </button>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ التعديلات
                        </button>
                        <a href="view_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> إلغاء
                        </a>
                        <a href="invoices.php" class="btn btn-success">
                            <i class="fas fa-list"></i> العودة للقائمة
                        </a>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
        let itemCount = <?php echo count($invoice_items); ?>;
        
        // إضافة عنصر جديد
        function addItem() {
            const container = document.getElementById('items-container');
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>
                    <select name="items[${itemCount}][item_id]" class="form-control item-select" required>
                        <option value="">اختر المنتج</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['item_id']; ?>" 
                                data-price="<?php echo $product['selling_price']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?> - 
                                <?php echo number_format($product['selling_price'], 2); ?> د.ل
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="number" name="items[${itemCount}][quantity]" 
                           class="form-control" min="1" value="1" required 
                           onchange="calculateTotal(this)">
                </td>
                <td>
                    <input type="number" name="items[${itemCount}][unit_price]" 
                           class="form-control" min="0" step="0.01" required 
                           onchange="calculateTotal(this)">
                </td>
                <td>
                    <span class="item-total">0.00</span> د.ل
                </td>
                <td>
                    <button type="button" class="btn btn-danger" onclick="removeItem(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            container.appendChild(newRow);
            
            // إضافة event listener للاختيار الجديد
            const select = newRow.querySelector('.item-select');
            select.addEventListener('change', function() {
                const price = this.options[this.selectedIndex]?.getAttribute('data-price') || 0;
                const quantityInput = this.closest('tr').querySelector('input[name$="[quantity]"]');
                const priceInput = this.closest('tr').querySelector('input[name$="[unit_price]"]');
                
                priceInput.value = price;
                calculateTotal(quantityInput);
            });
            
            itemCount++;
        }
        
        // حذف عنصر
        function removeItem(button) {
            const row = button.closest('tr');
            if (document.getElementById('items-container').rows.length > 1) {
                row.remove();
                calculateTotalAmount();
            } else {
                alert('يجب أن تحتوي الفاتورة على عنصر واحد على الأقل');
            }
        }
        
        // حساب المجموع للعنصر
        function calculateTotal(input) {
            const row = input.closest('tr');
            const quantity = parseFloat(row.querySelector('input[name$="[quantity]"]').value) || 0;
            const price = parseFloat(row.querySelector('input[name$="[unit_price]"]').value) || 0;
            const total = quantity * price;
            
            row.querySelector('.item-total').textContent = total.toFixed(2);
            calculateTotalAmount();
        }
        
        // حساب المبلغ الإجمالي
        function calculateTotalAmount() {
            let total = 0;
            document.querySelectorAll('.item-total').forEach(element => {
                total += parseFloat(element.textContent) || 0;
            });
            
            document.getElementById('total-amount').textContent = total.toFixed(2);
        }
        
        // عند تغيير المنتج، تعيين السعر التلقائي
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.item-select').forEach(select => {
                select.addEventListener('change', function() {
                    const price = this.options[this.selectedIndex]?.getAttribute('data-price') || 0;
                    const priceInput = this.closest('tr').querySelector('input[name$="[unit_price]"]');
                    priceInput.value = price;
                    calculateTotal(priceInput);
                });
            });
        });
    </script>
</body>
</html>