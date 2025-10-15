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

// جلب عناصر الفاتورة
try {
    $stmt = $db->prepare("
        SELECT ii.*, inv.name as product_name 
        FROM invoice_items ii 
        JOIN inventory inv ON ii.item_id = inv.item_id 
        WHERE ii.invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "خطأ في جلب عناصر الفاتورة: " . $e->getMessage();
    header("Location: invoices.php");
    exit();
}

// جلب سجل المدفوعات (إذا كان موجوداً)
$payments_history = [];
try {
    $stmt = $db->prepare("
        SELECT ip.*, u.full_name as created_by_name 
        FROM invoice_payments ip 
        LEFT JOIN users u ON ip.created_by = u.user_id 
        WHERE ip.invoice_id = ? 
        ORDER BY ip.payment_date DESC
    ");
    $stmt->execute([$invoice_id]);
    $payments_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // قد يكون الجدول غير موجود، لذلك نتعامل مع الخطأ بهدوء
}

// تعريف مصفوفات للترجمة
$payment_methods = [
    'cash' => 'نقداً',
    'bank_transfer' => 'تحويل بنكي',
    'credit_card' => 'بطاقة ائتمان',
    'check' => 'شيك'
];

$payment_statuses = [
    'pending' => 'معلق',
    'partial' => 'مدفوع جزئياً',
    'paid' => 'مدفوع بالكامل'
];

// حساب المبلغ المتبقي
$remaining_amount = $invoice['total_amount'] - $invoice['amount_paid'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عرض الفاتورة #<?= $invoice['invoice_number'] ?></title>
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
            max-width: 1000px;
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
        
        .invoice-items {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
        }
        
        .invoice-items th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: right;
        }
        
        .invoice-items td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .invoice-summary {
            margin-top: 30px;
            border-top: 2px solid #eee;
            padding-top: 20px;
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
        
        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .text-right {
            text-align: right;
        }
        
        .actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #4361ee;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
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
        }
        
        .payments-history {
            margin-top: 30px;
        }
        
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .payments-table th, .payments-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }
        
        .payments-table th {
            background: #f8f9fa;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .col-md-6 {
            flex: 0 0 50%;
            padding: 0 15px;
            box-sizing: border-box;
        }
        
        @media (max-width: 768px) {
            .col-md-6 {
                flex: 0 0 100%;
            }
            
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
                <h1>فاتورة #<?= $invoice['invoice_number'] ?></h1>
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
            
            <div class="invoice-container">
                <div class="invoice-header">
                    <div>
                        <h2 class="invoice-title">فاتورة بيع</h2>
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
                                    ($invoice['payment_status'] === 'partial' ? 'badge-partial' : 
                                    ($invoice['status'] === 'cancelled' ? 'badge-cancelled' : 'badge-pending')) 
                                ?>">
                                    <?= $payment_statuses[$invoice['payment_status']] ?? 'غير محدد' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <img src="images/logo.png" alt="الشعار" style="max-height: 80px;">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h3>معلومات البائع:</h3>
                        <p>شركة المطبعة الحديثة</p>
                        <p>السجل التجاري: 123456789</p>
                        <p>الرقم الضريبي: 987654321</p>
                        <p>البريد الإلكتروني: info@printing.com</p>
                        <p>الهاتف: +9661122334455</p>
                    </div>
                    <div class="col-md-6">
                        <h3>معلومات العميل:</h3>
                        <p><?= htmlspecialchars($invoice['customer_name']) ?></p>
                        <?php if (!empty($invoice['company_name'])): ?>
                        <p><?= htmlspecialchars($invoice['company_name']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($invoice['tax_number'])): ?>
                        <p>الرقم الضريبي: <?= htmlspecialchars($invoice['tax_number']) ?></p>
                        <?php endif; ?>
                        <p>الهاتف: <?= htmlspecialchars($invoice['phone']) ?></p>
                        <?php if (!empty($invoice['customer_email'])): ?>
                        <p>البريد: <?= htmlspecialchars($invoice['customer_email']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- قسم معلومات الدفع والعربون -->
                <div class="payment-info">
                    <h3>معلومات الدفع</h3>
                    <div class="payment-grid">
                        <div>
                            <div class="payment-item">
                                <span>طريقة الدفع:</span>
                                <span><?= $payment_methods[$invoice['payment_method']] ?? $invoice['payment_method'] ?></span>
                            </div>
                            <div class="payment-item">
                                <span>العربون:</span>
                                <span><?= number_format($invoice['deposit_amount'], 2) ?> د.ل</span>
                            </div>
                        </div>
                        <div>
                            <div class="payment-item">
                                <span>المبلغ المدفوع:</span>
                                <span><?= number_format($invoice['amount_paid'], 2) ?> د.ل</span>
                            </div>
                            <div class="payment-item">
                                <span>تاريخ الدفع:</span>
                                <span><?= $invoice['payment_date'] ? date('Y-m-d', strtotime($invoice['payment_date'])) : 'لم يتم الدفع بعد' ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h3 style="margin-top: 30px;">تفاصيل الفاتورة</h3>
                <table class="invoice-items">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المنتج</th>
                            <th>الكمية</th>
                            <th>سعر الوحدة</th>
                            <th>الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoice_items as $index => $item): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td><?= number_format($item['unit_price'], 2) ?> د.ل</td>
                            <td><?= number_format($item['quantity'] * $item['unit_price'], 2) ?> د.ل</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="invoice-summary">
                    <div style="display: flex; justify-content: flex-end;">
                        <div style="width: 350px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span>المجموع الفرعي:</span>
                                <span><?= number_format($invoice['total_amount'], 2) ?> د.ل</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span>العربون:</span>
                                <span><?= number_format($invoice['deposit_amount'], 2) ?> د.ل</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                <span>المبلغ المدفوع:</span>
                                <span><?= number_format($invoice['amount_paid'], 2) ?> د.ل</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.2em; border-top: 1px solid #eee; padding-top: 10px; margin-top: 10px; color: <?= $remaining_amount > 0 ? '#dc3545' : '#28a745' ?>;">
                                <span>المبلغ المتبقي:</span>
                                <span><?= number_format($remaining_amount, 2) ?> د.ل</span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($invoice['notes'])): ?>
                    <div style="margin-top: 20px;">
                        <h4>ملاحظات:</h4>
                        <p><?= htmlspecialchars($invoice['notes']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- سجل المدفوعات -->
                <?php if (!empty($payments_history)): ?>
                <div class="payments-history">
                    <h3>سجل المدفوعات</h3>
                    <table class="payments-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>تاريخ الدفع</th>
                                <th>المبلغ</th>
                                <th>طريقة الدفع</th>
                                <th>ملاحظات</th>
                                <th>مسجل بواسطة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments_history as $index => $payment): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($payment['payment_date'])) ?></td>
                                <td><?= number_format($payment['amount'], 2) ?> د.ل</td>
                                <td><?= $payment_methods[$payment['payment_method']] ?? $payment['payment_method'] ?></td>
                                <td><?= htmlspecialchars($payment['notes'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($payment['created_by_name']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <div class="actions">
                    <a href="print_invoice.php?id=<?= $invoice_id ?>" class="btn btn-primary" target="_blank">
                        <i class="fas fa-print"></i> طباعة الفاتورة
                    </a>
                    
                    <?php if ($invoice['payment_status'] !== 'paid' && $invoice['status'] !== 'cancelled'): ?>
                    <a href="add_payment.php?id=<?= $invoice_id ?>" class="btn btn-success">
                        <i class="fas fa-money-bill-wave"></i> تسديد دفعة
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($invoice['payment_status'] === 'pending' && $invoice['status'] !== 'cancelled'): ?>
                    <a href="mark_paid.php?id=<?= $invoice_id ?>" class="btn btn-warning">
                        <i class="fas fa-check"></i> تم الدفع بالكامل
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($invoice['status'] !== 'cancelled'): ?>
                    <a href="cancel_invoice.php?id=<?= $invoice_id ?>" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من إلغاء الفاتورة؟')">
                        <i class="fas fa-times"></i> إلغاء الفاتورة
                    </a>
                    <?php endif; ?>
                    
                    <a href="invoices.php" class="btn" style="background: #6c757d; color: white;">
                        <i class="fas fa-list"></i> العودة للقائمة
                    </a>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // تأكيد قبل إلغاء الفاتورة
        document.addEventListener('DOMContentLoaded', function() {
            const cancelBtn = document.querySelector('a[href*="cancel_invoice"]');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function(e) {
                    if (!confirm('هل أنت متأكد من إلغاء الفاتورة؟ لا يمكن التراجع عن هذا الإجراء.')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>