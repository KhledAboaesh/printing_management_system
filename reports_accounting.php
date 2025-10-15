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

// معالجة معلمات التقرير
$report_type = $_GET['report_type'] ?? 'financial_summary';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$customer_id = $_GET['customer_id'] ?? '';

// تهيئة المتغيرات لتجنب Undefined variable
$total_revenue = 0;
$total_expenses = 0;
$net_profit = 0;
$total_invoices = 0;
$paid_invoices = 0;
$unpaid_invoices = 0;
$total_receivables = 0;
$revenue_data = [];
$expense_data = [];
$invoice_data = [];
$invoice_stats = [];
$cash_flow_data = [];

try {
    global $db;
    
    // تقرير الملخص المالي
    if ($report_type == 'financial_summary') {
        $stmt = $db->prepare("SELECT SUM(amount) as total_revenue FROM revenues WHERE revenue_date BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

        $stmt = $db->prepare("SELECT SUM(amount) as total_expenses FROM expenses WHERE expense_date BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        $total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0;

        $net_profit = $total_revenue - $total_expenses;

        $stmt = $db->prepare("SELECT COUNT(*) as total_invoices FROM invoices WHERE invoice_date BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        $total_invoices = $stmt->fetch(PDO::FETCH_ASSOC)['total_invoices'] ?? 0;

        $stmt = $db->prepare("SELECT COUNT(*) as paid_invoices FROM invoices WHERE invoice_date BETWEEN ? AND ? AND status = 'paid'");
        $stmt->execute([$start_date, $end_date]);
        $paid_invoices = $stmt->fetch(PDO::FETCH_ASSOC)['paid_invoices'] ?? 0;

        $stmt = $db->prepare("SELECT COUNT(*) as unpaid_invoices FROM invoices WHERE invoice_date BETWEEN ? AND ? AND status = 'unpaid'");
        $stmt->execute([$start_date, $end_date]);
        $unpaid_invoices = $stmt->fetch(PDO::FETCH_ASSOC)['unpaid_invoices'] ?? 0;

        $stmt = $db->prepare("SELECT SUM(total_amount) as total_receivables FROM invoices WHERE invoice_date BETWEEN ? AND ? AND status = 'unpaid'");
        $stmt->execute([$start_date, $end_date]);
        $total_receivables = $stmt->fetch(PDO::FETCH_ASSOC)['total_receivables'] ?? 0;
    }
    
    // تقرير الإيرادات
  // تقرير الإيرادات
if ($report_type == 'revenue_report') {
    $stmt = $db->prepare("
        SELECT r.* 
        FROM revenues r
        WHERE r.revenue_date BETWEEN ? AND ? 
        ORDER BY r.revenue_date DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT SUM(amount) as total_revenue FROM revenues WHERE revenue_date BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
}

    
    // تقرير المصروفات
    if ($report_type == 'expense_report') {
        $stmt = $db->prepare("SELECT e.*, ec.name as category_name FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.category_id WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC");
        $stmt->execute([$start_date, $end_date]);
        $expense_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT SUM(amount) as total_expenses FROM expenses WHERE expense_date BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        $total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0;
    }

    // تقرير الفواتير
    if ($report_type == 'invoice_report') {
        $sql = "SELECT i.*, c.name as customer_name FROM invoices i LEFT JOIN customers c ON i.customer_id = c.customer_id WHERE i.invoice_date BETWEEN ? AND ?";
        $params = [$start_date, $end_date];
        if (!empty($customer_id)) {
            $sql .= " AND i.customer_id = ?";
            $params[] = $customer_id;
        }
        $sql .= " ORDER BY i.invoice_date DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $invoice_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT COUNT(*) as total_invoices, SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) as paid_invoices, SUM(CASE WHEN status='unpaid' THEN 1 ELSE 0 END) as unpaid_invoices, SUM(total_amount) as total_amount, SUM(CASE WHEN status='paid' THEN total_amount ELSE 0 END) as paid_amount, SUM(CASE WHEN status='unpaid' THEN total_amount ELSE 0 END) as unpaid_amount FROM invoices WHERE invoice_date BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        $invoice_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // تقرير التدفق النقدي
    if ($report_type == 'cash_flow') {
        $stmt = $db->prepare("SELECT DATE_FORMAT(revenue_date,'%Y-%m') as month, SUM(amount) as revenue FROM revenues WHERE revenue_date BETWEEN ? AND ? GROUP BY DATE_FORMAT(revenue_date,'%Y-%m') ORDER BY month");
        $stmt->execute([$start_date, $end_date]);
        $revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("SELECT DATE_FORMAT(expense_date,'%Y-%m') as month, SUM(amount) as expenses FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY DATE_FORMAT(expense_date,'%Y-%m') ORDER BY month");
        $stmt->execute([$start_date, $end_date]);
        $expense_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cash_flow_data = [];
        foreach ($revenue_data as $rev) {
            $cash_flow_data[$rev['month']] = ['revenue'=>$rev['revenue'],'expenses'=>0,'profit'=>$rev['revenue']];
        }
        foreach ($expense_data as $exp) {
            if(isset($cash_flow_data[$exp['month']])){
                $cash_flow_data[$exp['month']]['expenses'] = $exp['expenses'];
                $cash_flow_data[$exp['month']]['profit'] = $cash_flow_data[$exp['month']]['revenue'] - $exp['expenses'];
            } else {
                $cash_flow_data[$exp['month']] = ['revenue'=>0,'expenses'=>$exp['expenses'],'profit'=>-$exp['expenses']];
            }
        }
    }

    // جلب قائمة العملاء لتقرير الفواتير
    $stmt = $db->query("SELECT customer_id, name FROM customers ORDER BY name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_accounting_reports', 'عرض التقارير المالية');

} catch (PDOException $e) {
    error_log('Accounting Reports Error: '.$e->getMessage());
    $error = "حدث خطأ في جلب بيانات التقرير";
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - التقارير المالية</title>
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
        
        .report-controls {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        select, input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .btn-print {
            background-color: var(--secondary-color);
        }
        
        .btn-print:hover {
            background-color: #1a252f;
        }
        
        .btn-export {
            background-color: var(--success-color);
        }
        
        .btn-export:hover {
            background-color: #27ae60;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .report-content {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .report-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light-color);
        }
        
        .report-title {
            font-size: 24px;
            color: var(--secondary-color);
            margin-bottom: 10px;
        }
        
        .report-period {
            color: #777;
            font-size: 16px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: var(--light-color);
            border-radius: var(--border-radius);
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #777;
            font-size: 14px;
        }
        
        .revenue-stat { border-left: 4px solid var(--success-color); }
        .expense-stat { border-left: 4px solid var(--danger-color); }
        .profit-stat { border-left: 4px solid var(--primary-color); }
        .invoice-stat { border-left: 4px solid var(--warning-color); }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        
        .data-table th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        .data-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
            margin-top: 20px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #777;
            font-size: 18px;
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
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
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
        
        <h1 class="dashboard-title">التقارير المالية</h1>
        
        <?php if (isset($error)): ?>
        <div style="background-color: #ffebee; color: #d32f2f; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="report-controls">
            <form method="GET" action="reports_accounting.php">
                <div class="form-row">
                    <div class="form-group">
                        <label for="report_type">نوع التقرير</label>
                        <select id="report_type" name="report_type" onchange="this.form.submit()">
                            <option value="financial_summary" <?php echo $report_type == 'financial_summary' ? 'selected' : ''; ?>>ملخص مالي</option>
                            <option value="revenue_report" <?php echo $report_type == 'revenue_report' ? 'selected' : ''; ?>>تقرير الإيرادات</option>
                            <option value="expense_report" <?php echo $report_type == 'expense_report' ? 'selected' : ''; ?>>تقرير المصروفات</option>
                            <option value="invoice_report" <?php echo $report_type == 'invoice_report' ? 'selected' : ''; ?>>تقرير الفواتير</option>
                            <option value="cash_flow" <?php echo $report_type == 'cash_flow' ? 'selected' : ''; ?>>تقرير التدفق النقدي</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">من تاريخ</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">إلى تاريخ</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    
                    <?php if ($report_type == 'invoice_report'): ?>
                    <div class="form-group">
                        <label for="customer_id">العميل (اختياري)</label>
                        <select id="customer_id" name="customer_id">
                            <option value="">جميع العملاء</option>
                            <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['customer_id']; ?>" <?php echo $customer_id == $customer['customer_id'] ? 'selected' : ''; ?>>
                                <?php echo $customer['name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn">عرض التقرير</button>
                    <button type="button" class="btn btn-print" onclick="window.print()">طباعة التقرير</button>
                    <button type="button" class="btn btn-export" onclick="exportToExcel()">تصدير إلى Excel</button>
                </div>
            </form>
        </div>
        
        <div class="report-content">
            <div class="report-header">
                <h2 class="report-title">
                    <?php
                    $report_titles = [
                        'financial_summary' => 'التقرير المالي الشامل',
                        'revenue_report' => 'تقرير الإيرادات',
                        'expense_report' => 'تقرير المصروفات',
                        'invoice_report' => 'تقرير الفواتير',
                        'cash_flow' => 'تقرير التدفق النقدي'
                    ];
                    echo $report_titles[$report_type] ?? 'تقرير';
                    ?>
                </h2>
                <div class="report-period">
                    للفترة من <?php echo date('Y-m-d', strtotime($start_date)); ?> إلى <?php echo date('Y-m-d', strtotime($end_date)); ?>
                </div>
            </div>
            
            <?php if ($report_type == 'financial_summary'): ?>
                <div class="stats-grid">
                    <div class="stat-card revenue-stat">
                        <div class="stat-value"><?php echo number_format($total_revenue, 2); ?> د.ل</div>
                        <div class="stat-label">إجمالي الإيرادات</div>
                    </div>
                    
                    <div class="stat-card expense-stat">
                        <div class="stat-value"><?php echo number_format($total_expenses, 2); ?> د.ل</div>
                        <div class="stat-label">إجمالي المصروفات</div>
                    </div>
                    
                    <div class="stat-card profit-stat">
                        <div class="stat-value"><?php echo number_format($net_profit, 2); ?> د.ل</div>
                        <div class="stat-label">صافي الربح</div>
                    </div>
                    
                    <div class="stat-card invoice-stat">
                        <div class="stat-value"><?php echo $total_invoices; ?></div>
                        <div class="stat-label">إجمالي الفواتير</div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div>
                        <h3 style="margin-bottom: 15px; color: var(--secondary-color);">ملخص الفواتير</h3>
                        <div class="summary-item">
                            <span>إجمالي الفواتير:</span>
                            <span class="summary-value"><?php echo $total_invoices; ?></span>
                        </div>
                        <div class="summary-item">
                            <span>الفواتير المدفوعة:</span>
                            <span class="summary-value" style="color: var(--success-color);"><?php echo $paid_invoices; ?></span>
                        </div>
                        <div class="summary-item">
                            <span>الفواتير غير المدفوعة:</span>
                            <span class="summary-value" style="color: var(--danger-color);"><?php echo $unpaid_invoices; ?></span>
                        </div>
                        <div class="summary-item">
                            <span>إجمالي المديونيات:</span>
                            <span class="summary-value"><?php echo number_format($total_receivables, 2); ?> د.ل</span>
                        </div>
                    </div>
                    
                    <div>
                        <h3 style="margin-bottom: 15px; color: var(--secondary-color);">مؤشرات الأداء</h3>
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
                            <span>نسبة الفواتير المدفوعة:</span>
                            <span class="summary-value">
                                <?php echo $total_invoices > 0 ? number_format(($paid_invoices / $total_invoices) * 100, 2) : 0; ?>%
                            </span>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($report_type == 'revenue_report'): ?>
                <div class="stat-card revenue-stat" style="max-width: 300px; margin-bottom: 20px;">
                    <div class="stat-value"><?php echo number_format($total_revenue, 2); ?> د.ل</div>
                    <div class="stat-label">إجمالي الإيرادات</div>
                </div>
                
                <?php if (!empty($revenue_data)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>العميل</th>
                                <th>الوصف</th>
                                <th>المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($revenue_data as $revenue): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($revenue['revenue_date'])); ?></td>
                                <td><?php echo $revenue['customer_name'] ?? 'غير محدد'; ?></td>
                                <td><?php echo $revenue['description']; ?></td>
                                <td style="color: var(--success-color); font-weight: 600;"><?php echo number_format($revenue['amount'], 2); ?> د.ل</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">لا توجد بيانات للإيرادات في الفترة المحددة</div>
                <?php endif; ?>
                
            <?php elseif ($report_type == 'expense_report'): ?>
                <div class="stat-card expense-stat" style="max-width: 300px; margin-bottom: 20px;">
                    <div class="stat-value"><?php echo number_format($total_expenses, 2); ?> د.ل</div>
                    <div class="stat-label">إجمالي المصروفات</div>
                </div>
                
                <?php if (!empty($expense_data)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الفئة</th>
                                <th>الوصف</th>
                                <th>المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expense_data as $expense): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($expense['expense_date'])); ?></td>
                                <td><?php echo $expense['category_name'] ?? 'غير محدد'; ?></td>
                                <td><?php echo $expense['description']; ?></td>
                                <td style="color: var(--danger-color); font-weight: 600;"><?php echo number_format($expense['amount'], 2); ?> د.ل</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">لا توجد بيانات للمصروفات في الفترة المحددة</div>
                <?php endif; ?>
                
            <?php elseif ($report_type == 'invoice_report'): ?>
                <div class="stats-grid">
                    <div class="stat-card invoice-stat">
                        <div class="stat-value"><?php echo $invoice_stats['total_invoices'] ?? 0; ?></div>
                        <div class="stat-label">إجمالي الفواتير</div>
                    </div>
                    
                    <div class="stat-card" style="border-left: 4px solid var(--success-color);">
                        <div class="stat-value"><?php echo $invoice_stats['paid_invoices'] ?? 0; ?></div>
                        <div class="stat-label">الفواتير المدفوعة</div>
                    </div>
                    
                    <div class="stat-card" style="border-left: 4px solid var(--danger-color);">
                        <div class="stat-value"><?php echo $invoice_stats['unpaid_invoices'] ?? 0; ?></div>
                        <div class="stat-label">الفواتير غير المدفوعة</div>
                    </div>
                    
                    <div class="stat-card" style="border-left: 4px solid var(--warning-color);">
                        <div class="stat-value"><?php echo number_format($invoice_stats['unpaid_amount'] ?? 0, 2); ?> د.ل</div>
                        <div class="stat-label">إجمالي المديونيات</div>
                    </div>
                </div>
                
                <?php if (!empty($invoice_data)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>العميل</th>
                                <th>المبلغ الإجمالي</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoice_data as $invoice): ?>
                            <tr>
                                <td>#<?php echo $invoice['invoice_id']; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($invoice['invoice_date'])); ?></td>
                                <td><?php echo $invoice['customer_name'] ?? 'غير محدد'; ?></td>
                                <td style="font-weight: 600;"><?php echo number_format($invoice['total_amount'], 2); ?> د.ل</td>
                                <td>
                                    <span style="padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; 
                                        background-color: <?php echo $invoice['status'] == 'paid' ? 'var(--success-color)' : 'var(--danger-color)'; ?>; 
                                        color: white;">
                                        <?php echo $invoice['status'] == 'paid' ? 'مدفوع' : 'غير مدفوع'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">لا توجد بيانات للفواتير في الفترة المحددة</div>
                <?php endif; ?>
                
            <?php elseif ($report_type == 'cash_flow'): ?>
                <div class="chart-container">
                    <canvas id="cashFlowChart"></canvas>
                </div>
                
                <?php if (!empty($cash_flow_data)): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>الشهر</th>
                                <th>الإيرادات</th>
                                <th>المصروفات</th>
                                <th>صافي الربح/الخسارة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cash_flow_data as $month => $data): ?>
                            <tr>
                                <td><?php echo date('M Y', strtotime($month . '-01')); ?></td>
                                <td style="color: var(--success-color); font-weight: 600;"><?php echo number_format($data['revenue'], 2); ?> د.ل</td>
                                <td style="color: var(--danger-color); font-weight: 600;"><?php echo number_format($data['expenses'], 2); ?> د.ل</td>
                                <td style="color: <?php echo $data['profit'] >= 0 ? 'var(--success-color)' : 'var(--danger-color)'; ?>; font-weight: 600;">
                                    <?php echo number_format($data['profit'], 2); ?> د.ل
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">لا توجد بيانات للتدفق النقدي في الفترة المحددة</div>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>
    </div>

    <script>
        // رسم بياني للتدفق النقدي
        <?php if ($report_type == 'cash_flow' && !empty($cash_flow_data)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('cashFlowChart').getContext('2d');
            
            // تحضير البيانات للرسم البياني
            const months = [];
            const revenues = [];
            const expenses = [];
            const profits = [];
            
            <?php 
            foreach ($cash_flow_data as $month => $data) {
                $month_name = date('M Y', strtotime($month . '-01'));
                echo "months.push('$month_name');";
                echo "revenues.push(" . ($data['revenue'] ?? 0) . ");";
                echo "expenses.push(" . ($data['expenses'] ?? 0) . ");";
                echo "profits.push(" . ($data['profit'] ?? 0) . ");";
            }
            ?>
            
            const cashFlowChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'الإيرادات',
                            data: revenues,
                            backgroundColor: 'rgba(46, 204, 113, 0.7)',
                            borderColor: '#2ecc71',
                            borderWidth: 1
                        },
                        {
                            label: 'المصروفات',
                            data: expenses,
                            backgroundColor: 'rgba(231, 76, 60, 0.7)',
                            borderColor: '#e74c3c',
                            borderWidth: 1
                        },
                        {
                            label: 'صافي الربح/الخسارة',
                            data: profits,
                            type: 'line',
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            borderWidth: 2,
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
        <?php endif; ?>
        
        // وظيفة التصدير إلى Excel (مبسطة)
        function exportToExcel() {
            // في التطبيق الحقيقي، يمكن إرسال طلب إلى الخادم لإنشاء ملف Excel
            alert('سيتم تنزيل ملف Excel يحتوي على بيانات التقرير. (هذه وظيفة تجريبية)');
        }
    </script>
</body> 
</html>
