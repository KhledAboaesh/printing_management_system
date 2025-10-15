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

// معالجة بيانات النموذج المرسلة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_data = $_POST;
} else {
    // إذا لم تكن بيانات POST، إعادة التوجيه إلى إنشاء الفاتورة
    header("Location: create_invoice.php");
    exit();
}

// بيانات افتراضية للبائع
$company_info = [
    'name' => 'شركة المطبعة الحديثة',
    'commercial_no' => '123456789',
    'tax_number' => '987654321',
    'email' => 'info@printing.com',
    'phone' => '+9661122334455',
    'address' => 'الرياض، المملكة العربية السعودية'
];

// جلب بيانات العميل إذا كان معرف العميل موجوداً
$customer_info = [];
if (!empty($invoice_data['customer_id'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM customers WHERE customer_id = ?");
        $stmt->execute([$invoice_data['customer_id']]);
        $customer_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // تجاهل الخطأ واستخدام البيانات الافتراضية
    }
}

// إذا لم يتم العثور على العميل، استخدام بيانات افتراضية
if (empty($customer_info)) {
    $customer_info = [
        'name' => 'عميل',
        'company_name' => '',
        'tax_number' => '',
        'phone' => '',
        'email' => ''
    ];
}

// معالجة عناصر الفاتورة
$items = [];
$subtotal = 0;

if (!empty($invoice_data['items'])) {
    foreach ($invoice_data['items'] as $item) {
        if (!empty($item['item_id']) && !empty($item['quantity'])) {
            // جلب بيانات المنتج من قاعدة البيانات
            try {
                $stmt = $db->prepare("SELECT name, selling_price FROM inventory WHERE item_id = ?");
                $stmt->execute([$item['item_id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    $quantity = intval($item['quantity']);
                    $unit_price = floatval($product['selling_price']);
                    $total = $quantity * $unit_price;
                    $subtotal += $total;
                    
                    $items[] = [
                        'name' => $product['name'],
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'total' => $total
                    ];
                }
            } catch (PDOException $e) {
                // تجاهل الخطأ
            }
        }
    }
}

// بيانات الدفع والعربون
$payment_method = $invoice_data['payment_method'] ?? 'cash';
$deposit_amount = floatval($invoice_data['deposit_amount'] ?? 0);
$amount_paid = floatval($invoice_data['amount_paid'] ?? 0);
$total_amount = $subtotal;
$remaining_amount = $total_amount - $amount_paid;

// تحديد حالة الدفع
if ($amount_paid >= $total_amount && $total_amount > 0) {
    $payment_status = 'paid';
} elseif ($amount_paid > 0) {
    $payment_status = 'partial';
} else {
    $payment_status = 'pending';
}

// مصفوفات الترجمة
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

// معلومات الفاتورة
$invoice_date = $invoice_data['invoice_date'] ?? date('Y-m-d');
$due_date = $invoice_data['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
$notes = $invoice_data['notes'] ?? '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>معاينة الفاتورة - نظام المطبعة</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tajawal', sans-serif;
        }
        
        body {
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .preview-container {
            max-width: 1000px;
            margin: 20px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .preview-header {
            background: linear-gradient(135deg, #4361ee, #3a0ca3);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .preview-title {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .preview-subtitle {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .preview-content {
            padding: 30px;
        }
        
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .invoice-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        
        .invoice-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .col-md-6 {
            flex: 0 0 50%;
            padding: 0 15px;
            margin-bottom: 20px;
        }
        
        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .info-section h3 {
            color: #4361ee;
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .payment-info {
            background: #e7f3ff;
            border-left: 4px solid #4361ee;
        }
        
        .invoice-items {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
        }
        
        .invoice-items th {
            background: #4361ee;
            color: white;
            padding: 12px 15px;
            text-align: right;
        }
        
        .invoice-items td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .invoice-items tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .invoice-summary {
            margin-top: 30px;
            border-top: 2px solid #eee;
            padding-top: 20px;
        }
        
        .summary-table {
            width: 100%;
            max-width: 400px;
            margin-left: auto;
        }
        
        .summary-table td {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .summary-table tr:last-child td {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1em;
            color: #4361ee;
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
        
        .actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
            padding: 20px;
            border-top: 1px solid #eee;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 5px;
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
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-outline {
            background: white;
            color: #4361ee;
            border: 1px solid #4361ee;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(67, 97, 238, 0.1);
            z-index: -1;
            font-weight: bold;
            pointer-events: none;
        }
        
        @media print {
            body {
                background: white;
            }
            
            .preview-container {
                box-shadow: none;
                margin: 0;
                max-width: none;
            }
            
            .preview-header {
                background: #4361ee !important;
                -webkit-print-color-adjust: exact;
            }
            
            .actions {
                display: none;
            }
            
            .watermark {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .col-md-6 {
                flex: 0 0 100%;
            }
            
            .invoice-header {
                flex-direction: column;
                text-align: center;
            }
            
            .invoice-meta {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="watermark">معاينة</div>
    
    <div class="preview-container">
        <div class="preview-header">
            <h1 class="preview-title">معاينة الفاتورة</h1>
            <p class="preview-subtitle">مراجعة الفاتورة قبل الحفظ النهائي</p>
        </div>
        
        <div class="preview-content">
            <div class="invoice-header">
                <div>
                    <h2 style="color: #4361ee; margin-bottom: 10px;">فاتورة بيع</h2>
                    <div class="invoice-meta">
                        <div class="invoice-meta-item">
                            <i class="fas fa-calendar-alt"></i>
                            <span>تاريخ الفاتورة: <?= date('Y-m-d', strtotime($invoice_date)) ?></span>
                        </div>
                        <div class="invoice-meta-item">
                            <i class="fas fa-calendar-check"></i>
                            <span>تاريخ الاستحقاق: <?= date('Y-m-d', strtotime($due_date)) ?></span>
                        </div>
                        <div class="invoice-meta-item">
                            <span class="badge <?= 
                                $payment_status === 'paid' ? 'badge-paid' : 
                                ($payment_status === 'partial' ? 'badge-partial' : 'badge-pending') 
                            ?>">
                                <?= $payment_statuses[$payment_status] ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div>
                    <div style="background: white; padding: 15px; border-radius: 8px; display: inline-block;">
                        <img src="images/logo.png" alt="الشعار" style="max-height: 60px;" onerror="this.style.display='none'">
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="info-section">
                        <h3><i class="fas fa-store"></i> معلومات البائع</h3>
                        <p><strong>الاسم:</strong> <?= $company_info['name'] ?></p>
                        <p><strong>السجل التجاري:</strong> <?= $company_info['commercial_no'] ?></p>
                        <p><strong>الرقم الضريبي:</strong> <?= $company_info['tax_number'] ?></p>
                        <p><strong>البريد الإلكتروني:</strong> <?= $company_info['email'] ?></p>
                        <p><strong>الهاتف:</strong> <?= $company_info['phone'] ?></p>
                        <p><strong>العنوان:</strong> <?= $company_info['address'] ?></p>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="info-section">
                        <h3><i class="fas fa-user"></i> معلومات العميل</h3>
                        <p><strong>الاسم:</strong> <?= htmlspecialchars($customer_info['name']) ?></p>
                        <?php if (!empty($customer_info['company_name'])): ?>
                        <p><strong>الشركة:</strong> <?= htmlspecialchars($customer_info['company_name']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($customer_info['tax_number'])): ?>
                        <p><strong>الرقم الضريبي:</strong> <?= htmlspecialchars($customer_info['tax_number']) ?></p>
                        <?php endif; ?>
                        <p><strong>الهاتف:</strong> <?= htmlspecialchars($customer_info['phone']) ?></p>
                        <?php if (!empty($customer_info['email'])): ?>
                        <p><strong>البريد الإلكتروني:</strong> <?= htmlspecialchars($customer_info['email']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- قسم معلومات الدفع -->
            <div class="info-section payment-info">
                <h3><i class="fas fa-credit-card"></i> معلومات الدفع</h3>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>طريقة الدفع:</strong> <?= $payment_methods[$payment_method] ?></p>
                        <p><strong>العربون:</strong> <?= number_format($deposit_amount, 2) ?> د.ل</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>المبلغ المدفوع:</strong> <?= number_format($amount_paid, 2) ?> د.ل</p>
                        <p><strong>حالة الدفع:</strong> <?= $payment_statuses[$payment_status] ?></p>
                    </div>
                </div>
            </div>
            
            <!-- تفاصيل الفاتورة -->
            <h3 style="margin-top: 30px; color: #4361ee; border-bottom: 2px solid #4361ee; padding-bottom: 10px;">
                <i class="fas fa-list"></i> تفاصيل الفاتورة
            </h3>
            
            <?php if (!empty($items)): ?>
            <table class="invoice-items">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المنتج / الخدمة</th>
                        <th>الكمية</th>
                        <th>سعر الوحدة</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td><?= number_format($item['unit_price'], 2) ?> د.ل</td>
                        <td><?= number_format($item['total'], 2) ?> د.ل</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 8px; margin: 20px 0;">
                <i class="fas fa-exclamation-circle" style="font-size: 48px; color: #6c757d; margin-bottom: 15px;"></i>
                <p style="font-size: 18px; color: #6c757d;">لا توجد عناصر في الفاتورة</p>
            </div>
            <?php endif; ?>
            
            <!-- ملخص الفاتورة -->
            <div class="invoice-summary">
                <table class="summary-table">
                    <tr>
                        <td>المجموع الفرعي:</td>
                        <td style="text-align: left;"><?= number_format($subtotal, 2) ?> د.ل</td>
                    </tr>
                    <tr>
                        <td>العربون:</td>
                        <td style="text-align: left;"><?= number_format($deposit_amount, 2) ?> د.ل</td>
                    </tr>
                    <tr>
                        <td>المبلغ المدفوع:</td>
                        <td style="text-align: left;"><?= number_format($amount_paid, 2) ?> د.ل</td>
                    </tr>
                    <tr style="border-top: 2px solid #4361ee;">
                        <td><strong>المبلغ المتبقي:</strong></td>
                        <td style="text-align: left; color: <?= $remaining_amount > 0 ? '#dc3545' : '#28a745' ?>;">
                            <strong><?= number_format($remaining_amount, 2) ?> د.ل</strong>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- الملاحظات -->
            <?php if (!empty($notes)): ?>
            <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h4 style="color: #4361ee; margin-bottom: 10px;"><i class="fas fa-sticky-note"></i> ملاحظات</h4>
                <p><?= nl2br(htmlspecialchars($notes)) ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="actions">
            <button type="button" onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> طباعة المعاينة
            </button>
            <button type="button" onclick="window.close()" class="btn btn-outline">
                <i class="fas fa-times"></i> إغلاق النافذة
            </button>
            <a href="create_invoice.php" class="btn btn-success">
                <i class="fas fa-edit"></i> العودة للتعديل
            </a>
        </div>
    </div>

    <script>
        // إضافة تأثيرات عند الطباعة
        document.addEventListener('DOMContentLoaded', function() {
            // إضافة تاريخ المعاينة
            const previewDate = document.createElement('div');
            previewDate.style.textAlign = 'center';
            previewDate.style.marginTop = '10px';
            previewDate.style.fontSize = '12px';
            previewDate.style.color = '#6c757d';
            previewDate.textContent = 'تمت المعاينة في: ' + new Date().toLocaleString('ar-SA');
            document.querySelector('.preview-header').appendChild(previewDate);
            
            // رسالة تأكيد قبل الإغلاق
            window.addEventListener('beforeunload', function(e) {
                if (!document.querySelector('.btn-success').clicked) {
                    return 'هل تريد بالتأكيد مغادرة صفحة المعاينة؟';
                }
            });
        });
    </script>
</body>
</html>