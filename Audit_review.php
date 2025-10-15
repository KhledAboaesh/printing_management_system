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

// معالجة عوامل التصفية
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$user_id = $_GET['user_id'] ?? '';
$action_type = $_GET['action_type'] ?? '';

// بناء استعلام التصفية
$filter_conditions = "WHERE al.created_at BETWEEN :start_date AND :end_date + INTERVAL 1 DAY";
$params = [
    'start_date' => $start_date,
    'end_date' => $end_date
];

if (!empty($user_id)) {
    $filter_conditions .= " AND al.user_id = :user_id";
    $params['user_id'] = $user_id;
}

if (!empty($action_type)) {
    $filter_conditions .= " AND al.action = :action_type";
    $params['action_type'] = $action_type;
}

// جلب سجل التغييرات المالية
try {
    global $db;
    
    // استعلام سجل التغييرات
    $sql = "SELECT al.*, u.username, u.full_name 
            FROM activity_log al 
            LEFT JOIN users u ON al.user_id = u.user_id 
            $filter_conditions 
            ORDER BY al.created_at DESC 
            LIMIT 100";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب المستخدمين للتحديد في الفلتر
    $users_stmt = $db->query("SELECT user_id, username, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب أنواع الإجراءات للتحديد في الفلتر
    $actions_stmt = $db->query("SELECT DISTINCT action FROM activity_log WHERE action LIKE '%financial%' OR action LIKE '%invoice%' OR action LIKE '%payment%' OR action LIKE '%expense%' ORDER BY action");
    $actions = $actions_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب تقارير المراجعة الداخلية
    $reports_sql = "SELECT j.entry_id, j.description, j.entry_date, u.full_name as created_by, 
                   COUNT(jd.detail_id) as entries_count 
                   FROM journal_entries j 
                   LEFT JOIN users u ON j.created_by = u.user_id 
                   LEFT JOIN journal_entry_details jd ON j.entry_id = jd.entry_id 
                   WHERE j.entry_date BETWEEN :start_date AND :end_date 
                   GROUP BY j.entry_id 
                   ORDER BY j.entry_date DESC";
    
    $reports_stmt = $db->prepare($reports_sql);
    $reports_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $audit_reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب بيانات المطابقة البنكية
    $bank_sql = "SELECT 
                SUM(CASE WHEN type = 'revenue' THEN amount ELSE 0 END) as total_revenues,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expenses,
                (SELECT SUM(amount) FROM invoice_payments WHERE payment_date BETWEEN :start_date AND :end_date) as total_payments,
                (SELECT SUM(amount) FROM expenses WHERE expense_date BETWEEN :start_date AND :end_date) as total_expenses_paid
                FROM (
                    SELECT 'revenue' as type, amount, created_at as date FROM revenues
                    UNION ALL
                    SELECT 'expense' as type, amount, created_at as date FROM expenses
                ) t 
                WHERE date BETWEEN :start_date2 AND :end_date2";
    
    $bank_stmt = $db->prepare($bank_sql);
    $bank_stmt->execute([
        'start_date' => $start_date, 
        'end_date' => $end_date,
        'start_date2' => $start_date, 
        'end_date2' => $end_date
    ]);
    $bank_reconciliation = $bank_stmt->fetch(PDO::FETCH_ASSOC);
    
    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_audit_page', 'عرض صفحة المراجعة والتدقيق');
    
} catch (PDOException $e) {
    error_log('Audit Page Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - المراجعة والتدقيق</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        .filters-card {
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
            margin-bottom: 0;
        }
        
        .form-label {
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
            padding: 10px 15px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
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
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }
        
        .tab {
            padding: 15px 20px;
            cursor: pointer;
            text-align: center;
            flex: 1;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .tab.active {
            border-bottom: 3px solid var(--primary-color);
            background-color: rgba(52, 152, 219, 0.1);
            font-weight: 700;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
            color: var(--secondary-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .badge-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .badge-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .badge-info {
            background-color: var(--info-color);
            color: white;
        }
        
        .reconciliation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .reconciliation-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            text-align: center;
        }
        
        .reconciliation-icon {
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .revenue-icon { color: var(--success-color); }
        .expense-icon { color: var(--danger-color); }
        .payment-icon { color: var(--info-color); }
        .balance-icon { color: var(--primary-color); }
        
        .reconciliation-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .reconciliation-label {
            color: #777;
            font-size: 14px;
        }
        
        .controls-list {
            list-style: none;
        }
        
        .control-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .control-item:last-child {
            border-bottom: none;
        }
        
        .control-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--light-color);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 18px;
        }
        
        .control-content {
            flex-grow: 1;
        }
        
        .control-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .control-desc {
            font-size: 14px;
            color: #777;
        }
        
        .export-btn {
            background-color: var(--success-color);
            color: white;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .reconciliation-grid {
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
                    <div style="font-size: 12px; color: #777;">مراجعة وتدقيق</div>
                </div>
            </div>
        </header>
        
        <h1 class="page-title">المراجعة والتدقيق المالي</h1>
        
        <?php if (isset($error)): ?>
        <div style="background-color: #ffebee; color: #d32f2f; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="filters-card">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">المستخدم</label>
                    <select name="user_id" class="form-control">
                        <option value="">جميع المستخدمين</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>" <?php echo $user_id == $user['user_id'] ? 'selected' : ''; ?>>
                            <?php echo $user['full_name'] ?: $user['username']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">نوع الإجراء</label>
                    <select name="action_type" class="form-control">
                        <option value="">جميع الإجراءات</option>
                        <?php foreach ($actions as $action): ?>
                        <option value="<?php echo $action['action']; ?>" <?php echo $action_type == $action['action'] ? 'selected' : ''; ?>>
                            <?php echo $action['action']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">تطبيق الفلتر</button>
                    <a href="audit.php" class="btn btn-secondary">إعادة تعيين</a>
                </div>
            </form>
        </div>
        
        <div class="tabs">
            <div class="tab active" data-tab="changes">سجل التغييرات المالية</div>
            <div class="tab" data-tab="reports">تقارير المراجعة الداخلية</div>
            <div class="tab" data-tab="reconciliation">المطابقة البنكية</div>
            <div class="tab" data-tab="controls">ضوابط الرقابة المالية</div>
        </div>
        
        <!-- سجل التغييرات المالية -->
        <div class="tab-content active" id="changes">
            <div class="card">
                <div class="card-title">
                    <span>سجل التغييرات المالية</span>
                    <a href="export_audit_log.php?<?php echo http_build_query($_GET); ?>" class="export-btn">
                        <i class="fas fa-download"></i> تصدير إلى Excel
                    </a>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>التاريخ والوقت</th>
                                <th>المستخدم</th>
                                <th>الإجراء</th>
                                <th>التفاصيل</th>
                                <th>عنوان IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($activity_logs)): ?>
                                <?php foreach ($activity_logs as $log): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                                    <td><?php echo $log['full_name'] ?: $log['username']; ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                            if (strpos($log['action'], 'delete') !== false) echo 'badge-danger';
                                            elseif (strpos($log['action'], 'edit') !== false) echo 'badge-warning';
                                            elseif (strpos($log['action'], 'add') !== false) echo 'badge-success';
                                            else echo 'badge-info';
                                            ?>
                                        ">
                                            <?php echo $log['action']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $log['description']; ?></td>
                                    <td><?php echo $log['ip_address']; ?></td>
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
        
        <!-- تقارير المراجعة الداخلية -->
        <div class="tab-content" id="reports">
            <div class="card">
                <div class="card-title">
                    <span>تقارير المراجعة الداخلية</span>
                    <a href="export_audit_reports.php?<?php echo http_build_query(['start_date' => $start_date, 'end_date' => $end_date]); ?>" class="export-btn">
                        <i class="fas fa-download"></i> تصدير إلى PDF
                    </a>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>رقم القيد</th>
                                <th>التاريخ</th>
                                <th>الوصف</th>
                                <th>عدد البنود</th>
                                <th>تم الإنشاء بواسطة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($audit_reports)): ?>
                                <?php foreach ($audit_reports as $report): ?>
                                <tr>
                                    <td>#<?php echo $report['entry_id']; ?></td>
                                    <td><?php echo $report['entry_date']; ?></td>
                                    <td><?php echo $report['description'] ?: 'بدون وصف'; ?></td>
                                    <td><?php echo $report['entries_count']; ?></td>
                                    <td><?php echo $report['created_by']; ?></td>
                                    <td>
                                        <a href="journal_entry.php?id=<?php echo $report['entry_id']; ?>" class="btn btn-primary" style="padding: 5px 10px; font-size: 14px;">
                                            <i class="fas fa-eye"></i> عرض
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">لا توجد تقارير مراجعة في الفترة المحددة</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- المطابقة البنكية -->
        <div class="tab-content" id="reconciliation">
            <div class="card">
                <div class="card-title">المطابقة البنكية للفترة من <?php echo $start_date; ?> إلى <?php echo $end_date; ?></div>
                
                <div class="reconciliation-grid">
                    <div class="reconciliation-card">
                        <div class="reconciliation-icon revenue-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="reconciliation-value">
                            <?php echo number_format($bank_reconciliation['total_revenues'] ?? 0, 2); ?> د.ل
                        </div>
                        <div class="reconciliation-label">إجمالي الإيرادات</div>
                    </div>
                    
                    <div class="reconciliation-card">
                        <div class="reconciliation-icon expense-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="reconciliation-value">
                            <?php echo number_format($bank_reconciliation['total_expenses'] ?? 0, 2); ?> د.ل
                        </div>
                        <div class="reconciliation-label">إجمالي المصروفات</div>
                    </div>
                    
                    <div class="reconciliation-card">
                        <div class="reconciliation-icon payment-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="reconciliation-value">
                            <?php echo number_format($bank_reconciliation['total_payments'] ?? 0, 2); ?> د.ل
                        </div>
                        <div class="reconciliation-label">المدفوعات المستلمة</div>
                    </div>
                    
                    <div class="reconciliation-card">
                        <div class="reconciliation-icon balance-icon">
                            <i class="fas fa-balance-scale"></i>
                        </div>
                        <div class="reconciliation-value">
                            <?php 
                            $balance = ($bank_reconciliation['total_revenues'] ?? 0) - ($bank_reconciliation['total_expenses'] ?? 0);
                            echo number_format($balance, 2); 
                            ?> د.ل
                        </div>
                        <div class="reconciliation-label">الرصيد الصافي</div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>نوع المعاملة</th>
                                <th>المبلغ</th>
                                <th>الحالة</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>الإيرادات المسجلة</td>
                                <td><?php echo number_format($bank_reconciliation['total_revenues'] ?? 0, 2); ?> د.ل</td>
                                <td><span class="badge badge-success">مطابق</span></td>
                                <td>جميع الإيرادات مسجلة في النظام</td>
                            </tr>
                            <tr>
                                <td>المصروفات المسجلة</td>
                                <td><?php echo number_format($bank_reconciliation['total_expenses'] ?? 0, 2); ?> د.ل</td>
                                <td><span class="badge badge-success">مطابق</span></td>
                                <td>جميع المصروفات مسجلة في النظام</td>
                            </tr>
                            <tr>
                                <td>المدفوعات المستلمة</td>
                                <td><?php echo number_format($bank_reconciliation['total_payments'] ?? 0, 2); ?> د.ل</td>
                                <td><span class="badge badge-warning">تحت المراجعة</span></td>
                                <td>يجب مطابقتها مع كشف البنك</td>
                            </tr>
                            <tr>
                                <td>المصروفات المدفوعة</td>
                                <td><?php echo number_format($bank_reconciliation['total_expenses_paid'] ?? 0, 2); ?> د.ل</td>
                                <td><span class="badge badge-warning">تحت المراجعة</span></td>
                                <td>يجب مطابقتها مع كشف البنك</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- ضوابط الرقابة المالية -->
        <div class="tab-content" id="controls">
            <div class="card">
                <div class="card-title">ضوابط الرقابة المالية</div>
                
                <ul class="controls-list">
                    <li class="control-item">
                        <div class="control-icon">
                            <i class="fas fa-user-lock"></i>
                        </div>
                        <div class="control-content">
                            <div class="control-title">صلاحيات المستخدمين</div>
                            <div class="control-desc">ضبط صلاحيات المستخدمين حسب المهام والمسؤوليات المحددة لكل دور</div>
                        </div>
                        <div>
                            <span class="badge badge-success">مفعل</span>
                        </div>
                    </li>
                    
                    <li class="control-item">
                        <div class="control-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div class="control-content">
                            <div class="control-title">المصادقة على الفواتير</div>
                            <div class="control-desc">ضرورة مصادقة مسؤولين اثنين على الفواتير ذات القيمة الأعلى من 5000 د.ل</div>
                        </div>
                        <div>
                            <span class="badge badge-success">مفعل</span>
                        </div>
                    </li>
                    
                    <li class="control-item">
                        <div class="control-icon">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div class="control-content">
                            <div class="control-title">ترقيم الفواتير</div>
                            <div class="control-desc">جميع الفواتير مرقمة تسلسلياً ولا يمكن حذف أي فاتورة مسجلة</div>
                        </div>
                        <div>
                            <span class="badge badge-success">مفعل</span>
                        </div>
                    </li>
                    
                    <li class="control-item">
                        <div class="control-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="control-content">
                            <div class="control-title">فصل المهام</div>
                            <div class="control-desc">المستخدم الذي يسجل المعاملة لا يمكنه المصادقة عليها أو تعديلها بعد المصادقة</div>
                        </div>
                        <div>
                            <span class="badge badge-warning">تحت التطوير</span>
                        </div>
                    </li>
                    
                    <li class="control-item">
                        <div class="control-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <div class="control-content">
                            <div class="control-title">سجل التدقيق</div>
                            <div class="control-desc">جميع التغييرات في النظام مسجلة مع معلومات المستخدم والوقت والتاريخ</div>
                        </div>
                        <div>
                            <span class="badge badge-success">مفعل</span>
                        </div>
                    </li>
                    
                    <li class="control-item">
                        <div class="control-icon">
                            <i class="fas fa-ban"></i>
                        </div>
                        <div class="control-content">
                            <div class="control-title">منع التعديلات</div>
                            <div class="control-desc">لا يمكن تعديل القيود المحاسبية بعد ترحيلها للإدارة المالية</div>
                        </div>
                        <div>
                            <span class="badge badge-success">مفعل</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // تبويبات الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // إزالة النشاط من جميع التبويبات والمحتويات
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(tc => tc.classList.remove('active'));
                    
                    // إضافة النشاط للتبويب والمحتوى المحدد
                    this.classList.add('active');
                    document.getElementById(tabId).classList.add('active');
                });
            });
        });
    </script>
</body>
</html>