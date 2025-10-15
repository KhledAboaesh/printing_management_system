<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

define('INCLUDED', true);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// التحقق من الصلاحيات
$allowed_roles = ['admin', 'manager', 'accountant'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: dashboard.php?error=no_permission");
    exit();
}

// معالجة معايير التصفية
$filters = [
    'report_type' => $_GET['report_type'] ?? 'sales',
    'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
    'customer_id' => $_GET['customer_id'] ?? null,
    'product_id' => $_GET['product_id'] ?? null
];

// جلب البيانات حسب نوع التقرير
$report_data = [];
$report_title = '';
$chart_data = [];
$summary_stats = [];

try {
    switch ($filters['report_type']) {
        case 'sales':
            $report_title = '📊 تقرير المبيعات الشامل';
            
            // بيانات المبيعات اليومية
            $stmt = $db->prepare("
                SELECT DATE(i.issue_date) as date, 
                       SUM(i.total_amount) as total,
                       COUNT(i.invoice_id) as count,
                       AVG(i.total_amount) as average
                FROM invoices i
                WHERE i.issue_date BETWEEN ? AND ?
                GROUP BY DATE(i.issue_date)
                ORDER BY i.issue_date
            ");
            $stmt->execute([$filters['date_from'], $filters['date_to']]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // إحصائيات الملخص
            $stmt = $db->prepare("
                SELECT 
                    COUNT(i.invoice_id) as total_invoices,
                    SUM(i.total_amount) as total_revenue,
                    AVG(i.total_amount) as avg_invoice,
                    MAX(i.total_amount) as max_invoice,
                    MIN(i.total_amount) as min_invoice
                FROM invoices i
                WHERE i.issue_date BETWEEN ? AND ?
            ");
            $stmt->execute([$filters['date_from'], $filters['date_to']]);
            $summary_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // تحضير بيانات الرسم البياني
            foreach ($report_data as $row) {
                $chart_data['labels'][] = $row['date'];
                $chart_data['datasets'][0]['data'][] = $row['total'];
                $chart_data['datasets'][0]['label'] = 'إجمالي المبيعات';
            }
            break;
            
        case 'customers':
            $report_title = '👥 تقرير العملاء';
            $stmt = $db->prepare("
                SELECT c.customer_id, c.name, c.email, c.phone,
                       COUNT(i.invoice_id) as invoice_count,
                       SUM(i.total_amount) as total_spent,
                       MAX(i.issue_date) as last_purchase
                FROM customers c
                LEFT JOIN invoices i ON c.customer_id = i.customer_id
                WHERE (i.issue_date BETWEEN ? AND ? OR i.issue_date IS NULL)
                GROUP BY c.customer_id
                ORDER BY total_spent DESC
            ");
            $stmt->execute([$filters['date_from'], $filters['date_to']]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // إحصائيات العملاء
            $stmt = $db->prepare("
                SELECT 
                    COUNT(DISTINCT c.customer_id) as total_customers,
                    COUNT(i.invoice_id) as total_invoices,
                    AVG(i.total_amount) as avg_spent
                FROM customers c
                LEFT JOIN invoices i ON c.customer_id = i.customer_id
                WHERE i.issue_date BETWEEN ? AND ?
            ");
            $stmt->execute([$filters['date_from'], $filters['date_to']]);
            $summary_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        case 'products':
            $report_title = '📦 تقرير المنتجات';
            $stmt = $db->prepare("
                SELECT p.item_id, p.name, p.selling_price,
                       SUM(ii.quantity) as sold_quantity,
                       SUM(ii.quantity * ii.unit_price) as total_revenue,
                       AVG(ii.quantity) as avg_quantity
                FROM inventory p
                JOIN invoice_items ii ON p.item_id = ii.item_id
                JOIN invoices i ON ii.invoice_id = i.invoice_id
                WHERE i.issue_date BETWEEN ? AND ?
                GROUP BY p.item_id
                ORDER BY total_revenue DESC
            ");
            $stmt->execute([$filters['date_from'], $filters['date_to']]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // إحصائيات المنتجات
            $stmt = $db->prepare("
                SELECT 
                    COUNT(DISTINCT p.item_id) as total_products,
                    SUM(ii.quantity) as total_sold,
                    SUM(ii.quantity * ii.unit_price) as total_revenue,
                    AVG(ii.quantity) as avg_sold
                FROM inventory p
                JOIN invoice_items ii ON p.item_id = ii.item_id
                JOIN invoices i ON ii.invoice_id = i.invoice_id
                WHERE i.issue_date BETWEEN ? AND ?
            ");
            $stmt->execute([$filters['date_from'], $filters['date_to']]);
            $summary_stats = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        case 'performance':
            $report_title = '🚀 تقرير الأداء';
            
            // أداء الموظفين
            $stmt = $db->prepare("
                SELECT u.user_id, u.full_name, u.role,
                       COUNT(i.invoice_id) as invoices_created,
                       SUM(i.total_amount) as total_sales,
                       AVG(i.total_amount) as avg_sale
                FROM users u
                LEFT JOIN invoices i ON u.user_id = i.created_by
                WHERE i.issue_date BETWEEN ? AND ?
                GROUP BY u.user_id
                ORDER BY total_sales DESC
            ");
            $stmt->execute([$filters['date_from'], $filters['date_to']]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch (PDOException $e) {
    error_log("Error generating report: " . $e->getMessage());
    $error = "حدث خطأ أثناء توليد التقرير";
}

// جلب قائمة العملاء للفلتر
$customers = [];
try {
    $stmt = $db->query("SELECT customer_id, name FROM customers ORDER BY name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching customers: " . $e->getMessage());
}

// جلب قائمة المنتجات للفلتر
$products = [];
try {
    $stmt = $db->query("SELECT item_id, name FROM inventory ORDER BY name");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير المتقدمة | نظام إدارة المطبعة</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
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
            --dark-blue: #1e2a4a;
            --light-blue: #f5f7fb;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #f5f7fb 0%, #e4e8f0 100%);
            color: var(--dark);
            min-height: 100vh;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* الشريط الجانبي */
        .sidebar {
            width: 280px;
            background: var(--dark-blue);
            color: white;
            padding: 20px 0;
            transition: all 0.3s ease;
        }
        
        .sidebar-header {
            padding: 0 25px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 25px;
        }
        
        .sidebar-header img {
            width: 100%;
            max-width: 180px;
            display: block;
            margin: 0 auto;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 8px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-right: 3px solid transparent;
        }
        
        .sidebar-menu a:hover, 
        .sidebar-menu a.active {
            background: rgba(67, 97, 238, 0.1);
            color: white;
            border-right-color: var(--primary);
        }
        
        .sidebar-menu i {
            margin-left: 12px;
            font-size: 18px;
            width: 22px;
            text-align: center;
        }
        
        /* المحتوى الرئيسي */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 2px solid rgba(67, 97, 238, 0.1);
        }
        
        .header h1 {
            color: var(--dark-blue);
            font-size: 32px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
        }
        
        /* تحسينات التصميم العامة */
        .reports-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .report-filters {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 35px;
            border: 1px solid rgba(67, 97, 238, 0.1);
            backdrop-filter: blur(10px);
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .filter-group {
            position: relative;
        }
        
        .filter-label {
            display: block;
            margin-bottom: 12px;
            font-weight: 600;
            color: var(--dark-blue);
            font-size: 15px;
        }
        
        .filter-select, 
        .filter-input {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e8ecf4;
            border-radius: 12px;
            font-size: 15px;
            background: white;
            transition: all 0.3s ease;
            font-family: 'Tajawal', sans-serif;
        }
        
        .filter-select:focus, 
        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .filter-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
        }
        
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(67, 97, 238, 0.3);
        }
        
        .report-results {
            background: white;
            border-radius: 16px;
            padding: 35px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 35px;
            border: 1px solid rgba(67, 97, 238, 0.1);
        }
        
        .report-title {
            color: var(--dark-blue);
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(67, 97, 238, 0.1);
            font-size: 26px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* إحصائيات الملخص */
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 15px;
            opacity: 0.9;
        }
        
        .chart-container {
            height: 450px;
            margin-bottom: 40px;
            position: relative;
        }
        
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 35px;
        }
        
        @media (max-width: 1024px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .report-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 25px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .report-table th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 18px 20px;
            text-align: right;
            font-weight: 600;
            font-size: 15px;
            border: none;
        }
        
        .report-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #f0f4f8;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }
        
        .report-table tr:last-child td {
            border-bottom: none;
        }
        
        .report-table tr:hover td {
            background-color: #f8faff;
        }
        
        .table-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid rgba(67, 97, 238, 0.1);
        }
        
        .export-actions {
            display: flex;
            gap: 15px;
        }
        
        .export-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .export-pdf {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
        }
        
        .export-excel {
            background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
            color: white;
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .no-data {
            text-align: center;
            padding: 60px 30px;
            color: var(--gray);
        }
        
        .no-data i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .no-data h3 {
            font-size: 22px;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .no-data p {
            font-size: 16px;
            opacity: 0.8;
        }
        
        /* تأثيرات للجداول */
        .table-row-animation {
            animation: slideInRow 0.6s ease-out;
        }
        
        @keyframes slideInRow {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* تخصيص شريط التمرير */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary);
        }
        
        /* التجاوب مع الشاشات */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .header-actions {
                justify-content: center;
            }
            
            .summary-stats {
                grid-template-columns: 1fr;
            }
            
            .export-actions {
                flex-direction: column;
            }
        }
        
        /* تأثيرات خاصة للتقارير */
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        .glow-effect {
            box-shadow: 0 0 20px rgba(67, 97, 238, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="reports-container">
                <div class="header">
                    <h1><i class="fas fa-chart-network"></i> لوحة التقارير المتقدمة</h1>
                    <div class="header-actions">
                        <button class="filter-btn" onclick="window.print()">
                            <i class="fas fa-print"></i> طباعة التقرير
                        </button>
                    </div>
                </div>
                
                <form method="get" class="report-filters">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label class="filter-label">📊 نوع التقرير</label>
                            <select name="report_type" class="filter-select">
                                <option value="sales" <?= $filters['report_type'] == 'sales' ? 'selected' : '' ?>>📈 تقرير المبيعات</option>
                                <option value="customers" <?= $filters['report_type'] == 'customers' ? 'selected' : '' ?>>👥 تقرير العملاء</option>
                                <option value="products" <?= $filters['report_type'] == 'products' ? 'selected' : '' ?>>📦 تقرير المنتجات</option>
                                <option value="performance" <?= $filters['report_type'] == 'performance' ? 'selected' : '' ?>>🚀 تقرير الأداء</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">📅 من تاريخ</label>
                            <input type="date" name="date_from" class="filter-input" 
                                   value="<?= $filters['date_from'] ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">📅 إلى تاريخ</label>
                            <input type="date" name="date_to" class="filter-input" 
                                   value="<?= $filters['date_to'] ?>">
                        </div>
                        
                        <div class="filter-group" style="align-self: flex-end;">
                            <button type="submit" class="filter-btn pulse-animation">
                                <i class="fas fa-filter"></i> تطبيق الفلتر
                            </button>
                        </div>
                    </div>
                    
                    <?php if (in_array($filters['report_type'], ['sales', 'customers', 'products'])): ?>
                    <div class="filter-row">
                        <?php if ($filters['report_type'] == 'sales'): ?>
                        <div class="filter-group">
                            <label class="filter-label">👤 العميل</label>
                            <select name="customer_id" class="filter-select">
                                <option value="">جميع العملاء</option>
                                <?php foreach ($customers as $customer): ?>
                                <option value="<?= $customer['customer_id'] ?>" 
                                    <?= $filters['customer_id'] == $customer['customer_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($customer['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($filters['report_type'] == 'products'): ?>
                        <div class="filter-group">
                            <label class="filter-label">📦 المنتج</label>
                            <select name="product_id" class="filter-select">
                                <option value="">جميع المنتجات</option>
                                <?php foreach ($products as $product): ?>
                                <option value="<?= $product['item_id'] ?>" 
                                    <?= $filters['product_id'] == $product['item_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($product['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </form>
                
                <div class="report-results">
                    <h2 class="report-title">
                        <?= $report_title ?>
                        <small style="font-size: 16px; color: var(--gray);">
                            (📅 من <?= date('d/m/Y', strtotime($filters['date_from'])) ?> إلى <?= date('d/m/Y', strtotime($filters['date_to'])) ?>)
                        </small>
                    </h2>
                    
                    <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%); color: white; padding: 20px; border-radius: 12px; margin-bottom: 25px;">
                        <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (empty($report_data)): ?>
                    <div class="no-data">
                        <i class="fas fa-chart-pie"></i>
                        <h3>لا توجد بيانات متاحة</h3>
                        <p>لم يتم العثور على بيانات مطابقة لمعايير البحث المحددة</p>
                    </div>
                    <?php else: ?>
                        <!-- إحصائيات الملخص -->
                        <?php if (!empty($summary_stats)): ?>
                        <div class="summary-stats">
                            <?php foreach ($summary_stats as $key => $value): ?>
                            <div class="stat-card">
                                <div class="stat-value"><?= is_numeric($value) ? number_format($value, 2) : $value ?></div>
                                <div class="stat-label"><?= ucfirst(str_replace('_', ' ', $key)) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- الرسوم البيانية -->
                        <?php if ($filters['report_type'] == 'sales' && !empty($chart_data)): ?>
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                        <?php endif; ?>
                        
                        <!-- الجداول -->
                        <div class="table-responsive">
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <?php if ($filters['report_type'] == 'sales'): ?>
                                        <th>📅 التاريخ</th>
                                        <th>📋 عدد الفواتير</th>
                                        <th>💰 إجمالي المبيعات</th>
                                        <th>📊 المتوسط</th>
                                        <?php elseif ($filters['report_type'] == 'customers'): ?>
                                        <th>👤 العميل</th>
                                        <th>📞 الهاتف</th>
                                        <th>📧 البريد</th>
                                        <th>📋 عدد الفواتير</th>
                                        <th>💰 إجمالي المشتريات</th>
                                        <th>📅 آخر شراء</th>
                                        <?php elseif ($filters['report_type'] == 'products'): ?>
                                        <th>📦 المنتج</th>
                                        <th>💰 السعر</th>
                                        <th>📊 الكمية المباعة</th>
                                        <th>💰 إجمالي الإيرادات</th>
                                        <th>📈 المتوسط</th>
                                        <?php elseif ($filters['report_type'] == 'performance'): ?>
                                        <th>👤 الموظف</th>
                                        <th>🎯 الدور</th>
                                        <th>📋 الفواتير</th>
                                        <th>💰 إجمالي المبيعات</th>
                                        <th>📊 متوسط الفاتورة</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $index => $row): ?>
                                    <tr class="table-row-animation" style="animation-delay: <?= $index * 0.1 ?>s">
                                        <?php if ($filters['report_type'] == 'sales'): ?>
                                        <td><?= $row['date'] ?></td>
                                        <td><?= $row['count'] ?></td>
                                        <td><?= number_format($row['total'], 2) ?> د.ل</td>
                                        <td><?= number_format($row['average'], 2) ?> د.ل</td>
                                        <?php elseif ($filters['report_type'] == 'customers'): ?>
                                        <td>
                                            <a href="view_customer.php?id=<?= $row['customer_id'] ?>" style="color: var(--primary); text-decoration: none;">
                                                <?= htmlspecialchars($row['name']) ?>
                                            </a>
                                        </td>
                                        <td><?= $row['phone'] ?? 'N/A' ?></td>
                                        <td><?= $row['email'] ?? 'N/A' ?></td>
                                        <td><?= $row['invoice_count'] ?></td>
                                        <td><?= number_format($row['total_spent'] ?? 0, 2) ?> د.ل</td>
                                        <td><?= $row['last_purchase'] ?? 'N/A' ?></td>
                                        <?php elseif ($filters['report_type'] == 'products'): ?>
                                        <td>
                                            <a href="view_product.php?id=<?= $row['item_id'] ?>" style="color: var(--primary); text-decoration: none;">
                                                <?= htmlspecialchars($row['name']) ?>
                                            </a>
                                        </td>
                                        <td><?= number_format($row['selling_price'], 2) ?> د.ل</td>
                                        <td><?= $row['sold_quantity'] ?></td>
                                        <td><?= number_format($row['total_revenue'], 2) ?> د.ل</td>
                                        <td><?= number_format($row['avg_quantity'], 1) ?></td>
                                        <?php elseif ($filters['report_type'] == 'performance'): ?>
                                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                                        <td><?= $row['role'] ?></td>
                                        <td><?= $row['invoices_created'] ?></td>
                                        <td><?= number_format($row['total_sales'], 2) ?> د.ل</td>
                                        <td><?= number_format($row['avg_sale'], 2) ?> د.ل</td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="table-actions">
                            <div class="export-actions">
                                <a href="export_report.php?type=pdf&<?= http_build_query($filters) ?>" 
                                   class="export-btn export-pdf">
                                    <i class="fas fa-file-pdf"></i> تصدير PDF
                                </a>
                                <a href="export_report.php?type=excel&<?= http_build_query($filters) ?>" 
                                   class="export-btn export-excel">
                                    <i class="fas fa-file-excel"></i> تصدير Excel
                                </a>
                            </div>
                            
                            <div class="total-summary">
                                <strong>المجموع: <?= count($report_data) ?> سجل</strong>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // تهيئة منتقي التواريخ
        flatpickr("input[type=date]", {
            dateFormat: "Y-m-d",
            locale: "ar",
            mode: "range",
            allowInput: true
        });
        
        <?php if ($filters['report_type'] == 'sales' && !empty($chart_data)): ?>
        // رسم الرسم البياني للمبيعات
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_data['labels'] ?? []) ?>,
                datasets: [{
                    label: 'إجمالي المبيعات',
                    data: <?= json_encode($chart_data['datasets'][0]['data'] ?? []) ?>,
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    borderColor: 'rgba(67, 97, 238, 1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: 'rgba(67, 97, 238, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                family: 'Tajawal',
                                size: 14
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            family: 'Tajawal',
                            size: 14
                        },
                        bodyFont: {
                            family: 'Tajawal',
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' د.ل';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            font: {
                                family: 'Tajawal',
                                size: 12
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString() + ' د.ل';
                            },
                            font: {
                                family: 'Tajawal',
                                size: 12
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                animations: {
                    tension: {
                        duration: 1000,
                        easing: 'linear'
                    }
                }
            }
        });
        <?php endif; ?>

        // تأثيرات تفاعلية للجدول
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('.report-table tr');
            tableRows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.1}s`;
            });
            
            // تأثيرات عند التمرير
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('glow-effect');
                    } else {
                        entry.target.classList.remove('glow-effect');
                    }
                });
            }, { threshold: 0.1 });
            tableRows.forEach(row => observer.observe(row));
        });
    </script>
</body> 
</html>
