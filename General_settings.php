<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (الإدارة فقط)
if ($_SESSION['role'] != 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// معالجة تحديث الإعدادات
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        global $db;
        
        // بدء معاملة
        $db->beginTransaction();
        
        // تحديث إعدادات الشركة
        if (isset($_POST['company_settings'])) {
            $stmt = $db->prepare("
                INSERT INTO settings (setting_key, setting_value, category) 
                VALUES 
                    ('company_name', ?, 'company'),
                    ('company_address', ?, 'company'),
                    ('company_phone', ?, 'company'),
                    ('company_email', ?, 'company'),
                    ('company_tax_number', ?, 'company')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            
            $stmt->execute([
                $_POST['company_name'],
                $_POST['company_address'],
                $_POST['company_phone'],
                $_POST['company_email'],
                $_POST['company_tax_number']
            ]);
        }
        
        // تحديث إعدادات العملة والتاريخ
        if (isset($_POST['currency_date_settings'])) {
            $stmt = $db->prepare("
                INSERT INTO settings (setting_key, setting_value, category) 
                VALUES 
                    ('currency', ?, 'system'),
                    ('currency_symbol', ?, 'system'),
                    ('date_format', ?, 'system'),
                    ('time_format', ?, 'system'),
                    ('timezone', ?, 'system')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            
            $stmt->execute([
                $_POST['currency'],
                $_POST['currency_symbol'],
                $_POST['date_format'],
                $_POST['time_format'],
                $_POST['timezone']
            ]);
        }
        
        // تحديث إعدادات القوالب والتنسيقات
        if (isset($_POST['template_settings'])) {
            $stmt = $db->prepare("
                INSERT INTO settings (setting_key, setting_value, category) 
                VALUES 
                    ('invoice_template', ?, 'templates'),
                    ('receipt_template', ?, 'templates'),
                    ('report_template', ?, 'templates'),
                    ('number_format', ?, 'system'),
                    ('decimal_places', ?, 'system')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            
            $stmt->execute([
                $_POST['invoice_template'],
                $_POST['receipt_template'],
                $_POST['report_template'],
                $_POST['number_format'],
                $_POST['decimal_places']
            ]);
        }
        
        // التأكد من نجاح المعاملة
        $db->commit();
        
        $success_message = "تم حفظ الإعدادات بنجاح";
        logActivity($_SESSION['user_id'], 'update_settings', 'تم تحديث الإعدادات العامة');
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('Settings Update Error: ' . $e->getMessage());
        $error_message = "حدث خطأ أثناء حفظ الإعدادات: " . $e->getMessage();
    }
}

// جلب الإعدادات الحالية
try {
    global $db;
    
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
    $settings_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $settings = [];
    foreach ($settings_result as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
} catch (PDOException $e) {
    error_log('Settings Fetch Error: ' . $e->getMessage());
    $error_message = "حدث خطأ في جلب الإعدادات من النظام";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - الإعدادات العامة</title>
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
        
        .page-title {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--secondary-color);
            text-align: center;
        }
        
        .settings-tabs {
            display: flex;
            margin-bottom: 20px;
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }
        
        .tab-button {
            flex: 1;
            padding: 15px;
            text-align: center;
            background-color: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .tab-button:hover {
            background-color: var(--light-color);
        }
        
        .tab-button.active {
            border-bottom: 3px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
            background-color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
            background-color: white;
            cursor: pointer;
        }
        
        .form-select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-secondary {
            background-color: var(--light-color);
            color: var(--dark-color);
        }
        
        .btn-secondary:hover {
            background-color: #dde4e6;
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .preview-box {
            background-color: var(--light-color);
            padding: 15px;
            border-radius: var(--border-radius);
            margin-top: 15px;
        }
        
        .preview-title {
            font-weight: 500;
            margin-bottom: 10px;
            color: var(--dark-color);
        }
        
        @media (max-width: 768px) {
            .settings-tabs {
                flex-direction: column;
            }
            
            .settings-grid {
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
                    <div style="font-size: 12px; color: #777;">مدير النظام</div>
                </div>
            </div>
        </header>
        
        <h1 class="page-title">الإعدادات العامة</h1>
        
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
            <button class="tab-button active" data-tab="company-settings">
                <i class="fas fa-building"></i> إعدادات الشركة
            </button>
            <button class="tab-button" data-tab="currency-date-settings">
                <i class="fas fa-money-bill-wave"></i> العملة والتاريخ
            </button>
            <button class="tab-button" data-tab="template-settings">
                <i class="fas fa-paint-brush"></i> القوالب والتنسيقات
            </button>
        </div>
        
        <form method="POST" action="">
            <!-- إعدادات الشركة -->
            <div class="tab-content active" id="company-settings">
                <h2 style="margin-bottom: 20px; color: var(--secondary-color);">إعدادات الشركة</h2>
                
                <div class="settings-grid">
                    <div class="form-group">
                        <label class="form-label">اسم الشركة</label>
                        <input type="text" class="form-control" name="company_name" 
                               value="<?php echo $settings['company_name'] ?? 'شركة الطباعة المتميزة'; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" class="form-control" name="company_email" 
                               value="<?php echo $settings['company_email'] ?? 'info@example.com'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="text" class="form-control" name="company_phone" 
                               value="<?php echo $settings['company_phone'] ?? '+966112345678'; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الرقم الضريبي</label>
                        <input type="text" class="form-control" name="company_tax_number" 
                               value="<?php echo $settings['company_tax_number'] ?? '310000000000003'; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">عنوان الشركة</label>
                    <textarea class="form-control" name="company_address" rows="3"><?php echo $settings['company_address'] ?? 'الرياض، المملكة العربية السعودية'; ?></textarea>
                </div>
                
                <button type="submit" name="company_settings" class="btn btn-primary">
                    <i class="fas fa-save"></i> حفظ إعدادات الشركة
                </button>
            </div>
            
            <!-- إعدادات العملة والتاريخ -->
            <div class="tab-content" id="currency-date-settings">
                <h2 style="margin-bottom: 20px; color: var(--secondary-color);">إعدادات العملة والتاريخ</h2>
                
                <div class="settings-grid">
                    <div class="form-group">
                        <label class="form-label">العملة</label>
                        <select class="form-select" name="currency">
                            <option value="SAR" <?php echo ($settings['currency'] ?? 'SAR') == 'SAR' ? 'selected' : ''; ?>>ريال سعودي (SAR)</option>
                            <option value="USD" <?php echo ($settings['currency'] ?? 'SAR') == 'USD' ? 'selected' : ''; ?>>دولار أمريكي (USD)</option>
                            <option value="EUR" <?php echo ($settings['currency'] ?? 'SAR') == 'EUR' ? 'selected' : ''; ?>>يورو (EUR)</option>
                            <option value="AED" <?php echo ($settings['currency'] ?? 'SAR') == 'AED' ? 'selected' : ''; ?>>درهم إماراتي (AED)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">رمز العملة</label>
                        <input type="text" class="form-control" name="currency_symbol" 
                               value="<?php echo $settings['currency_symbol'] ?? 'د.ل'; ?>" required maxlength="5">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">تنسيق التاريخ</label>
                        <select class="form-select" name="date_format">
                            <option value="Y-m-d" <?php echo ($settings['date_format'] ?? 'Y-m-d') == 'Y-m-d' ? 'selected' : ''; ?>>2023-12-31</option>
                            <option value="d/m/Y" <?php echo ($settings['date_format'] ?? 'Y-m-d') == 'd/m/Y' ? 'selected' : ''; ?>>31/12/2023</option>
                            <option value="m/d/Y" <?php echo ($settings['date_format'] ?? 'Y-m-d') == 'm/d/Y' ? 'selected' : ''; ?>>12/31/2023</option>
                            <option value="d-m-Y" <?php echo ($settings['date_format'] ?? 'Y-m-d') == 'd-m-Y' ? 'selected' : ''; ?>>31-12-2023</option>
                        </select>
                        
                        <div class="preview-box">
                            <div class="preview-title">معاينة:</div>
                            <div id="date-format-preview"><?php echo date($settings['date_format'] ?? 'Y-m-d'); ?></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">تنسيق الوقت</label>
                        <select class="form-select" name="time_format">
                            <option value="H:i" <?php echo ($settings['time_format'] ?? 'H:i') == 'H:i' ? 'selected' : ''; ?>>24 ساعة (14:30)</option>
                            <option value="h:i A" <?php echo ($settings['time_format'] ?? 'H:i') == 'h:i A' ? 'selected' : ''; ?>>12 ساعة (02:30 PM)</option>
                        </select>
                        
                        <div class="preview-box">
                            <div class="preview-title">معاينة:</div>
                            <div id="time-format-preview"><?php echo date($settings['time_format'] ?? 'H:i'); ?></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">المنطقة الزمنية</label>
                        <select class="form-select" name="timezone">
                            <option value="Asia/Riyadh" <?php echo ($settings['timezone'] ?? 'Asia/Riyadh') == 'Asia/Riyadh' ? 'selected' : ''; ?>>الرياض (UTC+3)</option>
                            <option value="Asia/Dubai" <?php echo ($settings['timezone'] ?? 'Asia/Riyadh') == 'Asia/Dubai' ? 'selected' : ''; ?>>دبي (UTC+4)</option>
                            <option value="Europe/London" <?php echo ($settings['timezone'] ?? 'Asia/Riyadh') == 'Europe/London' ? 'selected' : ''; ?>>لندن (UTC+0)</option>
                            <option value="America/New_York" <?php echo ($settings['timezone'] ?? 'Asia/Riyadh') == 'America/New_York' ? 'selected' : ''; ?>>نيويورك (UTC-5)</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="currency_date_settings" class="btn btn-primary">
                    <i class="fas fa-save"></i> حفظ إعدادات العملة والتاريخ
                </button>
            </div>
            
            <!-- إعدادات القوالب والتنسيقات -->
            <div class="tab-content" id="template-settings">
                <h2 style="margin-bottom: 20px; color: var(--secondary-color);">إعدادات القوالب والتنسيقات</h2>
                
                <div class="settings-grid">
                    <div class="form-group">
                        <label class="form-label">قالب الفاتورة</label>
                        <select class="form-select" name="invoice_template">
                            <option value="default" <?php echo ($settings['invoice_template'] ?? 'default') == 'default' ? 'selected' : ''; ?>>الافتراضي</option>
                            <option value="modern" <?php echo ($settings['invoice_template'] ?? 'default') == 'modern' ? 'selected' : ''; ?>>مودرن</option>
                            <option value="classic" <?php echo ($settings['invoice_template'] ?? 'default') == 'classic' ? 'selected' : ''; ?>>كلاسيكي</option>
                            <option value="minimal" <?php echo ($settings['invoice_template'] ?? 'default') == 'minimal' ? 'selected' : ''; ?>>مينيمال</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">قالب الإيصال</label>
                        <select class="form-select" name="receipt_template">
                            <option value="default" <?php echo ($settings['receipt_template'] ?? 'default') == 'default' ? 'selected' : ''; ?>>الافتراضي</option>
                            <option value="thermal" <?php echo ($settings['receipt_template'] ?? 'default') == 'thermal' ? 'selected' : ''; ?>>حراري</option>
                            <option value="detailed" <?php echo ($settings['receipt_template'] ?? 'default') == 'detailed' ? 'selected' : ''; ?>>مفصل</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">قالب التقارير</label>
                        <select class="form-select" name="report_template">
                            <option value="default" <?php echo ($settings['report_template'] ?? 'default') == 'default' ? 'selected' : ''; ?>>الافتراضي</option>
                            <option value="corporate" <?php echo ($settings['report_template'] ?? 'default') == 'corporate' ? 'selected' : ''; ?>>شركات</option>
                            <option value="simple" <?php echo ($settings['report_template'] ?? 'default') == 'simple' ? 'selected' : ''; ?>>بسيط</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">تنسيق الأرقام</label>
                        <select class="form-select" name="number_format">
                            <option value="1,234.56" <?php echo ($settings['number_format'] ?? '1,234.56') == '1,234.56' ? 'selected' : ''; ?>>1,234.56 (الإنجليزية)</option>
                            <option value="1.234,56" <?php echo ($settings['number_format'] ?? '1,234.56') == '1.234,56' ? 'selected' : ''; ?>>1.234,56 (الأوروبية)</option>
                            <option value="1234.56" <?php echo ($settings['number_format'] ?? '1,234.56') == '1234.56' ? 'selected' : ''; ?>>1234.56 (بدون فواصل)</option>
                        </select>
                        
                        <div class="preview-box">
                            <div class="preview-title">معاينة:</div>
                            <div id="number-format-preview">
                                <?php 
                                $number = 1234.56;
                                if (($settings['number_format'] ?? '1,234.56') == '1.234,56') {
                                    echo number_format($number, 2, ',', '.');
                                } elseif (($settings['number_format'] ?? '1,234.56') == '1234.56') {
                                    echo number_format($number, 2, '.', '');
                                } else {
                                    echo number_format($number, 2);
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الخانات العشرية</label>
                        <select class="form-select" name="decimal_places">
                            <option value="2" <?php echo ($settings['decimal_places'] ?? '2') == '2' ? 'selected' : ''; ?>>2 (0.00)</option>
                            <option value="3" <?php echo ($settings['decimal_places'] ?? '2') == '3' ? 'selected' : ''; ?>>3 (0.000)</option>
                            <option value="0" <?php echo ($settings['decimal_places'] ?? '2') == '0' ? 'selected' : ''; ?>>0 (0)</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="template_settings" class="btn btn-primary">
                    <i class="fas fa-save"></i> حفظ إعدادات القوالب
                </button>
            </div>
        </form>
    </div>

    <script>
        // تبديل التبويبات
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', () => {
                // إزالة النشاط من جميع الأزرار والمحتويات
                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                // إضافة النشاط للعناصر المحددة
                button.classList.add('active');
                document.getElementById(button.dataset.tab).classList.add('active');
            });
        });
        
        // معاينة تنسيق التاريخ والوقت
        const dateFormatSelect = document.querySelector('select[name="date_format"]');
        const timeFormatSelect = document.querySelector('select[name="time_format"]');
        const numberFormatSelect = document.querySelector('select[name="number_format"]');
        
        if (dateFormatSelect) {
            dateFormatSelect.addEventListener('change', updateDatePreview);
        }
        
        if (timeFormatSelect) {
            timeFormatSelect.addEventListener('change', updateTimePreview);
        }
        
        if (numberFormatSelect) {
            numberFormatSelect.addEventListener('change', updateNumberPreview);
        }
        
        function updateDatePreview() {
            const format = dateFormatSelect.value;
            const now = new Date();
            const formattedDate = formatDate(now, format);
            document.getElementById('date-format-preview').textContent = formattedDate;
        }
        
        function updateTimePreview() {
            const format = timeFormatSelect.value;
            const now = new Date();
            const formattedTime = formatTime(now, format);
            document.getElementById('time-format-preview').textContent = formattedTime;
        }
        
        function updateNumberPreview() {
            const format = numberFormatSelect.value;
            const number = 1234.56;
            let formattedNumber;
            
            if (format === '1.234,56') {
                formattedNumber = number.toFixed(2).replace('.', ',').replace(/\d(?=(\d{3})+,)/g, '$&.');
            } else if (format === '1234.56') {
                formattedNumber = number.toFixed(2);
            } else {
                formattedNumber = number.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
            
            document.getElementById('number-format-preview').textContent = formattedNumber;
        }
        
        function formatDate(date, format) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            
            switch (format) {
                case 'Y-m-d': return `${year}-${month}-${day}`;
                case 'd/m/Y': return `${day}/${month}/${year}`;
                case 'm/d/Y': return `${month}/${day}/${year}`;
                case 'd-m-Y': return `${day}-${month}-${year}`;
                default: return `${year}-${month}-${day}`;
            }
        }
        
        function formatTime(date, format) {
            const hours = date.getHours();
            const minutes = String(date.getMinutes()).padStart(2, '0');
            
            if (format === 'h:i A') {
                const period = hours >= 12 ? 'PM' : 'AM';
                const twelveHour = hours % 12 || 12;
                return `${twelveHour}:${minutes} ${period}`;
            } else {
                return `${String(hours).padStart(2, '0')}:${minutes}`;
            }
        }
    </script>
</body>
</html>