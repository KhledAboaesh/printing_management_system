<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (المحاسبة أو الإدارة)
if ($_SESSION['role'] != 'accounting' && $_SESSION['role'] != 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// تهيئة المتغيرات لتجنب التحذيرات
$report_type = $_GET['report_type'] ?? 'income_statement';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$total_revenue = 0;
$total_expenses = 0;
$net_income = 0;
$total_assets = 0;
$total_liabilities = 0;
$total_equity = 0;
$balance_check = 0;
$operating_cash_flow = 0;
$investing_cash_flow = 0;
$financing_cash_flow = 0;
$net_cash_flow = 0;
$taxable_sales = 0;
$sales_tax_due = 0;
$deductible_expenses = 0;
$taxable_income = 0;
$income_tax_due = 0;
$error = '';

// جلب البيانات المالية
try {
    global $db;

    // قائمة الدخل
    if ($report_type == 'income_statement') {
        // الإيرادات
        $stmt = $db->prepare("SELECT SUM(amount) as total_revenue FROM revenues WHERE date BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

        // المصروفات
        $stmt = $db->prepare("SELECT SUM(amount) as total_expenses FROM expenses WHERE date BETWEEN ? AND ?");
        $stmt->execute([$start_date, $end_date]);
        $total_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0;

        // صافي الدخل
        $net_income = $total_revenue - $total_expenses;
    }

    // الميزانية العمومية
    if ($report_type == 'balance_sheet') {
        // الأصول
        $stmt = $db->prepare("SELECT SUM(balance) as total_assets FROM accounts WHERE type = 'asset' AND is_active = 1");
        $stmt->execute();
        $total_assets = $stmt->fetch(PDO::FETCH_ASSOC)['total_assets'] ?? 0;

        // الخصوم
        $stmt = $db->prepare("SELECT SUM(balance) as total_liabilities FROM accounts WHERE type = 'liability' AND is_active = 1");
        $stmt->execute();
        $total_liabilities = $stmt->fetch(PDO::FETCH_ASSOC)['total_liabilities'] ?? 0;

        // حقوق الملكية
        $stmt = $db->prepare("SELECT SUM(balance) as total_equity FROM accounts WHERE type = 'equity' AND is_active = 1");
        $stmt->execute();
        $total_equity = $stmt->fetch(PDO::FETCH_ASSOC)['total_equity'] ?? 0;

        // التحقق من المعادلة المحاسبية
        $balance_check = $total_assets - ($total_liabilities + $total_equity);
    }

    // التدفق النقدي
    if ($report_type == 'cash_flow') {
        // الأنشطة التشغيلية
        $stmt = $db->prepare("
            SELECT 
                SUM(CASE WHEN account_type = 'revenue' THEN amount ELSE 0 END) as cash_inflow,
                SUM(CASE WHEN account_type = 'expense' THEN amount ELSE 0 END) as cash_outflow
            FROM cash_transactions 
            WHERE date BETWEEN ? AND ? AND activity_type = 'operating'
        ");
        $stmt->execute([$start_date, $end_date]);
        $operating_activity = $stmt->fetch(PDO::FETCH_ASSOC);
        $operating_cash_flow = ($operating_activity['cash_inflow'] ?? 0) - ($operating_activity['cash_outflow'] ?? 0);

        // الأنشطة الاستثمارية
        $stmt = $db->prepare("
            SELECT 
                SUM(CASE WHEN transaction_type = 'inflow' THEN amount ELSE 0 END) as cash_inflow,
                SUM(CASE WHEN transaction_type = 'outflow' THEN amount ELSE 0 END) as cash_outflow
            FROM cash_transactions 
            WHERE date BETWEEN ? AND ? AND activity_type = 'investing'
        ");
        $stmt->execute([$start_date, $end_date]);
        $investing_activity = $stmt->fetch(PDO::FETCH_ASSOC);
        $investing_cash_flow = ($investing_activity['cash_inflow'] ?? 0) - ($investing_activity['cash_outflow'] ?? 0);

        // الأنشطة التمويلية
        $stmt = $db->prepare("
            SELECT 
                SUM(CASE WHEN transaction_type = 'inflow' THEN amount ELSE 0 END) as cash_inflow,
                SUM(CASE WHEN transaction_type = 'outflow' THEN amount ELSE 0 END) as cash_outflow
            FROM cash_transactions 
            WHERE date BETWEEN ? AND ? AND activity_type = 'financing'
        ");
        $stmt->execute([$start_date, $end_date]);
        $financing_activity = $stmt->fetch(PDO::FETCH_ASSOC);
        $financing_cash_flow = ($financing_activity['cash_inflow'] ?? 0) - ($financing_activity['cash_outflow'] ?? 0);

        // صافي التدفق النقدي
        $net_cash_flow = $operating_cash_flow + $investing_cash_flow + $financing_cash_flow;
    }

    // تقرير الضرائب
    if ($report_type == 'tax_report') {
        // المبيعات الخاضعة للضريبة
        $stmt = $db->prepare("
            SELECT SUM(total_amount) as taxable_sales
            FROM invoices 
            WHERE invoice_date BETWEEN ? AND ? AND is_taxable = 1
        ");
        $stmt->execute([$start_date, $end_date]);
        $taxable_sales = $stmt->fetch(PDO::FETCH_ASSOC)['taxable_sales'] ?? 0;

        // ضريبة المبيعات
        $sales_tax_rate = 0.15;
        $sales_tax_due = $taxable_sales * $sales_tax_rate;

        // المصروفات القابلة للخصم
        $stmt = $db->prepare("
            SELECT SUM(amount) as deductible_expenses
            FROM expenses 
            WHERE date BETWEEN ? AND ? AND is_deductible = 1
        ");
        $stmt->execute([$start_date, $end_date]);
        $deductible_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['deductible_expenses'] ?? 0;

        // الوعاء الضريبي
        $taxable_income = $taxable_sales - $deductible_expenses;
        $income_tax_due = $taxable_income * 0.2;
    }

    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_financial_reports', 'عرض التقارير المالية');

} catch (PDOException $e) {
    error_log('Financial Reports Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
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
        
        .filter-section {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-input, .form-select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        .btn {
            padding: 10px 15px;
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
        
        .report-section {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 22px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
            color: var(--secondary-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .print-btn {
            background-color: var(--secondary-color);
            color: white;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .financial-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: var(--light-color);
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .income { color: var(--success-color); }
        .expense { color: var(--danger-color); }
        .asset { color: var(--primary-color); }
        .liability { color: var(--warning-color); }
        
        .stat-label {
            color: #777;
            font-size: 16px;
        }
        
        .financial-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .financial-table th,
        .financial-table td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        
        .financial-table th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        .financial-table tr:last-child {
            font-weight: 700;
            background-color: #f5f5f5;
        }
        
        .text-right {
            text-align: right;
        }
        
        .positive { color: var(--success-color); }
        .negative { color: var(--danger-color); }
        
        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .financial-stats {
                grid-template-columns: 1fr;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
            }
            
            .financial-table {
                display: block;
                overflow-x: auto;
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
                    <div style="font-size: 12px; color: #777;">المحاسبة</div>
                </div>
            </div>
        </header>
        
        <h1 class="dashboard-title">التقارير المالية</h1>
        
        <?php if (isset($error)): ?>
        <div style="background-color: #ffebee; color: #d32f2f; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label class="form-label">نوع التقرير</label>
                    <select name="report_type" class="form-select">
                        <option value="income_statement" <?php echo $report_type == 'income_statement' ? 'selected' : ''; ?>>قائمة الدخل</option>
                        <option value="balance_sheet" <?php echo $report_type == 'balance_sheet' ? 'selected' : ''; ?>>الميزانية العمومية</option>
                        <option value="cash_flow" <?php echo $report_type == 'cash_flow' ? 'selected' : ''; ?>>التدفق النقدي</option>
                        <option value="tax_report" <?php echo $report_type == 'tax_report' ? 'selected' : ''; ?>>تقارير الضرائب</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="start_date" class="form-input" value="<?php echo $start_date; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="end_date" class="form-input" value="<?php echo $end_date; ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">عرض التقرير</button>
                </div>
            </form>
        </div>
        
        <!-- قائمة الدخل -->
        <?php if ($report_type == 'income_statement'): ?>
        <div class="report-section">
            <h2 class="section-title">
                قائمة الدخل (من <?php echo $start_date; ?> إلى <?php echo $end_date; ?>)
                <a href="javascript:window.print()" class="print-btn">
                    <i class="fas fa-print"></i> طباعة
                </a>
            </h2>
            
            <div class="financial-stats">
                <div class="stat-card">
                    <div class="stat-value income"><?php echo number_format($total_revenue, 2); ?> د.ل</div>
                    <div class="stat-label">إجمالي الإيرادات</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value expense"><?php echo number_format($total_expenses, 2); ?> د.ل</div>
                    <div class="stat-label">إجمالي المصروفات</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value <?php echo $net_income >= 0 ? 'income' : 'expense'; ?>">
                        <?php echo number_format($net_income, 2); ?> د.ل
                    </div>
                    <div class="stat-label">صافي الدخل</div>
                </div>
            </div>
            
            <table class="financial-table">
                <thead>
                    <tr>
                        <th>البند</th>
                        <th class="text-right">المبلغ (د.ل)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>الإيرادات</td>
                        <td class="text-right income"><?php echo number_format($total_revenue, 2); ?></td>
                    </tr>
                    <tr>
                        <td>المصروفات</td>
                        <td class="text-right expense">(<?php echo number_format($total_expenses, 2); ?>)</td>
                    </tr>
                    <tr>
                        <td><strong>صافي الدخل</strong></td>
                        <td class="text-right <?php echo $net_income >= 0 ? 'income' : 'expense'; ?>">
                            <strong><?php echo number_format($net_income, 2); ?></strong>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- الميزانية العمومية -->
        <?php if ($report_type == 'balance_sheet'): ?>
        <div class="report-section">
            <h2 class="section-title">
                الميزانية العمومية (حتى <?php echo $end_date; ?>)
                <a href="javascript:window.print()" class="print-btn">
                    <i class="fas fa-print"></i> طباعة
                </a>
            </h2>
            
            <div class="financial-stats">
                <div class="stat-card">
                    <div class="stat-value asset"><?php echo number_format($total_assets, 2); ?> د.ل</div>
                    <div class="stat-label">إجمالي الأصول</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value liability"><?php echo number_format($total_liabilities, 2); ?> د.ل</div>
                    <div class="stat-label">إجمالي الخصوم</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value asset"><?php echo number_format($total_equity, 2); ?> د.ل</div>
                    <div class="stat-label">إجمالي حقوق الملكية</div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h3 style="margin-bottom: 15px; color: var(--primary-color);">الأصول</h3>
                    <table class="financial-table">
                        <tr>
                            <td>الأصول المتداولة</td>
                            <td class="text-right"><?php echo number_format($total_assets * 0.6, 2); ?></td>
                        </tr>
                        <tr>
                            <td>الأصول الثابتة</td>
                            <td class="text-right"><?php echo number_format($total_assets * 0.4, 2); ?></td>
                        </tr>
                        <tr>
                            <td><strong>إجمالي الأصول</strong></td>
                            <td class="text-right asset"><strong><?php echo number_format($total_assets, 2); ?></strong></td>
                        </tr>
                    </table>
                </div>
                
                <div>
                    <h3 style="margin-bottom: 15px; color: var(--warning-color);">الخصوم وحقوق الملكية</h3>
                    <table class="financial-table">
                        <tr>
                            <td>الخصوم المتداولة</td>
                            <td class="text-right"><?php echo number_format($total_liabilities * 0.7, 2); ?></td>
                        </tr>
                        <tr>
                            <td>الخصوم طويلة الأجل</td>
                            <td class="text-right"><?php echo number_format($total_liabilities * 0.3, 2); ?></td>
                        </tr>
                        <tr>
                            <td><strong>إجمالي الخصوم</strong></td>
                            <td class="text-right liability"><strong><?php echo number_format($total_liabilities, 2); ?></strong></td>
                        </tr>
                        <tr>
                            <td>حقوق الملكية</td>
                            <td class="text-right"><?php echo number_format($total_equity, 2); ?></td>
                        </tr>
                        <tr>
                            <td><strong>إجمالي الخصوم وحقوق الملكية</strong></td>
                            <td class="text-right asset"><strong><?php echo number_format($total_liabilities + $total_equity, 2); ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: var(--border-radius);">
                <strong>ملاحظة:</strong> 
                <?php if ($balance_check == 0): ?>
                <span class="positive">المعادلة المحاسبية متوازنة (الأصول = الخصوم + حقوق الملكية)</span>
                <?php else: ?>
                <span class="negative">المعادلة المحاسبية غير متوازنة. الفرق: <?php echo number_format($balance_check, 2); ?> د.ل</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- التدفق النقدي -->
        <?php if ($report_type == 'cash_flow'): ?>
        <div class="report-section">
            <h2 class="section-title">
                التدفق النقدي (من <?php echo $start_date; ?> إلى <?php echo $end_date; ?>)
                <a href="javascript:window.print()" class="print-btn">
                    <i class="fas fa-print"></i> طباعة
                </a>
            </h2>
            
            <div class="financial-stats">
                <div class="stat-card">
                    <div class="stat-value <?php echo $operating_cash_flow >= 0 ? 'income' : 'expense'; ?>">
                        <?php echo number_format($operating_cash_flow, 2); ?> د.ل
                    </div>
                    <div class="stat-label">التدفق النقدي التشغيلي</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value <?php echo $investing_cash_flow >= 0 ? 'income' : 'expense'; ?>">
                        <?php echo number_format($investing_cash_flow, 2); ?> د.ل
                    </div>
                    <div class="stat-label">التدفق النقدي الاستثماري</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value <?php echo $financing_cash_flow >= 0 ? 'income' : 'expense'; ?>">
                        <?php echo number_format($financing_cash_flow, 2); ?> د.ل
                    </div>
                    <div class="stat-label">التدفق النقدي التمويلي</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value <?php echo $net_cash_flow >= 0 ? 'income' : 'expense'; ?>">
                        <?php echo number_format($net_cash_flow, 2); ?> د.ل
                    </div>
                    <div class="stat-label">صافي التدفق النقدي</div>
                </div>
            </div>
            
            <table class="financial-table">
                <thead>
                    <tr>
                        <th>البند</th>
                        <th class="text-right">المبلغ (د.ل)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>التدفق النقدي من الأنشطة التشغيلية</strong></td>
                        <td class="text-right <?php echo $operating_cash_flow >= 0 ? 'income' : 'expense'; ?>">
                            <strong><?php echo number_format($operating_cash_flow, 2); ?></strong>
                        </td>
                    </tr>
                    <tr>
                        <td>التدفق النقدي من الأنشطة الاستثمارية</td>
                        <td class="text-right <?php echo $investing_cash_flow >= 0 ? 'income' : 'expense'; ?>">
                            <?php echo number_format($investing_cash_flow, 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>التدفق النقدي من الأنشطة التمويلية</td>
                        <td class="text-right <?php echo $financing_cash_flow >= 0 ? 'income' : 'expense'; ?>">
                            <?php echo number_format($financing_cash_flow, 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>صافي التدفق النقدي</strong></td>
                        <td class="text-right <?php echo $net_cash_flow >= 0 ? 'income' : 'expense'; ?>">
                            <strong><?php echo number_format($net_cash_flow, 2); ?></strong>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- تقارير الضرائب -->
        <?php if ($report_type == 'tax_report'): ?>
        <div class="report-section">
            <h2 class="section-title">
                تقرير الضرائب (من <?php echo $start_date; ?> إلى <?php echo $end_date; ?>)
                <a href="javascript:window.print()" class="print-btn">
                    <i class="fas fa-print"></i> طباعة
                </a>
            </h2>
            
            <div class="financial-stats">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($taxable_sales, 2); ?> د.ل</div>
                    <div class="stat-label">المبيعات الخاضعة للضريبة</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($deductible_expenses, 2); ?> د.ل</div>
                    <div class="stat-label">المصروفات القابلة للخصم</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($sales_tax_due, 2); ?> د.ل</div>
                    <div class="stat-label">ضريبة المبيعات المستحقة</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($income_tax_due, 2); ?> د.ل</div>
                    <div class="stat-label">ضريبة الدخل المستحقة</div>
                </div>
            </div>
            
            <table class="financial-table">
                <thead>
                    <tr>
                        <th>البند</th>
                        <th class="text-right">المبلغ (د.ل)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>إجمالي المبيعات الخاضعة للضريبة</td>
                        <td class="text-right"><?php echo number_format($taxable_sales, 2); ?></td>
                    </tr>
                    <tr>
                        <td>ضريبة القيمة المضافة (15%)</td>
                        <td class="text-right"><?php echo number_format($sales_tax_due, 2); ?></td>
                    </tr>
                    <tr>
                        <td colspan="2"><hr></td>
                    </tr>
                    <tr>
                        <td>إجمالي المبيعات الخاضعة للضريبة</td>
                        <td class="text-right"><?php echo number_format($taxable_sales, 2); ?></td>
                    </tr>
                    <tr>
                        <td>ناقص: المصروفات القابلة للخصم</td>
                        <td class="text-right">(<?php echo number_format($deductible_expenses, 2); ?>)</td>
                    </tr>
                    <tr>
                        <td><strong>الوعاء الضريبي</strong></td>
                        <td class="text-right"><strong><?php echo number_format($taxable_income, 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td>ضريبة الدخل (20%)</td>
                        <td class="text-right"><?php echo number_format($income_tax_due, 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>إجمالي الالتزامات الضريبية</strong></td>
                        <td class="text-right"><strong><?php echo number_format($sales_tax_due + $income_tax_due, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>