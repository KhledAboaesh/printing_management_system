<?php
// ✅ إدارة الجلسة والتحقق من المصادقة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'designer') {
    header("Location: login.php");
    exit();
}

// ✅ تضمين ملفات النظام
define('INCLUDED', true);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// ✅ متغيرات لتخزين البيانات
$invoices = [];
$orders   = [];
$stats = [
    'total'     => 0,
    'pending'   => 0,
    'approved'  => 0,
    'rejected'  => 0,
    'cancelled' => 0
];

// ========================
// ✅ جلب الفواتير المسندة للمصمم الحالي
// ========================
try {
    $stmt = $db->prepare("
        SELECT i.*, 
               c.name AS customer_name, 
               c.company_name, 
               u.full_name AS created_by_name, 
               ua.full_name AS assigned_by_name,
               id.designer_status, 
               id.designer_notes, 
               ia.assigned_at, 
               ia.notes AS assignment_notes
        FROM invoice_assignments ia
        JOIN invoices i ON ia.invoice_id = i.invoice_id
        JOIN customers c ON i.customer_id = c.customer_id
        JOIN users u ON i.created_by = u.user_id
        JOIN users ua ON ia.assigned_by = ua.user_id
        LEFT JOIN invoice_designer id 
               ON i.invoice_id = id.invoice_id AND id.designer_id = ?
        WHERE ia.designer_id = ? 
          AND i.status != 'cancelled'
        ORDER BY ia.assigned_at DESC, i.issue_date DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($invoices as $invoice) {
        $stats['total']++;
        $status = $invoice['designer_status'] ?? 'pending';
        if ($status === 'pending')      $stats['pending']++;
        elseif ($status === 'approved') $stats['approved']++;
        elseif ($status === 'rejected') $stats['rejected']++;
        elseif ($status === 'cancelled') $stats['cancelled']++;
    }

} catch (PDOException $e) {
    $error = "خطأ في جلب بيانات الفواتير: " . $e->getMessage();
}

// ========================
// ✅ جلب الطلبات المسندة للمصمم الحالي
// ========================
try {
    $stmt = $db->prepare("
        SELECT o.*, 
               c.name AS customer_name,
               u.full_name AS created_by_name,
               od.designer_status,
               od.designer_notes
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN users u ON o.created_by = u.user_id
        LEFT JOIN order_designer od 
               ON o.order_id = od.order_id AND od.designer_id = ?
        WHERE o.status != 'cancelled'
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orders as $order) {
        $stats['total']++;
        $status = $order['designer_status'] ?? 'pending';
        if ($status === 'pending')      $stats['pending']++;
        elseif ($status === 'approved') $stats['approved']++;
        elseif ($status === 'rejected') $stats['rejected']++;
        elseif ($status === 'cancelled') $stats['cancelled']++;
    }

} catch (PDOException $e) {
    $error = "خطأ في جلب بيانات الطلبات: " . $e->getMessage();
}

// ========================
// ✅ معالجة تحديث حالة الفاتورة أو الطلب
// ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['type'], $_POST['id'])) {
    try {
        $id     = (int) $_POST['id'];
        $action = $_POST['action'];
        $notes  = trim($_POST['designer_notes'] ?? '');
        $type   = $_POST['type']; // "invoice" أو "order"

        if ($type === 'invoice') {
            // التحقق من الإسناد
            $stmt = $db->prepare("SELECT * FROM invoice_assignments WHERE invoice_id = ? AND designer_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$assignment) throw new Exception("ليس لديك صلاحية للتعامل مع هذه الفاتورة");

            // التحقق من وجود سجل المصمم
            $stmt = $db->prepare("SELECT * FROM invoice_designer WHERE invoice_id = ? AND designer_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $designer_assignment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$designer_assignment) {
                $stmt = $db->prepare("
                    INSERT INTO invoice_designer (invoice_id, designer_id, designer_status, designer_notes, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$id, $_SESSION['user_id'], $action, $notes]);
            } else {
                $stmt = $db->prepare("
                    UPDATE invoice_designer 
                    SET designer_status = ?, designer_notes = ?, updated_at = NOW()
                    WHERE invoice_id = ? AND designer_id = ?
                ");
                $stmt->execute([$action, $notes, $id, $_SESSION['user_id']]);
            }

        } elseif ($type === 'order') {
            // التحقق من الإسناد
            $stmt = $db->prepare("SELECT * FROM order_assignments WHERE order_id = ? AND designer_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$assignment) throw new Exception("ليس لديك صلاحية للتعامل مع هذا الطلب");

            // تحديث ملاحظات الطلب مباشرة في جدول orders
            $stmt = $db->prepare("UPDATE orders SET notes = ?, updated_at = NOW() WHERE order_id = ?");
            $stmt->execute([$notes, $id]);

            // التحقق من وجود سجل المصمم
            $stmt = $db->prepare("SELECT * FROM order_designer WHERE order_id = ? AND designer_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
            $designer_assignment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$designer_assignment) {
                $stmt = $db->prepare("
                    INSERT INTO order_designer (order_id, designer_id, designer_status, designer_notes, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$id, $_SESSION['user_id'], $action, $notes]);
            } else {
                $stmt = $db->prepare("
                    UPDATE order_designer 
                    SET designer_status = ?, designer_notes = ?, updated_at = NOW()
                    WHERE order_id = ? AND designer_id = ?
                ");
                $stmt->execute([$action, $notes, $id, $_SESSION['user_id']]);
            }

        } else {
            throw new Exception("نوع غير معروف");
        }

        // تسجيل النشاط
        logActivity($_SESSION['user_id'], 'update_designer_status', "تم تحديث الحالة #$type ID:$id إلى $action");

        header("Location: designer_dashboard.php?updated=1");
        exit();

    } catch (Exception $e) {
        $error = "حدث خطأ أثناء التحديث: " . $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم المصمم - نظام المطبعة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>

        
        :root {
    --primary: #4361ee;
    --secondary: #3f37c9;
    --success: #28a745;
    --danger: #dc3545;
    --warning: #f8961e;
    --info: #4895ef;
    --dark: #212529;
    --light: #f8f9fa;
    --gray: #6c757d;
}

/* ======= صندوق المحتوى ======= */
.content-box, .dashboard-container {
    background-color: white;
    border-radius: 12px;
    padding: 20px 25px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
    margin-bottom: 30px;
}

/* ======= بطاقات الإحصائيات ======= */
.stats-cards, .stats-container {
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
    text-align: center;
    transition: transform 0.3s, box-shadow 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-card.total { border-top: 4px solid var(--primary); }
.stat-card.pending { border-top: 4px solid var(--warning); }
.stat-card.approved { border-top: 4px solid var(--success); }
.stat-card.rejected { border-top: 4px solid var(--danger); }
.stat-card.cancelled { border-top: 4px solid var(--gray); }

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    margin: 10px 0;
}

.stat-title {
    color: var(--gray);
    font-size: 0.9rem;
}

.stat-icon {
    font-size: 2rem;
    margin-top: 10px;
    color: var(--primary);
}

/* ======= الجداول ======= */
.invoices-table, .data-table, .items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    background-color: white;
}

.invoices-table th, .invoices-table td, 
.data-table th, .data-table td,
.items-table th, .items-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #eee;
    text-align: right;
}

.invoices-table th, .data-table th, .items-table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.invoices-table tbody tr:hover, .data-table tbody tr:hover {
    background-color: #f8fafd;
}

/* ======= شارات الحالة ======= */
.status-badge, .badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-block;
}

.status-pending, .badge-pending { background-color: #ffc107; color: #856404; }
.status-approved, .badge-approved { background-color: #28a745; color: white; }
.status-rejected, .badge-rejected { background-color: #dc3545; color: white; }
.status-cancelled, .badge-cancelled { background-color: #6c757d; color: white; }

/* ======= أزرار ======= */
.btn-sm, .add-btn, .add-item-btn, .action-btn {
    border: none;
    border-radius: 8px;
    padding: 6px 12px;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 0.85rem;
}

.btn-approve { background-color: #28a745; color: white; }
.btn-reject { background-color: #dc3545; color: white; }
.btn-view { background-color: #17a2b8; color: white; }
.add-btn { background-color: var(--primary); color: white; display: inline-flex; align-items: center; gap: 8px; }
.add-btn:hover { background-color: var(--secondary); transform: translateY(-2px); }
.add-item-btn { background-color: #f8f9fa; border: 1px dashed #ddd; color: #6c757d; }
.add-item-btn:hover { background-color: #e9ecef; color: #495057; }
.action-btn { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; background-color: #f8f9fa; color: var(--gray); border-radius: 50%; }
.action-btn:hover { background-color: var(--primary); color: white; transform: scale(1.1); }

.action-buttons { display: flex; gap: 10px; justify-content: flex-end; }

/* ======= التبويبات ======= */
.nav-tabs {
    display: flex;
    border-bottom: 2px solid #e0e0e0;
    margin-bottom: 20px;
}
.nav-tabs .nav-item { margin-right: 10px; }
.nav-tabs .nav-link {
    padding: 10px 20px;
    background-color: #f8f9fa;
    border-radius: 8px 8px 0 0;
    border: 1px solid #e0e0e0;
    border-bottom: none;
    color: #495057;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}
.nav-tabs .nav-link.active {
    background-color: #ffffff;
    border-color: #dee2e6 #dee2e6 #ffffff;
    color: var(--primary);
    font-weight: 600;
}
.nav-tabs .nav-link:hover { background-color: #e9ecef; }

.tab-content {
    padding: 15px;
    background-color: #ffffff;
    border: 1px solid #dee2e6;
    border-radius: 0 8px 8px 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}
.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* ======= مودال ======= */
.modal {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}
.modal-content {
    background: white;
    border-radius: 12px;
    width: 500px;
    max-width: 90%;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; }
.modal-title { font-size: 1.2rem; font-weight: 600; }
.close-modal { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6c757d; }
.form-group { margin-bottom: 20px; }
.form-label { display: block; margin-bottom: 8px; font-weight: 500; }
.form-control { width: 100%; padding: 10px 15px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 15px; }
.form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }

/* ======= البحث والتصفية ======= */
.search-filter { display: flex; gap: 15px; margin-bottom: 25px; align-items: center; }
.search-box { flex: 1; position: relative; }
.search-box input {
    width: 100%;
    padding: 12px 20px 12px 45px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
}
.search-box input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(67,97,238,0.2); outline: none; }
.search-box i { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: var(--gray); }

.filter-select select {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    background-color: white;
    font-size: 14px;
    cursor: pointer;
}

/* ======= التحسينات للهواتف ======= */
@media (max-width: 768px) {
    .stats-container, .charts-container { grid-template-columns: 1fr; }
    .search-filter { flex-direction: column; align-items: stretch; }
}


    </style>
</head>
<body>
  <div class="container">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="header">
            <h1>لوحة تحكم المصمم</h1>
            <?php include 'includes/user-menu.php'; ?>
        </div>
        
        <div class="dashboard-container">
            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> تم تحديث الحالة بنجاح
            </div>
            <?php endif; ?>
            
            <!-- بطاقات الإحصائيات -->
            <div class="stats-cards">
                <div class="stat-card total">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-title">الإجمالي</div>
                    <i class="fas fa-file-invoice stat-icon"></i>
                </div>
                
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-title">قيد المراجعة</div>
                    <i class="fas fa-clock stat-icon"></i>
                </div>
                
                <div class="stat-card approved">
                    <div class="stat-number"><?php echo $stats['approved']; ?></div>
                    <div class="stat-title">مقبولة</div>
                    <i class="fas fa-check-circle stat-icon"></i>
                </div>
                
                <div class="stat-card rejected">
                    <div class="stat-number"><?php echo $stats['rejected']; ?></div>
                    <div class="stat-title">مرفوضة</div>
                    <i class="fas fa-times-circle stat-icon"></i>
                </div>
            </div>
            
            <!-- ✅ التبويبات -->
            <ul class="nav nav-tabs" id="dashboardTabs">
                <li class="nav-item">
                    <a class="nav-link active" href="#invoices" onclick="showTab(event, 'invoices')">الفواتير</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#orders" onclick="showTab(event, 'orders')">الطلبات</a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- ✅ تبويب الفواتير -->
                <div id="invoices" class="tab-pane active">
                    <h2>الفواتير المرسلة إليك</h2>
                    <div class="table-responsive">
                        <table class="invoices-table">
                            <thead>
                                <tr>
                                    <th>رقم الفاتورة</th>
                                    <th>العميل</th>
                                    <th>تاريخ الإصدار</th>
                                    <th>تاريخ الإرسال</th>
                                    <th>مرسل بواسطة</th>
                                    <th>المبلغ</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 30px;">
                                        <i class="fas fa-inbox" style="font-size: 3rem; color: #dee2e6; margin-bottom: 15px;"></i>
                                        <p>لا توجد فواتير مرسلة إليك بعد</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($invoices as $invoice): 
                                        $status = $invoice['designer_status'] ?? 'pending';
                                        $statusClass = "status-{$status}";
                                    ?>
                                    <tr>
                                        <td><?php echo $invoice['invoice_number']; ?></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($invoice['customer_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($invoice['company_name'] ?? ''); ?></small>
                                        </td>
                                        <td><?php echo $invoice['issue_date']; ?></td>
                                        <td>
                                            <?php echo date('Y-m-d', strtotime($invoice['assigned_at'])); ?>
                                            <div class="assignment-info">
                                                <?php echo date('H:i', strtotime($invoice['assigned_at'])); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($invoice['assigned_by_name']); ?></td>
                                        <td><?php echo number_format($invoice['total_amount'], 2); ?> د.ل</td>
                                        <td>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php 
                                                $statusText = [
                                                    'pending' => 'قيد المراجعة',
                                                    'approved' => 'مقبولة',
                                                    'rejected' => 'مرفوضة',
                                                    'cancelled' => 'ملغاة'
                                                ];
                                                echo $statusText[$status] ?? $status;
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                          <div class="action-buttons">
    <!-- زر عرض الفاتورة -->
    <button class="btn-sm btn-view" onclick="viewInvoice(<?php echo $invoice['invoice_id']; ?>)">
        <i class="fas fa-eye"></i> عرض
    </button>

    <?php if ($status === 'pending'): ?>
        <!-- زر قبول الفاتورة -->
        <button class="btn-sm btn-approve" 
                onclick="openModal(<?php echo $invoice['invoice_id']; ?>, 'approved', 'invoice')">
            <i class="fas fa-check"></i> قبول
        </button>

        <!-- زر رفض الفاتورة -->
        <button class="btn-sm btn-reject" 
                onclick="openModal(<?php echo $invoice['invoice_id']; ?>, 'rejected', 'invoice')">
            <i class="fas fa-times"></i> رفض
        </button>
    <?php endif; ?>
</div>

                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ✅ تبويب الطلبات -->
                <div id="orders" class="tab-pane" style="display:none;">
                    <h2>الطلبات المرسلة إليك</h2>
                    <div class="table-responsive">
                        <table class="invoices-table">
                            <thead>
                                <tr>
                                    <th>رقم الطلب</th>
                                    <th>العميل</th>
                                    <th>تاريخ الطلب</th>
                                    <th>مرسل بواسطة</th>
                                    <th>الملاحظات</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px;">
                                        <i class="fas fa-inbox" style="font-size: 3rem; color: #dee2e6; margin-bottom: 15px;"></i>
                                        <p>لا توجد طلبات مرسلة إليك بعد</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($orders as $order): 
                                        $status = $order['designer_status'] ?? 'pending';
                                        $statusClass = "status-{$status}";
                                        // إذا كانت ملاحظات المصمم موجودة اعرضها، وإلا اعرض الوصف العام
                                        $notesToShow = $order['designer_notes'] ?? $order['description'] ?? '';
                                    ?>
                                    <tr>
                                        <td><?php echo $order['order_id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td><?php echo $order['order_date']; ?></td>
                                        <td><?php echo htmlspecialchars($order['created_by_name']); ?></td>
                                        <td><?php echo htmlspecialchars($notesToShow); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php 
                                                $statusText = [
                                                    'pending' => 'قيد المراجعة',
                                                    'approved' => 'مقبولة',
                                                    'rejected' => 'مرفوضة',
                                                    'cancelled' => 'ملغاة'
                                                ];
                                                echo $statusText[$status] ?? $status;
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-sm btn-view" onclick="viewOrder(<?php echo $order['order_id']; ?>)">
                                                    <i class="fas fa-eye"></i> عرض
                                                </button>
                                                
                                                <?php if ($status === 'pending'): ?>
                                                <button class="btn-sm btn-approve" onclick="openModal(<?php echo $order['order_id']; ?>, 'approved', 'order')">
                                                    <i class="fas fa-check"></i> قبول
                                                </button>
                                                <button class="btn-sm btn-reject" onclick="openModal(<?php echo $order['order_id']; ?>, 'rejected', 'order')">
                                                    <i class="fas fa-times"></i> رفض
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>


    <!-- Modal لتحديث حالة الفاتورة -->
    <div class="modal" id="statusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">تحديث حالة الفاتورة</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            
            <form id="statusForm" method="POST">
                <input type="hidden" name="invoice_id" id="modalInvoiceId">
                <input type="hidden" name="action" id="modalAction">
                
                <div class="form-group">
                    <label for="designer_notes" class="form-label">ملاحظات المصمم</label>
                    <textarea name="designer_notes" id="designer_notes" class="form-control" rows="4" 
                              placeholder="أضف ملاحظاتك حول الفاتورة (اختياري)"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">إلغاء</button>
                    <button type="submit" class="btn btn-primary" id="modalSubmitBtn">تأكيد</button>
                </div>
            </form>
        </div>
    </div>

    <script>
// =======================
// تبديل التبويبات
// =======================
function showTab(event, tabId) {
    event.preventDefault();
    document.querySelectorAll('.tab-pane').forEach(tab => tab.style.display = 'none');
    document.getElementById(tabId).style.display = 'block';
    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
    event.target.classList.add('active');
}

// =======================
// فتح modal لتحديث الحالة
// =======================
function openModal(id, action, type) {
    const modal = document.getElementById('statusModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalIdInput = document.getElementById('modalInvoiceId');
    const modalActionInput = document.getElementById('modalAction');
    const modalForm = document.getElementById('statusForm');
    const modalSubmitBtn = document.getElementById('modalSubmitBtn');

    // تنظيف modal قبل الإضافة
    modal.querySelectorAll('input[name="type"]').forEach(el => el.remove());

    // إعداد الحقول
    modalIdInput.name = "id"; // اسم عام للـ PHP
    modalIdInput.value = id;
    modalActionInput.value = action;

    // إضافة نوع الطلب/الفاتورة
    const typeInput = document.createElement("input");
    typeInput.type = "hidden";
    typeInput.name = "type";
    typeInput.value = type;
    modalForm.appendChild(typeInput);

    // تحديث النصوص والألوان حسب الإجراء
    if (action === 'approved') {
        modalTitle.textContent = (type === 'invoice' ? 'قبول الفاتورة #' : 'قبول الطلب #') + id;
        modalSubmitBtn.innerHTML = '<i class="fas fa-check"></i> ' + (type === 'invoice' ? 'قبول الفاتورة' : 'قبول الطلب');
        modalSubmitBtn.className = 'btn btn-primary';
    } else {
        modalTitle.textContent = (type === 'invoice' ? 'رفض الفاتورة #' : 'رفض الطلب #') + id;
        modalSubmitBtn.innerHTML = '<i class="fas fa-times"></i> ' + (type === 'invoice' ? 'رفض الفاتورة' : 'رفض الطلب');
        modalSubmitBtn.className = 'btn btn-danger';
    }

    modal.style.display = 'flex';
}

// =======================
// إغلاق modal
// =======================
function closeModal() {
    const modal = document.getElementById('statusModal');
    modal.style.display = 'none';
    document.getElementById('designer_notes').value = '';
}

// إغلاق modal عند النقر خارج المحتوى
window.addEventListener('click', function(event) {
    const modal = document.getElementById('statusModal');
    if (event.target === modal) closeModal();
});

// =======================
// عرض الفاتورة أو الطلب
// =======================
function viewInvoice(invoiceId) {
    window.open('view_invoice.php?id=' + invoiceId, '_blank');
}

function viewOrder(orderId) {
    window.open('view_order.php?id=' + orderId, '_blank');
}

    </script>
</body>
</html>