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
$success = '';
$error = '';
$budgets = [];
$current_revenue = 0;
$current_expenses = 0;
$monthly_data = [];
$variance_data = [];

// معالجة نموذج إضافة ميزانية جديدة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_budget'])) {
    $title = $_POST['title'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $category = $_POST['category'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $description = $_POST['description'] ?? '';

    try {
        $stmt = $db->prepare("INSERT INTO budgets (title, amount, category, start_date, end_date, description, created_by) 
                             VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $amount, $category, $start_date, $end_date, $description, $_SESSION['user_id']]);

        $success = "تم إضافة الميزانية بنجاح";
        logActivity($_SESSION['user_id'], 'add_budget', "إضافة ميزانية جديدة: $title");
    } catch (PDOException $e) {
        error_log('Budget Add Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء إضافة الميزانية";
    }
}

// جلب بيانات الميزانية والإيرادات والمصروفات
try {
    // جلب الميزانيات
    $stmt = $db->query("SELECT * FROM budgets ORDER BY created_at DESC");
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $current_year = date('Y');
    $current_month = date('m');

    // إيرادات الشهر الحالي
    $stmt = $db->prepare("SELECT SUM(amount) as total FROM revenues 
                         WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?");
    $stmt->execute([$current_year, $current_month]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_revenue = $row['total'] ?? 0;

    // مصروفات الشهر الحالي
    $stmt = $db->prepare("SELECT SUM(amount) as total FROM expenses 
                         WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?");
    $stmt->execute([$current_year, $current_month]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_expenses = $row['total'] ?? 0;

    // جلب بيانات الأداء للرسوم البيانية
    $stmt = $db->prepare("SELECT MONTH(created_at) as month, 
                          SUM(CASE WHEN type = 'revenue' THEN amount ELSE 0 END) as revenue,
                          SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
                          FROM financial_records 
                          WHERE YEAR(created_at) = ?
                          GROUP BY MONTH(created_at)");
    $stmt->execute([$current_year]);
    $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // حساب الانحرافات
    $stmt = $db->query("SELECT b.category, b.amount as budgeted, 
                       COALESCE(SUM(CASE WHEN f.type = 'actual' THEN f.amount ELSE 0 END), 0) as actual,
                       (b.amount - COALESCE(SUM(CASE WHEN f.type = 'actual' THEN f.amount ELSE 0 END), 0)) as variance
                       FROM budgets b
                       LEFT JOIN financial_records f ON b.category = f.category
                       WHERE b.start_date <= CURDATE() AND b.end_date >= CURDATE()
                       GROUP BY b.category, b.amount");
    $variance_data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_budget_planning', 'عرض صفحة الميزانية والتخطيط');

} catch (PDOException $e) {
    error_log('Budget Planning Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - الميزانية والتخطيط</title>
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
        
        .page-title {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--secondary-color);
            text-align: center;
        }
        
        .tabs {
            display: flex;
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .tab {
            padding: 15px 20px;
            cursor: pointer;
            flex: 1;
            text-align: center;
            transition: background-color 0.3s;
        }
        
        .tab.active {
            background-color: var(--primary-color);
            color: white;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
        
        .budget-icon { color: var(--primary-color); }
        .revenue-icon { color: var(--success-color); }
        .expense-icon { color: var(--warning-color); }
        .variance-icon { color: var(--danger-color); }
        
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
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .card-title {
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
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
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
        
        .positive-variance {
            color: var(--success-color);
            font-weight: bold;
        }
        
        .negative-variance {
            color: var(--danger-color);
            font-weight: bold;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        button:hover {
            background-color: #2980b9;
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
        
        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tabs {
                flex-direction: column;
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
        
        <h1 class="page-title">الميزانية والتخطيط</h1>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('overview')">نظرة عامة</div>
            <div class="tab" onclick="switchTab('budgets')">إدارة الميزانيات</div>
            <div class="tab" onclick="switchTab('analysis')">تحليل الانحرافات</div>
            <div class="tab" onclick="switchTab('forecasting')">التوقعات المالية</div>
        </div>
        
        <!-- نظرة عامة -->
        <div id="overview" class="tab-content active">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon budget-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format(500000); ?> د.ل</div>
                    <div class="stat-label">إجمالي الميزانية</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon revenue-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($current_revenue); ?> د.ل</div>
                    <div class="stat-label">الإيرادات الشهرية</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon expense-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($current_expenses); ?> د.ل</div>
                    <div class="stat-label">المصروفات الشهرية</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon variance-icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($current_revenue - $current_expenses); ?> د.ل</div>
                    <div class="stat-label">صافي الدخل</div>
                </div>
            </div>
            
            <div class="content-grid">
                <div class="card">
                    <h2 class="card-title">أداء الإيرادات مقابل المصروفات</h2>
                    <div class="chart-container">
                        <canvas id="revenueExpenseChart"></canvas>
                    </div>
                </div>
                
                <div class="card">
                    <h2 class="card-title">توزيع الميزانية</h2>
                    <div class="chart-container">
                        <canvas id="budgetDistributionChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h2 class="card-title">أحدث الميزانيات</h2>
                <table>
                    <thead>
                        <tr>
                            <th>الفئة</th>
                            <th>الميزانية</th>
                            <th>المتحقق</th>
                            <th>الانحراف</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($variance_data)): ?>
                            <?php foreach ($variance_data as $item): ?>
                            <tr>
                                <td><?php echo $item['category']; ?></td>
                                <td><?php echo number_format($item['budgeted']); ?> د.ل</td>
                                <td><?php echo number_format($item['actual']); ?> د.ل</td>
                                <td class="<?php echo $item['variance'] >= 0 ? 'positive-variance' : 'negative-variance'; ?>">
                                    <?php echo number_format($item['variance']); ?> د.ل
                                </td>
                                <td>
                                    <span style="color: <?php echo $item['variance'] >= 0 ? '#2ecc71' : '#e74c3c'; ?>;">
                                        <?php echo $item['variance'] >= 0 ? 'تحت السيطرة' : 'يتطلب الاهتمام'; ?>
                                    </span>
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
        
        <!-- إدارة الميزانيات -->
        <div id="budgets" class="tab-content">
            <div class="content-grid">
                <div class="card">
                    <h2 class="card-title">إضافة ميزانية جديدة</h2>
                    <form method="POST">
                        <div class="form-group">
                            <label for="title">عنوان الميزانية</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">المبلغ (د.ل)</label>
                            <input type="number" id="amount" name="amount" min="0" step="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="category">الفئة</label>
                            <select id="category" name="category" required>
                                <option value="">اختر الفئة</option>
                                <option value="مواد خام">مواد خام</option>
                                <option value="مرتبات">مرتبات</option>
                                <option value="تسويق">تسويق</option>
                                <option value="صيانة">صيانة</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="start_date">تاريخ البدء</label>
                            <input type="date" id="start_date" name="start_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="end_date">تاريخ الانتهاء</label>
                            <input type="date" id="end_date" name="end_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">الوصف (اختياري)</label>
                            <textarea id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <button type="submit" name="add_budget">إضافة الميزانية</button>
                    </form>
                </div>
                
                <div class="card">
                    <h2 class="card-title">قائمة الميزانيات</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>العنوان</th>
                                <th>المبلغ</th>
                                <th>الفئة</th>
                                <th>الفترة</th>
                                <th>الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($budgets)): ?>
                                <?php foreach ($budgets as $budget): ?>
                                <tr>
                                    <td><?php echo $budget['title']; ?></td>
                                    <td><?php echo number_format($budget['amount']); ?> د.ل</td>
                                    <td><?php echo $budget['category']; ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($budget['start_date'])); ?> إلى <?php echo date('Y-m-d', strtotime($budget['end_date'])); ?></td>
                                    <td>
                                        <?php
                                        $current_date = date('Y-m-d');
                                        $status = '';
                                        $color = '';
                                        
                                        if ($current_date < $budget['start_date']) {
                                            $status = 'لم تبدأ';
                                            $color = '#f39c12';
                                        } elseif ($current_date > $budget['end_date']) {
                                            $status = 'منتهية';
                                            $color = '#95a5a6';
                                        } else {
                                            $status = 'نشطة';
                                            $color = '#2ecc71';
                                        }
                                        ?>
                                        <span style="color: <?php echo $color; ?>;"><?php echo $status; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">لا توجد ميزانيات مضافة</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- تحليل الانحرافات -->
        <div id="analysis" class="tab-content">
            <div class="card">
                <h2 class="card-title">تحليل الانحرافات</h2>
                <div class="chart-container">
                    <canvas id="varianceAnalysisChart"></canvas>
                </div>
            </div>
            
            <div class="card">
                <h2 class="card-title">تفاصيل الانحرافات</h2>
                <table>
                    <thead>
                        <tr>
                            <th>الفئة</th>
                            <th>الميزانية</th>
                            <th>المتحقق</th>
                            <th>الانحراف</th>
                            <th>نسبة الانحراف</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($variance_data)): ?>
                            <?php foreach ($variance_data as $item): ?>
                            <tr>
                                <td><?php echo $item['category']; ?></td>
                                <td><?php echo number_format($item['budgeted']); ?> د.ل</td>
                                <td><?php echo number_format($item['actual']); ?> د.ل</td>
                                <td class="<?php echo $item['variance'] >= 0 ? 'positive-variance' : 'negative-variance'; ?>">
                                    <?php echo number_format($item['variance']); ?> د.ل
                                </td>
                                <td>
                                    <?php
                                    $percentage = $item['budgeted'] > 0 ? ($item['variance'] / $item['budgeted']) * 100 : 0;
                                    $class = $percentage >= 0 ? 'positive-variance' : 'negative-variance';
                                    ?>
                                    <span class="<?php echo $class; ?>">
                                        <?php echo number_format(abs($percentage), 2); ?>%
                                    </span>
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
        
        <!-- التوقعات المالية -->
        <div id="forecasting" class="tab-content">
            <div class="content-grid">
                <div class="card">
                    <h2 class="card-title">التوقعات المالية للعام القادم</h2>
                    <div class="chart-container">
                        <canvas id="financialForecastChart"></canvas>
                    </div>
                </div>
                
                <div class="card">
                    <h2 class="card-title">افتراضات التوقع</h2>
                    <div class="form-group">
                        <label>نمو الإيرادات المتوقع (%)</label>
                        <input type="number" value="15" min="0" max="100" step="0.1">
                    </div>
                    
                    <div class="form-group">
                        <label>نمو المصروفات المتوقع (%)</label>
                        <input type="number" value="8" min="0" max="100" step="0.1">
                    </div>
                    
                    <div class="form-group">
                        <label>معدل التضخم المتوقع (%)</label>
                        <input type="number" value="3.5" min="0" max="100" step="0.1">
                    </div>
                    
                    <button>تحديث التوقعات</button>
                </div>
            </div>
            
            <div class="card">
                <h2 class="card-title">ملخص التوقعات</h2>
                <table>
                    <thead>
                        <tr>
                            <th>العام</th>
                            <th>الإيرادات المتوقعة</th>
                            <th>المصروفات المتوقعة</th>
                            <th>صافي الدخل المتوقع</th>
                            <th>نسبة النمو</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>2023</td>
                            <td>2,500,000 د.ل</td>
                            <td>1,800,000 د.ل</td>
                            <td>700,000 د.ل</td>
                            <td class="positive-variance">+10%</td>
                        </tr>
                        <tr>
                            <td>2024</td>
                            <td>2,875,000 د.ل</td>
                            <td>1,944,000 د.ل</td>
                            <td>931,000 د.ل</td>
                            <td class="positive-variance">+33%</td>
                        </tr>
                        <tr>
                            <td>2025</td>
                            <td>3,306,250 د.ل</td>
                            <td>2,099,520 د.ل</td>
                            <td>1,206,730 د.ل</td>
                            <td class="positive-variance">+30%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // تبديل التبويبات
        function switchTab(tabId) {
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            document.querySelector(`.tab:nth-child(${Array.from(document.querySelectorAll('.tab')).findIndex(t => t.textContent.includes(document.querySelector(`#${tabId} h2`).textContent.split(' ')[0])) + 1})`).classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }
        
        // مخطط الإيرادات مقابل المصروفات
        const revenueExpenseCtx = document.getElementById('revenueExpenseChart').getContext('2d');
        const revenueExpenseChart = new Chart(revenueExpenseCtx, {
            type: 'bar',
            data: {
                labels: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو'],
                datasets: [
                    {
                        label: 'الإيرادات',
                        data: [120000, 150000, 180000, 140000, 160000, 190000],
                        backgroundColor: '#2ecc71',
                    },
                    {
                        label: 'المصروفات',
                        data: [100000, 110000, 130000, 120000, 125000, 135000],
                        backgroundColor: '#f39c12',
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
                    }
                }
            }
        });
        
        // مخطط توزيع الميزانية
        const budgetDistributionCtx = document.getElementById('budgetDistributionChart').getContext('2d');
        const budgetDistributionChart = new Chart(budgetDistributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['مواد خام', 'مرتبات', 'تسويق', 'صيانة', 'أخرى'],
                datasets: [{
                    data: [40, 35, 15, 7, 3],
                    backgroundColor: [
                        '#3498db',
                        '#2ecc71',
                        '#f39c12',
                        '#e74c3c',
                        '#9b59b6'
                    ],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
        
        // مخطط تحليل الانحرافات
        const varianceAnalysisCtx = document.getElementById('varianceAnalysisChart').getContext('2d');
        const varianceAnalysisChart = new Chart(varianceAnalysisCtx, {
            type: 'bar',
            data: {
                labels: ['مواد خام', 'مرتبات', 'تسويق', 'صيانة', 'أخرى'],
                datasets: [{
                    label: 'الانحراف',
                    data: [15000, -8000, 5000, -3000, 2000],
                    backgroundColor: function(context) {
                        const value = context.dataset.data[context.dataIndex];
                        return value >= 0 ? '#2ecc71' : '#e74c3c';
                    },
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString() + ' د.ل';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        
        // مخطط التوقعات المالية
        const financialForecastCtx = document.getElementById('financialForecastChart').getContext('2d');
        const financialForecastChart = new Chart(financialForecastCtx, {
            type: 'line',
            data: {
                labels: ['2022', '2023', '2024', '2025'],
                datasets: [
                    {
                        label: 'الإيرادات',
                        data: [2000000, 2500000, 2875000, 3306250],
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'المصروفات',
                        data: [1700000, 1800000, 1944000, 2099520],
                        borderColor: '#f39c12',
                        backgroundColor: 'rgba(243, 156, 18, 0.1)',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
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
                    }
                }
            }
        });
    </script>
</body>
</html>