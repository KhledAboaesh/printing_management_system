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

// التحقق من وجود معرف الفاتورة
$invoice_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$invoice_id) {
    $_SESSION['error'] = "معرف الفاتورة غير صالح";
    header("Location: invoices.php");
    exit();
}

// جلب بيانات الفاتورة
try {
    $stmt = $db->prepare("
        SELECT 
            i.*,
            c.name as customer_name,
            c.email as customer_email,
            c.phone as customer_phone,
            u.full_name as created_by_name,
            uc.full_name as cashier_name
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.customer_id
        LEFT JOIN users u ON i.created_by = u.user_id
        LEFT JOIN users uc ON i.cashier_id = uc.user_id
        WHERE i.invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "خطأ في جلب بيانات الفاتورة: " . $e->getMessage();
    header("Location: invoices.php");
    exit();
}

if (!$invoice) {
    $_SESSION['error'] = "الفاتورة غير موجودة";
    header("Location: invoices.php");
    exit();
}

// جلب عناصر الفاتورة
$invoice_items = [];
try {
    $stmt = $db->prepare("
        SELECT ii.*, s.name as service_name, s.description as service_description
        FROM invoice_items ii
        LEFT JOIN services s ON ii.service_id = s.service_id
        WHERE ii.invoice_id = ?
        ORDER BY ii.item_order
    ");
    $stmt->execute([$invoice_id]);
    $invoice_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "خطأ في جلب عناصر الفاتورة: " . $e->getMessage();
}

// جلب المدفوعات
$payments = [];
try {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name as received_by_name, pt.name as payment_type_name
        FROM payments p
        LEFT JOIN users u ON p.received_by = u.user_id
        LEFT JOIN payment_types pt ON p.payment_type_id = pt.type_id
        WHERE p.invoice_id = ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$invoice_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // يمكن تجاهل الخطأ
}

// حساب الإجماليات
$total_paid = 0;
foreach ($payments as $payment) {
    $total_paid += $payment['amount'];
}
$remaining_amount = $invoice['total_amount'] - $total_paid;

// معالجة طلب إضافة دفعة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    if (!hasPermission($_SESSION['user_id'], 'add_payment')) {
        $error = "ليس لديك صلاحية لإضافة دفعات";
    } else {
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $payment_type = filter_input(INPUT_POST, 'payment_type', FILTER_VALIDATE_INT);
        $notes = trim($_POST['notes'] ?? '');

        if ($amount <= 0) {
            $error = "المبلغ يجب أن يكون أكبر من الصفر";
        } elseif ($amount > $remaining_amount) {
            $error = "المبلغ أكبر من المبلغ المتبقي";
        } else {
            try {
                $db->beginTransaction();

                // إضافة الدفعة
                $stmt = $db->prepare("
                    INSERT INTO payments (invoice_id, amount, payment_date, payment_type_id, notes, received_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$invoice_id, $amount, $payment_date, $payment_type, $notes, $_SESSION['user_id']]);

                // تحديث حالة الفاتورة إذا تم سدادها بالكامل
                $new_remaining = $remaining_amount - $amount;
                $new_status = $new_remaining <= 0 ? 'paid' : ($new_remaining < $invoice['total_amount'] ? 'partial' : 'pending');
                
                $stmt = $db->prepare("
                    UPDATE invoices 
                    SET payment_status = ?, last_payment_date = NOW()
                    WHERE invoice_id = ?
                ");
                $stmt->execute([$new_status, $invoice_id]);

                // تسجيل النشاط
                logActivity($_SESSION['user_id'], 'add_payment', 
                           "تم إضافة دفعة للفاتورة {$invoice['invoice_number']} بمبلغ " . number_format($amount, 2));

                $db->commit();

                $_SESSION['success'] = "تم إضافة الدفعة بنجاح";
                header("Location: invoice_details.php?id=" . $invoice_id);
                exit();

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = "حدث خطأ أثناء إضافة الدفعة: " . $e->getMessage();
            }
        }
    }
}

// جلب أنواع الدفع
$payment_types = [];
try {
    $stmt = $db->prepare("SELECT type_id, name FROM payment_types WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $payment_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // يمكن تجاهل الخطأ
}

// معالجة طلب طباعة الفاتورة
if (isset($_GET['print'])) {
    // سيتم إضافة كود الطباعة لاحقاً
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الفاتورة - نظام المطبعة</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .invoice-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin: 20px auto;
            max-width: 1200px;
        }
        
        .invoice-header {
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .invoice-title {
            color: #4361ee;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .invoice-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
            font-weight: 500;
        }
        
        .btn-primary {
            background: #4361ee;
            color: white;
        }
        
        .btn-primary:hover {
            background: #3a56d4;
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-1px);
        }
        
        .btn-outline {
            background: white;
            color: #6c757d;
            border: 1px solid #6c757d;
        }
        
        .btn-outline:hover {
            background: #f8f9fa;
            transform: translateY(-1px);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .info-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        
        .info-card h3 {
            margin-top: 0;
            color: #4361ee;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
        }
        
        .info-label {
            font-weight: 500;
            color: #666;
        }
        
        .info-value {
            font-weight: 600;
            color: #333;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-partial {
            background: #cce7ff;
            color: #004085;
        }
        
        .items-section, .payments-section {
            margin: 30px 0;
        }
        
        .section-title {
            color: #4361ee;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .items-table, .payments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .items-table th, .items-table td,
        .payments-table th, .payments-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: center;
        }
        
        .items-table th, .payments-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .total-row {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .payment-form {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .form-grid {
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
        
        .amount-display {
            background: #e9ecef;
            padding: 10px 15px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .progress-container {
            background: #e9ecef;
            height: 10px;
            border-radius: 5px;
            margin: 15px 0;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 100%;
            background: #28a745;
            transition: width 0.3s ease;
        }
        
        .payment-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5em;
            font-weight: bold;
            margin: 5px 0;
        }
        
        .stat-label {
            font-size: 0.9em;
            color: #666;
        }
        
        .text-success { color: #28a745; }
        .text-warning { color: #ffc107; }
        .text-danger { color: #dc3545; }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        @media (max-width: 768px) {
            .invoice-container {
                padding: 20px;
                margin: 10px;
            }
            
            .invoice-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .items-table, .payments-table {
                font-size: 12px;
            }
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .invoice-container {
                box-shadow: none;
                padding: 0;
            }
            
            .btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <h1>تفاصيل الفاتورة</h1>
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
            
            <div class="invoice-container">
                <!-- رأس الفاتورة -->
                <div class="invoice-header">
                    <div>
                        <h2 class="invoice-title">
                            <i class="fas fa-receipt"></i> الفاتورة #<?= htmlspecialchars($invoice['invoice_number']) ?>
                        </h2>
                        <span class="badge <?= 
                            $invoice['payment_status'] === 'paid' ? 'badge-paid' : 
                            ($invoice['payment_status'] === 'partial' ? 'badge-partial' : 'badge-pending')
                        ?>">
                            <?= 
                                $invoice['payment_status'] === 'paid' ? 'مدفوعة' : 
                                ($invoice['payment_status'] === 'partial' ? 'مدفوعة جزئياً' : 'معلقة')
                            ?>
                        </span>
                    </div>
                    
                    <div class="invoice-actions no-print">
                        <a href="invoices.php" class="btn btn-outline">
                            <i class="fas fa-arrow-right"></i> العودة للقائمة
                        </a>
                        <a href="invoice_details.php?id=<?= $invoice_id ?>&print=1" class="btn btn-warning" target="_blank">
                            <i class="fas fa-print"></i> طباعة
                        </a>
                        <?php if (hasPermission($_SESSION['user_id'], 'edit_invoice')): ?>
                        <a href="edit_invoice.php?id=<?= $invoice_id ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> تعديل
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- معلومات الفاتورة -->
                <div class="info-grid">
                    <div class="info-card">
                        <h3><i class="fas fa-info-circle"></i> معلومات الفاتورة</h3>
                        <div class="info-item">
                            <span class="info-label">رقم الفاتورة:</span>
                            <span class="info-value"><?= htmlspecialchars($invoice['invoice_number']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">تاريخ الإصدار:</span>
                            <span class="info-value"><?= date('Y-m-d', strtotime($invoice['issue_date'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">تاريخ الاستحقاق:</span>
                            <span class="info-value"><?= date('Y-m-d', strtotime($invoice['due_date'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">الحالة:</span>
                            <span class="info-value">
                                <span class="badge <?= 
                                    $invoice['payment_status'] === 'paid' ? 'badge-paid' : 
                                    ($invoice['payment_status'] === 'partial' ? 'badge-partial' : 'badge-pending')
                                ?>">
                                    <?= 
                                        $invoice['payment_status'] === 'paid' ? 'مدفوعة' : 
                                        ($invoice['payment_status'] === 'partial' ? 'مدفوعة جزئياً' : 'معلقة')
                                    ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">تم الإنشاء بواسطة:</span>
                            <span class="info-value"><?= htmlspecialchars($invoice['created_by_name']) ?></span>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h3><i class="fas fa-user"></i> معلومات العميل</h3>
                        <div class="info-item">
                            <span class="info-label">اسم العميل:</span>
                            <span class="info-value"><?= htmlspecialchars($invoice['customer_name']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">البريد الإلكتروني:</span>
                            <span class="info-value"><?= htmlspecialchars($invoice['customer_email'] ?? 'غير محدد') ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">الهاتف:</span>
                            <span class="info-value"><?= htmlspecialchars($invoice['customer_phone'] ?? 'غير محدد') ?></span>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h3><i class="fas fa-calculator"></i> الإجماليات</h3>
                        <div class="info-item">
                            <span class="info-label">المبلغ الإجمالي:</span>
                            <span class="info-value"><?= number_format($invoice['total_amount'], 2) ?> د.ل</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">المبلغ المدفوع:</span>
                            <span class="info-value text-success"><?= number_format($total_paid, 2) ?> د.ل</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">المبلغ المتبقي:</span>
                            <span class="info-value <?= $remaining_amount > 0 ? 'text-danger' : 'text-success' ?>">
                                <?= number_format($remaining_amount, 2) ?> د.ل
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- شريط التقدم -->
                <div class="progress-container">
                    <div class="progress-bar" style="width: <?= $invoice['total_amount'] > 0 ? ($total_paid / $invoice['total_amount'] * 100) : 0 ?>%"></div>
                </div>
                
                <!-- عناصر الفاتورة -->
                <div class="items-section">
                    <h3 class="section-title">
                        <i class="fas fa-list"></i> عناصر الفاتورة
                    </h3>
                    
                    <?php if (!empty($invoice_items)): ?>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الخدمة</th>
                                <th>الوصف</th>
                                <th>الكمية</th>
                                <th>سعر الوحدة</th>
                                <th>الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoice_items as $index => $item): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($item['service_name'] ?? $item['description']) ?></td>
                                <td><?= htmlspecialchars($item['notes'] ?? '') ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td><?= number_format($item['unit_price'], 2) ?> د.ل</td>
                                <td><?= number_format($item['quantity'] * $item['unit_price'], 2) ?> د.ل</td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="5" style="text-align: left;">المبلغ الإجمالي</td>
                                <td><strong><?= number_format($invoice['total_amount'], 2) ?> د.ل</strong></td>
                            </tr>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align: center; padding: 20px; color: #666;">
                        <i class="fas fa-exclamation-circle"></i> لا توجد عناصر في هذه الفاتورة
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- المدفوعات -->
                <div class="payments-section">
                    <h3 class="section-title">
                        <i class="fas fa-credit-card"></i> سجل المدفوعات
                    </h3>
                    
                    <!-- إحصائيات سريعة -->
                    <div class="payment-stats">
                        <div class="stat-card">
                            <div class="stat-label">المبلغ الإجمالي</div>
                            <div class="stat-number"><?= number_format($invoice['total_amount'], 2) ?> د.ل</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">المدفوع</div>
                            <div class="stat-number text-success"><?= number_format($total_paid, 2) ?> د.ل</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">المتبقي</div>
                            <div class="stat-number <?= $remaining_amount > 0 ? 'text-danger' : 'text-success' ?>">
                                <?= number_format($remaining_amount, 2) ?> د.ل
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">نسبة السداد</div>
                            <div class="stat-number"><?= $invoice['total_amount'] > 0 ? round(($total_paid / $invoice['total_amount']) * 100, 2) : 0 ?>%</div>
                        </div>
                    </div>
                    
                    <!-- نموذج إضافة دفعة -->
                    <?php if ($remaining_amount > 0 && hasPermission($_SESSION['user_id'], 'add_payment')): ?>
                    <div class="payment-form no-print">
                        <h4><i class="fas fa-plus-circle"></i> إضافة دفعة جديدة</h4>
                        <form method="POST">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">المبلغ المتبقي</label>
                                    <div class="amount-display text-danger"><?= number_format($remaining_amount, 2) ?> د.ل</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="amount" class="form-label">المبلغ <span class="text-danger">*</span></label>
                                    <input type="number" id="amount" name="amount" class="form-control" 
                                           step="0.01" min="0.01" max="<?= $remaining_amount ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="payment_date" class="form-label">تاريخ الدفع</label>
                                    <input type="date" id="payment_date" name="payment_date" class="form-control" 
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="payment_type" class="form-label">طريقة الدفع</label>
                                    <select id="payment_type" name="payment_type" class="form-control" required>
                                        <option value="">-- اختر طريقة الدفع --</option>
                                        <?php foreach ($payment_types as $type): ?>
                                            <option value="<?= $type['type_id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="notes" class="form-label">ملاحظات</label>
                                    <input type="text" id="notes" name="notes" class="form-control" 
                                           placeholder="ملاحظات حول الدفعة...">
                                </div>
                                
                                <div class="form-group">
                                    <button type="submit" name="add_payment" class="btn btn-success">
                                        <i class="fas fa-check"></i> تأكيد الدفعة
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <!-- جدول المدفوعات -->
                    <?php if (!empty($payments)): ?>
                    <table class="payments-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المبلغ</th>
                                <th>تاريخ الدفع</th>
                                <th>طريقة الدفع</th>
                                <th>ملاحظات</th>
                                <th>تم الاستلام بواسطة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $index => $payment): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td class="text-success"><?= number_format($payment['amount'], 2) ?> د.ل</td>
                                <td><?= date('Y-m-d', strtotime($payment['payment_date'])) ?></td>
                                <td><?= htmlspecialchars($payment['payment_type_name']) ?></td>
                                <td><?= htmlspecialchars($payment['notes'] ?? '') ?></td>
                                <td><?= htmlspecialchars($payment['received_by_name']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align: center; padding: 20px; color: #666;">
                        <i class="fas fa-exclamation-circle"></i> لا توجد مدفوعات مسجلة لهذه الفاتورة
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // تحديث الحد الأقصى للمبلغ عند التحميل
        document.addEventListener('DOMContentLoaded', function() {
            const amountInput = document.getElementById('amount');
            const remainingAmount = <?= $remaining_amount ?>;
            
            if (amountInput) {
                amountInput.max = remainingAmount;
                amountInput.placeholder = 'أقصى مبلغ: ' + remainingAmount.toFixed(2);
            }
            
            // طباعة الفاتورة عند الطلب
            <?php if (isset($_GET['print'])): ?>
                window.print();
            <?php endif; ?>
        });
        
        // التحقق من المبلغ قبل الإرسال
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const amountInput = document.getElementById('amount');
            if (amountInput) {
                const amount = parseFloat(amountInput.value);
                if (amount > <?= $remaining_amount ?>) {
                    e.preventDefault();
                    alert('المبلغ المدخل أكبر من المبلغ المتبقي!');
                    amountInput.focus();
                }
            }
        });
    </script>
</body>
</html>