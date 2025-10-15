<?php



ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

define('INCLUDED', true);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// جلب بيانات المخزون
$inventory = getInventory($db);

// جلب فئات المنتجات
$categories = getCategories($db);

// جلب إحصائيات المخزون
$inventoryStats = getInventoryStats($db);

// معالجة حذف عنصر
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    deleteInventoryItem($db, $_GET['delete']);
    header("Location: inventory.php");
    exit();
}

// دالة جديدة لجلب إحصائيات المخزون
function getInventoryStats($db) {
    $stats = [];

    // إجمالي المنتجات
    $stmt = $db->prepare("SELECT COUNT(*) as total_products FROM inventory_items");
    $stmt->execute();
    $stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_products'] ?? 0;

    // إجمالي قيمة المخزون
    $stmt = $db->prepare("SELECT SUM(quantity * price) as total_value FROM inventory_items");
    $stmt->execute();
    $stats['total_value'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0;

    // المنتجات منخفضة المخزون
    $stmt = $db->prepare("SELECT COUNT(*) as low_stock FROM inventory_items WHERE quantity > 0 AND quantity < 5");
    $stmt->execute();
    $stats['low_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['low_stock'] ?? 0;

    // المنتجات التي نفذت من المخزون
    $stmt = $db->prepare("SELECT COUNT(*) as out_of_stock FROM inventory_items WHERE quantity = 0");
    $stmt->execute();
    $stats['out_of_stock'] = $stmt->fetch(PDO::FETCH_ASSOC)['out_of_stock'] ?? 0;

    // متوسط سعر المنتج
    $stmt = $db->prepare("SELECT AVG(price) as avg_price FROM inventory_items");
    $stmt->execute();
    $stats['avg_price'] = $stmt->fetch(PDO::FETCH_ASSOC)['avg_price'] ?? 0;

    // أعلى 5 منتجات سعراً
    $stmt = $db->prepare("SELECT name, price FROM inventory_items ORDER BY price DESC LIMIT 5");
    $stmt->execute();
    $stats['top_priced'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // المنتجات الأكثر مبيعًا
    $stmt = $db->prepare("
        SELECT ii.item_id, i.name, SUM(ii.quantity) as total_sold
        FROM invoice_items ii
        JOIN inventory_items i ON ii.item_id = i.item_id
        GROUP BY ii.item_id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $stmt->execute();
    $stats['top_selling'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // المنتجات الأكثر ربحية
    $stmt = $db->prepare("
        SELECT ii.item_id, i.name, SUM((i.selling_price - i.cost_price) * ii.quantity) as profit_margin
        FROM invoice_items ii
        JOIN inventory_items i ON ii.item_id = i.item_id
        GROUP BY ii.item_id
        ORDER BY profit_margin DESC
        LIMIT 5
    ");
    $stmt->execute();
    $stats['top_profitable'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // توزيع المخزون حسب الفئات
    $stmt = $db->prepare("
        SELECT c.name as category_name, COUNT(*) as product_count
        FROM inventory_items i
        LEFT JOIN product_categories c ON i.category_id = c.category_id
        GROUP BY c.category_id
    ");
    $stmt->execute();
    $stats['category_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $stats;
}




?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المخزون - نظام المطبعة</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --dark: #212529;
            --light: #f8f9fa;
            --gray: #6c757d;
        }
        
        .content-box {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .search-filter {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            align-items: center;
        }
        
        .search-box {
            flex: 1;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            outline: none;
        }
        
        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .filter-select {
            min-width: 200px;
        }
        
        .filter-select select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: white;
            font-size: 14px;
            cursor: pointer;
        }
        
        .add-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
        }
        
        .add-btn:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
            padding: 15px;
            text-align: right;
            position: sticky;
            top: 0;
        }
        
        .data-table td {
            padding: 15px;
            text-align: right;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr:hover {
            background-color: #f8fafd;
        }
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: rgba(29, 185, 84, 0.1);
            color: #1db954;
        }
        
        .badge-warning {
            background-color: rgba(248, 150, 30, 0.1);
            color: #f8961e;
        }
        
        .badge-danger {
            background-color: rgba(247, 37, 133, 0.1);
            color: #f72585;
        }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background-color: #f8f9fa;
            color: var(--gray);
            margin-left: 5px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .action-btn:hover {
            background-color: var(--primary);
            color: white;
            transform: scale(1.1);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 8px;
        }
        
        .pagination a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .pagination a.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination a:hover:not(.active) {
            background-color: #f0f0f0;
        }
        
        /* تأثيرات حركية */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .data-table tbody tr {
            animation: fadeIn 0.3s ease-out;
            animation-fill-mode: both;
        }
        
        .data-table tbody tr:nth-child(1) { animation-delay: 0.1s; }
        .data-table tbody tr:nth-child(2) { animation-delay: 0.2s; }
        .data-table tbody tr:nth-child(3) { animation-delay: 0.3s; }
        
        /* مودال إضافة/تعديل عنصر */
        .modal-content {
            border-radius: 12px;
        }
        
        /* إحصائيات المخزون */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--gray);
        }
        
        .stat-change {
            font-size: 12px;
            font-weight: 600;
        }
        
        .stat-change.positive {
            color: var(--success);
        }
        
        .stat-change.negative {
            color: var(--danger);
        }
        
        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .chart-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .chart-actions {
            display: flex;
            gap: 10px;
        }
        
        .chart-action-btn {
            background: #f8f9fa;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .chart-action-btn:hover {
            background: var(--primary);
            color: white;
        }
        
        .top-products {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .top-products-list {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        
        .product-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-rank {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-left: 10px;
            font-size: 14px;
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-name {
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .product-metric {
            font-size: 12px;
            color: var(--gray);
        }
        
        .product-value {
            font-weight: 600;
            color: var(--primary);
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        .tab-container {
            margin-bottom: 30px;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #f0f0f0;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: var(--gray);
            font-weight: 500;
            padding: 12px 20px;
            border-radius: 8px 8px 0 0;
            margin-bottom: -2px;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary);
            background-color: white;
            border-bottom: 2px solid var(--primary);
        }
        
        .tab-content {
            padding-top: 20px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--gray);
        }
        
        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- الشريط الجانبي -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- المحتوى الرئيسي -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">إدارة المخزون</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                            <i class="fas fa-plus me-1"></i> إضافة عنصر جديد
                        </button>
                    </div>
                </div>
                
                <!-- تبويبات الصفحة -->
                <div class="tab-container">
                    <ul class="nav nav-tabs" id="inventoryTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="stats-tab" data-bs-toggle="tab" data-bs-target="#stats" type="button" role="tab" aria-controls="stats" aria-selected="true">
                                <i class="fas fa-chart-bar me-1"></i> الإحصائيات المتقدمة
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory" type="button" role="tab" aria-controls="inventory" aria-selected="false">
                                <i class="fas fa-boxes me-1"></i> قائمة المخزون
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="inventoryTabsContent">
                        <!-- تبويب الإحصائيات -->
                        <div class="tab-pane fade show active" id="stats" role="tabpanel" aria-labelledby="stats-tab">
                            <!-- بطاقات الإحصائيات -->
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #4361ee, #3a0ca3);">
                                        <i class="fas fa-boxes"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value"><?= $inventoryStats['total_products'] ?></div>
                                        <div class="stat-label">إجمالي المنتجات</div>
                                        <div class="stat-change positive">+5% عن الشهر الماضي</div>
                                    </div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #4cc9f0, #4895ef);">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value"><?= number_format($inventoryStats['total_value'], 2) ?> د.ل</div>
                                        <div class="stat-label">إجمالي قيمة المخزون</div>
                                        <div class="stat-change positive">+12% عن الشهر الماضي</div>
                                    </div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #f8961e, #f3722c);">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value"><?= $inventoryStats['low_stock'] ?></div>
                                        <div class="stat-label">منتجات منخفضة المخزون</div>
                                        <div class="stat-change negative">+2 عن الأسبوع الماضي</div>
                                    </div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon" style="background: linear-gradient(135deg, #f72585, #b5179e);">
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value"><?= $inventoryStats['out_of_stock'] ?></div>
                                        <div class="stat-label">منتجات نفذت من المخزون</div>
                                        <div class="stat-change positive">-1 عن الأسبوع الماضي</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- المخططات البيانية -->
                            <div class="charts-container">
                                <div class="chart-box">
                                    <div class="chart-header">
                                        <div class="chart-title">توزيع المخزون حسب الفئات</div>
                                        <div class="chart-actions">
                                            <button class="chart-action-btn" onclick="toggleFullscreen('categoryChart')"><i class="fas fa-expand"></i></button>
                                            <button class="chart-action-btn" onclick="downloadChart('categoryChart')"><i class="fas fa-download"></i></button>
                                        </div>
                                    </div>
                                    <canvas id="categoryChart" height="250"></canvas>
                                </div>
                                
                                <div class="chart-box">
                                    <div class="chart-header">
                                        <div class="chart-title">حالة المخزون</div>
                                        <div class="chart-actions">
                                            <button class="chart-action-btn" onclick="toggleFullscreen('stockStatusChart')"><i class="fas fa-expand"></i></button>
                                            <button class="chart-action-btn" onclick="downloadChart('stockStatusChart')"><i class="fas fa-download"></i></button>
                                        </div>
                                    </div>
                                    <canvas id="stockStatusChart" height="250"></canvas>
                                </div>
                            </div>
                            
                            <!-- المنتجات الأكثر ربحية ومبيعاً -->
                            <div class="top-products">
                                <div class="top-products-list">
                                    <div class="section-title">
                                        <i class="fas fa-chart-line"></i>
                                        <span>المنتجات الأكثر ربحية</span>
                                    </div>
                                    
                                    <?php if(!empty($inventoryStats['top_profitable'])): ?>
                                        <?php foreach($inventoryStats['top_profitable'] as $index => $product): ?>
                                        <div class="product-item">
                                            <div class="product-rank" style="background: <?= $index < 3 ? 'linear-gradient(135deg, #ffd700, #ffa500)' : '#f8f9fa' ?>; color: <?= $index < 3 ? 'white' : 'inherit' ?>;">
                                                <?= $index + 1 ?>
                                            </div>
                                            <div class="product-info">
                                                <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                                                <div class="product-metric">هامش الربح</div>
                                            </div>
                                            <div class="product-value"><?= number_format($product['profit_margin'], 1) ?>%</div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-data">
                                            <i class="fas fa-chart-line"></i>
                                            <p>لا توجد بيانات عن المنتجات الأكثر ربحية</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="top-products-list">
                                    <div class="section-title">
                                        <i class="fas fa-fire"></i>
                                        <span>المنتجات الأكثر مبيعاً</span>
                                    </div>
                                    
                                    <?php if(!empty($inventoryStats['top_selling'])): ?>
                                        <?php foreach($inventoryStats['top_selling'] as $index => $product): ?>
                                        <div class="product-item">
                                            <div class="product-rank" style="background: <?= $index < 3 ? 'linear-gradient(135deg, #ff6b6b, #ee5a52)' : '#f8f9fa' ?>; color: <?= $index < 3 ? 'white' : 'inherit' ?>;">
                                                <?= $index + 1 ?>
                                            </div>
                                            <div class="product-info">
                                                <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                                                <div class="product-metric">المخزون المتبقي</div>
                                            </div>
                                            <div class="product-value"><?= $product['stock_level'] ?? 0 ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-data">
                                            <i class="fas fa-fire"></i>
                                            <p>لا توجد بيانات عن المنتجات الأكثر مبيعاً</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- تبويب قائمة المخزون -->
                        <div class="tab-pane fade" id="inventory" role="tabpanel" aria-labelledby="inventory-tab">
                            <div class="content-box">
                                <div class="search-filter">
                                    <div class="search-box">
                                        <input type="text" id="searchInput" placeholder="ابحث عن منتج...">
                                        <i class="fas fa-search"></i>
                                    </div>
                                    
                                    <div class="filter-select">
                                        <select id="categoryFilter">
                                            <option value="">جميع الفئات</option>
                                            <?php foreach($categories as $category): ?>
                                            <option value="<?= $category['category_id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="filter-select">
                                        <select id="stockFilter">
                                            <option value="">جميع الحالات</option>
                                            <option value="low">منخفض الكمية</option>
                                            <option value="normal">طبيعي</option>
                                            <option value="out">نفذ من المخزون</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>الصورة</th>
                                                <th>اسم المنتج</th>
                                                <th>الفئة</th>
                                                <th>الكمية المتاحة</th>
                                                <th>سعر الشراء</th>
                                                <th>سعر البيع</th>
                                                <th>حالة المخزون</th>
                                                <th>الإجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if(!empty($inventory)): ?>
                                                <?php foreach($inventory as $index => $item): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td>
                                                        <img src="images/products/<?= $item['image'] ?? 'default.png' ?>" 
                                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;" 
                                                             alt="<?= htmlspecialchars($item['name']) ?>">
                                                    </td>
                                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                                    <td data-category-id="<?= $item['category_id'] ?>"><?= htmlspecialchars($item['category_name'] ?? 'بدون فئة') ?></td>
                                                    <td><?= $item['current_quantity'] ?> <?= $item['unit'] ?></td>
                                                    <td><?= number_format($item['cost_price'], 2) ?> د.ل</td>
                                                    <td><?= number_format($item['selling_price'], 2) ?> د.ل</td>
                                                    <td>
                                                        <?php
                                                        if($item['current_quantity'] <= 0) {
                                                            echo '<span class="badge badge-danger">نفذ من المخزون</span>';
                                                        } elseif($item['current_quantity'] < $item['min_quantity']) {
                                                            echo '<span class="badge badge-warning">كمية منخفضة</span>';
                                                        } else {
                                                            echo '<span class="badge badge-success">متوفر</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <a href="edit_inventory.php?id=<?= $item['item_id'] ?>" class="action-btn" title="تعديل">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="#" onclick="confirmDelete(<?= $item['item_id'] ?>, '<?= htmlspecialchars($item['name']) ?>')" class="action-btn" title="حذف">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-4">
                                                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                                        <p class="text-muted">لا توجد منتجات في المخزون</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php if(!empty($inventory)): ?>
                                <div class="pagination">
                                    <a href="#" class="active">1</a>
                                    <a href="#">2</a>
                                    <a href="#">3</a>
                                    <a href="#">التالي <i class="fas fa-chevron-left"></i></a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- مودال إضافة عنصر جديد -->
    <div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addItemModalLabel">إضافة عنصر جديد للمخزون</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="inventoryForm" action="save_inventory.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">اسم المنتج</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="category_id" class="form-label">الفئة</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">بدون فئة</option>
                                    <?php foreach($categories as $category): ?>
                                    <option value="<?= $category['category_id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="current_quantity" class="form-label">الكمية الحالية</label>
                                <input type="number" step="0.01" class="form-control" id="current_quantity" name="current_quantity" required>
                            </div>
                            <div class="col-md-4">
                                <label for="min_quantity" class="form-label">الحد الأدنى</label>
                                <input type="number" step="0.01" class="form-control" id="min_quantity" name="min_quantity" required>
                            </div>
                            <div class="col-md-4">
                                <label for="unit" class="form-label">الوحدة</label>
                                <select class="form-select" id="unit" name="unit" required>
                                    <option value="piece">قطعة</option>
                                    <option value="pack">علبة</option>
                                    <option value="ream">رزمة</option>
                                    <option value="box">صندوق</option>
                                    <option value="kg">كيلوغرام</option>
                                    <option value="meter">متر</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="cost_price" class="form-label">سعر الشراء</label>
                                <input type="number" step="0.01" class="form-control" id="cost_price" name="cost_price" required>
                            </div>
                            <div class="col-md-6">
                                <label for="selling_price" class="form-label">سعر البيع</label>
                                <input type="number" step="0.01" class="form-control" id="selling_price" name="selling_price" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="barcode" class="form-label">باركود</label>
                                <input type="text" class="form-control" id="barcode" name="barcode">
                            </div>
                            <div class="col-md-6">
                                <label for="image" class="form-label">صورة المنتج</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">وصف المنتج</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ العنصر</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // تأكيد الحذف
        function confirmDelete(id, name) {
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: `ستقوم بحذف المنتج "${name}"`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#4361ee',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-primary me-2',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `inventory.php?delete=${id}`;
                }
            });
        }
        
        // البحث الفوري
        $(document).ready(function() {
            $('#searchInput').on('keyup', function() {
                const value = $(this).val().toLowerCase();
                $('.data-table tbody tr').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });
            
            // تصفية حسب الفئة
            $('#categoryFilter').change(function() {
                const value = $(this).val().toLowerCase();
                if (value === '') {
                    $('.data-table tbody tr').show();
                } else {
                    $('.data-table tbody tr').each(function() {
                        const categoryId = $(this).find('td:nth-child(4)').data('category-id');
                        $(this).toggle(categoryId == value);
                    });
                }
            });
            
            // تصفية حسب حالة المخزون
            $('#stockFilter').change(function() {
                const value = $(this).val().toLowerCase();
                if (value === '') {
                    $('.data-table tbody tr').show();
                } else if (value === 'low') {
                    $('.data-table tbody tr').each(function() {
                        const badgeClass = $(this).find('.badge').attr('class');
                        $(this).toggle(badgeClass.includes('badge-warning'));
                    });
                } else if (value === 'out') {
                    $('.data-table tbody tr').each(function() {
                        const badgeClass = $(this).find('.badge').attr('class');
                        $(this).toggle(badgeClass.includes('badge-danger'));
                    });
                } else if (value === 'normal') {
                    $('.data-table tbody tr').each(function() {
                        const badgeClass = $(this).find('.badge').attr('class');
                        $(this).toggle(badgeClass.includes('badge-success'));
                    });
                }
            });
            
            // إنشاء المخططات البيانية
            createCharts();
        });
        
        // دالة لإنشاء المخططات البيانية
        function createCharts() {
            // مخطط توزيع المخزون حسب الفئات
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            const categoryChart = new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: [
                        <?php foreach($inventoryStats['category_distribution'] as $category): ?>
                        '<?= $category['category_name'] ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        data: [
                            <?php foreach($inventoryStats['category_distribution'] as $category): ?>
                            <?= $category['product_count'] ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: [
                            '#4361ee', '#4cc9f0', '#f8961e', '#f72585', '#7209b7', 
                            '#3a0ca3', '#4895ef', '#560bad', '#b5179e', '#f15bb5'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            rtl: true
                        }
                    }
                }
            });
            
            // مخطط حالة المخزون
            const stockStatusCtx = document.getElementById('stockStatusChart').getContext('2d');
            const stockStatusChart = new Chart(stockStatusCtx, {
                type: 'bar',
                data: {
                    labels: ['متوفر', 'منخفض', 'نفذ'],
                    datasets: [{
                        label: 'عدد المنتجات',
                        data: [
                            <?= $inventoryStats['available_products'] ?>,
                            <?= $inventoryStats['low_stock'] ?>,
                            <?= $inventoryStats['out_of_stock'] ?>
                        ],
                        backgroundColor: [
                            'rgba(76, 201, 240, 0.7)',
                            'rgba(248, 150, 30, 0.7)',
                            'rgba(247, 37, 133, 0.7)'
                        ],
                        borderColor: [
                            '#4cc9f0',
                            '#f8961e',
                            '#f72585'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
        
        // وظائف للمخططات
        function toggleFullscreen(chartId) {
            const chart = document.getElementById(chartId);
            if (!document.fullscreenElement) {
                chart.requestFullscreen().catch(err => {
                    console.log(`Error attempting to enable full-screen mode: ${err.message}`);
                });
            } else {
                document.exitFullscreen();
            }
        }
        
        function downloadChart(chartId) {
            const chart = document.getElementById(chartId);
            const link = document.createElement('a');
            link.download = `${chartId}.png`;
            link.href = chart.toDataURL();
            link.click();
        }
    </script>
</body>
</html>