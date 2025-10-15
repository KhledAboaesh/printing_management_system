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

// معالجة تحديث الإعدادات
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        global $db;
        
        // تحديث إعدادات النظام المحاسبي
        if (isset($_POST['update_accounting_settings'])) {
            $tax_rate = filter_var($_POST['tax_rate'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            $currency = filter_var($_POST['currency'], FILTER_SANITIZE_STRING);
            $invoice_prefix = filter_var($_POST['invoice_prefix'], FILTER_SANITIZE_STRING);
            $due_days = filter_var($_POST['due_days'], FILTER_SANITIZE_NUMBER_INT);
            $financial_year_start = filter_var($_POST['financial_year_start'], FILTER_SANITIZE_STRING);
            
            // تحديث الإعدادات في قاعدة البيانات
            $settings = [
                'tax_rate' => $tax_rate,
                'currency' => $currency,
                'invoice_prefix' => $invoice_prefix,
                'due_days' => $due_days,
                'financial_year_start' => $financial_year_start
            ];
            
            foreach ($settings as $key => $value) {
                $stmt = $db->prepare("REPLACE INTO accounting_settings (setting_key, setting_value) VALUES (?, ?)");
                $stmt->execute([$key, $value]);
            }
            
            $success_message = "تم تحديث إعدادات النظام المحاسبي بنجاح";
        }
        
        // إضافة فئة مصروفات جديدة
        if (isset($_POST['add_expense_category'])) {
            $category_name = filter_var($_POST['category_name'], FILTER_SANITIZE_STRING);
            $category_description = filter_var($_POST['category_description'], FILTER_SANITIZE_STRING);
            
            $stmt = $db->prepare("INSERT INTO expense_categories (name, description) VALUES (?, ?)");
            $stmt->execute([$category_name, $category_description]);
            
            $success_message = "تم إضافة فئة المصروفات بنجاح";
        }
        
        // تحديث فئة مصروفات
        if (isset($_POST['update_expense_category'])) {
            $category_id = filter_var($_POST['category_id'], FILTER_SANITIZE_NUMBER_INT);
            $category_name = filter_var($_POST['category_name'], FILTER_SANITIZE_STRING);
            $category_description = filter_var($_POST['category_description'], FILTER_SANITIZE_STRING);
            
            $stmt = $db->prepare("UPDATE expense_categories SET name = ?, description = ? WHERE category_id = ?");
            $stmt->execute([$category_name, $category_description, $category_id]);
            
            $success_message = "تم تحديث فئة المصروفات بنجاح";
        }
        
        // حذف فئة مصروفات
        if (isset($_POST['delete_expense_category'])) {
            $category_id = filter_var($_POST['category_id'], FILTER_SANITIZE_NUMBER_INT);
            
            // التحقق من عدم وجود مصروفات مرتبطة بهذه الفئة
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM expenses WHERE category_id = ?");
            $stmt->execute([$category_id]);
            $expense_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($expense_count > 0) {
                $error_message = "لا يمكن حذف هذه الفئة لأنها مرتبطة بمصروفات موجودة";
            } else {
                $stmt = $db->prepare("DELETE FROM expense_categories WHERE category_id = ?");
                $stmt->execute([$category_id]);
                $success_message = "تم حذف فئة المصروفات بنجاح";
            }
        }
        
        // إضافة طريقة دفع جديدة
        if (isset($_POST['add_payment_method'])) {
            $method_name = filter_var($_POST['method_name'], FILTER_SANITIZE_STRING);
            $method_description = filter_var($_POST['method_description'], FILTER_SANITIZE_STRING);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $db->prepare("INSERT INTO payment_methods (name, description, is_active) VALUES (?, ?, ?)");
            $stmt->execute([$method_name, $method_description, $is_active]);
            
            $success_message = "تم إضافة طريقة الدفع بنجاح";
        }
        
        // تحديث طريقة دفع
        if (isset($_POST['update_payment_method'])) {
            $method_id = filter_var($_POST['method_id'], FILTER_SANITIZE_NUMBER_INT);
            $method_name = filter_var($_POST['method_name'], FILTER_SANITIZE_STRING);
            $method_description = filter_var($_POST['method_description'], FILTER_SANITIZE_STRING);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $db->prepare("UPDATE payment_methods SET name = ?, description = ?, is_active = ? WHERE method_id = ?");
            $stmt->execute([$method_name, $method_description, $is_active, $method_id]);
            
            $success_message = "تم تحديث طريقة الدفع بنجاح";
        }
        
        // تسجيل النشاط
        logActivity($_SESSION['user_id'], 'update_accounting_settings', 'تحديث إعدادات المحاسبة');
        
    } catch (PDOException $e) {
        error_log('Accounting Settings Error: ' . $e->getMessage());
        $error_message = "حدث خطأ أثناء حفظ الإعدادات";
    }
}

// جلب الإعدادات الحالية
try {
    global $db;
    
    // جلب إعدادات النظام المحاسبي
    $stmt = $db->query("SELECT setting_key, setting_value FROM accounting_settings");
    $accounting_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // جلب فئات المصروفات
    $stmt = $db->query("SELECT * FROM expense_categories ORDER BY name");
    $expense_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب طرق الدفع
    $stmt = $db->query("SELECT * FROM payment_methods ORDER BY name");
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب سجل التغييرات في الإعدادات
    $stmt = $db->prepare("
        SELECT al.*, u.username 
        FROM activity_log al 
        LEFT JOIN users u ON al.user_id = u.user_id 
        WHERE al.action_type LIKE '%settings%' 
        ORDER BY al.activity_date DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $settings_log = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Accounting Settings Load Error: ' . $e->getMessage());
    $error_message = "حدث خطأ في جلب الإعدادات";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - إعدادات المحاسبة</title>
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
            max-width: 1200px;
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
        
        .dashboard-title {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--secondary-color);
            text-align: center;
        }
        
        .settings-tabs {
            display: flex;
            background-color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            overflow: hidden;
            margin-bottom: 0;
        }
        
        .tab {
            padding: 15px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .tab.active {
            border-bottom: 3px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
            background-color: white;
            padding: 20px;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .btn-success {
            background-color: var(--success-color);
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
        }
        
        .btn-warning:hover {
            background-color: #d35400;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        
        .table th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        .table tr:hover {
            background-color: #f5f5f5;
        }
        
        .actions {
            display: flex;
            gap: 5px;
        }
        
        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background-color: rgba(46, 204, 113, 0.2);
            color: var(--success-color);
        }
        
        .status-inactive {
            background-color: rgba(231, 76, 60, 0.2);
            color: var(--danger-color);
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: rgba(46, 204, 113, 0.2);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }
        
        .alert-danger {
            background-color: rgba(231, 76, 60, 0.2);
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }
        
        .section-title {
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
            color: var(--secondary-color);
        }
        
        .log-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .log-item:last-child {
            border-bottom: none;
        }
        
        .log-action {
            font-weight: 600;
        }
        
        .log-date {
            font-size: 12px;
            color: #777;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input {
            width: auto;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .settings-tabs {
                flex-direction: column;
            }
            
            .actions {
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            header {
                flex-direction: column;
                gap: 15px;
            }
            
            .table {
                font-size: 14px;
            }
            
            .table th, .table td {
                padding: 8px 10px;
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
                    <div style="font-size: 12px; color: #777;">محاسبة</div>
                </div>
            </div>
        </header>
        
        <h1 class="dashboard-title">إعدادات المحاسبة</h1>
        
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <div class="settings-tabs">
            <div class="tab active" data-tab="accounting-settings">الإعدادات العامة</div>
            <div class="tab" data-tab="expense-categories">فئات المصروفات</div>
            <div class="tab" data-tab="payment-methods">طرق الدفع</div>
            <div class="tab" data-tab="activity-log">سجل التغييرات</div>
        </div>
        
        <!-- تبويب الإعدادات العامة -->
        <div class="tab-content active" id="accounting-settings">
            <h2 class="section-title">الإعدادات العامة للنظام المحاسبي</h2>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="tax_rate">معدل الضريبة (%)</label>
                        <input type="number" id="tax_rate" name="tax_rate" step="0.01" min="0" max="100" 
                               value="<?php echo $accounting_settings['tax_rate'] ?? 0; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="currency">العملة</label>
                        <select id="currency" name="currency" required>
                            <option value="د.ل" <?php echo ($accounting_settings['currency'] ?? 'د.ل') == 'د.ل' ? 'selected' : ''; ?>>دينار ليبي (د.ل)</option>
                            <option value="$" <?php echo ($accounting_settings['currency'] ?? 'د.ل') == '$' ? 'selected' : ''; ?>>دولار ($)</option>
                            <option value="€" <?php echo ($accounting_settings['currency'] ?? 'د.ل') == '€' ? 'selected' : ''; ?>>يورو (€)</option>
                            <option value="£" <?php echo ($accounting_settings['currency'] ?? 'د.ل') == '£' ? 'selected' : ''; ?>>جنيه إسترليني (£)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="invoice_prefix">بادئة أرقام الفواتير</label>
                        <input type="text" id="invoice_prefix" name="invoice_prefix" 
                               value="<?php echo $accounting_settings['invoice_prefix'] ?? 'INV-'; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_days">عدد أيام الاستحقاق (افتراضي)</label>
                        <input type="number" id="due_days" name="due_days" min="1" max="365" 
                               value="<?php echo $accounting_settings['due_days'] ?? 30; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="financial_year_start">بداية السنة المالية</label>
                    <input type="date" id="financial_year_start" name="financial_year_start" 
                           value="<?php echo $accounting_settings['financial_year_start'] ?? date('Y-01-01'); ?>" required>
                </div>
                
                <button type="submit" name="update_accounting_settings" class="btn">
                    <i class="fas fa-save"></i> حفظ الإعدادات
                </button>
            </form>
        </div>
        
        <!-- تبويب فئات المصروفات -->
        <div class="tab-content" id="expense-categories">
            <h2 class="section-title">إدارة فئات المصروفات</h2>
            
            <form method="POST" class="form-row">
                <div class="form-group">
                    <label for="category_name">اسم الفئة</label>
                    <input type="text" id="category_name" name="category_name" required>
                </div>
                
                <div class="form-group">
                    <label for="category_description">وصف الفئة</label>
                    <input type="text" id="category_description" name="category_description">
                </div>
                
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" name="add_expense_category" class="btn btn-success">
                        <i class="fas fa-plus"></i> إضافة فئة
                    </button>
                </div>
            </form>
            
            <?php if (!empty($expense_categories)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>اسم الفئة</th>
                        <th>الوصف</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expense_categories as $category): ?>
                    <tr>
                        <td><?php echo $category['name']; ?></td>
                        <td><?php echo $category['description'] ?? 'لا يوجد وصف'; ?></td>
                        <td class="actions">
                            <button type="button" class="action-btn btn-warning" onclick="editExpenseCategory(<?php echo $category['category_id']; ?>, '<?php echo $category['name']; ?>', '<?php echo $category['description'] ?? ''; ?>')">
                                <i class="fas fa-edit"></i> تعديل
                            </button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="category_id" value="<?php echo $category['category_id']; ?>">
                                <button type="submit" name="delete_expense_category" class="action-btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذه الفئة؟')">
                                    <i class="fas fa-trash"></i> حذف
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 20px; color: #777;">
                لا توجد فئات مصروفات مضافة حتى الآن
            </div>
            <?php endif; ?>
            
            <!-- نموذج تعديل فئة المصروفات (مخفي) -->
            <div id="edit-expense-category-form" style="display: none; margin-top: 20px; padding: 20px; background-color: #f9f9f9; border-radius: var(--border-radius);">
                <h3>تعديل فئة المصروفات</h3>
                <form method="POST" class="form-row">
                    <input type="hidden" id="edit_category_id" name="category_id">
                    
                    <div class="form-group">
                        <label for="edit_category_name">اسم الفئة</label>
                        <input type="text" id="edit_category_name" name="category_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_category_description">وصف الفئة</label>
                        <input type="text" id="edit_category_description" name="category_description">
                    </div>
                    
                    <div class="form-group" style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" name="update_expense_category" class="btn btn-success">
                            <i class="fas fa-save"></i> حفظ التعديلات
                        </button>
                        <button type="button" class="btn btn-danger" onclick="cancelEdit()">
                            <i class="fas fa-times"></i> إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- تبويب طرق الدفع -->
        <div class="tab-content" id="payment-methods">
            <h2 class="section-title">إدارة طرق الدفع</h2>
            
            <form method="POST" class="form-row">
                <div class="form-group">
                    <label for="method_name">اسم طريقة الدفع</label>
                    <input type="text" id="method_name" name="method_name" required>
                </div>
                
                <div class="form-group">
                    <label for="method_description">وصف طريقة الدفع</label>
                    <input type="text" id="method_description" name="method_description">
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                    <label for="is_active">مفعل</label>
                </div>
                
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" name="add_payment_method" class="btn btn-success">
                        <i class="fas fa-plus"></i> إضافة طريقة
                    </button>
                </div>
            </form>
            
            <?php if (!empty($payment_methods)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>اسم طريقة الدفع</th>
                        <th>الوصف</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payment_methods as $method): ?>
                    <tr>
                        <td><?php echo $method['name']; ?></td>
                        <td><?php echo $method['description'] ?? 'لا يوجد وصف'; ?></td>
                        <td>
                            <span class="status-badge <?php echo $method['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $method['is_active'] ? 'مفعل' : 'غير مفعل'; ?>
                            </span>
                        </td>
                        <td class="actions">
                            <button type="button" class="action-btn btn-warning" onclick="editPaymentMethod(<?php echo $method['method_id']; ?>, '<?php echo $method['name']; ?>', '<?php echo $method['description'] ?? ''; ?>', <?php echo $method['is_active']; ?>)">
                                <i class="fas fa-edit"></i> تعديل
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 20px; color: #777;">
                لا توجد طرق دفع مضافة حتى الآن
            </div>
            <?php endif; ?>
            
            <!-- نموذج تعديل طريقة الدفع (مخفي) -->
            <div id="edit-payment-method-form" style="display: none; margin-top: 20px; padding: 20px; background-color: #f9f9f9; border-radius: var(--border-radius);">
                <h3>تعديل طريقة الدفع</h3>
                <form method="POST" class="form-row">
                    <input type="hidden" id="edit_method_id" name="method_id">
                    
                    <div class="form-group">
                        <label for="edit_method_name">اسم طريقة الدفع</label>
                        <input type="text" id="edit_method_name" name="method_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_method_description">وصف طريقة الدفع</label>
                        <input type="text" id="edit_method_description" name="method_description">
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="edit_is_active" name="is_active" value="1">
                        <label for="edit_is_active">مفعل</label>
                    </div>
                    
                    <div class="form-group" style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" name="update_payment_method" class="btn btn-success">
                            <i class="fas fa-save"></i> حفظ التعديلات
                        </button>
                        <button type="button" class="btn btn-danger" onclick="cancelEditMethod()">
                            <i class="fas fa-times"></i> إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- تبويب سجل التغييرات -->
        <div class="tab-content" id="activity-log">
            <h2 class="section-title">سجل التغييرات في الإعدادات</h2>
            
            <?php if (!empty($settings_log)): ?>
                <?php foreach ($settings_log as $log): ?>
                <div class="log-item">
                    <div class="log-action"><?php echo $log['action_description']; ?></div>
                    <div>بواسطة: <?php echo $log['username'] ?? 'مستخدم غير معروف'; ?></div>
                    <div class="log-date"><?php echo date('Y-m-d H:i:s', strtotime($log['activity_date'])); ?></div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div style="text-align: center; padding: 20px; color: #777;">
                لا توجد تغييرات مسجلة حتى الآن
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // إدارة التبويبات
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // إزالة النشاط من جميع التبويبات
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // إضافة النشاط للتبويب المحدد
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab).classList.add('active');
            });
        });
        
        // تعديل فئة المصروفات
        function editExpenseCategory(id, name, description) {
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = name;
            document.getElementById('edit_category_description').value = description;
            document.getElementById('edit-expense-category-form').style.display = 'block';
            
            // التمرير إلى النموذج
            document.getElementById('edit-expense-category-form').scrollIntoView({ behavior: 'smooth' });
        }
        
        // إلغاء تعديل فئة المصروفات
        function cancelEdit() {
            document.getElementById('edit-expense-category-form').style.display = 'none';
        }
        
        // تعديل طريقة الدفع
        function editPaymentMethod(id, name, description, isActive) {
            document.getElementById('edit_method_id').value = id;
            document.getElementById('edit_method_name').value = name;
            document.getElementById('edit_method_description').value = description;
            document.getElementById('edit_is_active').checked = isActive == 1;
            document.getElementById('edit-payment-method-form').style.display = 'block';
            
            // التمرير إلى النموذج
            document.getElementById('edit-payment-method-form').scrollIntoView({ behavior: 'smooth' });
        }
        
        // إلغاء تعديل طريقة الدفع
        function cancelEditMethod() {
            document.getElementById('edit-payment-method-form').style.display = 'none';
        }
    </script>
</body>
</html>