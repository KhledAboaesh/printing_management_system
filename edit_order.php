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

// التحقق من وجود معرف الطلب
if(!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "معرف الطلب غير صحيح";
    header("Location: orders.php");
    exit();
}

$order_id = intval($_GET['id']);

// جلب بيانات الطلب
try {
    $stmt = $db->prepare("
        SELECT o.*, c.name as customer_name, c.phone as customer_phone, 
               c.email as customer_email, u.username as approved_by_name
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.customer_id 
        LEFT JOIN users u ON o.approved_by = u.user_id 
        WHERE o.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$order) {
        $_SESSION['error_message'] = "الطلب غير موجود";
        header("Location: orders.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "حدث خطأ أثناء جلب بيانات الطلب: " . $e->getMessage();
    header("Location: orders.php");
    exit();
}

// جلب عناصر الطلب
try {
    $stmt = $db->prepare("
        SELECT oi.*, i.name as product_name, i.selling_price as default_price
        FROM order_items oi 
        LEFT JOIN items i ON oi.item_id = i.item_id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "حدث خطأ أثناء جلب عناصر الطلب: " . $e->getMessage();
    header("Location: orders.php");
    exit();
}

// جلب البيانات الأخرى اللازمة للنموذج
$customers = getCustomers($db);
$products = getProducts($db);

// معالجة تحديث الطلب إذا تم إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    try {
        $db->beginTransaction();
        
        // تحديث بيانات الطلب الأساسية
        $stmt = $db->prepare("
            UPDATE orders SET 
            customer_id = ?, 
            required_date = ?, 
            priority = ?, 
            status = ?,
            notes = ?,
            special_instructions = ?,
            total_amount = ?,
            updated_at = NOW()
            WHERE order_id = ?
        ");
        
        $total_amount = floatval($_POST['total_amount']);
        
        $stmt->execute([
            $_POST['customer_id'],
            $_POST['required_date'],
            $_POST['priority'],
            $_POST['status'],
            $_POST['notes'],
            $_POST['special_instructions'],
            $total_amount,
            $order_id
        ]);
        
        // حذف العناصر القديمة
        $stmt = $db->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        
        // إضافة العناصر الجديدة
        if(isset($_POST['items']) && is_array($_POST['items'])) {
            $item_stmt = $db->prepare("
                INSERT INTO order_items 
                (order_id, item_id, quantity, unit_price, discount, specifications) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach($_POST['items'] as $item) {
                $item_stmt->execute([
                    $order_id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price'],
                    $item['discount'],
                    $item['specifications']
                ]);
            }
        }
        
        $db->commit();
        
        $_SESSION['success_message'] = "تم تحديث الطلب بنجاح";
        header("Location: view_order.php?id=$order_id");
        exit();
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error_message'] = "حدث خطأ أثناء تحديث الطلب: " . $e->getMessage();
        header("Location: edit_order.php?id=$order_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الطلب - نظام المطبعة</title>
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
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .order-info {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .info-label {
            font-size: 12px;
            color: var(--gray);
        }
        
        .info-value {
            font-size: 14px;
            font-weight: 500;
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
        
        .remove-item {
            margin-bottom: 8px;
        }
        
        .totals-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .form-section {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #155724;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .order-info {
                width: 100%;
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
                    <h1 class="h2">تعديل الطلب</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="orders.php" class="btn btn-secondary me-2">
                            <i class="fas fa-arrow-right me-1"></i> رجوع
                        </a>
                        <a href="view_order.php?id=<?= $order_id ?>" class="btn btn-outline-primary">
                            <i class="fas fa-eye me-1"></i> عرض الطلب
                        </a>
                    </div>
                </div>
                
                <!-- رسائل التنبيه -->
                <?php if(isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?= $_SESSION['success_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['error_message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
                
                <div class="content-box">
                    <!-- معلومات الطلب -->
                    <div class="order-header">
                        <div class="order-info">
                            <div class="info-item">
                                <span class="info-label">رقم الطلب</span>
                                <span class="info-value">ORD-<?= str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">تاريخ الإنشاء</span>
                                <span class="info-value"><?= date('Y-m-d', strtotime($order['order_date'])) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">آخر تحديث</span>
                                <span class="info-value"><?= !empty($order['updated_at']) ? date('Y-m-d H:i', strtotime($order['updated_at'])) : '---' ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">الحالة الحالية</span>
                                <?php
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
                                ?>
                                <span class="badge <?= $statusConfig[$order['status']]['class'] ?? 'badge-secondary' ?>">
                                    <?= $statusConfig[$order['status']]['label'] ?? $order['status'] ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- نموذج تعديل الطلب -->
                    <form id="orderForm" method="POST">
                        <input type="hidden" name="update_order" value="1">
                        
                        <!-- معلومات أساسية -->
                        <div class="form-section">
                            <h3 class="section-title">المعلومات الأساسية</h3>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="customer_id" class="form-label">العميل</label>
                                    <select class="form-select" id="customer_id" name="customer_id" required>
                                        <option value="">اختر عميل...</option>
                                        <?php foreach($customers as $customer): ?>
                                        <option value="<?= $customer['customer_id'] ?>" 
                                            <?= $customer['customer_id'] == $order['customer_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($customer['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="required_date" class="form-label">تاريخ التسليم المطلوب</label>
                                    <input type="date" class="form-control" id="required_date" name="required_date" 
                                           value="<?= !empty($order['required_date']) ? date('Y-m-d', strtotime($order['required_date'])) : '' ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="priority" class="form-label">الأولوية</label>
                                    <select class="form-select" id="priority" name="priority">
                                        <option value="low" <?= $order['priority'] == 'low' ? 'selected' : '' ?>>منخفض</option>
                                        <option value="medium" <?= $order['priority'] == 'medium' || empty($order['priority']) ? 'selected' : '' ?>>متوسط</option>
                                        <option value="high" <?= $order['priority'] == 'high' ? 'selected' : '' ?>>عالي</option>
                                        <option value="urgent" <?= $order['priority'] == 'urgent' ? 'selected' : '' ?>>عاجل</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="status" class="form-label">الحالة</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="pending" <?= $order['status'] == 'pending' ? 'selected' : '' ?>>معلق</option>
                                        <option value="approved" <?= $order['status'] == 'approved' ? 'selected' : '' ?>>تمت الموافقة</option>
                                        <option value="design" <?= $order['status'] == 'design' ? 'selected' : '' ?>>تصميم</option>
                                        <option value="production" <?= $order['status'] == 'production' ? 'selected' : '' ?>>إنتاج</option>
                                        <option value="ready" <?= $order['status'] == 'ready' ? 'selected' : '' ?>>جاهز</option>
                                        <option value="delivered" <?= $order['status'] == 'delivered' ? 'selected' : '' ?>>تم التسليم</option>
                                        <option value="completed" <?= $order['status'] == 'completed' ? 'selected' : '' ?>>مكتمل</option>
                                        <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : '' ?>>ملغى</option>
                                        <option value="rejected" <?= $order['status'] == 'rejected' ? 'selected' : '' ?>>مرفوض</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="notes" class="form-label">ملاحظات</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- عناصر الطلب -->
                        <div class="form-section">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h3 class="section-title mb-0">عناصر الطلب</h3>
                                <button type="button" id="addItemBtn" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i> إضافة منتج
                                </button>
                            </div>
                            
                            <div id="orderItemsContainer">
                                <?php if(empty($order_items)): ?>
                                    <!-- عنصر افتراضي إذا لم يكن هناك عناصر -->
                                    <div class="order-item-row">
                                        <div class="row">
                                            <div class="col-md-5 mb-2">
                                                <label class="form-label">المنتج</label>
                                                <select class="form-select product-select" name="items[0][product_id]" required>
                                                    <option value="">اختر منتج...</option>
                                                    <?php foreach($products as $product): ?>
                                                    <option value="<?= $product['item_id'] ?>" 
                                                        data-price="<?= $product['selling_price'] ?>">
                                                        <?= htmlspecialchars($product['name']) ?> - <?= number_format($product['selling_price'], 2) ?> د.ل
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <label class="form-label">الكمية</label>
                                                <input type="number" class="form-control quantity" name="items[0][quantity]" value="1" min="1" required>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <label class="form-label">السعر</label>
                                                <input type="number" class="form-control price" name="items[0][price]" step="0.01" required>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <label class="form-label">الخصم</label>
                                                <input type="number" class="form-control discount" name="items[0][discount]" value="0" min="0" step="0.01">
                                            </div>
                                            <div class="col-md-1 d-flex align-items-end mb-2">
                                                <button type="button" class="btn btn-sm btn-danger remove-item">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-md-12">
                                                <label class="form-label">مواصفات خاصة</label>
                                                <textarea class="form-control specifications" name="items[0][specifications]" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- عرض العناصر الموجودة -->
                                    <?php foreach($order_items as $index => $item): ?>
                                    <div class="order-item-row">
                                        <div class="row">
                                            <div class="col-md-5 mb-2">
                                                <label class="form-label">المنتج</label>
                                                <select class="form-select product-select" name="items[<?= $index ?>][product_id]" required>
                                                    <option value="">اختر منتج...</option>
                                                    <?php foreach($products as $product): ?>
                                                    <option value="<?= $product['item_id'] ?>" 
                                                        data-price="<?= $product['selling_price'] ?>"
                                                        <?= $product['item_id'] == $item['item_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($product['name']) ?> - <?= number_format($product['selling_price'], 2) ?> د.ل
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <label class="form-label">الكمية</label>
                                                <input type="number" class="form-control quantity" name="items[<?= $index ?>][quantity]" 
                                                       value="<?= $item['quantity'] ?>" min="1" required>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <label class="form-label">السعر</label>
                                                <input type="number" class="form-control price" name="items[<?= $index ?>][price]" 
                                                       value="<?= $item['unit_price'] ?>" step="0.01" required>
                                            </div>
                                            <div class="col-md-2 mb-2">
                                                <label class="form-label">الخصم</label>
                                                <input type="number" class="form-control discount" name="items[<?= $index ?>][discount]" 
                                                       value="<?= $item['discount'] ?>" min="0" step="0.01">
                                            </div>
                                            <div class="col-md-1 d-flex align-items-end mb-2">
                                                <button type="button" class="btn btn-sm btn-danger remove-item">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-md-12">
                                                <label class="form-label">مواصفات خاصة</label>
                                                <textarea class="form-control specifications" name="items[<?= $index ?>][specifications]" rows="2"><?= htmlspecialchars($item['specifications'] ?? '') ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- معلومات إضافية -->
                        <div class="form-section">
                            <h3 class="section-title">معلومات إضافية</h3>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="special_instructions" class="form-label">تعليمات خاصة</label>
                                    <textarea class="form-control" id="special_instructions" name="special_instructions" rows="3"><?= htmlspecialchars($order['special_instructions'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- الإجماليات -->
                        <div class="totals-card">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="total_amount" class="form-label">الإجمالي النهائي</label>
                                        <input type="number" class="form-control" id="total_amount" name="total_amount" 
                                               value="<?= $order['total_amount'] ?>" step="0.01" required readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>المجموع:</span>
                                                <span id="subtotalAmount">0.00 د.ل</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>الخصم:</span>
                                                <span id="discountAmount">0.00 د.ل</span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>الضريبة (15%):</span>
                                                <span id="taxAmount">0.00 د.ل</span>
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between fw-bold">
                                                <span>الإجمالي النهائي:</span>
                                                <span id="totalAmountDisplay">0.00 د.ل</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- أزرار الإجراءات -->
                        <div class="action-buttons">
                            <a href="orders.php" class="btn btn-secondary">إلغاء</a>
                            <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // تعريف تسميات وألوان الحالات
            const statusConfig = {
                'pending': { label: 'معلق', class: 'badge-pending' },
                'approved': { label: 'تمت الموافقة', class: 'badge-approved' },
                'design': { label: 'تصميم', class: 'badge-design' },
                'production': { label: 'إنتاج', class: 'badge-production' },
                'ready': { label: 'جاهز', class: 'badge-ready' },
                'delivered': { label: 'تم التسليم', class'badge-delivered' },
                'completed': { label: 'مكتمل', class: 'badge-completed' },
                'cancelled': { label: 'ملغى', class: 'badge-cancelled' },
                'rejected': { label: 'مرفوض', class: 'badge-rejected' }
            };
            
            // تحديث عرض الحالة عند التغيير
            $('#status').change(function() {
                const status = $(this).val();
                const statusInfo = statusConfig[status] || { label: status, class: 'badge-secondary' };
                
                // تحديث البادج في رأس الصفحة
                $('.order-header .badge')
                    .removeClass()
                    .addClass('badge ' + statusInfo.class)
                    .text(statusInfo.label);
            });
            
            // إضافة عنصر طلب جديد
            let itemCounter = <?= empty($order_items) ? 1 : count($order_items) ?>;
            $('#addItemBtn').click(function() {
                const newItem = `
                    <div class="order-item-row">
                        <div class="row">
                            <div class="col-md-5 mb-2">
                                <select class="form-select product-select" name="items[${itemCounter}][product_id]" required>
                                    <option value="">اختر منتج...</option>
                                    <?php foreach($products as $product): ?>
                                    <option value="<?= $product['item_id'] ?>" 
                                        data-price="<?= $product['selling_price'] ?>">
                                        <?= htmlspecialchars($product['name']) ?> - <?= number_format($product['selling_price'], 2) ?> د.ل
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <input type="number" class="form-control quantity" name="items[${itemCounter}][quantity]" value="1" min="1" required>
                            </div>
                            <div class="col-md-2 mb-2">
                                <input type="number" class="form-control price" name="items[${itemCounter}][price]" step="0.01" required>
                            </div>
                            <div class="col-md-2 mb-2">
                                <input type="number" class="form-control discount" name="items[${itemCounter}][discount]" value="0" min="0" step="0.01">
                            </div>
                            <div class="col-md-1 d-flex align-items-end mb-2">
                                <button type="button" class="btn btn-sm btn-danger remove-item">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <textarea class="form-control specifications" name="items[${itemCounter}][specifications]" rows="2" placeholder="مواصفات خاصة"></textarea>
                            </div>
                        </div>
                    </div>
                `;
                $('#orderItemsContainer').append(newItem);
                itemCounter++;
            });
            
            // إزالة عنصر طلب
            $(document).on('click', '.remove-item', function() {
                if($('#orderItemsContainer .order-item-row').length > 1) {
                    $(this).closest('.order-item-row').remove();
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
                const price = $(this).find(':selected').data('price');
                const row = $(this).closest('.order-item-row');
                if(price) {
                    row.find('.price').val(price);
                }
                calculateItemTotal(row);
            });
            
            // حساب إجمالي العنصر
            $(document).on('change', '.quantity, .price, .discount', function() {
                const row = $(this).closest('.order-item-row');
                calculateItemTotal(row);
            });
            
            function calculateItemTotal(row) {
                const quantity = parseFloat(row.find('.quantity').val()) || 0;
                const price = parseFloat(row.find('.price').val()) || 0;
                const discount = parseFloat(row.find('.discount').val()) || 0;
                
                // يمكن إضافة منطق لحساب إجمالي العنصر هنا إذا لزم الأمر
                calculateTotals();
            }
            
            // حساب الإجماليات النهائية
            function calculateTotals() {
                let subtotal = 0;
                let totalDiscount = 0;
                
                $('#orderItemsContainer .order-item-row').each(function() {
                    const quantity = parseFloat($(this).find('.quantity').val()) || 0;
                    const price = parseFloat($(this).find('.price').val()) || 0;
                    const discount = parseFloat($(this).find('.discount').val()) || 0;
                    
                    subtotal += (price * quantity);
                    totalDiscount += discount;
                });
                
                const tax = subtotal * 0.15; // ضريبة 15%
                const total = subtotal + tax - totalDiscount;
                
                $('#subtotalAmount').text(subtotal.toFixed(2) + ' د.ل');
                $('#discountAmount').text(totalDiscount.toFixed(2) + ' د.ل');
                $('#taxAmount').text(tax.toFixed(2) + ' د.ل');
                $('#totalAmountDisplay').text(total.toFixed(2) + ' د.ل');
                $('#total_amount').val(total.toFixed(2));
            }
            
            // حساب الإجماليات عند تحميل الصفحة
            calculateTotals();
            
            // إرسال النموذج
            $('#orderForm').submit(function(e) {
                e.preventDefault();
                
                // التحقق من صحة البيانات قبل الإرسال
                let isValid = true;
                let errorMessage = '';
                
                // التحقق من أن هناك عناصر في الطلب
                if($('#orderItemsContainer .order-item-row').length === 0) {
                    isValid = false;
                    errorMessage = 'يجب إضافة منتج واحد على الأقل إلى الطلب';
                }
                
                // التحقق من صحة تاريخ التسليم
                const orderDate = new Date('<?= $order['order_date'] ?>');
                const requiredDate = new Date($('#required_date').val());
                
                if(requiredDate <= orderDate) {
                    isValid = false;
                    errorMessage = 'تاريخ التسليم المطلوب يجب أن يكون بعد تاريخ إنشاء الطلب';
                }
                
                if(!isValid) {
                    Swal.fire({
                        title: 'خطأ في البيانات',
                        text: errorMessage,
                        icon: 'error',
                        confirmButtonColor: '#4361ee'
                    });
                    return false;
                }
                
                // إرسال النموذج
                this.submit();
            });
        });
    </script>
</body>
</html>