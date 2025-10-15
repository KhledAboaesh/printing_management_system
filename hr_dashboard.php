<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// يمكن تعطيل التحقق لو أردت السماح للجميع بالدخول
// checkLogin();

// التحقق من الصلاحية - فقط مدير النظام ومسؤول الموارد البشرية يمكنهم الوصول
// if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'hr'])) {
//     header("Location: dashboard.php");
//     exit();
// }

$hr_stats = [
    'total_employees'   => 0,
    'active_employees'  => 0,
    'by_department'     => [],
    'total_salaries'    => 0,
    'recent_leaves'     => [],
    'attendance_today'  => 0
];

try {
    // إجمالي الموظفين
    $stmt = $db->query("SELECT COUNT(*) FROM employees");
    $hr_stats['total_employees'] = (int)$stmt->fetchColumn();

    // الموظفين النشطين
    $stmt = $db->query("
        SELECT COUNT(*) 
        FROM employees e 
        LEFT JOIN users u ON e.user_id = u.user_id 
        WHERE u.is_active = 1 OR u.is_active IS NULL
    ");
    $hr_stats['active_employees'] = (int)$stmt->fetchColumn();

    // الموظفين حسب الأقسام
    $stmt = $db->query("
        SELECT department, COUNT(*) as count 
        FROM employees 
        GROUP BY department 
        ORDER BY count DESC
    ");
    $by_department = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($by_department as &$row) {
        $row['department'] = $row['department'] ?? 'غير محدد';
    }
    unset($row);
    $hr_stats['by_department'] = $by_department;

    // إجمالي الرواتب الشهرية
    $stmt = $db->query("SELECT COALESCE(SUM(salary), 0) FROM employees");
    $hr_stats['total_salaries'] = (float)$stmt->fetchColumn();

    // طلبات الإجازة الأخيرة
    $stmt = $db->query("
        SELECT lr.*, u.full_name, e.position
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.employee_id
        JOIN users u ON e.user_id = u.user_id
        ORDER BY lr.requested_at DESC 
        LIMIT 5
    ");
    $recent_leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($recent_leaves as &$leave) {
        $leave['position'] = $leave['position'] ?? '-';
        $leave['full_name'] = $leave['full_name'] ?? '-';
    }
    unset($leave);
    $hr_stats['recent_leaves'] = $recent_leaves;

    // الحضور اليوم
    $today = date('Y-m-d');
    $stmt = $db->prepare("
        SELECT COUNT(*) 
        FROM attendance 
        WHERE date = ? AND status = 'present'
    ");
    $stmt->execute([$today]);
    $hr_stats['attendance_today'] = (int)$stmt->fetchColumn();

} catch (PDOException $e) {
    error_log('HR Dashboard Error: ' . $e->getMessage());
}

// الموظفون الجدد (آخر 30 يوم)
$new_employees = [];
try {
    $stmt = $db->query("
        SELECT e.*, u.full_name, u.email, u.phone
        FROM employees e
        JOIN users u ON e.user_id = u.user_id
        WHERE e.hire_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY e.hire_date DESC 
        LIMIT 5
    ");
    $new_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($new_employees as &$emp) {
        $emp['department'] = $emp['department'] ?? 'غير محدد';
        $emp['position'] = $emp['position'] ?? '-';
        $emp['full_name'] = $emp['full_name'] ?? '-';
        $emp['hire_date'] = $emp['hire_date'] ?? '-';
        $emp['salary'] = $emp['salary'] ?? 0;
    }
    unset($emp);
} catch (PDOException $e) {
    error_log('New Employees Error: ' . $e->getMessage());
}

// أعياد الميلاد القادمة (الأسبوع القادم)
$upcoming_birthdays = [];
try {
    $stmt = $db->query("
        SELECT u.full_name, e.position, e.department, e.birth_date
        FROM employees e
        JOIN users u ON e.user_id = u.user_id
        WHERE DATE_FORMAT(e.birth_date, '%m-%d') 
              BETWEEN DATE_FORMAT(CURDATE(), '%m-%d')
              AND DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '%m-%d')
        ORDER BY DATE_FORMAT(e.birth_date, '%m-%d') ASC
        LIMIT 5
    ");
    $upcoming_birthdays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($upcoming_birthdays as &$emp) {
        $emp['department'] = $emp['department'] ?? 'غير محدد';
        $emp['position'] = $emp['position'] ?? '-';
        $emp['full_name'] = $emp['full_name'] ?? '-';
        $emp['birth_date'] = $emp['birth_date'] ?? '-';
    }
    unset($emp);
} catch (PDOException $e) {
    error_log('Birthdays Error: ' . $e->getMessage());
}

// أسماء الأقسام بالعربية
$departments_ar = [
    'management' => 'الإدارة',
    'sales' => 'المبيعات',
    'production' => 'الإنتاج',
    'design' => 'التصميم',
    'accounting' => 'المحاسبة',
    'hr' => 'الموارد البشرية',
    'غير محدد' => 'غير محدد'
];

// أنواع الإجازات بالعربية
$leave_types_ar = [
    'annual' => 'سنوية',
    'sick' => 'مرضية',
    'unpaid' => 'غير مدفوعة',
    'other' => 'أخرى'
];
?>



<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم الموارد البشرية - نظام المطبعة</title>
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
        
        /* بطاقات الإحصائيات */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .stat-card-title {
            font-size: 16px;
            color: var(--gray);
            font-weight: 600;
        }
        
        .stat-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .stat-card-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .stat-card-footer {
            font-size: 14px;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* الأقسام */
        .dashboard-section {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            border: 1px solid #f0f0f0;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f8f9fa;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        /* الجداول */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .data-table th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 15px 12px;
            text-align: right;
            font-weight: 600;
            font-size: 14px;
        }
        
        .data-table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #f8f9fa;
            font-size: 14px;
        }
        
        .data-table tr:hover {
            background-color: #fafbff;
        }
        
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
        }
        
        /* أزرار سريعة */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .quick-action {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .quick-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(67, 97, 238, 0.3);
        }
        
        .quick-action i {
            font-size: 24px;
        }
        
        .quick-action span {
            font-weight: 600;
            font-size: 16px;
        }
        
        /* حالة الإجازات */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: rgba(248, 150, 30, 0.1);
            color: var(--warning);
        }
        
        .status-approved {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }
        
        .status-rejected {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--danger);
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
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
                <li><a href="hr_dashboard.php" class="active"><i class="fas fa-user-tie"></i> الموارد البشرية</a></li>
                <li><a href="employees_report.php"><i class="fas fa-users"></i> إدارة الموظفين</a></li>
                <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> الحضور والانصراف</a></li>
                <li><a href="leave_requests.php"><i class="fas fa-calendar-alt"></i> طلبات الإجازة</a></li>
                <li><a href="payroll.php"><i class="fas fa-money-bill-wave"></i> كشوف المرتبات</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> التقارير</a></li>
                <li><a href="settings_hr.php"><i class="fas fa-cog"></i> الإعدادات</a></li>
            </ul>
        </aside>
        
        <!-- المحتوى الرئيسي -->
        <main class="main-content">
            <div class="header">
                <h1>لوحة تحكم الموارد البشرية</h1>
                
                <div class="user-menu">
                    <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['user_id']; ?></div>
                        <div class="user-role">
                            <?php echo ($_SESSION['user_role'] ?? 'موظف') == 'admin' ? 'مدير النظام' : 'مسؤول الموارد البشرية'; ?>
                        </div>
                    </div>
                    <img src="images/user.png" alt="User">
                </div>
            </div>
            
            <!-- بطاقات الإحصائيات -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">إجمالي الموظفين</div>
                        <div class="stat-card-icon" style="background-color: rgba(67, 97, 238, 0.1); color: var(--primary);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $hr_stats['total_employees']; ?></div>
                    <div class="stat-card-footer">
                        <i class="fas fa-user-check"></i>
                        <?php echo $hr_stats['active_employees']; ?> موظف نشط
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">إجمالي الرواتب</div>
                        <div class="stat-card-icon" style="background-color: rgba(76, 201, 240, 0.1); color: var(--success);">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($hr_stats['total_salaries']); ?> د.ل</div>
                    <div class="stat-card-footer">
                        <i class="fas fa-calendar"></i>
                        شهرياً
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">الحضور اليوم</div>
                        <div class="stat-card-icon" style="background-color: rgba(248, 150, 30, 0.1); color: var(--warning);">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $hr_stats['attendance_today']; ?></div>
                    <div class="stat-card-footer">
                        <i class="fas fa-clock"></i>
                        <?php echo date('Y/m/d'); ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">الأقسام</div>
                        <div class="stat-card-icon" style="background-color: rgba(247, 37, 133, 0.1); color: var(--danger);">
                            <i class="fas fa-sitemap"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo count($hr_stats['by_department']); ?></div>
                    <div class="stat-card-footer">
                        <i class="fas fa-chart-pie"></i>
                        <?php echo $hr_stats['by_department'][0]['department'] ?? 'لا يوجد'; ?> الأعلى
                    </div>
                </div>
            </div>
            
            <!-- الأقسام السريعة -->
            <div class="quick-actions">
                <a href="add_employee.php" class="quick-action">
                    <i class="fas fa-user-plus"></i>
                    <span>إضافة موظف جديد</span>
                </a>
                
                <a href="attendance.php" class="quick-action">
                    <i class="fas fa-calendar-check"></i>
                    <span>تسجيل الحضور</span>
                </a>
                
                <a href="leave_requests.php" class="quick-action">
                    <i class="fas fa-calendar-alt"></i>
                    <span>طلبات الإجازة</span>
                </a>
                
                <a href="payroll.php" class="quick-action">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>كشوف المرتبات</span>
                </a>
            </div>
            
            <!-- الموظفون الجدد -->
            <div class="dashboard-section">
                <h3 class="section-title"><i class="fas fa-user-plus"></i> الموظفون الجدد</h3>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>الاسم</th>
                                <th>المسمى الوظيفي</th>
                                <th>القسم</th>
                                <th>تاريخ التعيين</th>
                                <th>الراتب</th>
                            </tr>
                        </thead>
                     <tbody>
    <?php if (!empty($new_employees)): ?>
        <?php foreach ($new_employees as $employee): ?>
            <tr>
                <td><?php echo htmlspecialchars($employee['full_name'] ?? 'غير معروف'); ?></td>
                <td><?php echo htmlspecialchars($employee['position'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($departments_ar[$employee['department'] ?? ''] ?? ($employee['department'] ?? 'غير محدد')); ?></td>
                <td><?php echo isset($employee['hire_date']) ? date('Y/m/d', strtotime($employee['hire_date'])) : '-'; ?></td>
                <td><?php echo isset($employee['salary']) ? number_format($employee['salary'], 2) . ' د.ل' : '-'; ?></td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="5" style="text-align: center; padding: 20px; color: var(--gray);">
                <i class="fas fa-info-circle"></i> لا يوجد موظفون جدد
            </td>
        </tr>
    <?php endif; ?>
</tbody>

                    </table>
                </div>
            </div>
            
            <!-- طلبات الإجازة الأخيرة -->
            <div class="dashboard-section">
                <h3 class="section-title"><i class="fas fa-calendar-alt"></i> طلبات الإجازة الأخيرة</h3>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>الموظف</th>
                                <th>نوع الإجازة</th>
                                <th>الفترة</th>
                                <th>الحالة</th>
                                <th>تاريخ الطلب</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($hr_stats['recent_leaves'])): ?>
                                <?php foreach ($hr_stats['recent_leaves'] as $leave): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($leave['full_name']); ?></td>
                                        <td><?php echo $leave_types_ar[$leave['type']] ?? $leave['type']; ?></td>
                                        <td>
                                            <?php echo date('Y/m/d', strtotime($leave['start_date'])); ?> 
                                            - 
                                            <?php echo date('Y/m/d', strtotime($leave['end_date'])); ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $leave['status']; ?>">
                                                <?php echo $leave['status'] == 'pending' ? 'قيد الانتظار' : 
                                                       ($leave['status'] == 'approved' ? 'موافق' : 'مرفوض'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y/m/d', strtotime($leave['requested_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 20px; color: var(--gray);">
                                        <i class="fas fa-info-circle"></i> لا توجد طلبات إجازة حديثة
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- التوزيع حسب الأقسام -->
            <div class="dashboard-section">
                <h3 class="section-title"><i class="fas fa-chart-pie"></i> التوزيع حسب الأقسام</h3>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>القسم</th>
                                <th>عدد الموظفين</th>
                                <th>النسبة</th>
                                <th>إجمالي الرواتب</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($hr_stats['by_department'])): ?>
                                <?php foreach ($hr_stats['by_department'] as $dept): ?>
                                    <tr>
                                        <td><?php echo $departments_ar[$dept['department']] ?? $dept['department']; ?></td>
                                        <td><?php echo $dept['count']; ?></td>
                                        <td>
                                            <?php echo round(($dept['count'] / $hr_stats['total_employees']) * 100, 1); ?>%
                                        </td>
                                        <td>
                                            <?php 
                                            // حساب إجمالي رواتب القسم (هذا مثال، تحتاج إلى استعلام حقيقي)
                                            $salary_estimate = $dept['count'] * 1500; // تقديري
                                            echo number_format($salary_estimate); ?> د.ل
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 20px; color: var(--gray);">
                                        <i class="fas fa-info-circle"></i> لا توجد بيانات للأقسام
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
        // تحديث تلقائي كل 5 دقائق
        setTimeout(() => {
            window.location.reload();
        }, 300000);
        
        // تأثيرات عند التحميل
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('animate__fadeInUp');
            });
        });
    </script>
</body>
</html>