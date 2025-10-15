<?php
// إدارة الجلسة والتحقق من المصادقة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// تعريف المسارات وتضمين الملفات الأساسية
define('INCLUDED', true);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// التحقق من وجود معرف الطلب
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$order_id) {
    $_SESSION['error_message'] = "معرف الطلب غير صالح";
    header("Location: orders.php");
    exit();
}

// جلب بيانات الطلب
$order = getOrderDetails($db, $order_id);
if (!$order) {
    $_SESSION['error_message'] = "الطلب غير موجود";
    header("Location: orders.php");
    exit();
}

// جلب عناصر الطلب
$order_items = getOrderItems($db, $order_id);

// جلب بيانات الدفع إذا كانت موجودة
$payment_data = getOrderPaymentData($db, $order_id);

// =======================
// معالجة التحديثات
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1) تحديث حالة الطلب
    if (isset($_POST['change_status'])) {
        $new_status = $_POST['status'];
        $rejection_reason = $_POST['rejection_reason'] ?? null;

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
    }

    // 2) تحديث الأسعار والدفع (من النموذج الجديد)
    if (isset($_POST['update_payment'])) {
        $subtotal        = $_POST['subtotal']        ?? 0;
        $total_discount  = $_POST['total_discount']  ?? 0;
        $tax_amount      = $_POST['tax_amount']      ?? 0;
        $total_amount    = $_POST['total_amount']    ?? 0;
        $deposit         = $_POST['deposit']         ?? 0;
        $amount_paid     = $_POST['amount_paid']     ?? 0;
        $total_profit    = $_POST['total_profit']    ?? 0;

        $paid_total = $deposit + $amount_paid;

        // تحديد حالة الدفع
        if ($paid_total >= $total_amount && $total_amount > 0) {
            $payment_status = "paid"; // تم الدفع بالكامل
        } elseif ($paid_total > 0) {
            $payment_status = "partial"; // دفع جزئي
        } else {
            $payment_status = "unpaid"; // لم يتم الدفع
        }

        try {
            $db->beginTransaction();

            // تحديث جدول الطلبات
            $stmt = $db->prepare("UPDATE orders SET 
                                    subtotal = ?,
                                    total_discount = ?,
                                    tax_amount = ?,
                                    total_amount = ?,
                                    deposit_amount = ?,
                                    amount_paid = ?,
                                    total_profit = ?,
                                    payment_status = ?
                                  WHERE order_id = ?");
            $stmt->execute([
                $subtotal,
                $total_discount,
                $tax_amount,
                $total_amount,
                $deposit,
                $amount_paid,
                $total_profit,
                $payment_status,
                $order_id
            ]);

            $db->commit();

            $_SESSION['success_message'] = "تم تحديث بيانات الدفع والسعر بنجاح";
            header("Location: view_order.php?id=$order_id");
            exit();

        } catch (PDOException $e) {
            $db->rollBack();
            $_SESSION['error_message'] = "حدث خطأ أثناء تحديث الدفع: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عرض الطلب #<?= $order_id ?> - نظام المطبعة</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
        .order-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        
        .order-title {
            color: var(--primary);
            margin: 0;
            font-weight: 700;
        }
        
        .order-meta {
            display: flex;
            gap: 25px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .order-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray);
            font-size: 14px;
        }
        
        .order-meta-item i {
            color: var(--primary);
            width: 16px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin: 20px 0;
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
        
        /* ألوان الحالات */
        .badge-pending {
            background-color: rgba(248, 150, 30, 0.1);
            color: #f8961e;
        }
        
        .badge-approved {
            background-color: rgba(67, 97, 238, 0.1);
            color: #4361ee;
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
        
        .badge-completed {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .badge-rejected {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .badge-cancelled {
            background-color: rgba(247, 37, 133, 0.1);
            color: #f72585;
        }
        
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
        }
        
        .status-section {
            margin-top: 30px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }
        
        .summary-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #e9ecef;
        }
        
        .payment-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #e9ecef;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--dark);
        }
        
        .info-value {
            color: var(--gray);
        }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #f8f9fa;
            color: var(--gray);
            margin-left: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .action-btn:hover {
            background-color: var(--primary);
            color: white;
            transform: scale(1.1);
        }
        
        .btn-custom {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-primary-custom {
            background-color: var(--primary);
            color: white;
            border: none;
        }
        
        .btn-primary-custom:hover {
            background-color: var(--secondary);
            color: white;
        }
        
        .btn-secondary-custom {
            background-color: #6c757d;
            color: white;
            border: none;
        }
        
        .btn-secondary-custom:hover {
            background-color: #5a6268;
            color: white;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .alert-custom {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .alert-success-custom {
            background-color: rgba(40, 167, 69, 0.1);
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger-custom {
            background-color: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        @media (max-width: 768px) {
            .order-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .action-buttons .btn-custom {
                width: 100%;
                justify-content: center;
            }
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
                    <h1 class="h2">تفاصيل الطلب #<?= $order_id ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="orders.php" class="btn btn-primary me-2">
                            <i class="fas fa-arrow-right me-1"></i> العودة للقائمة
                        </a>
                        <a href="print_order.php?id=<?= $order_id ?>" class="btn btn-secondary" target="_blank">
                            <i class="fas fa-print me-1"></i> طباعة الطلب
                        </a>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-custom alert-danger-custom mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-custom alert-success-custom mb-4">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="content-box">
                    <div class="order-header">
                        <h2 class="order-title">معلومات الطلب الأساسية</h2>
                        <div class="order-meta">
                            <div class="order-meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span>تاريخ الطلب: <?= date('Y-m-d', strtotime($order['order_date'])) ?></span>
                            </div>
                            <div class="order-meta-item">
                                <i class="fas fa-user"></i>
                                <span>العميل: <?= htmlspecialchars($order['customer_name']) ?></span>
                            </div>
                            <div class="order-meta-item">
                                <i class="fas fa-phone"></i>
                                <span>هاتف: <?= htmlspecialchars($order['phone']) ?></span>
                            </div>
                            <div class="order-meta-item">
                                <i class="fas fa-envelope"></i>
                                <span>بريد: <?= htmlspecialchars($order['email'] ?? '---') ?></span>
                            </div>
                            <div class="order-meta-item">
                                <?php
                                $status_badges = [
                                    'pending' => ['text' => 'معلق', 'class' => 'badge-pending'],
                                    'approved' => ['text' => 'تمت الموافقة', 'class' => 'badge-approved'],
                                    'design' => ['text' => 'تصميم', 'class' => 'badge-design'],
                                    'production' => ['text' => 'إنتاج', 'class' => 'badge-production'],
                                    'ready' => ['text' => 'جاهز', 'class' => 'badge-ready'],
                                    'delivered' => ['text' => 'تم التسليم', 'class' => 'badge-delivered'],
                                    'completed' => ['text' => 'مكتمل', 'class' => 'badge-completed'],
                                    'rejected' => ['text' => 'مرفوض', 'class' => 'badge-rejected'],
                                    'cancelled' => ['text' => 'ملغى', 'class' => 'badge-cancelled']
                                ];
                                
                                $priorityConfig = [
                                    'low' => ['label' => 'منخفض', 'class' => 'priority-low'],
                                    'medium' => ['label' => 'متوسط', 'class' => 'priority-medium'],
                                    'high' => ['label' => 'عالي', 'class' => 'priority-high'],
                                    'urgent' => ['label' => 'عاجل', 'class' => 'priority-urgent']
                                ];
                                ?>
                                <span class="badge <?= $status_badges[$order['status']]['class'] ?>">
                                    <?= $status_badges[$order['status']]['text'] ?>
                                </span>
                            </div>
                            <div class="order-meta-item">
                                <i class="fas fa-flag"></i>
                                <span class="<?= $priorityConfig[$order['priority']]['class'] ?? '' ?>">
                                    الأولوية: <?= $priorityConfig[$order['priority']]['label'] ?? $order['priority'] ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-lg-8">
                            <h4 class="mb-3">عناصر الطلب</h4>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th width="5%">#</th>
                                            <th width="15%">الصورة</th>
                                            <th width="35%">المنتج</th>
                                            <th width="15%">الكمية</th>
                                            <th width="15%">السعر</th>
                                            <th width="15%">الإجمالي</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $subtotal = 0;
                                        foreach ($order_items as $index => $item): 
                                            $item_total = $item['quantity'] * $item['unit_price'];
                                            $subtotal += $item_total;
                                        ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td>
                                                <img src="images/products/<?= $item['image'] ?? 'default.png' ?>" 
                                                     class="product-image"
                                                     alt="<?= htmlspecialchars($item['product_name']) ?>">
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($item['product_name']) ?></div>
                                                <?php if (!empty($item['specifications'])): ?>
                                                    <div class="text-muted small mt-1"><?= htmlspecialchars($item['specifications']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $item['quantity'] ?></td>
                                            <td><?= number_format($item['unit_price'], 2) ?> د.ل</td>
                                            <td class="fw-bold"><?= number_format($item_total, 2) ?> د.ل</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- قسم تغيير حالة الطلب -->
                            <div class="status-section">
                                <h4 class="mb-3"><i class="fas fa-exchange-alt me-2"></i> تغيير حالة الطلب</h4>
                                
                                <form method="POST" id="statusForm">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="status" class="form-label fw-bold">الحالة الجديدة</label>
                                                <select name="status" id="status" class="form-select">
                                                    <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>معلق</option>
                                                    <option value="approved" <?= $order['status'] === 'approved' ? 'selected' : '' ?>>تمت الموافقة</option>
                                                    <option value="design" <?= $order['status'] === 'design' ? 'selected' : '' ?>>تصميم</option>
                                                    <option value="production" <?= $order['status'] === 'production' ? 'selected' : '' ?>>إنتاج</option>
                                                    <option value="ready" <?= $order['status'] === 'ready' ? 'selected' : '' ?>>جاهز</option>
                                                    <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>تم التسليم</option>
                                                    <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>مكتمل</option>
                                                    <option value="rejected" <?= $order['status'] === 'rejected' ? 'selected' : '' ?>>مرفوض</option>
                                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>ملغى</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div id="rejectionReasonContainer" style="display: none;">
                                        <div class="mb-3">
                                            <label for="rejection_reason" class="form-label fw-bold">سبب الرفض</label>
                                            <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="3"><?= htmlspecialchars($order['rejection_reason'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="change_status" class="btn btn-primary-custom">
                                        <i class="fas fa-save me-1"></i> حفظ التغييرات
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                       <!-- ملخص الطلب -->
<!-- ملخص الطلب -->




<!-- معلومات إضافية -->
<div class="summary-card">
    <h4 class="mb-3"><i class="fas fa-info-circle me-2"></i> معلومات إضافية</h4>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">تاريخ التسليم المطلوب:</span>
            <span class="info-value"><?= !empty($order['required_date']) ? date('Y-m-d', strtotime($order['required_date'])) : '---' ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">تم الإنشاء بواسطة:</span>
            <span class="info-value"><?= htmlspecialchars($order['created_by_name'] ?? '---') ?></span>
        </div>
        <?php if (!empty($order['special_instructions'])): ?>
        <div class="info-item">
            <span class="info-label">تعليمات خاصة:</span>
            <span class="info-value"><?= htmlspecialchars($order['special_instructions']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($order['notes'])): ?>
        <div class="info-item">
            <span class="info-label">ملاحظات:</span>
            <span class="info-value"><?= htmlspecialchars($order['notes']) ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>


                            
                            <?php if ($order['status'] === 'approved' || $order['status'] === 'rejected'): ?>
                            <div class="summary-card">
                                <h4 class="mb-3"><i class="fas fa-user-check me-2"></i> معلومات الموافقة/الرفض</h4>
                                <div class="info-grid">
                                    <?php if ($order['status'] === 'approved'): ?>
                                        <div class="info-item">
                                            <span class="info-label">تمت الموافقة بواسطة:</span>
                                            <span class="info-value"><?= htmlspecialchars($order['approved_by_name'] ?? 'غير معروف') ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">تاريخ الموافقة:</span>
                                            <span class="info-value"><?= date('Y-m-d H:i', strtotime($order['approved_at'])) ?></span>
                                        </div>
                                    <?php elseif (!empty($order['rejection_reason'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">سبب الرفض:</span>
                                            <span class="info-value"><?= htmlspecialchars($order['rejection_reason']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="action-buttons mt-4">
                        <a href="orders.php" class="btn btn-primary-custom me-2">
                            <i class="fas fa-arrow-right me-1"></i> العودة للقائمة
                        </a>
                        <a href="edit_order.php?id=<?= $order_id ?>" class="btn btn-secondary-custom me-2">
                            <i class="fas fa-edit me-1"></i> تعديل الطلب
                        </a>
                        <a href="print_order.php?id=<?= $order_id ?>" class="btn btn-secondary-custom me-2" target="_blank">
                            <i class="fas fa-print me-1"></i> طباعة الطلب
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // عرض/إخفاء حقل سبب الرفض عند الحاجة
    document.getElementById('status').addEventListener('change', function() {
        const rejectionContainer = document.getElementById('rejectionReasonContainer');
        rejectionContainer.style.display = this.value === 'rejected' ? 'block' : 'none';
    });

    // تنفيذ التغيير عند تحميل الصفحة لتحديد الحالة الحالية
    document.addEventListener('DOMContentLoaded', function() {
        const statusSelect = document.getElementById('status');
        const rejectionContainer = document.getElementById('rejectionReasonContainer');
        rejectionContainer.style.display = statusSelect.value === 'rejected' ? 'block' : 'none';
    });
    
    // تحسينات للعرض على الشاشات الصغيرة
    $(document).ready(function() {
        // إضافة تأثيرات للجدول
        $('.data-table tbody tr').hover(
            function() {
                $(this).css('transform', 'translateX(-5px)');
            },
            function() {
                $(this).css('transform', 'translateX(0)');
            }
        );
    });
    </script>
</body>
</html>