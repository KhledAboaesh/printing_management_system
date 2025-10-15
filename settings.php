<?php
// في أعلى ملف settings.php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

checkLogin();

// التحقق من الصلاحية - فقط مدير النظام يمكنه الوصول للإعدادات
// if ($_SESSION['user_role'] !== 'admin') {
//     header("Location: dashboard.php");
//     exit();
// }

// معالجة حفظ الإعدادات
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // حفظ الإعدادات العامة
        $settings = [
            // معلومات الشركة
            'company_name'       => $_POST['company_name']       ?? '',
            'company_email'      => $_POST['company_email']      ?? '',
            'company_phone'      => $_POST['company_phone']      ?? '',
            'company_address'    => $_POST['company_address']    ?? '',
            'company_logo'       => $_POST['company_logo']       ?? '',
            'company_website'    => $_POST['company_website']    ?? '',
            'company_fax'        => $_POST['company_fax']        ?? '',
            
            // الإعدادات المالية
            'currency'           => $_POST['currency']           ?? 'د.ل',
            'tax_rate'           => isset($_POST['tax_rate']) && is_numeric($_POST['tax_rate'])
                                    ? (float)$_POST['tax_rate'] : 0,
            'invoice_due_days'   => $_POST['invoice_due_days']   ?? '30',
            'late_fee_percentage'=> $_POST['late_fee_percentage'] ?? '5',
            'discount_percentage'=> $_POST['discount_percentage'] ?? '0',
            'payment_terms'      => $_POST['payment_terms']      ?? '',
            'bank_account'       => $_POST['bank_account']       ?? '',
            'tax_id'             => $_POST['tax_id']             ?? '',
            
            // إعدادات المستندات
            'invoice_prefix'     => $_POST['invoice_prefix']     ?? 'INV-',
            'order_prefix'       => $_POST['order_prefix']       ?? 'ORD-',
            'quote_prefix'       => $_POST['quote_prefix']       ?? 'QUO-',
            'receipt_prefix'     => $_POST['receipt_prefix']     ?? 'REC-',
            'default_notes'      => $_POST['default_notes']      ?? '',
            'footer_text'        => $_POST['footer_text']        ?? '',
            'document_language'  => $_POST['document_language']  ?? 'ar',
            
            // إعدادات النظام
            'timezone'           => $_POST['timezone']           ?? 'Africa/Tripoli',
            'date_format'        => $_POST['date_format']        ?? 'd/m/Y',
            'time_format'        => $_POST['time_format']        ?? 'H:i',
            'items_per_page'     => $_POST['items_per_page']     ?? '25',
            'auto_logout'        => $_POST['auto_logout']        ?? '60',
            'theme'              => $_POST['theme']              ?? 'light',
            'language'           => $_POST['language']           ?? 'ar',
            
            // إعدادات البريد الإلكتروني
            'smtp_host'          => $_POST['smtp_host']          ?? '',
            'smtp_port'          => $_POST['smtp_port']          ?? '587',
            'smtp_username'      => $_POST['smtp_username']      ?? '',
            'smtp_password'      => $_POST['smtp_password']      ?? '',
            'smtp_encryption'    => $_POST['smtp_encryption']    ?? 'tls',
            'email_from_name'    => $_POST['email_from_name']    ?? '',
            'email_from_address' => $_POST['email_from_address'] ?? '',
            
            // إعدادات الإشعارات
            'notify_new_orders'  => isset($_POST['notify_new_orders']) ? '1' : '0',
            'notify_low_stock'   => isset($_POST['notify_low_stock']) ? '1' : '0',
            'notify_overdue'     => isset($_POST['notify_overdue']) ? '1' : '0',
            'notify_daily_report'=> isset($_POST['notify_daily_report']) ? '1' : '0',
            'notify_weekly_report'=> isset($_POST['notify_weekly_report']) ? '1' : '0',
            'notify_monthly_report'=> isset($_POST['notify_monthly_report']) ? '1' : '0',
            
            // إعدادات الأمان
            'login_attempts'     => $_POST['login_attempts']     ?? '5',
            'password_expiry'    => $_POST['password_expiry']    ?? '90',
            'two_factor_auth'    => isset($_POST['two_factor_auth']) ? '1' : '0',
            'ip_restriction'     => isset($_POST['ip_restriction']) ? '1' : '0',
            'allowed_ips'        => $_POST['allowed_ips']        ?? '',
            
            // إعدادات النسخ الاحتياطي
            'auto_backup'        => isset($_POST['auto_backup']) ? '1' : '0',
            'backup_frequency'   => $_POST['backup_frequency']   ?? 'weekly',
            'backup_retention'   => $_POST['backup_retention']   ?? '30',
            'backup_email'       => $_POST['backup_email']       ?? '',
            
            // إعدادات الطباعة
            'print_header'       => isset($_POST['print_header']) ? '1' : '0',
            'print_footer'       => isset($_POST['print_footer']) ? '1' : '0',
            'print_logo'         => isset($_POST['print_logo']) ? '1' : '0',
            'paper_size'         => $_POST['paper_size']         ?? 'A4',
            'print_orientation'  => $_POST['print_orientation']  ?? 'portrait',
            
            // إعدادات متقدمة
            'debug_mode'         => isset($_POST['debug_mode']) ? '1' : '0',
            'maintenance_mode'   => isset($_POST['maintenance_mode']) ? '1' : '0',
            'api_enabled'        => isset($_POST['api_enabled']) ? '1' : '0',
            'api_key'            => $_POST['api_key']            ?? '',
            'cron_key'           => $_POST['cron_key']           ?? '',
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO settings (setting_key, setting_value, updated_by) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)
            ");
            $stmt->execute([$key, $value, $_SESSION['user_id']]);
        }
        
        $db->commit();
        
        // تسجيل النشاط
        logActivity($_SESSION['user_id'], 'update_settings', "تم تحديث إعدادات النظام");
        
        $success = true;
        $_SESSION['success_message'] = "تم حفظ الإعدادات بنجاح!";
        
    } catch (PDOException $e) {
        $db->rollBack();
        $errors[] = "حدث خطأ أثناء حفظ الإعدادات: " . $e->getMessage();
    }
}

// جلب الإعدادات الحالية
$current_settings = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
    $settings_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($settings_data as $setting) {
        $current_settings[$setting['setting_key']] = $setting['setting_value'];
    }
} catch (PDOException $e) {
    $errors[] = "خطأ في جلب الإعدادات: " . $e->getMessage();
}

// القيم الافتراضية
$default_settings = [
    // معلومات الشركة
    'company_name' => 'شركة المطبعة الحديثة',
    'company_email' => 'info@example.com',
    'company_phone' => '021-1234567',
    'company_address' => 'طرابلس - ليبيا',
    'company_logo' => 'images/logo.png',
    'company_website' => 'www.example.com',
    'company_fax' => '021-1234568',
    
    // الإعدادات المالية
    'currency' => 'د.ل',
    'tax_rate' => '18',
    'invoice_due_days' => '30',
    'late_fee_percentage' => '5',
    'discount_percentage' => '0',
    'payment_terms' => 'شروط الدفع: الدفع خلال 30 يوم من تاريخ الفاتورة',
    'bank_account' => '1234567890 - البنك الليبي',
    'tax_id' => '123456789',
    
    // إعدادات المستندات
    'invoice_prefix' => 'INV',
    'order_prefix' => 'ORD',
    'quote_prefix' => 'QUO',
    'receipt_prefix' => 'REC',
    'default_notes' => 'شكراً لتعاملكم معنا',
    'footer_text' => 'شركة المطبعة الحديثة - جميع الحقوق محفوظة',
    'document_language' => 'ar',
    
    // إعدادات النظام
    'timezone' => 'Africa/Tripoli',
    'date_format' => 'd/m/Y',
    'time_format' => 'H:i',
    'items_per_page' => '25',
    'auto_logout' => '60',
    'theme' => 'light',
    'language' => 'ar',
    
    // إعدادات البريد الإلكتروني
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => '587',
    'smtp_username' => 'user@example.com',
    'smtp_password' => '',
    'smtp_encryption' => 'tls',
    'email_from_name' => 'شركة المطبعة الحديثة',
    'email_from_address' => 'info@example.com',
    
    // إعدادات الإشعارات
    'notify_new_orders' => '1',
    'notify_low_stock' => '1',
    'notify_overdue' => '1',
    'notify_daily_report' => '0',
    'notify_weekly_report' => '1',
    'notify_monthly_report' => '1',
    
    // إعدادات الأمان
    'login_attempts' => '5',
    'password_expiry' => '90',
    'two_factor_auth' => '0',
    'ip_restriction' => '0',
    'allowed_ips' => '',
    
    // إعدادات النسخ الاحتياطي
    'auto_backup' => '0',
    'backup_frequency' => 'weekly',
    'backup_retention' => '30',
    'backup_email' => '',
    
    // إعدادات الطباعة
    'print_header' => '1',
    'print_footer' => '1',
    'print_logo' => '1',
    'paper_size' => 'A4',
    'print_orientation' => 'portrait',
    
    // إعدادات متقدمة
    'debug_mode' => '0',
    'maintenance_mode' => '0',
    'api_enabled' => '0',
    'api_key' => '',
    'cron_key' => '',
];

// دمج الإعدادات
$settings = array_merge($default_settings, $current_settings);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإعدادات - نظام المطبعة</title>
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
            overflow-y: auto;
            max-height: 100vh;
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
        
        /* تبويبات الإعدادات */
        .settings-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            flex-wrap: wrap;
            overflow-x: auto;
        }
        
        .tab {
            padding: 15px 25px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
            font-weight: 600;
        }
        
        .tab:hover {
            background-color: #f8f9fa;
        }
        
        /* محتوى التبويبات */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* نموذج الإعدادات */
        .settings-form {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .section-title {
            font-size: 20px;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-left: 10px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
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
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-help {
            font-size: 12px;
            color: var(--gray);
            margin-top: 5px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .checkbox-group input {
            margin-left: 10px;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
        }
        
        .btn-secondary {
            background-color: var(--gray);
            color: white;
            margin-right: 10px;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn i {
            margin-left: 8px;
        }
        
        /* الرسائل */
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
        
        /* بطاقات الإحصائيات */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .stat-card-value {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--primary);
        }
        
        .stat-card-title {
            font-size: 14px;
            color: var(--gray);
        }
        
        /* شريط التقدم */
        .progress-bar {
            height: 10px;
            background-color: #e9ecef;
            border-radius: 5px;
            margin: 10px 0;
            overflow: hidden;
        }
        
        .progress {
            height: 100%;
            background-color: var(--primary);
            border-radius: 5px;
            transition: width 0.3s ease;
        }
        
        /* علامات التبويب الفرعية */
        .sub-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            flex-wrap: wrap;
        }
        
        .sub-tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .sub-tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
        }
        
        .sub-tab-content {
            display: none;
        }
        
        .sub-tab-content.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .settings-tabs {
                flex-direction: column;
            }
            
            .tab {
                text-align: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 10px;
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
                <li><a href="invoices.php"><i class="fas fa-file-invoice-dollar"></i> الفواتير</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> التقارير</a></li>
                <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> الإعدادات</a></li>
            </ul>
        </aside>
        
        <!-- المحتوى الرئيسي -->
        <main class="main-content">
            <div class="header">
                <h1>إعدادات النظام</h1>
                
                <div class="user-menu">
                    <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['user_id']; ?></div>
                        <div class="user-role">مدير النظام</div>
                    </div>
                    <img src="images/user.png" alt="User">
                </div>
            </div>
            
            <!-- تبويبات الإعدادات -->
            <div class="settings-tabs">
                <div class="tab active" onclick="showTab('general')">الإعدادات العامة</div>
                <div class="tab" onclick="showTab('financial')">الإعدادات المالية</div>
                <div class="tab" onclick="showTab('documents')">إعدادات المستندات</div>
                <div class="tab" onclick="showTab('system')">إعدادات النظام</div>
                <div class="tab" onclick="showTab('email')">إعدادات البريد</div>
                <div class="tab" onclick="showTab('notifications')">الإشعارات</div>
                <div class="tab" onclick="showTab('security')">الأمان</div>
                <div class="tab" onclick="showTab('backup')">النسخ الاحتياطي</div>
                <div class="tab" onclick="showTab('printing')">إعدادات الطباعة</div>
                <div class="tab" onclick="showTab('advanced')">متقدم</div>
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
                    تم حفظ الإعدادات بنجاح!
                </div>
            <?php endif; ?>
            
            <!-- نموذج الإعدادات -->
            <form method="POST" action="" id="settingsForm">
                <!-- الإعدادات العامة -->
                <div id="general" class="tab-content active">
                    <div class="settings-form">
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-building"></i> معلومات الشركة</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="company_name">اسم الشركة *</label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" 
                                           value="<?php echo htmlspecialchars($settings['company_name']); ?>" required>
                                    <div class="form-help">الاسم الرسمي للشركة كما يظهر في الفواتير</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="company_email">البريد الإلكتروني</label>
                                    <input type="email" class="form-control" id="company_email" name="company_email" 
                                           value="<?php echo htmlspecialchars($settings['company_email']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="company_phone">رقم الهاتف</label>
                                    <input type="tel" class="form-control" id="company_phone" name="company_phone" 
                                           value="<?php echo htmlspecialchars($settings['company_phone']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="company_fax">رقم الفاكس</label>
                                    <input type="text" class="form-control" id="company_fax" name="company_fax" 
                                           value="<?php echo htmlspecialchars($settings['company_fax']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="company_website">الموقع الإلكتروني</label>
                                    <input type="url" class="form-control" id="company_website" name="company_website" 
                                           value="<?php echo htmlspecialchars($settings['company_website']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="company_logo">شعار الشركة</label>
                                    <input type="text" class="form-control" id="company_logo" name="company_logo" 
                                           value="<?php echo htmlspecialchars($settings['company_logo']); ?>">
                                    <div class="form-help">مسار ملف الشعار (مثال: images/logo.png)</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="company_address">العنوان</label>
                                <textarea class="form-control" id="company_address" name="company_address" 
                                          rows="2"><?php echo htmlspecialchars($settings['company_address']); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-globe"></i> إعدادات اللغة والوقت</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="language">لغة النظام</label>
                                    <select class="form-control" id="language" name="language">
                                        <option value="ar" <?php echo $settings['language'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
                                        <option value="en" <?php echo $settings['language'] == 'en' ? 'selected' : ''; ?>>English</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="timezone">المنطقة الزمنية</label>
                                    <select class="form-control" id="timezone" name="timezone">
                                        <option value="Africa/Tripoli" <?php echo $settings['timezone'] == 'Africa/Tripoli' ? 'selected' : ''; ?>>طرابلس (توقيت ليبيا)</option>
                                        <option value="Africa/Cairo" <?php echo $settings['timezone'] == 'Africa/Cairo' ? 'selected' : ''; ?>>القاهرة (توقيت مصر)</option>
                                        <option value="Asia/Riyadh" <?php echo $settings['timezone'] == 'Asia/Riyadh' ? 'selected' : ''; ?>>الرياض (توقيت السعودية)</option>
                                        <option value="UTC" <?php echo $settings['timezone'] == 'UTC' ? 'selected' : ''; ?>>توقيت عالمي (UTC)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="date_format">تنسيق التاريخ</label>
                                    <select class="form-control" id="date_format" name="date_format">
                                        <option value="d/m/Y" <?php echo $settings['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>يوم/شهر/سنة (25/12/2023)</option>
                                        <option value="Y-m-d" <?php echo $settings['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>سنة-شهر-يوم (2023-12-25)</option>
                                        <option value="m/d/Y" <?php echo $settings['date_format'] == 'm/d/Y' ? 'selected' : ''; ?>>شهر/يوم/سنة (12/25/2023)</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="time_format">تنسيق الوقت</label>
                                    <select class="form-control" id="time_format" name="time_format">
                                        <option value="H:i" <?php echo $settings['time_format'] == 'H:i' ? 'selected' : ''; ?>>24 ساعة (14:30)</option>
                                        <option value="h:i A" <?php echo $settings['time_format'] == 'h:i A' ? 'selected' : ''; ?>>12 ساعة (02:30 م)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- الإعدادات المالية -->
                <div id="financial" class="tab-content">
                    <div class="settings-form">
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-money-bill-wave"></i> الإعدادات المالية</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="currency">العملة</label>
                                    <select class="form-control" id="currency" name="currency">
                                        <option value="د.ل" <?php echo $settings['currency'] == 'د.ل' ? 'selected' : ''; ?>>دينار ليبي (د.ل)</option>
                                        <option value="$" <?php echo $settings['currency'] == '$' ? 'selected' : ''; ?>>دولار ($)</option>
                                        <option value="€" <?php echo $settings['currency'] == '€' ? 'selected' : ''; ?>>يورو (€)</option>
                                        <option value="£" <?php echo $settings['currency'] == '£' ? 'selected' : ''; ?>>جنيه إسترليني (£)</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="tax_rate">معدل الضريبة (%)</label>
                                    <input type="number" class="form-control" id="tax_rate" name="tax_rate" 
                                           value="<?php echo htmlspecialchars($settings['tax_rate']); ?>" min="0" max="100" step="0.1">
                                    <div class="form-help">النسبة المئوية للضريبة المطبقة على الفواتير</div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="invoice_due_days">أيام استحقاق الفاتورة</label>
                                    <input type="number" class="form-control" id="invoice_due_days" name="invoice_due_days" 
                                           value="<?php echo htmlspecialchars($settings['invoice_due_days']); ?>" min="1" max="365">
                                    <div class="form-help">عدد الأيام المسموح بها للدفع بعد إصدار الفاتورة</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="late_fee_percentage">رسوم التأخير (%)</label>
                                    <input type="number" class="form-control" id="late_fee_percentage" name="late_fee_percentage" 
                                           value="<?php echo htmlspecialchars($settings['late_fee_percentage']); ?>" min="0" max="100" step="0.1">
                                    <div class="form-help">النسبة المئوية لرسوم التأخير على الفواتير المتأخرة</div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="discount_percentage">خصم افتراضي (%)</label>
                                    <input type="number" class="form-control" id="discount_percentage" name="discount_percentage" 
                                           value="<?php echo htmlspecialchars($settings['discount_percentage']); ?>" min="0" max="100" step="0.1">
                                    <div class="form-help">النسبة المئوية للخصم الافتراضي على الفواتير</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="tax_id">الرقم الضريبي</label>
                                    <input type="text" class="form-control" id="tax_id" name="tax_id" 
                                           value="<?php echo htmlspecialchars($settings['tax_id']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="payment_terms">شروط الدفع</label>
                                <textarea class="form-control" id="payment_terms" name="payment_terms" 
                                          rows="3"><?php echo htmlspecialchars($settings['payment_terms']); ?></textarea>
                                <div class="form-help">سيظهر هذا النص في الفواتير كشروط للدفع</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="bank_account">معلومات الحساب البنكي</label>
                                <textarea class="form-control" id="bank_account" name="bank_account" 
                                          rows="2"><?php echo htmlspecialchars($settings['bank_account']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- إعدادات المستندات -->
                <div id="documents" class="tab-content">
                    <div class="settings-form">
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-file-alt"></i> إعدادات المستندات</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="invoice_prefix">بادئة الفواتير</label>
                                    <input type="text" class="form-control" id="invoice_prefix" name="invoice_prefix" 
                                           value="<?php echo htmlspecialchars($settings['invoice_prefix']); ?>">
                                    <div class="form-help">مثال: INV-2023-001</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="order_prefix">بادئة الطلبات</label>
                                    <input type="text" class="form-control" id="order_prefix" name="order_prefix" 
                                           value="<?php echo htmlspecialchars($settings['order_prefix']); ?>">
                                    <div class="form-help">مثال: ORD-2023-001</div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="quote_prefix">بادئة عروض الأسعار</label>
                                    <input type="text" class="form-control" id="quote_prefix" name="quote_prefix" 
                                           value="<?php echo htmlspecialchars($settings['quote_prefix']); ?>">
                                    <div class="form-help">مثال: QUO-2023-001</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="receipt_prefix">بادئة الإيصالات</label>
                                    <input type="text" class="form-control" id="receipt_prefix" name="receipt_prefix" 
                                           value="<?php echo htmlspecialchars($settings['receipt_prefix']); ?>">
                                    <div class="form-help">مثال: REC-2023-001</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="document_language">لغة المستندات</label>
                                <select class="form-control" id="document_language" name="document_language">
                                    <option value="ar" <?php echo $settings['document_language'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
                                    <option value="en" <?php echo $settings['document_language'] == 'en' ? 'selected' : ''; ?>>English</option>
                                    <option value="both" <?php echo $settings['document_language'] == 'both' ? 'selected' : ''; ?>>العربية والإنجليزية</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="default_notes">ملاحظات افتراضية</label>
                                <textarea class="form-control" id="default_notes" name="default_notes" 
                                          rows="3"><?php echo htmlspecialchars($settings['default_notes']); ?></textarea>
                                <div class="form-help">سيظهر هذا النص في نهاية الفواتير والعروض</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="footer_text">نص التذييل</label>
                                <textarea class="form-control" id="footer_text" name="footer_text" 
                                          rows="2"><?php echo htmlspecialchars($settings['footer_text']); ?></textarea>
                                <div class="form-help">سيظهر هذا النص في أسفل المستندات</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- إعدادات النظام -->
                <div id="system" class="tab-content">
                    <div class="settings-form">
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-cog"></i> إعدادات النظام</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="items_per_page">عدد العناصر في الصفحة</label>
                                    <select class="form-control" id="items_per_page" name="items_per_page">
                                        <option value="10" <?php echo $settings['items_per_page'] == '10' ? 'selected' : ''; ?>>10 عناصر</option>
                                        <option value="25" <?php echo $settings['items_per_page'] == '25' ? 'selected' : ''; ?>>25 عنصر</option>
                                        <option value="50" <?php echo $settings['items_per_page'] == '50' ? 'selected' : ''; ?>>50 عنصر</option>
                                        <option value="100" <?php echo $settings['items_per_page'] == '100' ? 'selected' : ''; ?>>100 عنصر</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="auto_logout">تسجيل الخروج التلقائي (دقيقة)</label>
                                    <input type="number" class="form-control" id="auto_logout" name="auto_logout" 
                                           value="<?php echo htmlspecialchars($settings['auto_logout']); ?>" min="5" max="480">
                                    <div class="form-help">سيتم تسجيل الخروج تلقائياً بعد هذه المدة من عدم النشاط</div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="theme">الثيم</label>
                                    <select class="form-control" id="theme" name="theme">
                                        <option value="light" <?php echo $settings['theme'] == 'light' ? 'selected' : ''; ?>>فاتح</option>
                                        <option value="dark" <?php echo $settings['theme'] == 'dark' ? 'selected' : ''; ?>>داكن</option>
                                        <option value="auto" <?php echo $settings['theme'] == 'auto' ? 'selected' : ''; ?>>تلقائي</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">إعدادات أخرى</label>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="debug_mode" name="debug_mode" value="1" <?php echo $settings['debug_mode'] == '1' ? 'checked' : ''; ?>>
                                        <label for="debug_mode">وضع التصحيح</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1" <?php echo $settings['maintenance_mode'] == '1' ? 'checked' : ''; ?>>
                                        <label for="maintenance_mode">وضع الصيانة</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- إعدادات البريد الإلكتروني -->
                <div id="email" class="tab-content">
                    <div class="settings-form">
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-envelope"></i> إعدادات البريد الإلكتروني</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="smtp_host">خادم SMTP</label>
                                    <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                           value="<?php echo htmlspecialchars($settings['smtp_host']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="smtp_port">منفذ SMTP</label>
                                    <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                           value="<?php echo htmlspecialchars($settings['smtp_port']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="smtp_username">اسم المستخدم</label>
                                    <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                           value="<?php echo htmlspecialchars($settings['smtp_username']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="smtp_password">كلمة المرور</label>
                                    <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                           value="<?php echo htmlspecialchars($settings['smtp_password']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="smtp_encryption">التشفير</label>
                                    <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                        <option value="tls" <?php echo $settings['smtp_encryption'] == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo $settings['smtp_encryption'] == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="" <?php echo $settings['smtp_encryption'] == '' ? 'selected' : ''; ?>>لا يوجد</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="email_from_name">اسم المرسل</label>
                                    <input type="text" class="form-control" id="email_from_name" name="email_from_name" 
                                           value="<?php echo htmlspecialchars($settings['email_from_name']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="email_from_address">عنوان المرسل</label>
                                <input type="email" class="form-control" id="email_from_address" name="email_from_address" 
                                       value="<?php echo htmlspecialchars($settings['email_from_address']); ?>">
                            </div>
                            
                            <div style="text-align: center; margin-top: 20px;">
                                <button type="button" class="btn btn-secondary" onclick="testEmailSettings()">
                                    <i class="fas fa-paper-plane"></i> اختبار إعدادات البريد
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- الإشعارات -->
                <div id="notifications" class="tab-content">
                    <div class="settings-form">
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-bell"></i> إعدادات الإشعارات</h3>
                            
                            <div class="form-group">
                                <label class="form-label">تنبيهات البريد الإلكتروني</label>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="notify_new_orders" name="notify_new_orders" value="1" <?php echo $settings['notify_new_orders'] == '1' ? 'checked' : ''; ?>>
                                        <label for="notify_new_orders">تنبيهات الطلبات الجديدة</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="notify_low_stock" name="notify_low_stock" value="1" <?php echo $settings['notify_low_stock'] == '1' ? 'checked' : ''; ?>>
                                        <label for="notify_low_stock">تنبيهات نفاد المخزون</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="notify_overdue" name="notify_overdue" value="1" <?php echo $settings['notify_overdue'] == '1' ? 'checked' : ''; ?>>
                                        <label for="notify_overdue">تنبيهات الفواتير المتأخرة</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="notify_daily_report" name="notify_daily_report" value="1" <?php echo $settings['notify_daily_report'] == '1' ? 'checked' : ''; ?>>
                                        <label for="notify_daily_report">تنبيهات الأداء اليومي</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="notify_weekly_report" name="notify_weekly_report" value="1" <?php echo $settings['notify_weekly_report'] == '1' ? 'checked' : ''; ?>>
                                        <label for="notify_weekly_report">تقارير أسبوعية</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="notify_monthly_report" name="notify_monthly_report" value="1" <?php echo $settings['notify_monthly_report'] == '1' ? 'checked' : ''; ?>>
                                        <label for="notify_monthly_report">تقارير شهرية</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- الأمان -->
                <div id="security" class="tab-content">
                    <div class="settings-form">
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-shield-alt"></i> إعدادات الأمان</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="login_attempts">عدد محاولات تسجيل الدخول المسموحة</label>
                                    <input type="number" class="form-control" id="login_attempts" name="login_attempts" 
                                           value="<?php echo htmlspecialchars($settings['login_attempts']); ?>" min="1" max="10">
                                    <div class="form-help">سيتم حظر المستخدم بعد تجاوز هذا العدد</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="password_expiry">انتهاء صلاحية كلمة المرور (يوم)</label>
                                    <input type="number" class="form-control" id="password_expiry" name="password_expiry" 
                                           value="<?php echo htmlspecialchars($settings['password_expiry']); ?>" min="1" max="365">
                                    <div class="form-help">سيطلب من المستخدم تغيير كلمة المرور بعد هذه المدة</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">خيارات أمان إضافية</label>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="two_factor_auth" name="two_factor_auth" value="1" <?php echo $settings['two_factor_auth'] == '1' ? 'checked' : ''; ?>>
                                        <label for="two_factor_auth">المصادقة الثنائية (2FA)</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="ip_restriction" name="ip_restriction" value="1" <?php echo $settings['ip_restriction'] == '1' ? 'checked' : ''; ?>>
                                        <label for="ip_restriction">تقييد عناوين IP</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="allowed_ips">عناوين IP المسموحة</label>
                                <textarea class="form-control" id="allowed_ips" name="allowed_ips" 
                                          rows="3" placeholder="192.168.1.1&#10;10.0.0.5&#10;172.16.0.10"><?php echo htmlspecialchars($settings['allowed_ips']); ?></textarea>
                                <div class="form-help">أدخل عناوين IP المسموح لها بالوصول (سطر واحد لكل عنوان)</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- النسخ الاحتياطي -->
                <div id="backup" class="tab-content">
                    <div class="settings-form">
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-database"></i> النسخ الاحتياطي</h3>
                            
                            <div class="stats-cards">
                                <div class="stat-card">
                                    <div class="stat-card-value">2.5</div>
                                    <div class="stat-card-title">ميجابايت حجم قاعدة البيانات</div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-card-value">3</div>
                                    <div class="stat-card-title">نسخ احتياطية</div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-card-value">٢٤</div>
                                    <div class="stat-card-title">ساعة منذ آخر نسخ</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">النسخ الاحتياطي التلقائي</label>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="auto_backup" name="auto_backup" value="1" <?php echo $settings['auto_backup'] == '1' ? 'checked' : ''; ?>>
                                    <label for="auto_backup">تفعيل النسخ الاحتياطي التلقائي</label>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="backup_frequency">تكرار النسخ الاحتياطي</label>
                                    <select class="form-control" id="backup_frequency" name="backup_frequency">
                                        <option value="daily" <?php echo $settings['backup_frequency'] == 'daily' ? 'selected' : ''; ?>>يومي</option>
                                        <option value="weekly" <?php echo $settings['backup_frequency'] == 'weekly' ? 'selected' : ''; ?>>أسبوعي</option>
                                        <option value="monthly" <?php echo $settings['backup_frequency'] == 'monthly' ? 'selected' : ''; ?>>شهري</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="backup_retention">فترة الاحتفاظ بالنسخ (يوم)</label>
                                    <input type="number" class="form-control" id="backup_retention" name="backup_retention" 
                                           value="<?php echo htmlspecialchars($settings['backup_retention']); ?>" min="1" max="365">
                                    <div class="form-help">سيتم حذف النسخ القديمة بعد هذه الفترة</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="backup_email">البريد الإلكتروني للإشعارات</label>
                                <input type="email" class="form-control" id="backup_email" name="backup_email" 
                                       value="<?php echo htmlspecialchars($settings['backup_email']); ?>">
                                <div class="form-help">سيتم إرسال إشعارات النسخ الاحتياطي إلى هذا البريد</div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">إنشاء نسخة احتياطية</label>
                                    <button type="button" class="btn btn-primary" style="width: 100%;" onclick="createBackup()">
                                        <i class="fas fa-download"></i> إنشاء نسخة احتياطية الآن
                                    </button>
                                    <div class="form-help">سيتم إنشاء نسخة كاملة من قاعدة البيانات</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">النسخ الاحتياطية المتاحة</label>
                                    <select class="form-control" id="backup_files">
                                        <option>backup_2023_12_01.sql</option>
                                        <option>backup_2023_11_15.sql</option>
                                        <option>backup_2023_11_01.sql</option>
                                    </select>
                                    <div class="form-help">اختر نسخة للاستعادة أو التنزيل</div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin-top: 30px;">
                            <button type="button" class="btn btn-success" style="margin-right: 10px;" onclick="downloadBackup()">
                                <i class="fas fa-download"></i> تنزيل النسخة
                            </button>
                            <button type="button" class="btn btn-danger" onclick="restoreBackup()">
                                <i class="fas fa-history"></i> استعادة النسخة
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- إعدادات الطباعة -->
                <div id="printing" class="tab-content">
                    <div class="settings-form">
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-print"></i> إعدادات الطباعة</h3>
                            
                            <div class="form-group">
                                <label class="form-label">خيارات الطباعة</label>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="print_header" name="print_header" value="1" <?php echo $settings['print_header'] == '1' ? 'checked' : ''; ?>>
                                        <label for="print_header">طباعة الرأس</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="print_footer" name="print_footer" value="1" <?php echo $settings['print_footer'] == '1' ? 'checked' : ''; ?>>
                                        <label for="print_footer">طباعة التذييل</label>
                                    </div>
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="print_logo" name="print_logo" value="1" <?php echo $settings['print_logo'] == '1' ? 'checked' : ''; ?>>
                                        <label for="print_logo">طباعة الشعار</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="paper_size">حجم الورق</label>
                                    <select class="form-control" id="paper_size" name="paper_size">
                                        <option value="A4" <?php echo $settings['paper_size'] == 'A4' ? 'selected' : ''; ?>>A4</option>
                                        <option value="A5" <?php echo $settings['paper_size'] == 'A5' ? 'selected' : ''; ?>>A5</option>
                                        <option value="Letter" <?php echo $settings['paper_size'] == 'Letter' ? 'selected' : ''; ?>>Letter</option>
                                        <option value="Legal" <?php echo $settings['paper_size'] == 'Legal' ? 'selected' : ''; ?>>Legal</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="print_orientation">اتجاه الطباعة</label>
                                    <select class="form-control" id="print_orientation" name="print_orientation">
                                        <option value="portrait" <?php echo $settings['print_orientation'] == 'portrait' ? 'selected' : ''; ?>>عمودي</option>
                                        <option value="landscape" <?php echo $settings['print_orientation'] == 'landscape' ? 'selected' : ''; ?>>أفقي</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div style="text-align: center; margin-top: 20px;">
                                <button type="button" class="btn btn-secondary" onclick="printTestPage()">
                                    <i class="fas fa-print"></i> طباعة صفحة اختبار
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- الإعدادات المتقدمة -->
                <div id="advanced" class="tab-content">
                    <div class="settings-form">
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-cogs"></i> الإعدادات المتقدمة</h3>
                            
                            <div class="form-group">
                                <label class="form-label">واجهة برمجة التطبيقات (API)</label>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="api_enabled" name="api_enabled" value="1" <?php echo $settings['api_enabled'] == '1' ? 'checked' : ''; ?>>
                                    <label for="api_enabled">تفعيل واجهة برمجة التطبيقات</label>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="api_key">مفتاح API</label>
                                    <input type="text" class="form-control" id="api_key" name="api_key" 
                                           value="<?php echo htmlspecialchars($settings['api_key']); ?>" readonly>
                                    <div class="form-help">مفتاح API للوصول إلى النظام من التطبيقات الخارجية</div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="cron_key">مفتاح Cron</label>
                                    <input type="text" class="form-control" id="cron_key" name="cron_key" 
                                           value="<?php echo htmlspecialchars($settings['cron_key']); ?>" readonly>
                                    <div class="form-help">مفتاح لتشغيل المهام المجدولة</div>
                                </div>
                            </div>
                            
                            <div style="text-align: center; margin-top: 20px;">
                                <button type="button" class="btn btn-secondary" onclick="generateApiKey()">
                                    <i class="fas fa-key"></i> إنشاء مفتاح API جديد
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="generateCronKey()">
                                    <i class="fas fa-redo"></i> إنشاء مفتاح Cron جديد
                                </button>
                            </div>
                            
                            <div class="form-group" style="margin-top: 30px;">
                                <label class="form-label">إجراءات النظام</label>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 15px;">
                                    <button type="button" class="btn btn-secondary" onclick="clearCache()">
                                        <i class="fas fa-broom"></i> مسح الذاكرة المؤقتة
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="optimizeDatabase()">
                                        <i class="fas fa-database"></i> تحسين قاعدة البيانات
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="checkUpdates()">
                                        <i class="fas fa-sync"></i> التحقق من التحديثات
                                    </button>
                                    <button type="button" class="btn btn-danger" onclick="resetSettings()">
                                        <i class="fas fa-trash"></i> إعادة تعيين الإعدادات
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- أزرار الحفظ -->
                <div style="text-align: center; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> حفظ جميع الإعدادات
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> إعادة تعيين
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
        // تبديل التبويبات
        function showTab(tabId) {
            // إخفاء جميع المحتويات
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // إلغاء تنشيط جميع التبويبات
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // إظهار المحتوى المحدد
            document.getElementById(tabId).classList.add('active');
            
            // تنشيط التبويب المحدد
            document.querySelectorAll('.tab').forEach(tab => {
                if (tab.textContent.trim() === document.querySelector(`[onclick="showTab('${tabId}')"]`).textContent.trim()) {
                    tab.classList.add('active');
                }
            });
        }
        
        // اختبار إعدادات البريد الإلكتروني
        function testEmailSettings() {
            alert('سيتم اختبار إعدادات البريد الإلكتروني قريباً');
        }
        
        // إنشاء نسخة احتياطية
        function createBackup() {
            if (confirm('هل تريد إنشاء نسخة احتياطية الآن؟')) {
                alert('جارٍ إنشاء النسخة الاحتياطية...');
                // هنا سيتم إضافة الكود لإنشاء نسخة احتياطية
            }
        }
        
        // تنزيل النسخة الاحتياطية
        function downloadBackup() {
            const backupFile = document.getElementById('backup_files').value;
            if (backupFile) {
                alert(`جارٍ تنزيل النسخة: ${backupFile}`);
                // هنا سيتم إضافة الكود لتنزيل النسخة
            } else {
                alert('يرجى اختيار نسخة احتياطية أولاً');
            }
        }
        
        // استعادة النسخة الاحتياطية
        function restoreBackup() {
            const backupFile = document.getElementById('backup_files').value;
            if (backupFile && confirm(`هل تريد استعادة النسخة: ${backupFile}؟ سيتم فقدان البيانات الحالية.`)) {
                alert('جارٍ استعادة النسخة الاحتياطية...');
                // هنا سيتم إضافة الكود لاستعادة النسخة
            }
        }
        
        // طباعة صفحة اختبار
        function printTestPage() {
            alert('جارٍ طباعة صفحة الاختبار...');
            // هنا سيتم إضافة الكود لطباعة صفحة اختبار
        }
        
        // إنشاء مفتاح API جديد
        function generateApiKey() {
            if (confirm('هل تريد إنشاء مفتاح API جديد؟ سيتم تعطيل المفاتيح القديمة.')) {
                const newKey = 'api_' + Math.random().toString(36).substr(2, 16);
                document.getElementById('api_key').value = newKey;
                alert('تم إنشاء مفتاح API جديد');
            }
        }
        
        // إنشاء مفتاح Cron جديد
        function generateCronKey() {
            if (confirm('هل تريد إنشاء مفتاح Cron جديد؟ سيتم تعطيل المفاتيح القديمة.')) {
                const newKey = 'cron_' + Math.random().toString(36).substr(2, 16);
                document.getElementById('cron_key').value = newKey;
                alert('تم إنشاء مفتاح Cron جديد');
            }
        }
        
        // مسح الذاكرة المؤقتة
        function clearCache() {
            if (confirm('هل تريد مسح الذاكرة المؤقتة؟')) {
                alert('جارٍ مسح الذاكرة المؤقتة...');
                // هنا سيتم إضافة الكود لمسح الذاكرة المؤقتة
            }
        }
        
        // تحسين قاعدة البيانات
        function optimizeDatabase() {
            if (confirm('هل تريد تحسين قاعدة البيانات؟')) {
                alert('جارٍ تحسين قاعدة البيانات...');
                // هنا سيتم إضافة الكود لتحسين قاعدة البيانات
            }
        }
        
        // التحقق من التحديثات
        function checkUpdates() {
            alert('جارٍ التحقق من التحديثات...');
            // هنا سيتم إضافة الكود للتحقق من التحديثات
        }
        
        // إعادة تعيين الإعدادات
        function resetSettings() {
            if (confirm('هل تريد إعادة تعيين جميع الإعدادات إلى القيم الافتراضية؟ لا يمكن التراجع عن هذا الإجراء.')) {
                alert('جارٍ إعادة تعيين الإعدادات...');
                // هنا سيتم إضافة الكود لإعادة تعيين الإعدادات
            }
        }
        
        // تهيئة الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            // يمكنك إضافة أي تهيئة إضافية هنا
        });
    </script>
</body>
</html>