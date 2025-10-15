<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (الموارد البشرية أو المدير)
if ($_SESSION['role'] != 'hr' && $_SESSION['role'] != 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// معالجة تحديث الإعدادات
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        global $db;
        
        // بدء معاملة لقاعدة البيانات
        $db->beginTransaction();
        
        // حفظ إعدادات الحضور والانصراف
        if (isset($_POST['attendance_settings'])) {
            $work_start = $_POST['work_start_time'] ?? '08:00:00';
            $work_end = $_POST['work_end_time'] ?? '16:00:00';
            $late_threshold = $_POST['late_threshold'] ?? 15;
            $early_departure = $_POST['early_departure_threshold'] ?? 30;
            
            // هنا سيتم حفظ الإعدادات في جدول الإعدادات (يجب إنشاؤه)
            $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value, category) VALUES 
                                ('work_start_time', ?, 'attendance'),
                                ('work_end_time', ?, 'attendance'),
                                ('late_threshold', ?, 'attendance'),
                                ('early_departure_threshold', ?, 'attendance')");
            $stmt->execute([$work_start, $work_end, $late_threshold, $early_departure]);
        }
        
        // حفظ سياسات الإجازات
        if (isset($_POST['leave_policies'])) {
            $annual_leave = $_POST['annual_leave_days'] ?? 21;
            $sick_leave = $_POST['sick_leave_days'] ?? 30;
            $emergency_leave = $_POST['emergency_leave_days'] ?? 7;
            $carry_over = $_POST['leave_carry_over'] ?? 7;
            
            $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value, category) VALUES 
                                ('annual_leave_days', ?, 'leave'),
                                ('sick_leave_days', ?, 'leave'),
                                ('emergency_leave_days', ?, 'leave'),
                                ('leave_carry_over_days', ?, 'leave')");
            $stmt->execute([$annual_leave, $sick_leave, $emergency_leave, $carry_over]);
        }
        
        // حفظ إعدادات المرتبات
        if (isset($_POST['payroll_settings'])) {
            $payday = $_POST['payday'] ?? 25;
            $overtime_rate = $_POST['overtime_rate'] ?? 1.5;
            $deduction_tax = $_POST['tax_deduction'] ?? 10;
            
            $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value, category) VALUES 
                                ('payday', ?, 'payroll'),
                                ('overtime_rate', ?, 'payroll'),
                                ('tax_deduction_percentage', ?, 'payroll')");
            $stmt->execute([$payday, $overtime_rate, $deduction_tax]);
        }
        
        // حفظ قوالب المستندات
        if (isset($_POST['document_templates'])) {
            $contract_template = $_POST['contract_template'] ?? '';
            $warning_template = $_POST['warning_template'] ?? '';
            $termination_template = $_POST['termination_template'] ?? '';
            
            $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value, category) VALUES 
                                ('contract_template', ?, 'documents'),
                                ('warning_template', ?, 'documents'),
                                ('termination_template', ?, 'documents')");
            $stmt->execute([$contract_template, $warning_template, $termination_template]);
        }
        
        $db->commit();
        $success_message = "تم حفظ الإعدادات بنجاح";
        logActivity($_SESSION['user_id'], 'update_hr_settings', 'تم تحديث إعدادات الموارد البشرية');
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('HR Settings Error: ' . $e->getMessage());
        $error_message = "حدث خطأ أثناء حفظ الإعدادات: " . $e->getMessage();
    }
}

// جلب الإعدادات الحالية
$settings = [];
try {
    global $db;
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log('Settings Fetch Error: ' . $e->getMessage());
}

// تعيين القيم الافتراضية إذا لم تكن موجودة
$default_settings = [
    'work_start_time' => '08:00:00',
    'work_end_time' => '16:00:00',
    'late_threshold' => 15,
    'early_departure_threshold' => 30,
    'annual_leave_days' => 21,
    'sick_leave_days' => 30,
    'emergency_leave_days' => 7,
    'leave_carry_over_days' => 7,
    'payday' => 25,
    'overtime_rate' => 1.5,
    'tax_deduction_percentage' => 10,
    'contract_template' => 'نموذج عقد عمل قياسي',
    'warning_template' => 'نموذج إنذار رسمي',
    'termination_template' => 'نموذج إنهاء خدمة'
];

foreach ($default_settings as $key => $value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $value;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - إعدادات الموارد البشرية</title>
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
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab-button {
            padding: 12px 20px;
            background-color: var(--light-color);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .tab-button.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .tab-button:hover:not(.active) {
            background-color: #d6dbdf;
        }
        
        .settings-section {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            margin-bottom: 25px;
            display: none;
        }
        
        .settings-section.active {
            display: block;
        }
        
        .section-title {
            font-size: 22px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--primary-color);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .submit-button {
            background-color: var(--success-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s ease;
            margin-top: 15px;
        }
        
        .submit-button:hover {
            background-color: #27ae60;
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
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .help-text {
            font-size: 14px;
            color: #777;
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .settings-tabs {
                flex-direction: column;
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
                    <div style="font-size: 12px; color: #777;">موارد بشرية</div>
                </div>
            </div>
        </header>
        
        <h1 class="page-title">إعدادات الموارد البشرية</h1>
        
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <div class="settings-tabs">
            <button class="tab-button active" onclick="showTab('attendance')">
                <i class="fas fa-clock"></i> إعدادات الحضور
            </button>
            <button class="tab-button" onclick="showTab('leave')">
                <i class="fas fa-calendar-alt"></i> سياسات الإجازات
            </button>
            <button class="tab-button" onclick="showTab('payroll')">
                <i class="fas fa-money-bill-wave"></i> إعدادات المرتبات
            </button>
            <button class="tab-button" onclick="showTab('documents')">
                <i class="fas fa-file-alt"></i> قوالب المستندات
            </button>
        </div>
        
        <form method="POST" action="">
            <!-- قسم إعدادات الحضور والانصراف -->
            <div id="attendance" class="settings-section active">
                <h2 class="section-title">
                    <i class="fas fa-clock"></i> إعدادات الحضور والانصراف
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">وقت بدء العمل</label>
                        <input type="time" class="form-input" name="work_start_time" 
                               value="<?php echo $settings['work_start_time']; ?>" required>
                        <div class="help-text">وقت بدء الدوام الرسمي</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">وقت انتهاء العمل</label>
                        <input type="time" class="form-input" name="work_end_time" 
                               value="<?php echo $settings['work_end_time']; ?>" required>
                        <div class="help-text">وقت انتهاء الدوام الرسمي</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">حد التأخير (دقائق)</label>
                        <input type="number" class="form-input" name="late_threshold" 
                               value="<?php echo $settings['late_threshold']; ?>" min="1" max="60" required>
                        <div class="help-text">عدد الدقائق المسموح بها بعد وقت الدخول لتسجيل تأخير</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">حد المغادرة المبكرة (دقائق)</label>
                        <input type="number" class="form-input" name="early_departure_threshold" 
                               value="<?php echo $settings['early_departure_threshold']; ?>" min="1" max="60" required>
                        <div class="help-text">عدد الدقائق المسموح بها قبل وقت الخروج لتسجيل مغادرة مبكرة</div>
                    </div>
                </div>
                
                <button type="submit" name="attendance_settings" class="submit-button">
                    <i class="fas fa-save"></i> حفظ إعدادات الحضور
                </button>
            </div>
            
            <!-- قسم سياسات الإجازات -->
            <div id="leave" class="settings-section">
                <h2 class="section-title">
                    <i class="fas fa-calendar-alt"></i> سياسات الإجازات
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">عدد أيام الإجازة السنوية</label>
                        <input type="number" class="form-input" name="annual_leave_days" 
                               value="<?php echo $settings['annual_leave_days']; ?>" min="0" max="365" required>
                        <div class="help-text">عدد أيام الإجازة السنوية المستحقة لكل موظف</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">عدد أيام الإجازة المرضية</label>
                        <input type="number" class="form-input" name="sick_leave_days" 
                               value="<?php echo $settings['sick_leave_days']; ?>" min="0" max="365" required>
                        <div class="help-text">عدد أيام الإجازة المرضية السنوية</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">عدد أيام الإجازة الطارئة</label>
                        <input type="number" class="form-input" name="emergency_leave_days" 
                               value="<?php echo $settings['emergency_leave_days']; ?>" min="0" max="365" required>
                        <div class="help-text">عدد أيام الإجازة الطارئة المسموح بها سنوياً</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">حد ترحيل الإجازة</label>
                        <input type="number" class="form-input" name="leave_carry_over" 
                               value="<?php echo $settings['leave_carry_over_days']; ?>" min="0" max="365" required>
                        <div class="help-text">الحد الأقصى لأيام الإجازة التي يمكن ترحيلها للعام التالي</div>
                    </div>
                </div>
                
                <button type="submit" name="leave_policies" class="submit-button">
                    <i class="fas fa-save"></i> حفظ سياسات الإجازات
                </button>
            </div>
            
            <!-- قسم إعدادات المرتبات -->
            <div id="payroll" class="settings-section">
                <h2 class="section-title">
                    <i class="fas fa-money-bill-wave"></i> إعدادات المرتبات
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">يوم صرف الراتب</label>
                        <input type="number" class="form-input" name="payday" 
                               value="<?php echo $settings['payday']; ?>" min="1" max="31" required>
                        <div class="help-text">يوم الشهر الذي يتم فيه صرف الرواتب (1-31)</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">معدل overtime</label>
                        <input type="number" class="form-input" name="overtime_rate" 
                               value="<?php echo $settings['overtime_rate']; ?>" min="1" max="3" step="0.1" required>
                        <div class="help-text">معدل حساب overtime (مثال: 1.5 يعني 1.5 ضعف الأجر العادي)</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">نسبة خصم الضريبة (%)</label>
                        <input type="number" class="form-input" name="tax_deduction" 
                               value="<?php echo $settings['tax_deduction_percentage']; ?>" min="0" max="50" required>
                        <div class="help-text">نسبة الخصم الضريبي من الراتب الأساسي</div>
                    </div>
                </div>
                
                <button type="submit" name="payroll_settings" class="submit-button">
                    <i class="fas fa-save"></i> حفظ إعدادات المرتبات
                </button>
            </div>
            
            <!-- قسم قوالب المستندات -->
            <div id="documents" class="settings-section">
                <h2 class="section-title">
                    <i class="fas fa-file-alt"></i> قوالب المستندات
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">نموذج عقد العمل</label>
                        <textarea class="form-textarea" name="contract_template" 
                                  placeholder="أدخل النموذج القياسي لعقود العمل..."><?php echo $settings['contract_template']; ?></textarea>
                        <div class="help-text">النموذج القياسي المستخدم لعقود العمل الجديدة</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">نموذج الإنذار</label>
                        <textarea class="form-textarea" name="warning_template" 
                                  placeholder="أدخل النموذج القياسي للإنذارات..."><?php echo $settings['warning_template']; ?></textarea>
                        <div class="help-text">النموذج القياسي المستخدم للإنذارات الرسمية</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">نموذج إنهاء الخدمة</label>
                        <textarea class="form-textarea" name="termination_template" 
                                  placeholder="أدخل النموذج القياسي لإنهاء الخدمة..."><?php echo $settings['termination_template']; ?></textarea>
                        <div class="help-text">النموذج القياسي المستخدم لإنهاء خدمات الموظفين</div>
                    </div>
                </div>
                
                <button type="submit" name="document_templates" class="submit-button">
                    <i class="fas fa-save"></i> حفظ قوالب المستندات
                </button>
            </div>
        </form>
    </div>

    <script>
        function showTab(tabId) {
            // إخفاء جميع الأقسام
            document.querySelectorAll('.settings-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // إلغاء تنشيط جميع الأزرار
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // إظهار القسم المحدد
            document.getElementById(tabId).classList.add('active');
            
            // تنشيط الزر المحدد
            document.querySelector(`button[onclick="showTab('${tabId}')"]`).classList.add('active');
        }
    </script>
</body>
</html>