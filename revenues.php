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

// معالجة نموذج إضافة إيراد جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_revenue'])) {
    $amount       = $_POST['amount'] ?? 0;
    $source       = $_POST['source'] ?? '';
    $type         = $_POST['type'] ?? 'other';
    $description  = $_POST['description'] ?? '';
    $revenue_date = $_POST['revenue_date'] ?? date('Y-m-d');
    $account_id   = $_POST['account_id'] ?? null; // الحساب المصروف/الوارد
    $received_by  = $_SESSION['user_id'];

    if (empty($account_id)) {
        $_SESSION['error'] = "الرجاء اختيار الحساب المرتبط بالإيراد.";
    } else {
        try {
            global $db;

            // التحقق من وجود الحساب المحدد
            $stmt_check = $db->prepare("SELECT * FROM accounts WHERE account_id = ?");
            $stmt_check->execute([$account_id]);
            $account = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if (!$account) {
                $_SESSION['error'] = "الحساب المختار غير موجود!";
            } else {
                // إضافة الإيراد
                $stmt = $db->prepare("INSERT INTO revenues (amount, source, type, description, revenue_date, received_by, account_id) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$amount, $source, $type, $description, $revenue_date, $received_by, $account_id]);
                $revenue_id = $db->lastInsertId();

                // تسجيل القيد المحاسبي في اليومية
                $journal_stmt = $db->prepare("INSERT INTO journal_entries (entry_date, description, created_by) 
                                              VALUES (?, ?, ?)");
                $journal_stmt->execute([$revenue_date, "قيد إيراد من " . $source, $received_by]);
                $entry_id = $db->lastInsertId();

                // الحصول على حساب الإيرادات (دائن)
                $stmt_revenue_account = $db->query("SELECT account_id FROM accounts WHERE type = 'revenue' LIMIT 1");
                $revenue_account = $stmt_revenue_account->fetch(PDO::FETCH_ASSOC);

                if (!$revenue_account) {
                    $_SESSION['error'] = "لا يوجد حساب إيرادات في النظام، يرجى إضافته أولاً.";
                } else {
                    $revenue_account_id = $revenue_account['account_id'];

                    // تفاصيل القيد (مدين - دائن)
                    $detail_stmt = $db->prepare("INSERT INTO journal_entry_details (entry_id, account_id, debit, credit) 
                                                 VALUES (?, ?, ?, ?)");
                    $detail_stmt->execute([$entry_id, $account_id, $amount, 0]);          // المدين (البنك/النقدية)
                    $detail_stmt->execute([$entry_id, $revenue_account_id, 0, $amount]); // الدائن (الإيرادات)

                    $_SESSION['success'] = "تم تسجيل الإيراد بنجاح وإضافة القيد المحاسبي";
                    header("Location: revenues.php");
                    exit();
                }
            }
        } catch (PDOException $e) {
            error_log('Add Revenue Error: ' . $e->getMessage());
            $_SESSION['error'] = "حدث خطأ أثناء تسجيل الإيراد: " . $e->getMessage();
        }
    }
}

// جلب الحسابات لعرضها في الفورم
try {
    $stmt_accounts = $db->query("SELECT account_id, name FROM accounts ORDER BY name ASC");
    $accounts = $stmt_accounts->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $accounts = [];
}

// جلب بيانات الإيرادات
try {
    global $db;

    // تحقق إذا كان جدول revenues فارغ
    $stmt_check = $db->query("SELECT COUNT(*) as cnt FROM revenues");
    $count_revenues = $stmt_check->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0;

    if ($count_revenues == 0) {
        // البيانات من الفواتير
        $stmt = $db->query("SELECT SUM(total_amount) as total_revenues FROM invoices");
        $total_revenues = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenues'] ?? 0;

        $first_day_month = date('Y-m-01');
        $stmt = $db->prepare("SELECT SUM(total_amount) as monthly_revenues FROM invoices WHERE invoice_date >= ?");
        $stmt->execute([$first_day_month]);
        $monthly_revenues = $stmt->fetch(PDO::FETCH_ASSOC)['monthly_revenues'] ?? 0;

        $stmt = $db->query("SELECT COUNT(*) as revenue_count FROM invoices");
        $revenue_count = $stmt->fetch(PDO::FETCH_ASSOC)['revenue_count'] ?? 0;

        // آخر الإيرادات من الفواتير مع اسم المستخدم
        $stmt = $db->query("SELECT i.invoice_id AS revenue_id, i.invoice_date AS revenue_date, i.total_amount AS amount, 
                                   'invoice' AS source, NULL AS type, i.created_by AS received_by,
                                   COALESCE(u.username, 'غير معروف') AS received_by_name
                            FROM invoices i
                            LEFT JOIN users u ON i.created_by = u.user_id
                            ORDER BY i.invoice_date DESC
                            LIMIT 10");
        $recent_revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->query("SELECT c.name AS source, SUM(i.total_amount) as total 
                            FROM invoices i
                            JOIN customers c ON i.customer_id = c.customer_id
                            GROUP BY c.name
                            ORDER BY total DESC
                            LIMIT 5");
        $revenue_sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->query("SELECT 'invoice' AS type, SUM(total_amount) AS total FROM invoices");
        $revenue_by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // البيانات من جدول revenues
        $stmt = $db->query("SELECT SUM(amount) as total_revenues FROM revenues");
        $total_revenues = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenues'];

        $first_day_month = date('Y-m-01');
        $stmt = $db->prepare("SELECT SUM(amount) as monthly_revenues FROM revenues WHERE revenue_date >= ?");
        $stmt->execute([$first_day_month]);
        $monthly_revenues = $stmt->fetch(PDO::FETCH_ASSOC)['monthly_revenues'];

        $stmt = $db->query("SELECT COUNT(*) as revenue_count FROM revenues");
        $revenue_count = $stmt->fetch(PDO::FETCH_ASSOC)['revenue_count'];

        $stmt = $db->query("SELECT source, SUM(amount) as total FROM revenues GROUP BY source ORDER BY total DESC LIMIT 5");
        $revenue_sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->query("SELECT type, SUM(amount) as total FROM revenues GROUP BY type ORDER BY total DESC");
        $revenue_by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->query("SELECT r.*, COALESCE(u.username,'غير معروف') as received_by_name
                            FROM revenues r
                            LEFT JOIN users u ON r.received_by = u.user_id
                            ORDER BY r.revenue_date DESC, r.revenue_id DESC
                            LIMIT 10");
        $recent_revenues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // الإيرادات للرسم البياني (آخر 6 أشهر)
    $six_months_ago = date('Y-m-01', strtotime('-5 months'));
    if ($count_revenues == 0) {
        $stmt = $db->prepare("SELECT DATE_FORMAT(invoice_date, '%Y-%m') as month, SUM(total_amount) as total 
                              FROM invoices 
                              WHERE invoice_date >= ? 
                              GROUP BY DATE_FORMAT(invoice_date, '%Y-%m') 
                              ORDER BY month");
    } else {
        $stmt = $db->prepare("SELECT DATE_FORMAT(revenue_date, '%Y-%m') as month, SUM(amount) as total 
                              FROM revenues 
                              WHERE revenue_date >= ? 
                              GROUP BY DATE_FORMAT(revenue_date, '%Y-%m') 
                              ORDER BY month");
    }
    $stmt->execute([$six_months_ago]);
    $revenue_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_revenues', 'عرض صفحة الإيرادات');

} catch (PDOException $e) {
    error_log('Revenues Page Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}
?>



<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - الإيرادات</title>
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
        
        .total-icon { color: var(--primary-color); }
        .monthly-icon { color: var(--success-color); }
        .count-icon { color: var(--warning-color); }
        .avg-icon { color: var(--danger-color); }
        
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
        
        .chart-section, .sources-section, .recent-section, .form-section {
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
        
        .sources-list {
            list-style: none;
        }
        
        .source-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .source-item:last-child {
            border-bottom: none;
        }
        
        .source-name {
            font-weight: 500;
        }
        
        .source-amount {
            font-weight: 700;
            color: var(--success-color);
        }
        
        .revenue-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .revenue-table th, .revenue-table td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }
        
        .revenue-table th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        .revenue-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .revenue-amount {
            font-weight: 700;
            color: var(--success-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .type-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .type-sales {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .type-service {
            background-color: #e3f2fd;
            color: #1565c0;
        }
        
        .type-other {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        
        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
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
            
            .revenue-table {
                font-size: 14px;
            }
            
            .revenue-table th, .revenue-table td {
                padding: 8px 10px;
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
        
        <h1 class="dashboard-title">إدارة الإيرادات</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value"><?php echo number_format($total_revenues ?? 0, 2); ?> <small>د.ل</small></div>
                <div class="stat-label">إجمالي الإيرادات</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon monthly-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo number_format($monthly_revenues ?? 0, 2); ?> <small>د.ل</small></div>
                <div class="stat-label">إيرادات الشهر الحالي</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon count-icon">
                    <i class="fas fa-list-alt"></i>
                </div>
                <div class="stat-value"><?php echo $revenue_count ?? 0; ?></div>
                <div class="stat-label">عدد معاملات الإيراد</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon avg-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="stat-value"><?php echo $revenue_count > 0 ? number_format($total_revenues / $revenue_count, 2) : 0; ?> <small>د.ل</small></div>
                <div class="stat-label">متوسط قيمة المعاملة</div>
            </div>
        </div>
        
        <div class="content-grid">
            <div class="chart-section">
                <h2 class="section-title">تطور الإيرادات خلال آخر 6 أشهر</h2>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            
            <div class="sources-section">
                <h2 class="section-title">أهم مصادر الإيراد</h2>
                <ul class="sources-list">
                    <?php if (!empty($revenue_sources)): ?>
                        <?php foreach ($revenue_sources as $source): ?>
                        <li class="source-item">
                            <span class="source-name"><?php echo $source['source']; ?></span>
                            <span class="source-amount"><?php echo number_format($source['total'], 2); ?> د.ل</span>
                        </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="source-item">لا توجد بيانات</li>
                    <?php endif; ?>
                </ul>
                
                <h2 class="section-title" style="margin-top: 30px;">الإيرادات حسب النوع</h2>
                <ul class="sources-list">
                    <?php if (!empty($revenue_by_type)): ?>
                        <?php foreach ($revenue_by_type as $type): ?>
                        <li class="source-item">
                            <span class="source-name"><?php echo $type['type']; ?></span>
                            <span class="source-amount"><?php echo number_format($type['total'], 2); ?> د.ل</span>
                        </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="source-item">لا توجد بيانات</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <div class="content-grid">
            <div class="recent-section">
                <h2 class="section-title">آخر الإيرادات المسجلة</h2>
                <table class="revenue-table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>المصدر</th>
                            <th>النوع</th>
                            <th>المبلغ</th>
                            <th>مسجل بواسطة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_revenues)): ?>
                            <?php foreach ($recent_revenues as $revenue): ?>
                            <tr>
                                <td><?php echo $revenue['revenue_date']; ?></td>
                                <td><?php echo $revenue['source']; ?></td>
                                <td>
                                    <span class="type-badge type-<?php echo $revenue['type']; ?>">
                                        <?php echo $revenue['type']; ?>
                                    </span>
                                </td>
                                <td class="revenue-amount"><?php echo number_format($revenue['amount'], 2); ?> د.ل</td>
                                <td><?php echo $revenue['received_by_name']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">لا توجد إيرادات مسجلة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="form-section">
                <h2 class="section-title">تسجيل إيراد جديد</h2>
                               <form method="POST" action="">
    <div class="form-group">
        <label class="form-label">المصدر</label>
        <input type="text" class="form-input" name="source" required placeholder="مصدر الإيراد">
    </div>
    
    <div class="form-group">
        <label class="form-label">النوع</label>
        <select class="form-select" name="type" required>
            <option value="sales">مبيعات</option>
            <option value="service">خدمات</option>
            <option value="other">أخرى</option>
        </select>
    </div>
    
    <div class="form-group">
        <label class="form-label">المبلغ (د.ل)</label>
        <input type="number" class="form-input" name="amount" step="0.01" min="0" required placeholder="0.00">
    </div>
    
    <div class="form-group">
        <label class="form-label">التاريخ</label>
        <input type="date" class="form-input" name="revenue_date" required value="<?php echo date('Y-m-d'); ?>">
    </div>
    
    <div class="form-group">
        <label class="form-label">الوصف (اختياري)</label>
        <textarea class="form-textarea" name="description" placeholder="وصف تفصيلي للإيراد"></textarea>
    </div>

    <div class="form-group">
        <label class="form-label">الحساب</label>
        <select class="form-select" name="account_id" required>
            <option value="">-- اختر الحساب --</option>
            <?php
            try {
                $stmt = $db->query("SELECT account_id, name FROM accounts");
                $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($accounts as $acc) {
                    echo "<option value='{$acc['account_id']}'>{$acc['name']}</option>";
                }
            } catch (PDOException $e) {
                echo "<option value=''>خطأ في تحميل الحسابات</option>";
            }
            ?>
        </select>
    </div>
    
    <button type="submit" name="add_revenue" class="btn btn-block">تسجيل الإيراد</button>
</form>

            </div>
        </div>
    </div>

    <script>
        // الرسم البياني لتطور الإيرادات
        document.addEventListener('DOMContentLoaded', function() {
            const revenueData = <?php echo json_encode($revenue_trends); ?>;
            
            // تحضير البيانات للرسم البياني
            const months = [];
            const amounts = [];
            
            // إنشاء مجموعة من الأشهر للستة أشهر الماضية
            const monthNames = ["يناير", "فبراير", "مارس", "أبريل", "مايو", "يونيو", 
                               "يوليو", "أغسطس", "سبتمبر", "أكتوبر", "نوفمبر", "ديسمبر"];
            
            for (let i = 5; i >= 0; i--) {
                const date = new Date();
                date.setMonth(date.getMonth() - i);
                const year = date.getFullYear();
                const month = date.getMonth();
                const monthKey = `${year}-${(month + 1).toString().padStart(2, '0')}`;
                
                months.push(`${monthNames[month]} ${year}`);
                
                // البحث عن البيانات المناسبة لهذا الشهر
                const revenueForMonth = revenueData.find(item => item.month === monthKey);
                amounts.push(revenueForMonth ? parseFloat(revenueForMonth.total) : 0);
            }
            
            //绘制图表
            const ctx = document.getElementById('revenueChart').getContext('2d');
            const revenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'الإيرادات بالدينار الليبي ',
                        data: amounts,
                        backgroundColor: 'rgba(52, 152, 219, 0.2)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(52, 152, 219, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(52, 152, 219, 1)',
                        tension: 0.3
                    }]
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
                            labels: {
                                font: {
                                    family: 'Tajawal'
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y.toLocaleString() + ' د.ل';
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