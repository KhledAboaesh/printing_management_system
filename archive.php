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

// التحقق من وجود صلاحية عرض الأرشيف
// if (!hasPermission($_SESSION['user_id'], 'view_archive')) {
//     $_SESSION['error'] = "ليس لديك صلاحية لعرض الأرشيف";
//     header("Location: dashboard.php");
//     exit();
// }

// معالجة معاملات البحث والتصفية
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';
$type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING) ?? 'customers';
$date_from = filter_input(INPUT_GET, 'date_from', FILTER_SANITIZE_STRING) ?? '';
$date_to = filter_input(INPUT_GET, 'date_to', FILTER_SANITIZE_STRING) ?? '';
$archived_by = filter_input(INPUT_GET, 'archived_by', FILTER_VALIDATE_INT) ?? 0;

// الترقيم
$page = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT,
    ['options' => ['default' => 1, 'min_range' => 1]]
);
$limit = 20;
$offset = ($page - 1) * $limit;

// إحصائيات الأرشيف
$stats = [];
try {
    // إحصائيات العملاء المؤرشفين
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_archived_customers,
            COUNT(CASE WHEN archive_reason LIKE '%توقف%' THEN 1 END) as stopped_customers,
            COUNT(CASE WHEN archive_reason LIKE '%مشكلة%' THEN 1 END) as problem_customers,
            COUNT(CASE WHEN archive_reason LIKE '%انتهاء%' THEN 1 END) as expired_customers
        FROM customers 
        WHERE is_archived = 1
    ");
    $stmt->execute();
    $stats['customers'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // إحصائيات الأرشيف الشهري
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(archived_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM customers 
        WHERE is_archived = 1 
        AND archived_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(archived_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $stmt->execute();
    $stats['monthly'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // المستخدمون الذين قاموا بالأرشفة
    $stmt = $db->prepare("
        SELECT DISTINCT u.user_id, u.full_name
        FROM customers c
        JOIN users u ON c.archived_by = u.user_id
        WHERE c.is_archived = 1
        ORDER BY u.full_name
    ");
    $stmt->execute();
    $stats['archivers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "خطأ في جلب الإحصائيات: " . $e->getMessage();
}

// جلب البيانات المؤرشفة حسب النوع
$archived_items = [];
$total_items = 0;
$total_pages = 0;

try {
    if ($type === 'customers') {
        // بناء استعلام العملاء المؤرشفين
        $where_conditions = ["c.is_archived = 1"];
        $params = [];

        if (!empty($search)) {
            $where_conditions[] = "(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
            $search_term = "%$search%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if (!empty($date_from)) {
            $where_conditions[] = "c.archived_at >= ?";
            $params[] = $date_from;
        }

        if (!empty($date_to)) {
            $where_conditions[] = "c.archived_at <= ?";
            $params[] = $date_to . ' 23:59:59';
        }

        if ($archived_by > 0) {
            $where_conditions[] = "c.archived_by = ?";
            $params[] = $archived_by;
        }

        $where_clause = implode(' AND ', $where_conditions);

        // جلب العدد الإجمالي
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM customers c 
            WHERE $where_clause
        ");
        $stmt->execute($params);
        $total_items = $stmt->fetchColumn();

        $total_pages = ceil($total_items / $limit);

        // جلب البيانات
        $query = "
            SELECT 
                c.*,
                u.full_name as archived_by_name,
                COUNT(i.invoice_id) as invoice_count,
                (SELECT SUM(total_amount) FROM invoices WHERE customer_id = c.customer_id) as total_invoice_amount
            FROM customers c
            LEFT JOIN users u ON c.archived_by = u.user_id
            LEFT JOIN invoices i ON c.customer_id = i.customer_id
            WHERE $where_clause
            GROUP BY c.customer_id
            ORDER BY c.archived_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $db->prepare($query);

        // ربط المتغيرات
        foreach ($params as $k => $v) {
            $stmt->bindValue($k + 1, $v); // لأن ? placeholders
        }

        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        $archived_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($type === 'invoices') {
        // يمكن إضافة استعلامات للفواتير المؤرشفة إذا كان النظام يدعمها
    }

} catch (PDOException $e) {
    $error = "خطأ في جلب البيانات المؤرشفة: " . $e->getMessage();
}

// معالجة طلب استعادة العميل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_customer'])) {
    $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
    
    if ($customer_id && hasPermission($_SESSION['user_id'], 'restore_customer')) {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                UPDATE customers 
                SET is_archived = 0, 
                    archive_reason = NULL,
                    archived_by = NULL,
                    archived_at = NULL,
                    restored_by = ?,
                    restored_at = NOW()
                WHERE customer_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $customer_id]);
            
            // تسجيل النشاط
            logActivity($_SESSION['user_id'], 'restore_customer', 
                       "تم استعادة العميل المؤرشف (ID: $customer_id)");
            
            $db->commit();
            
            $_SESSION['success'] = "تم استعادة العميل بنجاح";
            header("Location: archive.php?" . $_SERVER['QUERY_STRING']);
            exit();
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "حدث خطأ أثناء استعادة العميل: " . $e->getMessage();
        }
    } else {
        $error = "معرف العميل غير صالح أو لا تملك الصلاحية";
    }
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأرشيف - نظام المطبعة</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .archive-dashboard {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin: 20px auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stat-card.danger {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .filters-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .filter-grid {
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
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            border: none;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #4361ee;
            color: white;
        }
        
        .btn-primary:hover {
            background: #3a56d4;
        }
        
        .btn-outline {
            background: white;
            color: #6c757d;
            border: 1px solid #6c757d;
        }
        
        .btn-outline:hover {
            background: #f8f9fa;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .items-table th {
            background: #f8f9fa;
            padding: 12px;
            border: 1px solid #ddd;
            text-align: center;
            font-weight: 600;
        }
        
        .items-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: center;
        }
        
        .items-table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-secondary {
            background: #6c757d;
            color: white;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            gap: 5px;
        }
        
        .page-link {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #4361ee;
        }
        
        .page-link.active {
            background: #4361ee;
            color: white;
            border-color: #4361ee;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
        }
        
        .modal-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .items-table {
                font-size: 12px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <h1>إدارة الأرشيف</h1>
                <?php include 'includes/user-menu.php'; ?>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="archive-dashboard">
                <!-- إحصائيات سريعة -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">إجمالي العملاء المؤرشفين</div>
                        <div class="stat-number"><?= $stats['customers']['total_archived_customers'] ?? 0 ?></div>
                        <div class="stat-label">عميل</div>
                    </div>
                    
                    <div class="stat-card warning">
                        <div class="stat-label">عملاء متوقفون</div>
                        <div class="stat-number"><?= $stats['customers']['stopped_customers'] ?? 0 ?></div>
                        <div class="stat-label">عميل</div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-label">عملاء بمشاكل</div>
                        <div class="stat-number"><?= $stats['customers']['problem_customers'] ?? 0 ?></div>
                        <div class="stat-label">عميل</div>
                    </div>
                    
                    <div class="stat-card danger">
                        <div class="stat-label">عملاء منتهية</div>
                        <div class="stat-number"><?= $stats['customers']['expired_customers'] ?? 0 ?></div>
                        <div class="stat-label">عميل</div>
                    </div>
                </div>
                
                <!-- مخطط الإحصائيات الشهرية -->
                <?php if (!empty($stats['monthly'])): ?>
                <div class="chart-container">
                    <h3><i class="fas fa-chart-bar"></i> الإرشيف الشهري (آخر 6 أشهر)</h3>
                    <div style="height: 200px; display: flex; align-items: end; gap: 10px; justify-content: center; margin-top: 20px;">
                        <?php foreach (array_reverse($stats['monthly']) as $monthly): ?>
                            <div style="display: flex; flex-direction: column; align-items: center;">
                                <div style="background: #4361ee; width: 40px; height: <?= ($monthly['count'] / max(array_column($stats['monthly'], 'count')) * 150) ?>px; border-radius: 4px;"></div>
                                <div style="margin-top: 5px; font-size: 12px;"><?= $monthly['count'] ?></div>
                                <div style="font-size: 10px; color: #666;"><?= date('M', strtotime($monthly['month'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- فلاتر البحث -->
                <div class="filters-section">
                    <form method="GET" id="filterForm">
                        <div class="filter-grid">
                            <div class="form-group">
                                <label class="form-label">نوع الأرشيف</label>
                                <select name="type" class="form-control" onchange="document.getElementById('filterForm').submit()">
                                    <option value="customers" <?= $type === 'customers' ? 'selected' : '' ?>>العملاء المؤرشفين</option>
                                    <option value="invoices" <?= $type === 'invoices' ? 'selected' : '' ?>>الفواتير المؤرشفة</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">بحث</label>
                                <input type="text" name="search" class="form-control" placeholder="ابحث بالاسم أو البريد أو الهاتف..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">من تاريخ</label>
                                <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">إلى تاريخ</label>
                                <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">تم الأرشفة بواسطة</label>
                                <select name="archived_by" class="form-control">
                                    <option value="0">جميع المستخدمين</option>
                                    <?php foreach ($stats['archivers'] as $archiver): ?>
                                        <option value="<?= $archiver['user_id'] ?>" <?= $archived_by == $archiver['user_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($archiver['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> بحث
                                </button>
                                <a href="archive.php" class="btn btn-outline">
                                    <i class="fas fa-redo"></i> إعادة تعيين
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- جدول البيانات المؤرشفة -->
                <?php if ($type === 'customers'): ?>
                    <?php if (!empty($archived_items)): ?>
                        <div style="overflow-x: auto;">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>العميل</th>
                                        <th>معلومات الاتصال</th>
                                        <th>عدد الفواتير</th>
                                        <th>إجمالي المبالغ</th>
                                        <th>سبب الأرشفة</th>
                                        <th>تم الأرشفة بواسطة</th>
                                        <th>تاريخ الأرشفة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($archived_items as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($item['name']) ?></strong>
                                        </td>
                                        <td>
                                            <?php if (!empty($item['email'])): ?>
                                                <div><?= htmlspecialchars($item['email']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($item['phone'])): ?>
                                                <div><?= htmlspecialchars($item['phone']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary"><?= $item['invoice_count'] ?></span>
                                        </td>
                                        <td>
                                            <?= number_format($item['total_invoice_amount'] ?? 0, 2) ?> د.ل
                                        </td>
                                        <td>
                                            <span title="<?= htmlspecialchars($item['archive_reason']) ?>">
                                                <?= mb_substr($item['archive_reason'], 0, 30) . (mb_strlen($item['archive_reason']) > 30 ? '...' : '') ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($item['archived_by_name']) ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($item['archived_at'])) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="customer_details.php?id=<?= $item['customer_id'] ?>" 
                                                   class="btn btn-outline" title="عرض التفاصيل">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (hasPermission($_SESSION['user_id'], 'restore_customer')): ?>
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('هل أنت متأكد من استعادة هذا العميل؟')">
                                                    <input type="hidden" name="customer_id" value="<?= $item['customer_id'] ?>">
                                                    <button type="submit" name="restore_customer" class="btn btn-success" title="استعادة العميل">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- الترقيم -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                   class="page-link <?= $i == $page ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-archive"></i>
                            <h3>لا توجد بيانات مؤرشفة</h3>
                            <p>لم يتم العثور على عملاء مؤرشفين تطابق معايير البحث الخاصة بك.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($type === 'invoices'): ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h3>قريباً</h3>
                        <p>إدارة الفواتير المؤرشفة قيد التطوير.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        // تحديث الرسم البياني عند تغيير الفلاتر
        document.getElementById('filterForm').addEventListener('change', function() {
            // يمكن إضافة تحديث ديناميكي للرسم البياني هنا
        });
        
        // تأكيد قبل الاستعادة
        function confirmRestore(customerName) {
            return confirm(`هل أنت متأكد من استعادة العميل "${customerName}"؟\nسيتم إظهاره مرة أخرى في قائمة العملاء النشطين.`);
        }
        
        // تصدير البيانات
        function exportArchive() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.open('export_archive.php?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>