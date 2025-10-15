<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم
if ($_SESSION['role'] != 'hr' && $_SESSION['role'] != 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// عوامل التصفية
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$department_id = $_GET['department_id'] ?? '';
$employee_id = $_GET['employee_id'] ?? '';

try {
    global $db;

    // جلب الأقسام
    $departments = $db->query("SELECT * FROM departments")->fetchAll(PDO::FETCH_ASSOC);

    // جلب الموظفين
    $employees = $db->query("SELECT employee_id, full_name FROM employees WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

    // تقارير الحضور
    $attendance_query = "SELECT a.*, e.full_name, d.name as department_name 
                         FROM attendance a 
                         LEFT JOIN employees e ON a.employee_id = e.employee_id 
                         LEFT JOIN departments d ON e.department_id = d.department_id 
                         WHERE a.date BETWEEN :start_date AND :end_date";

    $params = [':start_date' => $start_date, ':end_date' => $end_date];

    if (!empty($department_id)) {
        $attendance_query .= " AND e.department_id = :department_id";
        $params[':department_id'] = $department_id;
    }
    if (!empty($employee_id)) {
        $attendance_query .= " AND a.employee_id = :employee_id";
        $params[':employee_id'] = $employee_id;
    }

    $attendance_query .= " ORDER BY a.date DESC, e.full_name";
    $stmt = $db->prepare($attendance_query);
    $stmt->execute($params);
    $attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تقارير الإجازات
    $leaves_query = "SELECT lr.*, e.full_name, d.name as department_name 
                     FROM leave_requests lr
                     LEFT JOIN employees e ON lr.employee_id = e.employee_id
                     LEFT JOIN departments d ON e.department_id = d.department_id
                     WHERE (lr.start_date BETWEEN :start_date AND :end_date OR lr.end_date BETWEEN :start_date AND :end_date)";

    $leaves_params = [':start_date' => $start_date, ':end_date' => $end_date];

    if (!empty($department_id)) {
        $leaves_query .= " AND e.department_id = :department_id";
        $leaves_params[':department_id'] = $department_id;
    }
    if (!empty($employee_id)) {
        $leaves_query .= " AND lr.employee_id = :employee_id";
        $leaves_params[':employee_id'] = $employee_id;
    }

    $leaves_query .= " ORDER BY lr.start_date DESC, e.full_name";
    $stmt = $db->prepare($leaves_query);
    $stmt->execute($leaves_params);
    $leaves_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // إحصائيات الموظفين بدون استخدام gender
    $employee_stats_query = "SELECT COUNT(*) as total_employees, d.name as department_name
                             FROM employees e
                             LEFT JOIN departments d ON e.department_id = d.department_id
                             GROUP BY e.department_id
                             ORDER BY d.name";

    $employee_stats = $db->query($employee_stats_query)->fetchAll(PDO::FETCH_ASSOC);

    // تقارير الرواتب
    $salary_query = "SELECT p.*, e.full_name, d.name as department_name,
                            (p.basic_salary + COALESCE(p.allowances,0) - COALESCE(p.deductions,0)) as net_salary
                     FROM payroll p
                     LEFT JOIN employees e ON p.employee_id = e.employee_id
                     LEFT JOIN departments d ON e.department_id = d.department_id
                     WHERE p.payment_date BETWEEN :start_date AND :end_date";

    $salary_params = [':start_date' => $start_date, ':end_date' => $end_date];
    if (!empty($department_id)) {
        $salary_query .= " AND e.department_id = :department_id";
        $salary_params[':department_id'] = $department_id;
    }
    if (!empty($employee_id)) {
        $salary_query .= " AND p.employee_id = :employee_id";
        $salary_params[':employee_id'] = $employee_id;
    }

    $salary_query .= " ORDER BY p.payment_date DESC, e.full_name";
    $stmt = $db->prepare($salary_query);
    $stmt->execute($salary_params);
    $salary_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_hr_reports', 'عرض تقارير الموارد البشرية');

} catch (PDOException $e) {
    error_log('HR Reports Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}
?>



<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - تقارير الموارد البشرية</title>
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
        
        .dashboard-title {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--secondary-color);
            text-align: center;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .filters-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }
        
        .filters-title {
            font-size: 20px;
            margin-bottom: 15px;
            color: var(--secondary-color);
        }
        
        .filters-form {
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
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #1e2a36;
        }
        
        .reports-tabs {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: #777;
            border-bottom: 3px solid transparent;
        }
        
        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .report-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .report-title {
            font-size: 20px;
            color: var(--secondary-color);
        }
        
        .export-btn {
            background-color: var(--success-color);
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        th, td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: #e6f7ee;
            color: var(--success-color);
        }
        
        .badge-warning {
            background-color: #fef5e6;
            color: var(--warning-color);
        }
        
        .badge-danger {
            background-color: #fbe7e8;
            color: var(--danger-color);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            text-align: center;
        }
        
        .stat-icon {
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .employees-icon { color: var(--primary-color); }
        .attendance-icon { color: var(--success-color); }
        .leaves-icon { color: var(--warning-color); }
        .salary-icon { color: var(--danger-color); }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #777;
            font-size: 16px;
        }
        
        .chart-container {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            height: 400px;
        }
        
        @media (max-width: 768px) {
            .filters-form {
                grid-template-columns: 1fr;
            }
            
            .reports-tabs {
                flex-direction: column;
            }
            
            .stats-grid {
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
                    <div style="font-size: 12px; color: #777;">موارد بشرية</div>
                </div>
            </div>
        </header>
        
        <a href="hr_dashboard.php" class="back-link">
            <i class="fas fa-arrow-right"></i> العودة إلى لوحة التحكم
        </a>
        
        <h1 class="dashboard-title">تقارير الموارد البشرية</h1>
        
        <?php if (isset($error)): ?>
        <div style="background-color: #ffebee; color: #d32f2f; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="filters-card">
            <h2 class="filters-title">تصفية التقارير</h2>
            <form method="GET" class="filters-form">
                <div class="form-group">
                    <label for="start_date">من تاريخ</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                
                <div class="form-group">
                    <label for="end_date">إلى تاريخ</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                
                <div class="form-group">
                    <label for="department_id">القسم</label>
                    <select id="department_id" name="department_id" class="form-control">
                        <option value="">جميع الأقسام</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['department_id']; ?>" <?php echo ($department_id == $dept['department_id']) ? 'selected' : ''; ?>>
                            <?php echo $dept['name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="employee_id">الموظف</label>
                    <select id="employee_id" name="employee_id" class="form-control">
    <option value="">جميع الموظفين</option>
    <?php foreach ($employees as $emp): ?>
        <option value="<?php echo $emp['employee_id']; ?>" <?php echo ($employee_id == $emp['employee_id']) ? 'selected' : ''; ?>>
            <?php echo $emp['name'] ?? 'غير محدد'; ?>
        </option>
    <?php endforeach; ?>
</select>

                </div>
                
                <div class="form-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary">تطبيق التصفية</button>
                    <a href="hr_reports.php" class="btn btn-secondary" style="margin-right: 10px;">إعادة تعيين</a>
                </div>
            </form>
        </div>
        
        <div class="reports-tabs">
            <button class="tab-btn active" onclick="openTab('attendance')">الحضور والانصراف</button>
            <button class="tab-btn" onclick="openTab('leaves')">الإجازات</button>
            <button class="tab-btn" onclick="openTab('employees')">إحصائيات الموظفين</button>
            <button class="tab-btn" onclick="openTab('salaries')">تطور الرواتب</button>
        </div>
        
        <!-- تقرير الحضور والانصراف -->
        <div id="attendance" class="tab-content active">
            <div class="report-card">
                <div class="report-header">
                    <h2 class="report-title">تقرير الحضور والانصراف</h2>
                    <button class="export-btn" onclick="exportToExcel('attendance-table', 'تقرير_الحضور')">
                        <i class="fas fa-download"></i> تصدير إلى Excel
                    </button>
                </div>
                
                <table id="attendance-table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>الموظف</th>
                            <th>القسم</th>
                            <th>وقت الدخول</th>
                            <th>وقت الخروج</th>
                            <th>الحالة</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                   <tbody>
    <?php if (!empty($attendance_data)): ?>
        <?php foreach ($attendance_data as $record): ?>
        <tr>
            <td><?php echo $record['date']; ?></td>
            <td><?php echo $record['full_name'] ?? 'غير محدد'; ?></td>
            <td><?php echo $record['department_name'] ?? 'غير محدد'; ?></td>
            <td><?php echo $record['check_in'] ?? '--:--'; ?></td>
            <td><?php echo $record['check_out'] ?? '--:--'; ?></td>
            <td>
                <?php 
                $status = $record['status'] ?? 'absent';
                $badge_class = '';
                $status_text = '';
                
                if ($status == 'present') {
                    $badge_class = 'badge-success';
                    $status_text = 'حاضر';
                } elseif ($status == 'absent') {
                    $badge_class = 'badge-danger';
                    $status_text = 'غائب';
                } elseif ($status == 'late') {
                    $badge_class = 'badge-warning';
                    $status_text = 'متأخر';
                }
                ?>
                <span class="badge <?php echo $badge_class; ?>"><?php echo $status_text; ?></span>
            </td>
            <td><?php echo $record['notes'] ?? 'لا توجد ملاحظات'; ?></td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="7" style="text-align: center;">لا توجد بيانات للعرض</td>
        </tr>
    <?php endif; ?>
</tbody>

            </div>
        </div>
        
        <!-- تقرير الإجازات -->
        <div id="leaves" class="tab-content">
            <div class="report-card">
                <div class="report-header">
                    <h2 class="report-title">تقرير الإجازات</h2>
                    <button class="export-btn" onclick="exportToExcel('leaves-table', 'تقرير_الإجازات')">
                        <i class="fas fa-download"></i> تصدير إلى Excel
                    </button>
                </div>
                
              <table id="leaves-table">
    <thead>
        <tr>
            <th>الموظف</th>
            <th>القسم</th>
            <th>نوع الإجازة</th>
            <th>من تاريخ</th>
            <th>إلى تاريخ</th>
            <th>المدة (أيام)</th>
            <th>الحالة</th>
            <th>سبب الإجازة</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($leaves_data)): ?>
            <?php foreach ($leaves_data as $record): ?>
            <tr>
                <td><?php echo $record['full_name'] ?? 'غير محدد'; ?></td>
                <td><?php echo $record['department_name'] ?? 'غير محدد'; ?></td>
                <td><?php echo $record['leave_type'] ?? '--'; ?></td>
                <td><?php echo $record['start_date'] ?? '--'; ?></td>
                <td><?php echo $record['end_date'] ?? '--'; ?></td>
                <td><?php echo $record['duration'] ?? '--'; ?></td>
                <td>
                    <?php 
                    $status = $record['status'] ?? 'pending';
                    $badge_class = '';
                    
                    if ($status == 'approved') {
                        $badge_class = 'badge-success';
                    } elseif ($status == 'pending') {
                        $badge_class = 'badge-warning';
                    } elseif ($status == 'rejected') {
                        $badge_class = 'badge-danger';
                    }
                    ?>
                    <span class="badge <?php echo $badge_class; ?>"><?php echo $status; ?></span>
                </td>
                <td><?php echo $record['reason'] ?? 'لا يوجد'; ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" style="text-align: center;">لا توجد بيانات للعرض</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

            </div>
        </div>
        
        <!-- إحصائيات الموظفين -->
        <div id="employees" class="tab-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon employees-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo array_sum(array_column($employee_stats, 'total_employees')); ?></div>
                    <div class="stat-label">إجمالي الموظفين</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon employees-icon">
                        <i class="fas fa-male"></i>
                    </div>
                    <div class="stat-value"><?php echo array_sum(array_column($employee_stats, 'male_count')); ?></div>
                    <div class="stat-label">موظفين ذكور</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon employees-icon">
                        <i class="fas fa-female"></i>
                    </div>
                    <div class="stat-value"><?php echo array_sum(array_column($employee_stats, 'female_count')); ?></div>
                    <div class="stat-label">موظفين إناث</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon employees-icon">
                        <i class="fas fa-birthday-cake"></i>
                    </div>
                    <div class="stat-value"><?php echo round(array_sum(array_column($employee_stats, 'avg_age')) / count($employee_stats), 1); ?></div>
                    <div class="stat-label">متوسط العمر</div>
                </div>
            </div>
            
            <div class="report-card">
                <div class="report-header">
                    <h2 class="report-title">توزيع الموظفين حسب الأقسام</h2>
                    <button class="export-btn" onclick="exportToExcel('employees-table', 'إحصائيات_الموظفين')">
                        <i class="fas fa-download"></i> تصدير إلى Excel
                    </button>
                </div>
                
                <table id="employees-table">
    <thead>
        <tr>
            <th>القسم</th>
            <th>عدد الموظفين</th>
            <th>ذكور</th>
            <th>إناث</th>
            <th>متوسط العمر</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($employee_stats) && is_array($employee_stats)): ?>
            <?php foreach ($employee_stats as $stat): ?>
            <tr>
                <td><?php echo $stat['department_name'] ?? 'غير محدد'; ?></td>
                <td><?php echo $stat['total_employees'] ?? 0; ?></td>
                <td><?php echo $stat['male_count'] ?? 0; ?></td>
                <td><?php echo $stat['female_count'] ?? 0; ?></td>
                <td><?php echo isset($stat['avg_age']) ? round($stat['avg_age'], 1) : '--'; ?> سنة</td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" style="text-align: center;">لا توجد بيانات للعرض</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

            </div>
        </div>
        
        <!-- تقرير تطور الرواتب -->
        <div id="salaries" class="tab-content">
            <div class="report-card">
                <div class="report-header">
                    <h2 class="report-title">تقرير تطور الرواتب</h2>
                    <button class="export-btn" onclick="exportToExcel('salaries-table', 'تقرير_الرواتب')">
                        <i class="fas fa-download"></i> تصدير إلى Excel
                    </button>
                </div>
                
                <table id="salaries-table">
                    <thead>
                        <tr>
                            <th>تاريخ الصرف</th>
                            <th>الموظف</th>
                            <th>القسم</th>
                            <th>الراتب الأساسي</th>
                            <th>العلاوات</th>
                            <th>الخصومات</th>
                            <th>صافي الراتب</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($salary_data)): ?>
                            <?php foreach ($salary_data as $record): ?>
                            <tr>
                                <td><?php echo $record['pay_date']; ?></td>
                                <td><?php echo $record['first_name'] . ' ' . $record['last_name']; ?></td>
                                <td><?php echo $record['department_name'] ?? 'غير محدد'; ?></td>
                                <td><?php echo number_format($record['base_salary'], 2); ?> د.ل</td>
                                <td><?php echo number_format($record['bonuses'] ?? 0, 2); ?> د.ل</td>
                                <td><?php echo number_format($record['deductions'] ?? 0, 2); ?> د.ل</td>
                                <td><strong><?php echo number_format($record['net_salary'], 2); ?> د.ل</strong></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">لا توجد بيانات للعرض</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function openTab(tabName) {
            // إخفاء جميع محتويات التبويبات
            var tabContents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // إلغاء تنشيط جميع أزرار التبويبات
            var tabButtons = document.getElementsByClassName('tab-btn');
            for (var i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }
            
            // إظهار المحتوى المحدد وتنشيط الزر
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        function exportToExcel(tableId, fileName) {
            var table = document.getElementById(tableId);
            var html = table.outerHTML;
            var url = 'data:application/vnd.ms-excel;charset=utf-8,' + encodeURIComponent(html);
            var downloadLink = document.createElement('a');
            downloadLink.href = url;
            downloadLink.download = fileName + '.xls';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }
    </script>
</body>
</html>