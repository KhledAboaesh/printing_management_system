<?php
// ملف view_employee.php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// ✅ التحقق من تسجيل الدخول
checkLogin();

// ✅ الحصول على دور المستخدم من الجلسة
$user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'guest';

// ✅ السماح فقط للمدير أو الموارد البشرية
if (!in_array($user_role, ['admin', 'hr'])) {
    header("Location: dashboard.php");
    exit();
}

// ✅ التحقق من معرف الموظف
if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: employees_report.php");
    exit();
}

$employee_id = intval($_GET['id']);

// ✅ جلب بيانات الموظف
$employee = [];
try {
    $stmt = $db->prepare("
        SELECT 
            u.user_id, u.full_name, u.username, u.email, u.phone, u.role, u.is_active, u.created_at,
            e.employee_id, e.national_id, e.hire_date, e.position, e.department_id, e.salary,
            e.emergency_contact, e.emergency_phone
        FROM users u
        INNER JOIN employees e ON u.user_id = e.user_id
        WHERE e.employee_id = ?
    ");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        header("Location: employees_report.php");
        exit();
    }
} catch (PDOException $e) {
    die("خطأ في جلب بيانات الموظف: " . htmlspecialchars($e->getMessage()));
}

// ✅ جلب الحضور (آخر 7 أيام)
$attendance = [];
try {
    $stmt = $db->prepare("
        SELECT date, check_in, check_out, status, notes
        FROM attendance 
        WHERE employee_id = ?
        ORDER BY date DESC 
        LIMIT 7
    ");
    $stmt->execute([$employee_id]);
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Attendance Error: ' . $e->getMessage());
}

// ✅ جلب طلبات الإجازة (آخر 5 طلبات)
$leave_requests = [];
try {
    $stmt = $db->prepare("
        SELECT start_date, end_date, type, status, requested_at
        FROM leave_requests 
        WHERE employee_id = ?
        ORDER BY requested_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$employee_id]);
    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Leave Requests Error: ' . $e->getMessage());
}

// ✅ جلب الرواتب (آخر 6 دفعات)
$payroll_history = [];
try {
    $stmt = $db->prepare("
        SELECT month, year, basic_salary, bonuses, deductions, tax, net_salary, payment_date, status
        FROM payroll 
        WHERE employee_id = ?
        ORDER BY year DESC, month DESC 
        LIMIT 6
    ");
    $stmt->execute([$employee_id]);
    $payroll_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Payroll Error: ' . $e->getMessage());
}

// ✅ حساب مدة العمل
$hire_date = !empty($employee['hire_date']) ? new DateTime($employee['hire_date']) : null;
$current_date = new DateTime();
$years_of_service = 0;
$months_of_service = 0;
if ($hire_date) {
    $service_duration = $hire_date->diff($current_date);
    $years_of_service = $service_duration->y;
    $months_of_service = $service_duration->m;
}

// ✅ أسماء الأقسام بالعربية
$departments = [
    1 => 'الإدارة',
    2 => 'المبيعات',
    3 => 'الإنتاج',
    4 => 'التصميم',
    5 => 'المحاسبة',
    6 => 'الموارد البشرية'
];

// ✅ تحديد اسم القسم للموظف
$employee_department = $departments[$employee['department_id']] ?? 'غير محدد';

// ✅ أنواع الإجازات
$leave_types = [
    'annual' => 'سنوية',
    'sick' => 'مرضية',
    'unpaid' => 'غير مدفوعة',
    'other' => 'أخرى'
];

// ✅ حالات الإجازات
$leave_statuses = [
    'pending' => 'قيد الانتظار',
    'approved' => 'موافق عليها',
    'rejected' => 'مرفوضة'
];
?>



<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الموظف - نظام المطبعة</title>
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
        
        /* بطاقة الموظف */
        .employee-card {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .employee-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .employee-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            font-weight: bold;
            margin-left: 20px;
        }
        
        .employee-info {
            flex: 1;
        }
        
        .employee-name {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .employee-position {
            font-size: 18px;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .employee-department {
            display: inline-block;
            padding: 5px 15px;
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border-radius: 20px;
            font-size: 14px;
        }
        
        .employee-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            margin-right: 10px;
        }
        
        .status-active {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }
        
        .status-inactive {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--danger);
        }
        
        /* أزرار الإجراءات */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
        
        /* تفاصيل الموظف */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .detail-section {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--primary);
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-left: 10px;
        }
        
        .detail-item {
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--gray);
        }
        
        .detail-value {
            color: var(--dark);
        }
        
        /* الجداول */
        .table-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .table-title {
            font-size: 18px;
            font-weight: 600;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
        }
        
        .data-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--gray);
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }
        
        .badge-warning {
            background-color: rgba(248, 150, 30, 0.1);
            color: var(--warning);
        }
        
        .badge-danger {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--danger);
        }
        
        .badge-info {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .employee-header {
                flex-direction: column;
                text-align: center;
            }
            
            .employee-avatar {
                margin-left: 0;
                margin-bottom: 20px;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
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
                <li><a href="hr.php" class="active"><i class="fas fa-user-tie"></i> الموارد البشرية</a></li>
                <li><a href="invoices.php"><i class="fas fa-file-invoice-dollar"></i> الفواتير</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> التقارير</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a></li>
            </ul>
        </aside>
        
        <!-- المحتوى الرئيسي -->
        <main class="main-content">
            <div class="header">
                <h1>تفاصيل الموظف</h1>
                
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
            
            <!-- بطاقة الموظف -->
            <div class="employee-card">
                <div class="employee-header">
                    <div class="employee-avatar">
                        <?php echo substr($employee['full_name'], 0, 1); ?>
                    </div>
                  <div class="employee-info">
    <h2 class="employee-name"><?php echo htmlspecialchars($employee['full_name']); ?></h2>
    <div class="employee-position"><?php echo htmlspecialchars($employee['position']); ?></div>
    <div class="employee-department"><?php echo htmlspecialchars($employee_department); ?></div>
    <span class="employee-status <?php echo $employee['is_active'] ? 'status-active' : 'status-inactive'; ?>">
        <?php echo $employee['is_active'] ? 'نشط' : 'غير نشط'; ?>
    </span>
</div>

                </div>
                
                <div class="action-buttons">
                    <a href="edit_employee.php?id=<?php echo $employee['employee_id']; ?>" class="btn btn-success">
                        <i class="fas fa-edit"></i> تعديل البيانات
                    </a>
                    <a href="employees_report.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> العودة للقائمة
                    </a>
                   <?php if ($user_role === 'admin' && !empty($employee['employee_id'])): ?>
    <a href="delete_employee.php?id=<?php echo (int)$employee['employee_id']; ?>" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا الموظف؟');">
        <i class="fas fa-trash"></i> حذف الموظف
    </a>
<?php endif; ?>

                </div>
            </div>
            
            <!-- تفاصيل الموظف -->
            <div class="details-grid">
                <!-- المعلومات الأساسية -->
                <div class="detail-section">
                    <h3 class="section-title"><i class="fas fa-info-circle"></i> المعلومات الأساسية</h3>
                    
                    <div class="detail-item">
                        <span class="detail-label">اسم المستخدم:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($employee['username']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">البريد الإلكتروني:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($employee['email'] ?? 'غير محدد'); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">رقم الهاتف:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($employee['phone'] ?? 'غير محدد'); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">الدور:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($employee['role']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">تاريخ الإنشاء:</span>
                        <span class="detail-value"><?php echo date('Y/m/d', strtotime($employee['created_at'])); ?></span>
                    </div>
                </div>
                
                <!-- المعلومات الوظيفية -->
                <div class="detail-section">
                    <h3 class="section-title"><i class="fas fa-briefcase"></i> المعلومات الوظيفية</h3>
                    
                    <div class="detail-item">
                        <span class="detail-label">الرقم الوطني:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($employee['national_id']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">تاريخ التعيين:</span>
                        <span class="detail-value"><?php echo date('Y/m/d', strtotime($employee['hire_date'])); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">مدة الخدمة:</span>
                        <span class="detail-value"><?php echo $years_of_service; ?> سنة و <?php echo $months_of_service; ?> شهر</span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">الراتب الأساسي:</span>
                        <span class="detail-value"><?php echo number_format($employee['salary'], 2); ?> د.ل</span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">حالة التوظيف:</span>
                        <span class="detail-value <?php echo $employee['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $employee['is_active'] ? 'نشط' : 'غير نشط'; ?>
                        </span>
                    </div>
                </div>
                
                <!-- معلومات الطوارئ -->
                <div class="detail-section">
                    <h3 class="section-title"><i class="fas fa-phone-alt"></i> معلومات الطوارئ</h3>
                    
                    <div class="detail-item">
                        <span class="detail-label">جهة الاتصال:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($employee['emergency_contact'] ?? 'غير محدد'); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">هاتف الطوارئ:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($employee['emergency_phone'] ?? 'غير محدد'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- سجل الحضور -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title"><i class="fas fa-calendar-check"></i> سجل الحضور (آخر 7 أيام)</h3>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>وقت الدخول</th>
                                <th>وقت الخروج</th>
                                <th>الحالة</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($attendance)): ?>
                                <?php foreach ($attendance as $record): ?>
                                    <tr>
                                        <td><?php echo date('Y/m/d', strtotime($record['date'])); ?></td>
                                        <td><?php echo $record['check_in'] ?? '--:--'; ?></td>
                                        <td><?php echo $record['check_out'] ?? '--:--'; ?></td>
                                        <td>
                                            <?php 
                                            $status_badges = [
                                                'present' => '<span class="badge badge-success">حاضر</span>',
                                                'absent' => '<span class="badge badge-danger">غائب</span>',
                                                'late' => '<span class="badge badge-warning">متأخر</span>',
                                                'vacation' => '<span class="badge badge-info">إجازة</span>',
                                                'sick' => '<span class="badge badge-info">مرضى</span>'
                                            ];
                                            echo $status_badges[$record['status']] ?? $record['status'];
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['notes'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-data">
                                        <i class="fas fa-calendar-times fa-2x" style="margin-bottom: 15px;"></i>
                                        <br>
                                        لا توجد سجلات حضور
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- طلبات الإجازة -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title"><i class="fas fa-calendar-alt"></i> طلبات الإجازة (آخر 5 طلبات)</h3>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>تاريخ الطلب</th>
                                <th>من تاريخ</th>
                                <th>إلى تاريخ</th>
                                <th>النوع</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($leave_requests)): ?>
                                <?php foreach ($leave_requests as $request): ?>
                                    <tr>
                                        <td><?php echo date('Y/m/d', strtotime($request['requested_at'])); ?></td>
                                        <td><?php echo date('Y/m/d', strtotime($request['start_date'])); ?></td>
                                        <td><?php echo date('Y/m/d', strtotime($request['end_date'])); ?></td>
                                        <td><?php echo $leave_types[$request['type']] ?? $request['type']; ?></td>
                                        <td>
                                            <?php 
                                            $status_badges = [
                                                'pending' => '<span class="badge badge-warning">قيد الانتظار</span>',
                                                'approved' => '<span class="badge badge-success">موافق عليها</span>',
                                                'rejected' => '<span class="badge badge-danger">مرفوضة</span>'
                                            ];
                                            echo $status_badges[$request['status']] ?? $request['status'];
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-data">
                                        <i class="fas fa-calendar-minus fa-2x" style="margin-bottom: 15px;"></i>
                                        <br>
                                        لا توجد طلبات إجازة
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- سجل الرواتب -->
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title"><i class="fas fa-money-bill-wave"></i> سجل الرواتب (آخر 6 أشهر)</h3>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>الشهر/السنة</th>
                                <th>الراتب الأساسي</th>
                                <th>المكافآت</th>
                                <th>الخصومات</th>
                                <th>الضريبة</th>
                                <th>صافي الراتب</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($payroll_history)): ?>
                                <?php foreach ($payroll_history as $payroll): ?>
                                    <tr>
                                        <td><?php echo $payroll['month']; ?>/<?php echo $payroll['year']; ?></td>
                                        <td><?php echo number_format($payroll['basic_salary'], 2); ?> د.ل</td>
                                        <td><?php echo number_format($payroll['bonuses'], 2); ?> د.ل</td>
                                        <td><?php echo number_format($payroll['deductions'], 2); ?> د.ل</td>
                                        <td><?php echo number_format($payroll['tax'], 2); ?> د.ل</td>
                                        <td><strong><?php echo number_format($payroll['net_salary'], 2); ?> د.ل</strong></td>
                                        <td>
                                            <?php 
                                            $status_badges = [
                                                'pending' => '<span class="badge badge-warning">قيد الانتظار</span>',
                                                'paid' => '<span class="badge badge-success">مدفوع</span>'
                                            ];
                                            echo $status_badges[$payroll['status']] ?? $payroll['status'];
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="no-data">
                                        <i class="fas fa-file-invoice-dollar fa-2x" style="margin-bottom: 15px;"></i>
                                        <br>
                                        لا توجد سجلات رواتب
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // تحديث الصفحة كل 5 دقائق للتأكد من بيانات حديثة
        setTimeout(() => {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>