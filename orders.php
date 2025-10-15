<?php
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

// جلب بيانات الطلبات والعملاء والمنتجات
$orders = getOrders($db);
$customers = getCustomers($db);
$products = getProducts($db);

// جلب المستخدمين (المصممين والأقسام الأخرى)
$designers = getUsersByRole($db, 'designer');
$all_users = getAllActiveUsers($db);

// جلب الإحصائيات (تجنب إعادة تعريف الدالة إذا كانت موجودة)
if (!function_exists('getOrderStatistics')) {
    function getOrderStatistics($db) {
        $stmt = $db->query("
            SELECT 
                COUNT(*) AS total_orders,
                SUM(CASE WHEN status IN ('completed','delivered') THEN 1 ELSE 0 END) AS completed_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
                SUM(total_amount) AS total_revenue
            FROM orders
        ");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        // معالجة القيم الافتراضية إذا كانت فارغة
        $total_orders = $data['total_orders'] ?? 0;
        $completed_orders = $data['completed_orders'] ?? 0;
        $pending_orders = $data['pending_orders'] ?? 0;
        $total_revenue = $data['total_revenue'] ?? 0;

        // حساب المعدلات
        $completion_rate = $total_orders > 0 ? round($completed_orders / $total_orders * 100, 2) : 0;
        $pending_rate = $total_orders > 0 ? round($pending_orders / $total_orders * 100, 2) : 0;

        // يمكنك تعديل نمو الإيرادات والنمو العام حسب بيانات الشهر السابق
        $orders_growth = 0; // مثال افتراضي
        $revenue_growth = 0; // مثال افتراضي

        return [
            'total_orders' => $total_orders,
            'completed_orders' => $completed_orders,
            'pending_orders' => $pending_orders,
            'total_revenue' => $total_revenue,
            'completion_rate' => $completion_rate,
            'pending_rate' => $pending_rate,
            'orders_growth' => $orders_growth,
            'revenue_growth' => $revenue_growth
        ];
    }
}

// جلب الإحصائيات
$stats = getOrderStatistics($db);

// معالجة تغيير حالة الطلب إذا تم إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $new_status = $_POST['status'];
    $rejection_reason = $_POST['rejection_reason'] ?? null;
    $order_id = $_POST['order_id'] ?? null;

    if ($order_id) {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("UPDATE orders SET 
                                status = ?,
                                rejection_reason = ?,
                                approved_by = ?,
                                approved_at = CASE WHEN ? = 'approved' THEN NOW() ELSE NULL END
                                WHERE order_id = ?");

            $stmt->execute([
                $new_status,
                $new_status === 'rejected' ? $rejection_reason : null,
                $_SESSION['user_id'],
                $new_status,
                $order_id
            ]);

            $db->commit();

            $_SESSION['success_message'] = "تم تحديث حالة الطلب بنجاح";
            header("Location: view_order.php?id=$order_id");
            exit();

        } catch (PDOException $e) {
            $db->rollBack();
            $_SESSION['error_message'] = "حدث خطأ أثناء تحديث الحالة: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "رقم الطلب غير موجود.";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الطلبات - نظام المطبعة</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
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
        
        .badge-pending {
            background-color: rgba(248, 150, 30, 0.1);
            color: #f8961e;
        }
        
        .badge-design {
            background-color: rgba(114, 9, 183, 0.1);
            color: #7209b7;
        }
        
        .badge-production {
            background-color: rgba(72, 149, 239, 0.1);
            color: #4895ef;
        }
        
        .badge-ready {
            background-color: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
        }
        
        .badge-delivered {
            background-color: rgba(29, 185, 84, 0.1);
            color: #1db954;
        }
        
        .badge-cancelled {
            background-color: rgba(247, 37, 133, 0.1);
            color: #f72585;
        }
        
        .priority-high {
            color: #f72585;
            font-weight: bold;
        }
        
        .priority-medium {
            color: #f8961e;
        }
        
        .priority-low {
            color: #4cc9f0;
        }
        
        .priority-urgent {
            color: #f72585;
            font-weight: bold;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
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
        
        /* مودال إضافة طلب */
        .order-item-row {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .order-item-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        /* لوحة الإحصائيات */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
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
            margin-left: 15px;
            font-size: 24px;
            color: white;
        }
        
        .stat-info {
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
            margin-top: 5px;
        }
        
        .stat-change.positive {
            color: #28a745;
        }
        
        .stat-change.negative {
            color: #dc3545;
        }
        
        /* مخططات */
        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .chart-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .chart-placeholder {
            height: 250px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            font-size: 14px;
        }
        
        /* ألوان البادجات حسب التصميم المطلوب */
        .badge-approved {
            background-color: rgba(67, 97, 238, 0.1);
            color: #4361ee;
        }
        
        .badge-completed {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .badge-rejected {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        /* ألوان الأولوية */
        .priority-low {
            color: #4cc9f0;
            font-weight: 500;
        }
        
        .priority-medium {
            color: #f8961e;
            font-weight: 500;
        }
        
        .priority-high {
            color: #f72585;
            font-weight: 600;
        }
        
        .priority-urgent {
            color: #dc3545;
            font-weight: 700;
            animation: pulse 1.5s infinite;
        }
        
        /* تحسينات مجموعة الأزرار */
        .actions-group {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
        }
        
        /* تحسينات للعرض على الشاشات الصغيرة */
        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .charts-container {
                grid-template-columns: 1fr;
            }
            
            .search-filter {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-select {
                min-width: auto;
            }
        }
        
        /* نتائج البحث */
        .search-results {
            position: absolute;
            background: #fff;
            border: 1px solid #ddd;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            z-index: 999;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .search-result-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .search-result-item:hover {
            background-color: #f1f1f1;
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        /* تحسينات لمودال إضافة الطلب */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
            max-height: 150px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 5px;
        }
        
        .payment-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .send-options {
            margin-top: 15px;
        }
        
        .form-check {
            margin-bottom: 8px;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .items-table th, .items-table td {
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
        }
        
        .items-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .add-item-btn {
            background-color: #f8f9fa;
            border: 1px dashed #ddd;
            padding: 8px 15px;
            border-radius: 5px;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .add-item-btn:hover {
            background-color: #e9ecef;
            color: #495057;
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
                    <h1 class="h2">لوحة تحكم الطلبات</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOrderModal">
                            <i class="fas fa-plus me-1"></i> طلب جديد
                        </button>
                    </div>
                </div>
                
                <!-- لوحة الإحصائيات -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #4361ee;">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= $stats['total_orders'] ?></div>
                            <div class="stat-label">إجمالي الطلبات</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i> <?= $stats['orders_growth'] ?>% عن الشهر الماضي
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #28a745;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= $stats['completed_orders'] ?></div>
                            <div class="stat-label">طلبات مكتملة</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i> <?= $stats['completion_rate'] ?>% معدل الإنجاز
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #f8961e;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= $stats['pending_orders'] ?></div>
                            <div class="stat-label">طلبات معلقة</div>
                            <div class="stat-change negative">
                                <i class="fas fa-arrow-down"></i> <?= $stats['pending_rate'] ?>% من الإجمالي
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #f72585;">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($stats['total_revenue'], 2) ?> د.ل</div>
                            <div class="stat-label">إجمالي الإيرادات</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i> <?= $stats['revenue_growth'] ?>% عن الشهر الماضي
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- المخططات -->
                <div class="charts-container">
                    <div class="chart-card">
                        <div class="chart-title">توزيع الطلبات حسب الحالة</div>
                        <div class="chart-placeholder">
                            <i class="fas fa-chart-pie me-2"></i> مخطط دائري لتوزيع الطلبات حسب الحالة
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-title">الإيرادات الشهرية</div>
                        <div class="chart-placeholder">
                            <i class="fas fa-chart-line me-2"></i> مخطط خطي للإيرادات الشهرية
                        </div>
                    </div>
                </div>
                
                <!-- قائمة الطلبات -->
                <div class="content-box">
                    <h3 class="mb-4">إدارة الطلبات</h3>
                    
                    <div class="search-filter">
                        <div class="search-box">
                            <input type="text" id="searchInput" placeholder="ابحث عن طلب أو عميل...">
                            <i class="fas fa-search"></i>
                        </div>
                        
                        <div class="filter-select">
                            <select id="statusFilter">
                                <option value="">جميع الحالات</option>
                                <option value="pending">معلق</option>
                                <option value="design">تصميم</option>
                                <option value="production">إنتاج</option>
                                <option value="ready">جاهز</option>
                                <option value="delivered">تم التسليم</option>
                                <option value="cancelled">ملغى</option>
                            </select>
                        </div>
                        
                        <div class="filter-select">
                            <select id="priorityFilter">
                                <option value="">جميع الأولويات</option>
                                <option value="low">منخفض</option>
                                <option value="medium">متوسط</option>
                                <option value="high">عالي</option>
                                <option value="urgent">عاجل</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>العميل</th>
                                    <th>تاريخ الطلب</th>
                                    <th>تاريخ التسليم</th>
                                    <th>الحالة</th>
                                    <th>الأولوية</th>
                                    <th>الإجمالي</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($orders as $order): 
                                    // تعريف تسميات وألوان الحالات
                                    $statusConfig = [
                                        'pending' => ['label' => 'معلق', 'class' => 'badge-pending'],
                                        'approved' => ['label' => 'تمت الموافقة', 'class' => 'badge-approved'],
                                        'design' => ['label' => 'تصميم', 'class' => 'badge-design'],
                                        'production' => ['label' => 'إنتاج', 'class' => 'badge-production'],
                                        'ready' => ['label' => 'جاهز', 'class' => 'badge-ready'],
                                        'delivered' => ['label' => 'تم التسليم', 'class' => 'badge-delivered'],
                                        'completed' => ['label' => 'مكتمل', 'class' => 'badge-completed'],
                                        'cancelled' => ['label' => 'ملغى', 'class' => 'badge-cancelled'],
                                        'rejected' => ['label' => 'مرفوض', 'class' => 'badge-rejected']
                                    ];
                                    
                                    // تعريف تسميات وألوان الأولوية
                                    $priorityConfig = [
                                        'low' => ['label' => 'منخفض', 'class' => 'priority-low'],
                                        'medium' => ['label' => 'متوسط', 'class' => 'priority-medium'],
                                        'high' => ['label' => 'عالي', 'class' => 'priority-high'],
                                        'urgent' => ['label' => 'عاجل', 'class' => 'priority-urgent']
                                    ];
                                ?>
                                <tr>
                                    <td>ORD-<?= str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                    <td><?= date('Y-m-d', strtotime($order['order_date'])) ?></td>
                                    <td><?= !empty($order['required_date']) ? date('Y-m-d', strtotime($order['required_date'])) : '---' ?></td>
                                    <td>
                                        <span class="badge <?= $statusConfig[$order['status']]['class'] ?? 'badge-secondary' ?>">
                                            <?= $statusConfig[$order['status']]['label'] ?? $order['status'] ?>
                                        </span>
                                    </td>
                                    <td class="<?= $priorityConfig[$order['priority']]['class'] ?? '' ?>">
                                        <?= $priorityConfig[$order['priority']]['label'] ?? $order['priority'] ?>
                                    </td>
                                    <td><?= number_format($order['total_amount'], 2) ?> د.ل</td>
                                    <td>
                                        <div class="actions-group">
                                            <a href="view_order.php?id=<?= $order['order_id'] ?>" class="action-btn" title="عرض">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_order.php?id=<?= $order['order_id'] ?>" class="action-btn" title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" onclick="confirmDelete(<?= $order['order_id'] ?>)" class="action-btn" title="حذف">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <a href="print_order.php?id=<?= $order['order_id'] ?>" class="action-btn" title="طباعة" target="_blank">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="pagination">
                        <a href="#" class="active">1</a>
                        <a href="#">2</a>
                        <a href="#">3</a>
                        <a href="#">التالي <i class="fas fa-chevron-left"></i></a>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
   <!-- مودال إنشاء طلب جديد مشابه للفاتورة -->
<div class="modal fade" id="addOrderModal" tabindex="-1" aria-labelledby="addOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addOrderModalLabel">إنشاء طلب جديد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="orderForm" action="save_order.php" method="POST">
                <div class="modal-body">

                    <!-- البحث الذكي عن العميل -->
                    <div class="form-group" style="position: relative;">
                        <label for="customer_search" class="form-label">العميل <span class="text-danger">*</span></label>
                        <input type="text" id="customer_search" class="form-control" placeholder="اكتب الاسم، الهاتف أو البريد..." autocomplete="off" required>
                        <input type="hidden" id="customer_id" name="customer_id">
                        <div id="customer_results" class="search-results"></div>
                    </div>

                    <!-- تواريخ الطلب -->
                    <div class="date-inputs row">
                        <div class="col-md-6">
                            <label for="order_date" class="form-label">تاريخ الطلب</label>
                            <input type="date" id="order_date" name="order_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="required_date" class="form-label">تاريخ التسليم المطلوب</label>
                            <input type="date" id="required_date" name="required_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                        </div>
                    </div>

                    <!-- عناصر الطلب -->
                    <div class="form-group mt-3">
                        <label class="form-label">عناصر الطلب <span class="text-danger">*</span></label>
                        <table class="items-table" id="items-table">
                            <thead>
                                <tr>
                                    <th width="40%">المنتج/الخدمة</th>
                                    <th width="20%">الكمية</th>
                                    <th width="20%">السعر</th>
                                    <th width="20%">المجموع</th>
                                    <th width="10%"></th>
                                </tr>
                            </thead>
                            <tbody id="items-tbody">
                                <!-- العناصر ستضاف هنا عبر JavaScript -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" style="text-align: left;"><strong>الإجمالي:</strong></td>
                                    <td id="total-amount">0.00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        <button type="button" id="add-item-btn" class="add-item-btn">
                            <i class="fas fa-plus"></i> إضافة عنصر
                        </button>
                    </div>

                    <!-- ملاحظات وتعليمات -->
                    <div class="form-group mt-3">
                        <label for="notes" class="form-label">ملاحظات</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2"></textarea>
                    </div>

                    <!-- بيانات الدفع -->
                    <div class="form-group mt-3">
                        <label class="form-label">بيانات الدفع</label>
                        <div class="payment-section" style="background: #f8f9fa; padding: 15px; border-radius: 10px;">
                            <div class="form-group">
                                <label for="payment_method" class="form-label">طريقة الدفع</label>
                                <select id="payment_method" name="payment_method" class="form-control">
                                    <option value="cash">نقداً</option>
                                    <option value="bank_transfer">تحويل بنكي</option>
                                    <option value="credit_card">بطاقة ائتمان</option>
                                    <option value="check">شيك</option>
                                </select>
                            </div>

                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <label for="deposit_amount" class="form-label">العربون (د.ل)</label>
                                    <input type="number" id="deposit_amount" name="deposit_amount" class="form-control" min="0" step="0.01" value="0">
                                </div>
                                <div class="col-md-6">
                                    <label for="amount_paid" class="form-label">المبلغ المدفوع (د.ل)</label>
                                    <input type="number" id="amount_paid" name="amount_paid" class="form-control" min="0" step="0.01" value="0">
                                </div>
                            </div>

                            <div id="payment-summary" class="mt-3" style="background: #fff; padding: 10px; border-radius: 5px;">
                                <p><strong>الإجمالي:</strong> <span id="total-display">0.00</span> د.ل</p>
                                <p><strong>المبلغ المتبقي:</strong> <span id="remaining-amount">0.00</span> د.ل</p>
                                <p><strong>حالة الدفع:</strong> <span id="payment-status">لم يتم الدفع</span></p>
                            </div>
                        </div>
                    </div>

                    <!-- إرسال الطلب إلى المصممين والأقسام -->
                    <div class="form-group mt-3">
                        <label class="form-label">إرسال الطلب إلى:</label>
                        <div class="send-options">

                            <!-- المصممين -->
                            <label class="form-label">المصممون:</label>
                            <div class="users-grid">
                                <?php foreach ($designers as $designer): ?>
                                    <div class="form-check">
                                        <input type="checkbox" name="send_to_designers[]" value="<?= $designer['user_id'] ?>" class="form-check-input" id="designer_<?= $designer['user_id'] ?>">
                                        <label for="designer_<?= $designer['user_id'] ?>" class="form-check-label"><?= htmlspecialchars($designer['full_name']) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- أقسام أخرى -->
                            <label class="form-label mt-3">أقسام أخرى:</label>
                            <div class="users-grid">
                                <div class="form-check">
                                    <input type="checkbox" name="send_to_sections[]" value="production" class="form-check-input" id="section_production">
                                    <label for="section_production" class="form-check-label">الورشة/الإنتاج</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="send_to_sections[]" value="quality" class="form-check-input" id="section_quality">
                                    <label for="section_quality" class="form-check-label">مراقبة الجودة</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="send_to_sections[]" value="delivery" class="form-check-input" id="section_delivery">
                                    <label for="section_delivery" class="form-check-label">التسليم</label>
                                </div>
                            </div>

                            <!-- خيار إرسال إلى العميل -->
                            <div class="form-check mt-3">
                                <input type="checkbox" name="send_to_customer" value="1" class="form-check-input" id="send_to_customer">
                                <label for="send_to_customer" class="form-check-label">إرسال إلى العميل</label>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ الطلب وإرساله</button>
                </div>
            </form>
        </div>
    </div>
</div>


    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   <script>
$(document).ready(function() {

    // تأكيد الحذف
    function confirmDelete(id) {
        Swal.fire({
            title: 'هل أنت متأكد؟',
            text: 'سيتم حذف هذا الطلب نهائياً',
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
                window.location.href = `delete_order.php?id=${id}`;
            }
        });
    }

    // ================= البحث الذكي عن العملاء =================
    let customerTimeout = null;
    $('#customer_search').on('input', function() {
        const term = $(this).val().trim();
        $('#customer_id').val('');
        if (term.length < 1) {
            $('#customer_results').html('');
            return;
        }
        clearTimeout(customerTimeout);
        customerTimeout = setTimeout(() => {
            $.getJSON('search_customers.php', { term: term }, function(data) {
                $('#customer_results').html('');
                if (data.length > 0) {
                    data.forEach(function(customer) {
                        const div = $('<div>').addClass('search-result-item')
                            .text(`${customer.name} - ${customer.phone} - ${customer.email}`)
                            .data('id', customer.customer_id)
                            .click(function() {
                                $('#customer_search').val(customer.name);
                                $('#customer_id').val(customer.customer_id);
                                $('#customer_results').html('');
                            });
                        $('#customer_results').append(div);
                    });
                } else {
                    $('#customer_results').html('<div class="text-muted p-2">لا توجد نتائج</div>');
                }
            });
        }, 300);
    });

    $(document).click(function(e) {
        if (!$(e.target).closest('#customer_search, #customer_results').length) {
            $('#customer_results').html('');
        }
    });

    // ================= إضافة عناصر الطلب =================
    let itemCounter = 0;
    function addOrderItem() {
        const newItem = `
            <tr class="order-item">
                <td>
                    <select class="form-select product-select" name="items[${itemCounter}][product_id]" required>
                        <option value="">اختر منتج...</option>
                        <?php foreach($products as $product): ?>
                        <option value="<?= $product['item_id'] ?>" data-price="<?= $product['selling_price'] ?>">
                            <?= htmlspecialchars($product['name']) ?> - <?= number_format($product['selling_price'], 2) ?> د.ل
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control quantity" name="items[${itemCounter}][quantity]" value="1" min="1" required>
                </td>
                <td>
                    <input type="number" class="form-control price" name="items[${itemCounter}][price]" step="0.01" readonly>
                </td>
                <td>
                    <input type="number" class="form-control discount" name="items[${itemCounter}][discount]" value="0" min="0" step="0.01">
                </td>
                <td class="item-total">0.00</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger remove-item">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
        $('#items-tbody').append(newItem);
        itemCounter++;
    }

    addOrderItem();
    $('#add-item-btn').click(addOrderItem);

    // إزالة عنصر
    $(document).on('click', '.remove-item', function() {
        if($('#items-tbody tr').length > 1) {
            $(this).closest('tr').remove();
            calculateTotals();
        } else {
            Swal.fire({
                title: 'خطأ',
                text: 'يجب أن يحتوي الطلب على منتج واحد على الأقل',
                icon: 'error',
                confirmButtonColor: '#4361ee'
            });
        }
    });

    // تحديث السعر عند اختيار المنتج
    $(document).on('change', '.product-select', function() {
        const price = parseFloat($(this).find(':selected').data('price')) || 0;
        const row = $(this).closest('tr');
        row.find('.price').val(price.toFixed(2));
        calculateItemTotal(row);
    });

    // تحديث المجموع عند تغيير الكمية أو الخصم
    $(document).on('input', '.quantity, .discount', function() {
        const row = $(this).closest('tr');
        calculateItemTotal(row);
    });

    // حساب إجمالي العنصر
    function calculateItemTotal(row) {
        const quantity = parseFloat(row.find('.quantity').val()) || 0;
        const price = parseFloat(row.find('.price').val()) || 0;
        const discount = parseFloat(row.find('.discount').val()) || 0;

        const total = Math.max((price * quantity) - discount, 0);
        row.find('.item-total').text(total.toFixed(2));
        calculateTotals();
    }

    // ================= حساب الإجماليات النهائية =================
    function calculateTotals() {
        let subtotal = 0;
        let totalDiscount = 0;

        $('#items-tbody tr').each(function() {
            const quantity = parseFloat($(this).find('.quantity').val()) || 0;
            const price = parseFloat($(this).find('.price').val()) || 0;
            const discount = parseFloat($(this).find('.discount').val()) || 0;

            subtotal += (price * quantity);
            totalDiscount += discount;
        });

        const tax = subtotal * 0.15; // ضريبة 15%
        const total = Math.max(subtotal + tax - totalDiscount, 0);
        const deposit = parseFloat($('#deposit_amount').val()) || 0;
        const paid = parseFloat($('#amount_paid').val()) || 0;
        const remaining = Math.max(total - (deposit + paid), 0);

        // تحديث العناصر داخل واجهة المستخدم
        $('#subtotalAmount').text(subtotal.toFixed(2) + ' د.ل');
        $('#discountAmount').text(totalDiscount.toFixed(2) + ' د.ل');
        $('#taxAmount').text(tax.toFixed(2) + ' د.ل');
        $('#total-display').text(total.toFixed(2) + ' د.ل');
        $('#remaining-amount').text(remaining.toFixed(2) + ' د.ل');

        if (remaining <= 0 && total > 0) {
            $('#payment-status').text('تم الدفع').css('color', 'green');
        } else if (paid > 0 || deposit > 0) {
            $('#payment-status').text('دفع جزئي').css('color', 'orange');
        } else {
            $('#payment-status').text('لم يتم الدفع').css('color', 'red');
        }
    }

    // تحديث الإجماليات عند تغيير المبلغ المدفوع أو العربون
    $('#amount_paid, #deposit_amount').on('input', calculateTotals);

    // ================= إرسال الطلب =================
    $('#orderForm').submit(function(e) {
        if (!$('#customer_id').val()) {
            e.preventDefault();
            Swal.fire({
                title: 'خطأ',
                text: 'يجب اختيار عميل من القائمة',
                icon: 'error',
                confirmButtonColor: '#4361ee'
            });
            return false;
        }
    });

});






























document.addEventListener('DOMContentLoaded', function() {

    const itemsTbody = document.getElementById('items-tbody');
    const totalAmount = document.getElementById('total-amount');
    const addItemBtn = document.getElementById('add-item-btn');

    let itemCounter = 0;

    // دالة لإضافة عنصر جديد
    function addItem() {
        const row = document.createElement('tr');
        row.classList.add('order-item');
        row.innerHTML = `
            <td>
                <input type="text" class="form-control product-name" placeholder="اسم المنتج" required>
            </td>
            <td>
                <input type="number" class="form-control quantity" value="1" min="1">
            </td>
            <td>
                <input type="number" class="form-control price" value="0.00" step="0.01">
            </td>
            <td class="item-total">0.00</td>
            <td>
                <button type="button" class="btn btn-sm btn-danger remove-item">&times;</button>
            </td>
        `;
        itemsTbody.appendChild(row);
        itemCounter++;
        attachRowEvents(row);
        calculateTotals();
    }

    // دالة لتحديث المجموع لكل صف
    function calculateRowTotal(row) {
        const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
        const price = parseFloat(row.querySelector('.price').value) || 0;
        const total = quantity * price;
        row.querySelector('.item-total').textContent = total.toFixed(2);
    }

    // دالة لحساب الإجمالي النهائي
    function calculateTotals() {
        let sum = 0;
        document.querySelectorAll('#items-tbody .order-item').forEach(row => {
            const total = parseFloat(row.querySelector('.item-total').textContent) || 0;
            sum += total;
        });
        totalAmount.textContent = sum.toFixed(2);
    }

    // ربط الأحداث لكل صف
    function attachRowEvents(row) {
        const inputs = row.querySelectorAll('.quantity, .price');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                calculateRowTotal(row);
                calculateTotals();
            });
        });

        // إزالة الصف
        row.querySelector('.remove-item').addEventListener('click', function() {
            if(itemsTbody.querySelectorAll('.order-item').length > 1) {
                row.remove();
                calculateTotals();
            } else {
                alert('يجب أن يحتوي الطلب على عنصر واحد على الأقل');
            }
        });
    }

    // إضافة أول عنصر عند تحميل الصفحة
    addItem();

    // زر إضافة عنصر جديد
    addItemBtn.addEventListener('click', addItem);

});
</script>

</body>
</html>