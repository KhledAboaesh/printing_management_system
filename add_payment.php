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
        SELECT i.*, c.name as customer_name, c.email as customer_email, 
               c.phone, c.company_name, c.tax_number,
               u.full_name as created_by_name
        FROM invoices i 
        JOIN customers c ON i.customer_id = c.customer_id 
        JOIN users u ON i.created_by = u.user_id 
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

// التحقق من إمكانية إضافة دفعة
if ($invoice['status'] === 'cancelled') {
    $_SESSION['error'] = "لا يمكن إضافة دفعة للفاتورة الملغاة";
    header("Location: view_invoice.php?id=" . $invoice_id);
    exit();
}

if ($invoice['payment_status'] === 'paid') {
    $_SESSION['error'] = "الفاتورة مدفوعة بالكامل بالفعل";
    header("Location: view_invoice.php?id=" . $invoice_id);
    exit();
}

// حساب المبلغ المتبقي
$remaining_amount = $invoice['total_amount'] - $invoice['amount_paid'];

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $notes = $_POST['notes'] ?? '';
        $mark_as_paid = isset($_POST['mark_as_paid']);

        if ($amount <= 0) throw new Exception("المبلغ يجب أن يكون أكبر من الصفر");
        if ($amount > $remaining_amount) throw new Exception("المبلغ المدخل أكبر من المبلغ المتبقي (" . number_format($remaining_amount, 2) . " د.ل)");

        $db->beginTransaction();

        // إضافة الدفعة مع received_by
        $stmt = $db->prepare("
            INSERT INTO invoice_payments 
            (invoice_id, amount, payment_method, notes, created_by, received_by, payment_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invoice_id,
            $amount,
            $payment_method,
            $notes,
            $_SESSION['user_id'], // created_by
            $_SESSION['user_id'], // received_by
            date('Y-m-d')
        ]);

        // تحديث الفاتورة
        $new_amount_paid = $invoice['amount_paid'] + $amount;
        if ($mark_as_paid || $new_amount_paid >= $invoice['total_amount']) {
            $payment_status = 'paid';
            $payment_date = date('Y-m-d');
        } elseif ($new_amount_paid > 0) {
            $payment_status = 'partial';
            $payment_date = date('Y-m-d');
        } else {
            $payment_status = 'pending';
            $payment_date = null;
        }

        $stmt = $db->prepare("
            UPDATE invoices 
            SET amount_paid = ?, payment_status = ?, payment_date = ? 
            WHERE invoice_id = ?
        ");
        $stmt->execute([$new_amount_paid, $payment_status, $payment_date, $invoice_id]);

        logActivity($_SESSION['user_id'], 'add_payment', 
            "تم إضافة دفعة بقيمة " . number_format($amount, 2) . " د.ل للفاتورة #" . $invoice['invoice_number']);

        $db->commit();

        $_SESSION['success'] = "تم إضافة الدفعة بنجاح وتحديث حالة الفاتورة";
        header("Location: view_invoice.php?id=" . $invoice_id);
        exit();

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = "حدث خطأ أثناء إضافة الدفعة: " . $e->getMessage();
    }
}

// تعريف مصفوفات للترجمة
$payment_methods = [
    'cash' => 'نقداً',
    'bank_transfer' => 'تحويل بنكي',
    'credit_card' => 'بطاقة ائتمان',
    'check' => 'شيك'
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة دفعة - فاتورة #<?= $invoice['invoice_number'] ?></title>
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
        
        .payment-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .payment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
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
            font-size: 1.1em;
            color: #dc3545;
        }
        
        .actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #4361ee;
            color: white;
        }
        
        .btn-primary:hover {
            background: #3a56d4;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: white;
            color: #6c757d;
            border: 1px solid #6c757d;
        }
        
        .btn-outline:hover {
            background: #f8f9fa;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
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
        
        .amount-input {
            position: relative;
        }
        
        .amount-input::after {
            content: "د.ل";
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-weight: 500;
        }
        
        .amount-input .form-control {
            padding-left: 50px;
        }
        
        @media (max-width: 768px) {
            .invoice-header {
                flex-direction: column;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <h1>إضافة دفعة - فاتورة #<?= $invoice['invoice_number'] ?></h1>
                <?php include 'includes/user-menu.php'; ?>
            </div>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <div class="invoice-container">
                <div class="invoice-header">
                    <div>
                        <h2 class="invoice-title">إضافة دفعة جديدة</h2>
                        <div class="invoice-meta">
                            <div class="invoice-meta-item">
                                <i class="fas fa-user"></i>
                                <span>العميل: <?= htmlspecialchars($invoice['customer_name']) ?></span>
                            </div>
                            <div class="invoice-meta-item">
                                <span class="badge <?= 
                                    $invoice['payment_status'] === 'paid' ? 'badge-paid' : 
                                    ($invoice['payment_status'] === 'partial' ? 'badge-partial' : 'badge-pending') 
                                ?>">
                                    <?= $invoice['payment_status'] === 'paid' ? 'مدفوع' : 
                                       ($invoice['payment_status'] === 'partial' ? 'مدفوع جزئياً' : 'معلق') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- معلومات الفاتورة -->
                <div class="payment-info">
                    <h3>معلومات الفاتورة</h3>
                    <div class="payment-grid">
                        <div>
                            <div class="payment-item">
                                <span>إجمالي الفاتورة:</span>
                                <span><?= number_format($invoice['total_amount'], 2) ?> د.ل</span>
                            </div>
                            <div class="payment-item">
                                <span>المبلغ المدفوع:</span>
                                <span><?= number_format($invoice['amount_paid'], 2) ?> د.ل</span>
                            </div>
                        </div>
                        <div>
                            <div class="payment-item">
                                <span>العربون:</span>
                                <span><?= number_format($invoice['deposit_amount'], 2) ?> د.ل</span>
                            </div>
                            <div class="payment-item">
                                <span>المبلغ المتبقي:</span>
                                <span><?= number_format($remaining_amount, 2) ?> د.ل</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- نموذج إضافة الدفعة -->
                <form method="POST" id="payment-form">
                    <div class="form-group">
                        <label for="amount" class="form-label">مبلغ الدفعة (د.ل) *</label>
                        <div class="amount-input">
                            <input type="number" id="amount" name="amount" 
                                   class="form-control" min="0.01" max="<?= $remaining_amount ?>" 
                                   step="0.01" value="<?= $remaining_amount ?>" required
                                   onchange="updatePaymentInfo()">
                        </div>
                        <small class="text-muted">أقصى مبلغ يمكن دفعه: <?= number_format($remaining_amount, 2) ?> د.ل</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method" class="form-label">طريقة الدفع *</label>
                        <select id="payment_method" name="payment_method" class="form-control" required>
                            <?php foreach ($payment_methods as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $value === 'cash' ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes" class="form-label">ملاحظات</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3" 
                                  placeholder="ملاحظات حول الدفعة (اختياري)"><?= $_POST['notes'] ?? '' ?></textarea>
                    </div>
                    
                    <?php if ($remaining_amount > 0): ?>
                    <div class="form-check">
                        <input type="checkbox" id="mark_as_paid" name="mark_as_paid" 
                               class="form-check-input" onchange="updatePaymentInfo()">
                        <label for="mark_as_paid" class="form-check-label">
                            تعيين الفاتورة كمدفوعة بالكامل
                        </label>
                    </div>
                    <?php endif; ?>
                    
                    <div class="actions">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> تأكيد الدفعة
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
        function updatePaymentInfo() {
            const amountInput = document.getElementById('amount');
            const markAsPaidCheckbox = document.getElementById('mark_as_paid');
            const maxAmount = parseFloat('<?= $remaining_amount ?>');
            let amount = parseFloat(amountInput.value) || 0;
            
            // التأكد من أن المبلغ لا يتجاوز الحد الأقصى
            if (amount > maxAmount) {
                amount = maxAmount;
                amountInput.value = maxAmount.toFixed(2);
            }
            
            // إذا كان المبلغ يساوي المتبقي، تفعيل خيار التعيين كمدفوع
            if (markAsPaidCheckbox) {
                if (amount === maxAmount) {
                    markAsPaidCheckbox.checked = true;
                    markAsPaidCheckbox.disabled = false;
                } else {
                    markAsPaidCheckbox.disabled = false;
                }
            }
        }
        
        // التحقق من صحة النموذج قبل الإرسال
        document.getElementById('payment-form').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            const maxAmount = parseFloat('<?= $remaining_amount ?>');
            
            if (amount <= 0) {
                e.preventDefault();
                alert('المبلغ يجب أن يكون أكبر من الصفر');
                return false;
            }
            
            if (amount > maxAmount) {
                e.preventDefault();
                alert('المبلغ المدخل أكبر من المبلغ المتبقي (' + maxAmount.toFixed(2) + ' د.ل)');
                return false;
            }
            
            return true;
        });
        
        // تحديث المعلومات عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            updatePaymentInfo();
        });
    </script>
</body>
</html>