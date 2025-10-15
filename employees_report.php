<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
checkLogin();

// الحصول على دور المستخدم
$user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'guest';

// التحقق من الصلاحية - فقط مدير النظام أو الموارد البشرية
if ($user_role !== 'admin' && $user_role !== 'hr') {
    header("Location: dashboard.php");
    exit();
}

// معالجة معايير التصفية والبحث
$filters = [];
$where_conditions = [];
$params = [];

// فلترة حسب القسم
if (!empty($_GET['department'])) {
    $where_conditions[] = "e.department_id = ?";
    $params[] = $_GET['department'];
    $filters['department'] = $_GET['department'];
}

// فلترة حسب المسمى الوظيفي
if (!empty($_GET['position'])) {
    $where_conditions[] = "e.position LIKE ?";
    $params[] = '%' . $_GET['position'] . '%';
    $filters['position'] = $_GET['position'];
}

// فلترة حسب الحالة
if (!empty($_GET['status'])) {
    if ($_GET['status'] === 'active') {
        $where_conditions[] = "u.is_active = 1";
    } elseif ($_GET['status'] === 'inactive') {
        $where_conditions[] = "u.is_active = 0";
    }
    $filters['status'] = $_GET['status'];
}

// بناء شرط WHERE
$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// جلب بيانات الموظفين مع اسم القسم
$employees = [];
try {
    $sql = "SELECT 
                u.user_id, u.full_name, u.username, u.email, u.phone, u.role, u.is_active,
                e.employee_id, e.national_id, e.hire_date, e.position, e.salary,
                e.emergency_contact, e.emergency_phone,
                d.name AS department_name
            FROM users u
            INNER JOIN employees e ON u.user_id = e.user_id
            LEFT JOIN departments d ON e.department_id = d.department_id
            $where_sql
            ORDER BY u.full_name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطأ في جلب بيانات الموظفين: " . $e->getMessage();
}

// جلب إحصائيات الموظفين
$stats = ['total'=>0,'active'=>0,'inactive'=>0,'by_department'=>[]];
try {
    $stats['total'] = $db->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    $stats['active'] = $db->query("SELECT COUNT(*) FROM users u INNER JOIN employees e ON u.user_id=e.user_id WHERE u.is_active=1")->fetchColumn();
    $stats['inactive'] = $stats['total'] - $stats['active'];
    $stats['by_department'] = $db->query("
        SELECT d.name AS department_name, COUNT(*) as count
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.department_id
        GROUP BY d.name
        ORDER BY count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Employees Stats Error: '.$e->getMessage());
}

// تصدير Excel
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="employees_report_'.date('Y-m-d').'.xls"');
    
    echo "<table border='1'>
            <tr>
                <th>الاسم الكامل</th>
                <th>اسم المستخدم</th>
                <th>البريد الإلكتروني</th>
                <th>الهاتف</th>
                <th>الدور</th>
                <th>الحالة</th>
                <th>الرقم الوطني</th>
                <th>تاريخ التعيين</th>
                <th>المسمى الوظيفي</th>
                <th>القسم</th>
                <th>الراتب</th>
                <th>جهة اتصال الطوارئ</th>
                <th>هاتف الطوارئ</th>
            </tr>";
    
    foreach ($employees as $e) {
        echo "<tr>
                <td>".htmlspecialchars($e['full_name'] ?? 'N/A')."</td>
                <td>".htmlspecialchars($e['username'] ?? 'N/A')."</td>
                <td>".htmlspecialchars($e['email'] ?? 'N/A')."</td>
                <td>".htmlspecialchars($e['phone'] ?? 'N/A')."</td>
                <td>".htmlspecialchars($e['role'] ?? 'N/A')."</td>
                <td>".(!empty($e['is_active']) ? 'نشط' : 'غير نشط')."</td>
                <td>".htmlspecialchars($e['national_id'] ?? 'N/A')."</td>
                <td>".htmlspecialchars($e['hire_date'] ?? 'N/A')."</td>
                <td>".htmlspecialchars($e['position'] ?? 'N/A')."</td>
                <td>".htmlspecialchars($e['department_name'] ?? 'غير محدد')."</td>
                <td>".number_format($e['salary'] ?? 0,2)." د.ل</td>
                <td>".htmlspecialchars($e['emergency_contact'] ?? 'N/A')."</td>
                <td>".htmlspecialchars($e['emergency_phone'] ?? 'N/A')."</td>
              </tr>";
    }
    echo "</table>";
    exit();
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير الموظفين - نظام المطبعة</title>
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
        
        /* نموذج التصفية */
        .filter-form {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
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
            margin-left: 10px;
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
        
        /* جدول الموظفين */
        .table-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        .employees-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .employees-table th,
        .employees-table td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }
        
        .employees-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
        }
        
        .employees-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .status-active {
            color: var(--success);
            font-weight: 600;
        }
        
        .status-inactive {
            color: var(--danger);
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--gray);
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
            
            .stats-cards {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .btn {
                margin-bottom: 10px;
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
                <h1>تقرير الموظفين</h1>
                
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
            
            <!-- بطاقات الإحصائيات -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-card-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-card-title">إجمالي الموظفين</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-value"><?php echo $stats['active']; ?></div>
                    <div class="stat-card-title">موظف نشط</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-value"><?php echo $stats['inactive']; ?></div>
                    <div class="stat-card-title">موظف غير نشط</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-value"><?php echo count($stats['by_department']); ?></div>
                    <div class="stat-card-title">قسم</div>
                </div>
            </div>
            
            <!-- نموذج التصفية -->
            <div class="filter-form">
                <form method="GET" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="department">القسم</label>
                            <select class="form-control" id="department" name="department">
                                <option value="">جميع الأقسام</option>
                                <option value="management" <?php echo (($_GET['department'] ?? '') == 'management') ? 'selected' : ''; ?>>الإدارة</option>
                                <option value="sales" <?php echo (($_GET['department'] ?? '') == 'sales') ? 'selected' : ''; ?>>المبيعات</option>
                                <option value="production" <?php echo (($_GET['department'] ?? '') == 'production') ? 'selected' : ''; ?>>الإنتاج</option>
                                <option value="design" <?php echo (($_GET['department'] ?? '') == 'design') ? 'selected' : ''; ?>>التصميم</option>
                                <option value="accounting" <?php echo (($_GET['department'] ?? '') == 'accounting') ? 'selected' : ''; ?>>المحاسبة</option>
                                <option value="hr" <?php echo (($_GET['department'] ?? '') == 'hr') ? 'selected' : ''; ?>>الموارد البشرية</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="position">المسمى الوظيفي</label>
                            <input type="text" class="form-control" id="position" name="position" 
                                   value="<?php echo $_GET['position'] ?? ''; ?>" placeholder="ابحث بالمسمى الوظيفي">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="status">الحالة</label>
                            <select class="form-control" id="status" name="status">
                                <option value="">جميع الحالات</option>
                                <option value="active" <?php echo (($_GET['status'] ?? '') == 'active') ? 'selected' : ''; ?>>نشط</option>
                                <option value="inactive" <?php echo (($_GET['status'] ?? '') == 'inactive') ? 'selected' : ''; ?>>غير نشط</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> تطبيق التصفية
                        </button>
                        <a href="employees_report.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> إعادة تعيين
                        </a>
                        <a href="employees_report.php?export=excel<?php echo !empty($filters) ? '&' . http_build_query($filters) : ''; ?>" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> تصدير إلى Excel
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- جدول الموظفين -->
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">قائمة الموظفين</div>
                    <div class="table-actions">
                        <span class="badge"><?php echo count($employees); ?> موظف</span>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="employees-table">
                        <thead>
                            <tr>
                                <th>الاسم الكامل</th>
                                <th>المسمى الوظيفي</th>
                                <th>القسم</th>
                                <th>الراتب</th>
                                <th>تاريخ التعيين</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($employees)): ?>
                                <?php foreach ($employees as $employee): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($employee['full_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($employee['username']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                    <?php
$departments = [
    1 => 'الإدارة',
    2 => 'المبيعات',
    3 => 'الإنتاج',
    4 => 'التصميم',
    5 => 'المحاسبة',
    6 => 'الموارد البشرية'
];
?>

<td>
    <?php 
    $dept_id = $employee['department_id'] ?? 0; // استخدم department_id
    echo $departments[$dept_id] ?? 'غير محدد';
    ?>
</td>

                                        <td><?php echo number_format($employee['salary'], 2); ?> د.ل</td>
                                        <td><?php echo date('Y/m/d', strtotime($employee['hire_date'])); ?></td>
                                        <td>
                                            <span class="<?php echo $employee['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $employee['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="view_employee.php?id=<?php echo $employee['employee_id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit_employee.php?id=<?php echo $employee['employee_id']; ?>" class="btn btn-success btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="no-data">
                                        <i class="fas fa-users fa-2x" style="margin-bottom: 15px;"></i>
                                        <br>
                                        لا توجد بيانات للموظفين
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