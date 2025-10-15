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

// التحقق من وجود معرف العميل
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: customers.php");
    exit();
}

$customer_id = $_GET['id'];

// جلب بيانات العميل
$customer = getCustomerDetails($db, $customer_id);
if(!$customer) {
    header("Location: customers.php");
    exit();
}

// جلب إحصائيات العميل
$customerStats = getCustomerStats($db, $customer_id);

// جلب فواتير العميل
$customerInvoices = getCustomerInvoices($db, $customer_id);

// جلب آخر المعاملات
$customerTransactions = getCustomerTransactions($db, $customer_id);

// جلب الملاحظات
$customerNotes = getCustomerNotes($db, $customer_id);

// معالجة إضافة ملاحظة
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note_text = trim($_POST['note_text']);
    if(!empty($note_text)) {
        addCustomerNote($db, $customer_id, $_SESSION['user_id'], $note_text);
        header("Location: customer_details.php?id=" . $customer_id);
        exit();
    }
}

// الدوال المساعدة
function getCustomerDetails($db, $customer_id) {
    $stmt = $db->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM invoices WHERE customer_id = c.customer_id) as total_invoices,
               (SELECT SUM(total_amount) FROM invoices WHERE customer_id = c.customer_id AND status = 'paid') as total_paid,
               (SELECT SUM(total_amount) FROM invoices WHERE customer_id = c.customer_id AND status = 'pending') as total_pending
        FROM customers c 
        WHERE c.customer_id = ?
    ");
    $stmt->execute([$customer_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getCustomerStats($db, $customer_id) {
    $stats = [];

    $stmt = $db->prepare("SELECT COUNT(*) as total_invoices FROM invoices WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $stats['total_invoices'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_invoices'] ?? 0;

    $stmt = $db->prepare("SELECT SUM(total_amount) as total_sales FROM invoices WHERE customer_id = ? AND status = 'paid'");
    $stmt->execute([$customer_id]);
    $stats['total_sales'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_sales'] ?? 0;

    $stmt = $db->prepare("SELECT SUM(total_amount) as pending_amount FROM invoices WHERE customer_id = ? AND status = 'pending'");
    $stmt->execute([$customer_id]);
    $stats['pending_amount'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_amount'] ?? 0;

    $stmt = $db->prepare("SELECT AVG(total_amount) as avg_invoice FROM invoices WHERE customer_id = ? AND status = 'paid'");
    $stmt->execute([$customer_id]);
    $stats['avg_invoice'] = $stmt->fetch(PDO::FETCH_ASSOC)['avg_invoice'] ?? 0;

    $stmt = $db->prepare("SELECT MAX(created_at) as last_purchase FROM invoices WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $stats['last_purchase'] = $stmt->fetch(PDO::FETCH_ASSOC)['last_purchase'] ?? 'لم يقم بأي عمليات شراء';

    return $stats;
}

function getCustomerInvoices($db, $customer_id) {
    $stmt = $db->prepare("
        SELECT i.*, 
               (SELECT COUNT(*) FROM invoice_items WHERE invoice_id = i.invoice_id) as items_count
        FROM invoices i 
        WHERE i.customer_id = ? 
        ORDER BY i.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$customer_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCustomerTransactions($db, $customer_id) {
    $stmt = $db->prepare("
        SELECT 'invoice' as type, invoice_id as id, total_amount as amount, 
               created_at, status, 'فاتورة بيع' as description
        FROM invoices 
        WHERE customer_id = ?
        
        UNION ALL
        
        SELECT 'payment' as type, payment_id as id, amount, 
               payment_date as created_at, 'completed' as status, 
               CONCAT('سداد فاتورة #', invoice_id) as description
        FROM payments 
        WHERE customer_id = ?
        
        ORDER BY created_at DESC 
        LIMIT 15
    ");
    $stmt->execute([$customer_id, $customer_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function addCustomerNote($db, $customer_id, $user_id, $note_text) {
    $stmt = $db->prepare("INSERT INTO customer_notes (customer_id, created_by, note) VALUES (?, ?, ?)");
    return $stmt->execute([$customer_id, $user_id, $note_text]);
}

function getCustomerNotes($db, $customer_id) {
    $stmt = $db->prepare("
        SELECT cn.*, u.username 
        FROM customer_notes cn 
        LEFT JOIN users u ON cn.created_by = u.user_id 
        WHERE cn.customer_id = ? 
        ORDER BY cn.created_at DESC
        LIMIT 25
    ");
    $stmt->execute([$customer_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// مثال عرض النوع مع قيمة افتراضية لتجنب التحذير
function getCustomerTypeLabel($customer) {
    $type = $customer['type'] ?? 'individual';
    return $type === 'company' ? 'شركة' : 'فرد';
}
?>



<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل العميل - نظام المطبعة</title>
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
        
        .customer-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .customer-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: white;
            font-weight: bold;
        }
        
        .customer-info {
            flex: 1;
        }
        
        .customer-name {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .customer-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 10px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--gray);
            font-size: 14px;
        }
        
        .customer-actions {
            display: flex;
            gap: 10px;
        }
        
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
        
        .badge-info {
            background-color: rgba(72, 149, 239, 0.1);
            color: #4895ef;
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
        
        .note-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-right: 4px solid var(--primary);
        }
        
        .note-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .note-author {
            font-weight: 600;
            color: var(--dark);
        }
        
        .note-date {
            font-size: 12px;
            color: var(--gray);
        }
        
        .note-text {
            color: var(--dark);
            line-height: 1.5;
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
        
        .transaction-type {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .type-invoice {
            background: rgba(67, 97, 238, 0.1);
            color: #4361ee;
        }
        
        .type-payment {
            background: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
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
                <!-- رأس الصفحة -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">تفاصيل العميل</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">الرئيسية</a></li>
                                <li class="breadcrumb-item"><a href="customers.php">العملاء</a></li>
                                <li class="breadcrumb-item active">تفاصيل العميل</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="edit_customer.php?id=<?= $customer_id ?>" class="btn btn-outline-primary me-2">
                            <i class="fas fa-edit me-1"></i> تعديل العميل
                        </a>
                        <a href="create_invoice.php?customer_id=<?= $customer_id ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> فاتورة جديدة
                        </a>
                    </div>
                </div>
                
              <!-- معلومات العميل -->
<div class="content-box">
    <div class="customer-header">
        <div class="customer-avatar">
            <?= htmlspecialchars(substr($customer['name'] ?? '', 0, 1)) ?>
        </div>
        <div class="customer-info">
            <h2 class="customer-name"><?= htmlspecialchars($customer['name'] ?? 'اسم غير متوفر') ?></h2>
            <div class="customer-meta">
                <div class="meta-item">
                    <i class="fas fa-phone"></i>
                    <span><?= htmlspecialchars($customer['phone'] ?? 'لا يوجد') ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-envelope"></i>
                    <span><?= htmlspecialchars($customer['email'] ?? 'لا يوجد') ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?= htmlspecialchars($customer['address'] ?? 'لا يوجد') ?></span>
                </div>
            </div>
            <div class="customer-meta">
                <div class="meta-item">
                    <i class="fas fa-calendar"></i>
                    <span>مسجل منذ: <?= isset($customer['created_at']) ? date('Y-m-d', strtotime($customer['created_at'])) : 'غير محدد' ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-tag"></i>
                    <span>النوع: <?= ($customer['type'] ?? 'individual') === 'company' ? 'شركة' : 'فرد' ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- إحصائيات العميل -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #4361ee, #3a0ca3);">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($customerStats['total_invoices'] ?? 0) ?></div>
                <div class="stat-label">إجمالي الفواتير</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #4cc9f0, #4895ef);">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($customerStats['total_sales'] ?? 0, 2) ?> د.ل</div>
                <div class="stat-label">إجمالي المبيعات</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f8961e, #f3722c);">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($customerStats['pending_amount'] ?? 0, 2) ?> د.ل</div>
                <div class="stat-label">المبلغ المعلق</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f72585, #b5179e);">
                <i class="fas fa-calculator"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?= number_format($customerStats['avg_invoice'] ?? 0, 2) ?> د.ل</div>
                <div class="stat-label">متوسط الفاتورة</div>
            </div>
        </div>
    </div>
</div>

                
                <!-- تبويبات المحتوى -->
                <div class="tab-container">
                    <ul class="nav nav-tabs" id="customerTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoices" type="button" role="tab" aria-controls="invoices" aria-selected="true">
                                <i class="fas fa-receipt me-1"></i> الفواتير
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab" aria-controls="transactions" aria-selected="false">
                                <i class="fas fa-exchange-alt me-1"></i> المعاملات
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab" aria-controls="notes" aria-selected="false">
                                <i class="fas fa-sticky-note me-1"></i> الملاحظات
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="customerTabsContent">
                        <!-- تبويب الفواتير -->
                        <div class="tab-pane fade show active" id="invoices" role="tabpanel" aria-labelledby="invoices-tab">
                            <div class="content-box">
                                <div class="section-title">
                                    <i class="fas fa-receipt"></i>
                                    <span>فواتير العميل</span>
                                </div>
                                
                                <?php if(!empty($customerInvoices)): ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>رقم الفاتورة</th>
                                                <th>التاريخ</th>
                                                <th>عدد العناصر</th>
                                                <th>المبلغ الإجمالي</th>
                                                <th>الحالة</th>
                                                <th>الإجراءات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($customerInvoices as $index => $invoice): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td>#<?= $invoice['invoice_number'] ?></td>
                                                <td><?= date('Y-m-d', strtotime($invoice['created_at'])) ?></td>
                                                <td><?= $invoice['items_count'] ?></td>
                                                <td><?= number_format($invoice['total_amount'], 2) ?> د.ل</td>
                                                <td>
                                                    <?php
                                                    $status_badge = [
                                                        'paid' => 'badge-success',
                                                        'pending' => 'badge-warning',
                                                        'cancelled' => 'badge-danger'
                                                    ];
                                                    $status_text = [
                                                        'paid' => 'مدفوعة',
                                                        'pending' => 'معلقة',
                                                        'cancelled' => 'ملغاة'
                                                    ];
                                                    ?>
                                                    <span class="badge <?= $status_badge[$invoice['status']] ?>">
                                                        <?= $status_text[$invoice['status']] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="invoice.php?id=<?= $invoice['invoice_id'] ?>" class="action-btn" title="عرض">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit_invoice.php?id=<?= $invoice['invoice_id'] ?>" class="action-btn" title="تعديل">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">لا توجد فواتير لهذا العميل</p>
                                    <a href="create_invoice.php?customer_id=<?= $customer_id ?>" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i> إنشاء فاتورة جديدة
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- تبويب المعاملات -->
                        <div class="tab-pane fade" id="transactions" role="tabpanel" aria-labelledby="transactions-tab">
                            <div class="content-box">
                                <div class="section-title">
                                    <i class="fas fa-exchange-alt"></i>
                                    <span>آخر المعاملات</span>
                                </div>
                                
                                <?php if(!empty($customerTransactions)): ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>النوع</th>
                                                <th>الوصف</th>
                                                <th>المبلغ</th>
                                                <th>التاريخ</th>
                                                <th>الحالة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($customerTransactions as $index => $transaction): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td>
                                                    <span class="transaction-type type-<?= $transaction['type'] ?>">
                                                        <i class="fas fa-<?= $transaction['type'] == 'invoice' ? 'receipt' : 'money-bill-wave' ?>"></i>
                                                        <?= $transaction['type'] == 'invoice' ? 'فاتورة' : 'سداد' ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($transaction['description']) ?></td>
                                                <td><?= number_format($transaction['amount'], 2) ?> د.ل</td>
                                                <td><?= date('Y-m-d H:i', strtotime($transaction['created_at'])) ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $transaction['status'] == 'paid' || $transaction['status'] == 'completed' ? 'success' : 'warning' ?>">
                                                        <?= $transaction['status'] == 'paid' || $transaction['status'] == 'completed' ? 'مكتمل' : 'معلق' ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">لا توجد معاملات لهذا العميل</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                       <!-- تبويب الملاحظات -->
<div class="tab-pane fade" id="notes" role="tabpanel" aria-labelledby="notes-tab">
    <div class="content-box">
        <div class="section-title">
            <i class="fas fa-sticky-note"></i>
            <span>ملاحظات العميل</span>
        </div>
        
        <!-- نموذج إضافة ملاحظة -->
        <form method="POST" class="mb-4">
            <div class="row">
                <div class="col-md-10">
                    <textarea name="note_text" class="form-control" rows="3" placeholder="أضف ملاحظة جديدة عن العميل..." required><?= htmlspecialchars($_POST['note_text'] ?? '') ?></textarea>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="add_note" class="btn btn-primary h-100 w-100">
                        <i class="fas fa-plus me-1"></i> إضافة
                    </button>
                </div>
            </div>
        </form>
        
        <!-- قائمة الملاحظات -->
        <?php $customerNotes = getCustomerNotes($db, $customer_id); ?>
        <?php if(!empty($customerNotes)): ?>
            <?php foreach($customerNotes as $note): ?>
            <div class="note-item">
                <div class="note-header">
                    <span class="note-author"><?= htmlspecialchars($note['username'] ?? 'غير معروف') ?></span>
                    <span class="note-date"><?= isset($note['created_at']) ? date('Y-m-d H:i', strtotime($note['created_at'])) : 'غير محدد' ?></span>
                </div>
                <div class="note-text"><?= nl2br(htmlspecialchars($note['note'] ?? '')) ?></div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="fas fa-sticky-note fa-3x text-muted mb-3"></i>
            <p class="text-muted">لا توجد ملاحظات لهذا العميل</p>
        </div>
        <?php endif; ?>
    </div>
</div>

                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // تفعيل تبويبات Bootstrap
        $(document).ready(function() {
            $('#customerTabs button').on('click', function (e) {
                e.preventDefault();
                $(this).tab('show');
            });
        });
    </script>
</body>
</html>