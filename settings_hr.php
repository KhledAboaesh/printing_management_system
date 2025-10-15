<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التأكد من تسجيل الدخول
checkLogin();

// التحقق من صلاحية المستخدم بشكل آمن
// $user_role = $_SESSION['user_role'] ?? '';
// $user_id   = $_SESSION['user_id'] ?? 0;

// if (!in_array($user_role, ['admin', 'hr'])) {
//     header("Location: dashboard.php");
//     exit();
// }

// مصفوفة الإعدادات والرسائل
$leave_settings = [];
$errors = [];
$success = false;

// جلب الإعدادات الحالية
try {
    $stmt = $db->query("SELECT * FROM hr_settings WHERE setting_type = 'leave'");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($settings as $setting) {
        $leave_settings[$setting['setting_key']] = $setting['setting_value'];
    }
} catch (PDOException $e) {
    $errors[] = "خطأ في جلب الإعدادات: " . $e->getMessage();
}

// معالجة حفظ الإعدادات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // الحصول على القيم مع حماية من عدم وجودها
        $annual_leave_days    = intval($_POST['annual_leave_days'] ?? 0);
        $max_annual_carryover = intval($_POST['max_annual_carryover'] ?? 0);
        $min_service_months    = intval($_POST['min_service_months'] ?? 0);
        $sick_leave_days       = intval($_POST['sick_leave_days'] ?? 0);
        $emergency_leave_days  = intval($_POST['emergency_leave_days'] ?? 0);
        $leave_request_notice  = intval($_POST['leave_request_notice'] ?? 0);
        $max_consecutive_days  = intval($_POST['max_consecutive_days'] ?? 0);

        // التحقق من صحة القيم
        if ($annual_leave_days <= 0) $errors[] = "عدد أيام الإجازة السنوية يجب أن يكون أكبر من صفر";
        if ($max_annual_carryover < 0) $errors[] = "الحد الأقصى للرصيد المحول لا يمكن أن يكون سالباً";
        if ($min_service_months <= 0) $errors[] = "مدة الخدمة الأدنى يجب أن تكون أكبر من صفر";

        if (empty($errors)) {
            $settings_to_save = [
                'annual_leave_days'   => $annual_leave_days,
                'max_annual_carryover'=> $max_annual_carryover,
                'min_service_months'  => $min_service_months,
                'sick_leave_days'     => $sick_leave_days,
                'emergency_leave_days'=> $emergency_leave_days,
                'leave_request_notice'=> $leave_request_notice,
                'max_consecutive_days'=> $max_consecutive_days
            ];

            foreach ($settings_to_save as $key => $value) {
                $stmt = $db->prepare("
                    INSERT INTO hr_settings (setting_type, setting_key, setting_value, updated_by, updated_at)
                    VALUES ('leave', ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()
                ");
                $stmt->execute([$key, $value, $user_id]);
            }

            $db->commit();

            // تسجيل النشاط
            logActivity($user_id, 'update_hr_settings', "تم تحديث إعدادات الموارد البشرية");

            $success = true;
            $_SESSION['success_message'] = "تم حفظ الإعدادات بنجاح!";

            // إعادة تحميل الإعدادات
            $stmt = $db->query("SELECT * FROM hr_settings WHERE setting_type = 'leave'");
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $leave_settings = [];
            foreach ($settings as $setting) {
                $leave_settings[$setting['setting_key']] = $setting['setting_value'];
            }
        }

    } catch (PDOException $e) {
        $db->rollBack();
        $errors[] = "حدث خطأ أثناء حفظ الإعدادات: " . $e->getMessage();
    }
}

// جلب سجل التعديلات
$settings_history = [];
try {
    $stmt = $db->prepare("
        SELECT s.*, u.full_name 
        FROM hr_settings_history s 
        LEFT JOIN users u ON s.updated_by = u.user_id 
        WHERE s.setting_type = 'leave' 
        ORDER BY s.updated_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $settings_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Settings History Error: ' . $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات الموارد البشرية - نظام المطبعة</title>
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
        
        /* تبويبات الإعدادات */
        .settings-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .tab {
            padding: 15px 25px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
            font-weight: 600;
        }
        
        .tab:hover {
            background-color: #f8f9fa;
        }
        
        /* نموذج الإعدادات */
        .settings-form {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
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
        
        .section-description {
            color: var(--gray);
            margin-bottom: 25px;
            line-height: 1.6;
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
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .form-help {
            font-size: 12px;
            color: var(--gray);
            margin-top: 5px;
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
        
        .btn i {
            margin-left: 8px;
        }
        
        /* سجل التعديلات */
        .history-section {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .history-table th,
        .history-table td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }
        
        .history-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
        }
        
        .history-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--gray);
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
                <li><a href="payroll.php"><i class="fas fa-money-bill-wave"></i> كشوف المرتبات</a></li>
                <li><a href="invoices.php"><i class="fas fa-file-invoice-dollar"></i> الفواتير</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> التقارير</a></li>
                <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> الإعدادات</a></li>
            </ul>
        </aside>
        
        <!-- المحتوى الرئيسي -->
        <main class="main-content">
            <div class="header">
                <h1>إعدادات الموارد البشرية</h1>
                
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
            
            <!-- تبويبات الإعدادات -->
            <div class="settings-tabs">
                <div class="tab active">إعدادات الإجازات</div>
                <div class="tab">إعدادات التوظيف</div>
                <div class="tab">إعدادات الحضور</div>
                <div class="tab">الإشعارات</div>
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
            
            <!-- نموذج إعدادات الإجازات -->
            <form method="POST" action="">
                <div class="settings-form">
                    <!-- إعدادات الإجازات السنوية -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-calendar-alt"></i> الإجازات السنوية</h3>
                        <p class="section-description">
                            قم بتعيين السياسات والإعدادات الخاصة بالإجازات السنوية للموظفين
                        </p>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="annual_leave_days">عدد أيام الإجازة السنوية *</label>
                                <input type="number" class="form-control" id="annual_leave_days" name="annual_leave_days" 
                                       value="<?php echo $leave_settings['annual_leave_days'] ?? 21; ?>" min="1" max="365" required>
                                <div class="form-help">عدد الأيام السنوية المستحقة لكل موظف</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="max_annual_carryover">الحد الأقصى للرصيد المحول</label>
                                <input type="number" class="form-control" id="max_annual_carryover" name="max_annual_carryover" 
                                       value="<?php echo $leave_settings['max_annual_carryover'] ?? 7; ?>" min="0" max="365">
                                <div class="form-help">أقصى عدد من الأيام التي يمكن نقلها للعام التالي</div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="min_service_months">مدة الخدمة الأدنى (أشهر) *</label>
                                <input type="number" class="form-control" id="min_service_months" name="min_service_months" 
                                       value="<?php echo $leave_settings['min_service_months'] ?? 6; ?>" min="1" max="12" required>
                                <div class="form-help">أقل مدة خدمة لاستحقاق الإجازة الكاملة</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="max_consecutive_days">أقصى مدة متواصلة للإجازة</label>
                                <input type="number" class="form-control" id="max_consecutive_days" name="max_consecutive_days" 
                                       value="<?php echo $leave_settings['max_consecutive_days'] ?? 14; ?>" min="1" max="365">
                                <div class="form-help">أقصى عدد من الأيام المتواصلة المسموح بها للإجازة</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- إعدادات أنواع الإجازات الأخرى -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-heartbeat"></i> أنواع الإجازات الأخرى</h3>
                        <p class="section-description">
                            إعدادات أنواع الإجازات الأخرى مثل الإجازات المرضية والطارئة
                        </p>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="sick_leave_days">الإجازات المرضية السنوية</label>
                                <input type="number" class="form-control" id="sick_leave_days" name="sick_leave_days" 
                                       value="<?php echo $leave_settings['sick_leave_days'] ?? 30; ?>" min="0" max="365">
                                <div class="form-help">عدد أيام الإجازة المرضية المسموح بها سنوياً</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="emergency_leave_days">الإجازات الطارئة</label>
                                <input type="number" class="form-control" id="emergency_leave_days" name="emergency_leave_days" 
                                       value="<?php echo $leave_settings['emergency_leave_days'] ?? 5; ?>" min="0" max="365">
                                <div class="form-help">عدد أيام الإجازة الطارئة المسموح بها سنوياً</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- إعدادات طلبات الإجازة -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-clock"></i> إجراءات طلبات الإجازة</h3>
                        <p class="section-description">
                            إعدادات الفترات الزمنية والإجراءات المتبعة لطلبات الإجازة
                        </p>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="leave_request_notice">فترة الإشعار المسبق (أيام)</label>
                                <input type="number" class="form-control" id="leave_request_notice" name="leave_request_notice" 
                                       value="<?php echo $leave_settings['leave_request_notice'] ?? 3; ?>" min="0" max="30">
                                <div class="form-help">الحد الأدنى للأيام المطلوبة للإشعار قبل بدء الإجازة</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="max_advance_booking">الحجز المسبق الأقصى (أيام)</label>
                                <input type="number" class="form-control" id="max_advance_booking" name="max_advance_booking" 
value="<?php echo is_array($leave_settings) ? ($leave_settings['max_advance_booking'] ?? 90) : 90; ?>" min="0" max="365">
                                <div class="form-help">أقصى مدة للحجز المسبق للإجازة</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- أزرار الحفظ -->
                    <div style="text-align: center; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ الإعدادات
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> إعادة تعيين
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- سجل التعديلات -->
            <div class="history-section">
                <h3 class="section-title"><i class="fas fa-history"></i> سجل التعديلات</h3>
                
                <?php if (!empty($settings_history)): ?>
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>الإعداد</th>
                                <th>القيمة القديمة</th>
                                <th>القيمة الجديدة</th>
                                <th>المعدل</th>
                                <th>تاريخ التعديل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($settings_history as $history): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($history['setting_key']); ?></td>
                                    <td><?php echo htmlspecialchars($history['old_value']); ?></td>
                                    <td><?php echo htmlspecialchars($history['new_value']); ?></td>
                                    <td><?php echo htmlspecialchars($history['full_name'] ?? 'نظام'); ?></td>
                                    <td><?php echo date('Y/m/d H:i', strtotime($history['updated_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-history fa-2x" style="margin-bottom: 15px;"></i>
                        <br>
                        لا توجد سجلات تعديلات
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // التحقق من صحة البيانات قبل الإرسال
        document.querySelector('form').addEventListener('submit', function(e) {
            const annualLeave = document.getElementById('annual_leave_days');
            const minService = document.getElementById('min_service_months');
            
            if (annualLeave.value <= 0) {
                e.preventDefault();
                alert('يجب أن يكون عدد أيام الإجازة السنوية أكبر من الصفر');
                annualLeave.focus();
                return false;
            }
            
            if (minService.value <= 0) {
                e.preventDefault();
                alert('يجب أن تكون مدة الخدمة الأدنى أكبر من الصفر');
                minService.focus();
                return false;
            }
        });
        
        // تبديل التبويبات
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>