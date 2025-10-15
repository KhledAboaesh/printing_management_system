<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('INCLUDED', true);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// معرف المستخدم
$user_id = $_SESSION['user_id'] ?? 1; // تأكد أن user_id = 1 موجود في جدول users

// التحقق من معرف الفاتورة
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
        SELECT i.*, c.name as customer_name, c.email as customer_email, 
               c.phone, c.company_name, u.full_name as created_by_name
        FROM invoices i 
        JOIN customers c ON i.customer_id = c.customer_id 
        JOIN users u ON i.created_by = u.user_id 
        WHERE i.invoice_id = ? AND i.status != 'cancelled'
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "خطأ في جلب بيانات الفاتورة: " . $e->getMessage();
    header("Location: invoices.php");
    exit();
}

if (!$invoice) {
    $_SESSION['error'] = "الفاتورة غير موجودة أو ملغاة";
    header("Location: invoices.php");
    exit();
}

// إذا كانت الفاتورة مدفوعة بالفعل
if ($invoice['payment_status'] === 'paid') {
    $_SESSION['info'] = "الفاتورة مدفوعة بالفعل";
    header("Location: view_invoice.php?id=" . $invoice_id);
    exit();
}

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');
        
        if (!in_array($payment_method, ['cash', 'bank_transfer', 'credit_card', 'check'])) {
            throw new Exception("طريقة الدفع غير صالحة");
        }
        
        $db->beginTransaction();
        
        // حساب المبلغ المتبقي
        $remaining_amount = $invoice['total_amount'] - $invoice['amount_paid'];
        if ($remaining_amount <= 0) {
            throw new Exception("لا يوجد مبلغ متبٍ للدفع");
        }

        // مجموع المدفوع بعد هذه الدفعة
        $new_amount_paid = $invoice['amount_paid'] + $remaining_amount;

        // تحديد حالة الفاتورة بعد الدفع
        $new_status = $new_amount_paid >= $invoice['total_amount'] ? 'paid' : 'partial';

        // تحديث الفاتورة
        $stmt = $db->prepare("
            UPDATE invoices 
            SET amount_paid = ?, 
                payment_status = ?, 
                payment_date = ?, 
                payment_method = ?
            WHERE invoice_id = ?
        ");
        $stmt->execute([$new_amount_paid, $new_status, $payment_date, $payment_method, $invoice_id]);
        
        // تسجيل الدفعة في جدول invoice_payments
        $stmt = $db->prepare("
            INSERT INTO invoice_payments (invoice_id, amount, payment_method, notes, received_by, payment_date) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invoice_id,
            $remaining_amount,
            $payment_method,
            $notes ?: "تسديد كامل للفاتورة",
            $user_id,
            $payment_date
        ]);
        
        // تسجيل النشاط
        logActivity($user_id, 'invoice_paid', 
            "تم تسديد الفاتورة #{$invoice['invoice_number']} بالكامل. المبلغ: " . number_format($remaining_amount, 2) . " د.ل");
        
        $db->commit();
        
        $_SESSION['success'] = "تم تسديد الفاتورة بالكامل بنجاح";
        header("Location: view_invoice.php?id=" . $invoice_id);
        exit();
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "حدث خطأ أثناء تسديد الفاتورة: " . $e->getMessage();
    }
}

// تعريف مصفوفات للترجمة
$payment_methods = [
    'cash' => 'نقداً',
    'bank_transfer' => 'تحويل بنكي',
    'credit_card' => 'بطاقة ائتمان',
    'check' => 'شيك'
];

// حساب المبلغ المتبقي
$remaining_amount = $invoice['total_amount'] - $invoice['amount_paid'];
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسديد الفاتورة #<?= $invoice['invoice_number'] ?></title>
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
            max-width: 800px;
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .invoice-title {
            color: #4361ee;
            margin: 0;
        }
        
        .invoice-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }
        
        .invoice-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #4361ee;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            outline: none;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #4361ee;
            color: white;
        }
        
        .btn-primary:hover {
            background: #3a56d4;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: white;
            color: #4361ee;
            border: 1px solid #4361ee;
        }
        
        .btn-outline:hover {
            background: #f8f9fa;
        }
        
        .payment-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .payment-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .payment-item:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.2em;
            color: #28a745;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .col-md-6 {
            flex: 0 0 50%;
            padding: 0 10px;
            box-sizing: border-box;
        }
        
        @media (max-width: 768px) {
            .col-md-6 {
                flex: 0 0 100%;
            }
            
            .invoice-header {
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
                <h1>تسديد الفاتورة #<?= $invoice['invoice_number'] ?></h1>
                <?php include 'includes/user-menu.php'; ?>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <div class="invoice-container">
                <div class="invoice-header">
                    <div>
                        <h2 class="invoice-title">تسديد الفاتورة</h2>
                        <div class="invoice-meta">
                            <div class="invoice-meta-item">
                                <i class="fas fa-calendar-alt"></i>
                                <span>تاريخ الفاتورة: <?= date('Y-m-d', strtotime($invoice['issue_date'])) ?></span>
                            </div>
                            <div class="invoice-meta-item">
                                <i class="fas fa-user"></i>
                                <span>العميل: <?= htmlspecialchars($invoice['customer_name']) ?></span>
                            </div>
                            <div class="invoice-meta-item">
                                <span class="badge <?= 
                                    $invoice['payment_status'] === 'paid' ? 'badge-paid' : 
                                    ($invoice['payment_status'] === 'partial' ? 'badge-partial' : 'badge-pending') 
                                ?>">
                                    <?= $invoice['payment_status'] === 'paid' ? 'مدفوعة' : 
                                        ($invoice['payment_status'] === 'partial' ? 'مدفوع جزئياً' : 'قيد الانتظار') 
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ملخص الفاتورة -->
                <div class="payment-summary">
                    <h3>ملخص الفاتورة</h3>
                    <div class="payment-item">
                        <span>إجمالي الفاتورة:</span>
                        <span><?= number_format($invoice['total_amount'], 2) ?> د.ل</span>
                    </div>
                    <div class="payment-item">
                        <span>المبلغ المدفوع مسبقاً:</span>
                        <span><?= number_format($invoice['amount_paid'], 2) ?> د.ل</span>
                    </div>
                    <div class="payment-item">
                        <span>العربون:</span>
                        <span><?= number_format($invoice['deposit_amount'], 2) ?> د.ل</span>
                    </div>
                    <div class="payment-item">
                        <span>المبلغ المتبقي للدفع:</span>
                        <span><?= number_format($remaining_amount, 2) ?> د.ل</span>
                    </div>
                </div>
                
                <!-- نموذج تسديد الفاتورة -->
                <form method="POST" id="payment-form">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="payment_method" class="form-label">طريقة الدفع <span class="text-danger">*</span></label>
                                <select id="payment_method" name="payment_method" class="form-control" required>
                                    <?php foreach ($payment_methods as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= $value === ($invoice['payment_method'] ?? 'cash') ? 'selected' : '' ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="payment_date" class="form-label">تاريخ الدفع <span class="text-danger">*</span></label>
                                <input type="date" id="payment_date" name="payment_date" 
                                       class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes" class="form-label">ملاحظات</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3" 
                                  placeholder="ملاحظات إضافية حول عملية الدفع..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <div class="payment-item" style="background: #e8f5e8; padding: 15px; border-radius: 5px;">
                            <span style="font-size: 1.1em;">المبلغ الذي سيتم دفعه:</span>
                            <span style="font-size: 1.3em; font-weight: bold; color: #28a745;">
                                <?= number_format($remaining_amount, 2) ?> د.ل
                            </span>
                        </div>
                    </div>
                    
                    <div class="actions" style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check-circle"></i> تأكيد التسديد
                        </button>
                        <a href="view_invoice.php?id=<?= $invoice_id ?>" class="btn btn-outline">
                            <i class="fas fa-times"></i> إلغاء
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // التأكد من أن تاريخ الدفع لا يتجاوز اليوم
            const paymentDateInput = document.getElementById('payment_date');
            const today = new Date().toISOString().split('T')[0];
            paymentDateInput.max = today;
            
            // تأكيد قبل التسديد
            const paymentForm = document.getElementById('payment-form');
            paymentForm.addEventListener('submit', function(e) {
                const remainingAmount = <?= $remaining_amount ?>;
                if (remainingAmount <= 0) {
                    e.preventDefault();
                    alert('لا يوجد مبلغ متبٍ للدفع');
                    return false;
                }
                
                if (!confirm(`هل أنت متأكد من تسديد المبلغ ${remainingAmount.toFixed(2)} د.ل؟`)) {
                    e.preventDefault();
                    return false;
                }
            });
            
            // منع إدخال تاريخ مستقبلي
            paymentDateInput.addEventListener('change', function() {
                const selectedDate = this.value;
                if (selectedDate > today) {
                    alert('لا يمكن اختيار تاريخ مستقبلي للدفع');
                    this.value = today;
                }
            });
        });
    </script>
</body>
</html>