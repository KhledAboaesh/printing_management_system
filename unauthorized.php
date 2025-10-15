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

// جلب الإحصائيات المالية
try {
    global $db;
    
    // إجمالي الإيرادات لهذا الشهر
    $first_day_month = date('Y-m-01');
    $last_day_month = date('Y-m-t');
    $stmt = $db->prepare("SELECT SUM(amount) as total_revenue FROM revenues WHERE revenue_date BETWEEN ? AND ?");
    $stmt->execute([$first_day_month, $last_day_month]);
    $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
    
    // إجمالي المصروفات لهذا الشهر
    $stmt = $db->prepare("SELECT SUM(amount) as total_expenses FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $stmt->execute([$first_day_month, $last_day_month]);
    $total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0;
    
    // صافي الربح لهذا الشهر
    $net_profit = $total_revenue - $total_expenses;
    
    // إجمالي الفواتير غير المدفوعة
    $stmt = $db->query("SELECT COUNT(*) as unpaid_invoices FROM invoices WHERE status = 'unpaid'");
    $unpaid_invoices = $stmt->fetch(PDO::FETCH_ASSOC)['unpaid_invoices'] ?? 0;
    
    // إجمالي المديونيات (الفواتير غير المدفوعة)
    $stmt = $db->query("SELECT SUM(total_amount) as total_receivables FROM invoices WHERE status = 'unpaid'");
    $total_receivables = $stmt->fetch(PDO::FETCH_ASSOC)['total_receivables'] ?? 0;
    
    // التدفق النقدي لآخر 6 أشهر (لرسم البياني)
    $six_months_ago = date('Y-m-01', strtotime('-5 months'));
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(revenue_date, '%Y-%m') as month,
            SUM(amount) as revenue
        FROM revenues 
        WHERE revenue_date >= ? 
        GROUP BY DATE_FORMAT(revenue_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$six_months_ago]);
    $revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(expense_date, '%Y-%m') as month,
            SUM(amount) as expenses
        FROM expenses 
        WHERE expense_date >= ? 
        GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute([$six_months_ago]);
    $expense_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // دمج بيانات الإيرادات والمصروفات للرسم البياني
    $cash_flow_data = [];
    foreach ($revenue_data as $rev) {
        $cash_flow_data[$rev['month']] = [
            'revenue' => $rev['revenue'],
            'expenses' => 0,
            'profit' => $rev['revenue']
        ];
    }
    
    foreach ($expense_data as $exp) {
        if (isset($cash_flow_data[$exp['month']])) {
            $cash_flow_data[$exp['month']]['expenses'] = $exp['expenses'];
            $cash_flow_data[$exp['month']]['profit'] = $cash_flow_data[$exp['month']]['revenue'] - $exp['expenses'];
        } else {
            $cash_flow_data[$exp['month']] = [
                'revenue' => 0,
                'expenses' => $exp['expenses'],
                'profit' => -$exp['expenses']
            ];
        }
    }
    
    // جلب آخر المعاملات المالية
    $stmt = $db->query("
        (SELECT 'revenue' as type, revenue_id as id, amount, revenue_date as date, description 
         FROM revenues 
         ORDER BY revenue_date DESC 
         LIMIT 5)
        UNION ALL
        (SELECT 'expense' as type, expense_id as id, amount, expense_date as date, description 
         FROM expenses 
         ORDER BY expense_date DESC 
         LIMIT 5)
        ORDER BY date DESC 
        LIMIT 10
    ");
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_accounting_dashboard', 'عرض لوحة تحكم المحاسبة');
    
} catch (PDOException $e) {
    error_log('Accounting Dashboard Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - المحاسبة</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .revenue-icon { color: var(--success-color); }
        .expenses-icon { color: var(--danger-color); }
        .profit-icon { color: var(--primary-color); }
        .receivables-icon { color: var(--warning-color); }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #777;
            font-size: 16px;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-section, .transactions-section, .quick-links-section {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .section-title {
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
            color: var(--secondary-color);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .transaction-list {
            list-style: none;
        }
        
        .transaction-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .transaction-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .revenue-bg {
            background-color: rgba(46, 204, 113, 0.2);
            color: var(--success-color);
        }
        
        .expense-bg {
            background-color: rgba(231, 76, 60, 0.2);
            color: var(--danger-color);
        }
        
        .transaction-content {
            flex-grow: 1;
        }
        
        .transaction-amount {
            font-weight: 700;
        }
        
        .revenue-amount {
            color: var(--success-color);
        }
        
        .expense-amount {
            color: var(--danger-color);
        }
        
        .transaction-date {
            font-size: 12px;
            color: #777;
        }
        
        .links-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .quick-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: var(--light-color);
            padding: 15px;
            border-radius: var(--border-radius);
            text-align: center;
            text-decoration: none;
            color: var(--dark-color);
            transition: all 0.3s ease;
        }
        
        .quick-link:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-3px);
        }
        
        .link-icon {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .link-text {
            font-weight: 500;
        }
        
        .financial-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .summary-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .summary-item:last-child {
            border-bottom: none;
        }
        
        .summary-value {
            font-weight: 700;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .links-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .financial-summary {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .links-grid {
                grid-template-columns: 1fr;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
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
        
        <h1 class="dashboard-title">لوحة تحكم المحاسبة</h1>
        
        <?php if (isset($error)): ?>
        <div style="background-color: #ffebee; color: #d32f2f; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon revenue-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value"><?php echo number_format($total_revenue, 2); ?> <span style="font-size: 16px;">د.ل</span></div>
                <div class="stat-label">الإيرادات لهذا الشهر</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon expenses-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-value"><?php echo number_format($total_expenses, 2); ?> <span style="font-size: 16px;">د.ل</span></div>
                <div class="stat-label">المصروفات لهذا الشهر</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon profit-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo number_format($net_profit, 2); ?> <span style="font-size: 16px;">د.ل</span></div>
                <div class="stat-label">صافي الربح لهذا الشهر</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon receivables-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-value"><?php echo number_format($total_receivables, 2); ?> <span style="font-size: 16px;">د.ل</span></div>
                <div class="stat-label">إجمالي المدينون</div>
            </div>
        </div>
        
        <div class="content-grid">
            <div class="chart-section">
                <h2 class="section-title">التدفق النقدي لآخر 6 أشهر</h2>
                <div class="chart-container">
                    <canvas id="cashFlowChart"></canvas>
                </div>
            </div>
            
            <div class="transactions-section">
                <h2 class="section-title">آخر المعاملات</h2>
                <ul class="transaction-list">
                    <?php if (!empty($recent_transactions)): ?>
                        <?php foreach ($recent_transactions as $transaction): ?>
                        <li class="transaction-item">
                            <div class="transaction-icon <?php echo $transaction['type'] == 'revenue' ? 'revenue-bg' : 'expense-bg'; ?>">
                                <i class="fas <?php echo $transaction['type'] == 'revenue' ? 'fa-arrow-down' : 'fa-arrow-up'; ?>"></i>
                            </div>
                            <div class="transaction-content">
                                <div><?php echo $transaction['description']; ?></div>
                                <div class="transaction-amount <?php echo $transaction['type'] == 'revenue' ? 'revenue-amount' : 'expense-amount'; ?>">
                                    <?php echo number_format($transaction['amount'], 2); ?> د.ل
                                </div>
                                <div class="transaction-date">
                                    <?php echo date('Y-m-d', strtotime($transaction['date'])); ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="transaction-item">
                            <div class="transaction-content">لا توجد معاملات حديثة</div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <div class="financial-summary">
            <div class="summary-card">
                <h2 class="section-title">ملخص مالي</h2>
                <div class="summary-item">
                    <span>الفواتير غير المدفوعة:</span>
                    <span class="summary-value"><?php echo $unpaid_invoices; ?></span>
                </div>
                <div class="summary-item">
                    <span>إجمالي المدينون:</span>
                    <span class="summary-value"><?php echo number_format($total_receivables, 2); ?> د.ل</span>
                </div>
                <div class="summary-item">
                    <span>صافي الربح لهذا الشهر:</span>
                    <span class="summary-value" style="color: <?php echo $net_profit >= 0 ? 'var(--success-color)' : 'var(--danger-color)'; ?>">
                        <?php echo number_format($net_profit, 2); ?> د.ل
                    </span>
                </div>
            </div>
            
            <div class="summary-card">
                <h2 class="section-title">مؤشرات الأداء</h2>
                <div class="summary-item">
                    <span>هامش الربح:</span>
                    <span class="summary-value">
                        <?php echo $total_revenue > 0 ? number_format(($net_profit / $total_revenue) * 100, 2) : 0; ?>%
                    </span>
                </div>
                <div class="summary-item">
                    <span>نسبة المصروفات للإيرادات:</span>
                    <span class="summary-value">
                        <?php echo $total_revenue > 0 ? number_format(($total_expenses / $total_revenue) * 100, 2) : 0; ?>%
                    </span>
                </div>
                <div class="summary-item">
                    <span>متوسط الإيرادات الشهرية:</span>
                    <span class="summary-value">
                        <?php
                        $avg_revenue = 0;
                        if (!empty($revenue_data)) {
                            $sum = 0;
                            foreach ($revenue_data as $data) {
                                $sum += $data['revenue'];
                            }
                            $avg_revenue = $sum / count($revenue_data);
                        }
                        echo number_format($avg_revenue, 2); ?> د.ل
                    </span>
                </div>
            </div>
        </div>
        
        <div class="quick-links-section">
            <h2 class="section-title">روابط سريعة</h2>
            <div class="links-grid">
                <a href="invoices.php" class="quick-link">
                    <div class="link-icon"><i class="fas fa-file-invoice"></i></div>
                    <div class="link-text">إدارة الفواتير</div>
                </a>
                
                <a href="revenues.php" class="quick-link">
                    <div class="link-icon"><i class="fas fa-money-bill"></i></div>
                    <div class="link-text">الإيرادات</div>
                </a>
                
                <a href="expenses.php" class="quick-link">
                    <div class="link-icon"><i class="fas fa-receipt"></i></div>
                    <div class="link-text">المصروفات</div>
                </a>
                
                <a href="journal_entries.php" class="quick-link">
                    <div class="link-icon"><i class="fas fa-book"></i></div>
                    <div class="link-text">قيود اليومية</div>
                </a>
                
                <a href="reports_accounting.php" class="quick-link">
                    <div class="link-icon"><i class="fas fa-chart-pie"></i></div>
                    <div class="link-text">التقارير المالية</div>
                </a>
                
                <a href="settings_accounting.php" class="quick-link">
                    <div class="link-icon"><i class="fas fa-cog"></i></div>
                    <div class="link-text">إعدادات المحاسبة</div>
                </a>
            </div>
        </div>
    </div>

    <script>
        // رسم بياني للتدفق النقدي
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('cashFlowChart').getContext('2d');
            
            // تحضير البيانات للرسم البياني
            const months = [];
            const revenues = [];
            const expenses = [];
            const profits = [];
            
            <?php 
            if (!empty($cash_flow_data)) {
                foreach ($cash_flow_data as $month => $data) {
                    $month_name = date('M Y', strtotime($month . '-01'));
                    echo "months.push('$month_name');";
                    echo "revenues.push(" . ($data['revenue'] ?? 0) . ");";
                    echo "expenses.push(" . ($data['expenses'] ?? 0) . ");";
                    echo "profits.push(" . ($data['profit'] ?? 0) . ");";
                }
            }
            ?>
            
            const cashFlowChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'الإيرادات',
                            data: revenues,
                            borderColor: '#2ecc71',
                            backgroundColor: 'rgba(46, 204, 113, 0.1)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'المصروفات',
                            data: expenses,
                            borderColor: '#e74c3c',
                            backgroundColor: 'rgba(231, 76, 60, 0.1)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'صافي الربح',
                            data: profits,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            tension: 0.3,
                            fill: true
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
                                    return value.toLocaleString() + ' د.ل';
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
                            rtl: true,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw.toLocaleString() + ' د.ل';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>