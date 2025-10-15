<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (الإدارة العليا)
if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr' && $_SESSION['role'] != 'accountant') {
    header("Location: unauthorized.php");
    exit();
}

// تهيئة المتغيرات لتجنب التحذيرات
$hr_kpi = ['total_employees'=>0,'total_present'=>0,'total_absent'=>0,'attendance_rate'=>0];
$finance_kpi = ['total_revenue'=>0,'total_expenses'=>0,'net_income'=>0];
$payroll_data = ['total_payroll'=>0];
$monthly_data = [];
$department_data = [];
$revenue_per_employee = 0;
$error = '';
$success = '';

try {
    global $db;

    // فترة التقرير (افتراضيًا آخر 6 أشهر)
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01', strtotime('-5 months'));
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

    // مؤشرات الأداء الرئيسية للموارد البشرية
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT employee_id) as total_employees,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as total_present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as total_absent,
            (SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as attendance_rate
        FROM attendance 
        WHERE date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $hr_kpi_fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($hr_kpi_fetched) $hr_kpi = $hr_kpi_fetched;

    // مؤشرات الأداء المالية
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'revenue' THEN amount ELSE 0 END), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expenses,
            COALESCE(SUM(CASE WHEN type = 'revenue' THEN amount ELSE -amount END), 0) as net_income
        FROM (
            SELECT 'revenue' as type, amount, date FROM revenues
            UNION ALL
            SELECT 'expense' as type, amount, date FROM expenses
        ) financial_data
        WHERE date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $finance_kpi_fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($finance_kpi_fetched) $finance_kpi = $finance_kpi_fetched;

    // تكاليف الموارد البشرية (الرواتب)
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(net_salary), 0) as total_payroll
        FROM payroll 
        WHERE pay_date BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $payroll_fetched = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($payroll_fetched) $payroll_data = $payroll_fetched;

    // إيرادات مقابل تكاليف الموظفين
    $revenue_per_employee = $hr_kpi['total_employees'] > 0 ? 
        $finance_kpi['total_revenue'] / $hr_kpi['total_employees'] : 0;

    // بيانات للتخطيطات - الإيرادات والمصروفات شهريًا
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            COALESCE(SUM(CASE WHEN type = 'revenue' THEN amount ELSE 0 END), 0) as revenue,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expenses
        FROM (
            SELECT 'revenue' as type, amount, date FROM revenues
            UNION ALL
            SELECT 'expense' as type, amount, date FROM expenses
        ) financial_data
        WHERE date BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$start_date, $end_date]);
    $monthly_data_fetched = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($monthly_data_fetched) $monthly_data = $monthly_data_fetched;

    // بيانات للتخطيطات - أداء الأقسام
    $stmt = $db->prepare("
        SELECT 
            d.name as department_name,
            COUNT(e.employee_id) as employee_count,
            COALESCE(SUM(p.net_salary), 0) as department_payroll,
            COALESCE((
                SELECT SUM(amount) 
                FROM revenues r 
                WHERE r.department_id = d.department_id 
                AND r.date BETWEEN ? AND ?
            ), 0) as department_revenue
        FROM departments d
        LEFT JOIN employees e ON d.department_id = e.department_id
        LEFT JOIN payroll p ON e.employee_id = p.employee_id AND p.pay_date BETWEEN ? AND ?
        GROUP BY d.department_id, d.name
    ");
    $stmt->execute([$start_date, $end_date, $start_date, $end_date]);
    $department_data_fetched = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($department_data_fetched) $department_data = $department_data_fetched;

    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_integrated_reports', 'عرض التقارير المتكاملة');

} catch (PDOException $e) {
    error_log('Integrated Reports Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}
?>



<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - التقارير المتكاملة</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #17a2b8;
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
        
        .page-title {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--secondary-color);
            text-align: center;
        }
        
        .filters {
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-weight: 500;
            font-size: 14px;
        }
        
        .filter-group input, .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn {
            padding: 8px 16px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .kpi-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .kpi-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            text-align: center;
        }
        
        .kpi-icon {
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .financial-icon { color: var(--primary-color); }
        .hr-icon { color: var(--info-color); }
        .performance-icon { color: var(--success-color); }
        .cost-icon { color: var(--warning-color); }
        
        .kpi-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .kpi-label {
            color: #777;
            font-size: 16px;
        }
        
        .kpi-trend {
            font-size: 14px;
            margin-top: 5px;
        }
        
        .trend-up { color: var(--success-color); }
        .trend-down { color: var(--danger-color); }
        
        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .chart-title {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--secondary-color);
            text-align: center;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .tables-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(600px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .table-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            overflow-x: auto;
        }
        
        .table-title {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--secondary-color);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .positive-value {
            color: var(--success-color);
            font-weight: 500;
        }
        
        .negative-value {
            color: var(--danger-color);
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .charts-container {
                grid-template-columns: 1fr;
            }
            
            .tables-container {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
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
                    <div style="font-size: 12px; color: #777;"><?php echo $_SESSION['role'] == 'admin' ? 'مدير النظام' : ($_SESSION['role'] == 'hr' ? 'موارد بشرية' : 'محاسب'); ?></div>
                </div>
            </div>
        </header>
        
        <h1 class="page-title">التقارير المتكاملة</h1>
        
        <?php if (isset($error)): ?>
        <div style="background-color: #ffebee; color: #d32f2f; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <!-- فلترة التاريخ -->
        <form method="GET" class="filters">
            <div class="filter-group">
                <label for="start_date">من تاريخ</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            
            <div class="filter-group">
                <label for="end_date">إلى تاريخ</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            
            <div class="filter-group" style="align-self: flex-end;">
                <button type="submit" class="btn">تطبيق الفلتر</button>
            </div>
            
            <div class="filter-group" style="align-self: flex-end;">
                <button type="button" class="btn" onclick="window.print()">طباعة التقرير</button>
            </div>
        </form>
        
        <!-- مؤشرات الأداء الرئيسية -->
        <div class="kpi-cards">
            <div class="kpi-card">
                <div class="kpi-icon financial-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="kpi-value"><?php echo number_format($finance_kpi['net_income'] ?? 0, 2); ?> د.ل</div>
                <div class="kpi-label">صافي الدخل</div>
                <div class="kpi-trend <?php echo ($finance_kpi['net_income'] >= 0) ? 'trend-up' : 'trend-down'; ?>">
                    <i class="fas fa-arrow-<?php echo ($finance_kpi['net_income'] >= 0) ? 'up' : 'down'; ?>"></i>
                    <?php echo ($finance_kpi['net_income'] >= 0) ? 'أداء إيجابي' : 'أداء سلبي'; ?>
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-icon hr-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="kpi-value"><?php echo $hr_kpi['total_employees'] ?? 0; ?></div>
                <div class="kpi-label">عدد الموظفين</div>
                <div class="kpi-trend trend-up">
                    <i class="fas fa-info-circle"></i>
                    <?php echo $hr_kpi['attendance_rate'] ?? 0; ?>% نسبة الحضور
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-icon performance-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="kpi-value"><?php echo number_format($revenue_per_employee, 2); ?> د.ل</div>
                <div class="kpi-label">إيرادات لكل موظف</div>
                <div class="kpi-trend <?php echo ($revenue_per_employee > 0) ? 'trend-up' : 'trend-down'; ?>">
                    <i class="fas fa-arrow-<?php echo ($revenue_per_employee > 0) ? 'up' : 'down'; ?>"></i>
                    كفاءة الأداء
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-icon cost-icon">
                    <i class="fas fa-money-check"></i>
                </div>
                <div class="kpi-value"><?php echo number_format($payroll_data['total_payroll'] ?? 0, 2); ?> د.ل</div>
                <div class="kpi-label">إجمالي الرواتب</div>
                <div class="kpi-trend">
                    <i class="fas fa-percentage"></i>
                    <?php echo $finance_kpi['total_revenue'] > 0 ? number_format(($payroll_data['total_payroll'] / $finance_kpi['total_revenue']) * 100, 2) : 0; ?>% من الإيرادات
                </div>
            </div>
        </div>
        
        <!-- الرسوم البيانية -->
        <div class="charts-container">
            <div class="chart-card">
                <h3 class="chart-title">الإيرادات والمصروفات خلال الفترة</h3>
                <div class="chart-container">
                    <canvas id="revenueExpenseChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3 class="chart-title">الإيرادات مقابل تكاليف الموظفين</h3>
                <div class="chart-container">
                    <canvas id="revenueVsPayrollChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3 class="chart-title">أداء الأقسام حسب الإيرادات</h3>
                <div class="chart-container">
                    <canvas id="departmentPerformanceChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3 class="chart-title">توزيع القوى العاملة</h3>
                <div class="chart-container">
                    <canvas id="workforceDistributionChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- الجداول -->
        <div class="tables-container">
            <div class="table-card">
                <h3 class="table-title">أداء الأقسام</h3>
                <table>
                    <thead>
                        <tr>
                            <th>القسم</th>
                            <th>عدد الموظفين</th>
                            <th>إجمالي الرواتب</th>
                            <th>الإيرادات</th>
                            <th>الكفاءة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($department_data)): ?>
                            <?php foreach ($department_data as $dept): ?>
                                <?php 
                                $efficiency = $dept['department_revenue'] > 0 ? 
                                    ($dept['department_revenue'] - $dept['department_payroll']) / $dept['department_revenue'] * 100 : 0;
                                ?>
                                <tr>
                                    <td><?php echo $dept['department_name']; ?></td>
                                    <td><?php echo $dept['employee_count']; ?></td>
                                    <td><?php echo number_format($dept['department_payroll'], 2); ?> د.ل</td>
                                    <td><?php echo number_format($dept['department_revenue'], 2); ?> د.ل</td>
                                    <td class="<?php echo $efficiency >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                        <?php echo number_format($efficiency, 2); ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">لا توجد بيانات متاحة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="table-card">
                <h3 class="table-title">التكاليف مقابل الإيرادات الشهرية</h3>
                <table>
                    <thead>
                        <tr>
                            <th>الشهر</th>
                            <th>الإيرادات</th>
                            <th>المصروفات</th>
                            <th>صافي الدخل</th>
                            <th>هامش الربح</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($monthly_data)): ?>
                            <?php foreach ($monthly_data as $month): ?>
                                <?php 
                                $net_income = $month['revenue'] - $month['expenses'];
                                $profit_margin = $month['revenue'] > 0 ? ($net_income / $month['revenue']) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></td>
                                    <td><?php echo number_format($month['revenue'], 2); ?> د.ل</td>
                                    <td><?php echo number_format($month['expenses'], 2); ?> د.ل</td>
                                    <td class="<?php echo $net_income >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                        <?php echo number_format($net_income, 2); ?> د.ل
                                    </td>
                                    <td class="<?php echo $profit_margin >= 0 ? 'positive-value' : 'negative-value'; ?>">
                                        <?php echo number_format($profit_margin, 2); ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">لا توجد بيانات متاحة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // بيانات الرسوم البيانية
        const monthlyData = <?php echo json_encode($monthly_data); ?>;
        const departmentData = <?php echo json_encode($department_data); ?>;
        const payroll = <?php echo $payroll_data['total_payroll'] ?? 0; ?>;
        const revenue = <?php echo $finance_kpi['total_revenue'] ?? 0; ?>;
        
        // رسم بياني للإيرادات والمصروفات
        const revenueExpenseCtx = document.getElementById('revenueExpenseChart').getContext('2d');
        new Chart(revenueExpenseCtx, {
            type: 'bar',
            data: {
                labels: monthlyData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('ar-SA', { month: 'long', year: 'numeric' });
                }),
                datasets: [
                    {
                        label: 'الإيرادات',
                        data: monthlyData.map(item => item.revenue),
                        backgroundColor: 'rgba(52, 152, 219, 0.7)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'المصروفات',
                        data: monthlyData.map(item => item.expenses),
                        backgroundColor: 'rgba(231, 76, 60, 0.7)',
                        borderColor: 'rgba(231, 76, 60, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('ar-SA') + ' د.ل';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        rtl: true
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw.toLocaleString('ar-SA') + ' د.ل';
                            }
                        }
                    }
                }
            }
        });
        
        // رسم بياني للإيرادات مقابل الرواتب
        const revenueVsPayrollCtx = document.getElementById('revenueVsPayrollChart').getContext('2d');
        new Chart(revenueVsPayrollCtx, {
            type: 'doughnut',
            data: {
                labels: ['الإيرادات', 'تكاليف الرواتب'],
                datasets: [{
                    data: [revenue, payroll],
                    backgroundColor: [
                        'rgba(46, 204, 113, 0.7)',
                        'rgba(243, 156, 18, 0.7)'
                    ],
                    borderColor: [
                        'rgba(46, 204, 113, 1)',
                        'rgba(243, 156, 18, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        rtl: true
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw.toLocaleString('ar-SA') + ' د.ل';
                            }
                        }
                    }
                }
            }
        });
        
        // رسم بياني لأداء الأقسام
        const departmentPerformanceCtx = document.getElementById('departmentPerformanceChart').getContext('2d');
        new Chart(departmentPerformanceCtx, {
            type: 'bar',
            data: {
                labels: departmentData.map(item => item.department_name),
                datasets: [
                    {
                        label: 'الإيرادات',
                        data: departmentData.map(item => item.department_revenue),
                        backgroundColor: 'rgba(52, 152, 219, 0.7)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'تكاليف الموظفين',
                        data: departmentData.map(item => item.department_payroll),
                        backgroundColor: 'rgba(243, 156, 18, 0.7)',
                        borderColor: 'rgba(243, 156, 18, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('ar-SA') + ' د.ل';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        rtl: true
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw.toLocaleString('ar-SA') + ' د.ل';
                            }
                        }
                    }
                }
            }
        });
        
        // رسم بياني لتوزيع القوى العاملة
        const workforceDistributionCtx = document.getElementById('workforceDistributionChart').getContext('2d');
        new Chart(workforceDistributionCtx, {
            type: 'pie',
            data: {
                labels: departmentData.map(item => item.department_name),
                datasets: [{
                    data: departmentData.map(item => item.employee_count),
                    backgroundColor: [
                        'rgba(52, 152, 219, 0.7)',
                        'rgba(46, 204, 113, 0.7)',
                        'rgba(155, 89, 182, 0.7)',
                        'rgba(243, 156, 18, 0.7)',
                        'rgba(231, 76, 60, 0.7)',
                        'rgba(26, 188, 156, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        rtl: true
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw + ' موظف';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>