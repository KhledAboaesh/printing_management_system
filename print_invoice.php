<?php
// في أعلى ملف print_invoice.php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من وجود معرف الفاتورة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("معرف الفاتورة غير صحيح");
}

$invoice_id = intval($_GET['id']);

// جلب بيانات الفاتورة
$invoice = [];
$invoice_items = [];
$customer = [];

try {
    // جلب بيانات الفاتورة الأساسية
    $stmt = $db->prepare("
        SELECT i.*, c.name as customer_name, c.phone as customer_phone, 
               c.email as customer_email, c.address as customer_address,
               c.company_name, c.tax_number, u.full_name as created_by_name
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.customer_id
        LEFT JOIN users u ON i.created_by = u.user_id
        WHERE i.invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        die("الفاتورة غير موجودة");
    }
    
    // جلب عناصر الفاتورة
    $stmt = $db->prepare("
        SELECT ii.*, inv.name as item_name, inv.description as item_description
        FROM invoice_items ii
        LEFT JOIN inventory inv ON ii.item_id = inv.item_id
        WHERE ii.invoice_id = ?
        ORDER BY ii.item_id
    ");
    $stmt->execute([$invoice_id]);
    $invoice_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب بيانات العميل
    $stmt = $db->prepare("
        SELECT * FROM customers WHERE customer_id = ?
    ");
    $stmt->execute([$invoice['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("خطأ في جلب بيانات الفاتورة: " . $e->getMessage());
}

// حساب المجموع الفرعي إذا لم يكن موجوداً
if (empty($invoice['subtotal']) && !empty($invoice_items)) {
    $subtotal = 0;
    foreach ($invoice_items as $item) {
        $subtotal += $item['quantity'] * $item['unit_price'];
    }
    $invoice['subtotal'] = $subtotal;
}

// حساب المبلغ الإجمالي إذا لم يكن موجوداً
if (empty($invoice['total_amount'])) {
    $tax_amount = $invoice['tax_amount'] ?? 0;
    $discount_amount = $invoice['discount_amount'] ?? 0;
    $invoice['total_amount'] = $invoice['subtotal'] + $tax_amount - $discount_amount;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة فاتورة #<?php echo $invoice['invoice_number']; ?></title>
   <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Tahoma", Arial, sans-serif;
    }

    body {
        background: #f9f9f9;
        color: #000;
        padding: 20px;
        font-size: 15px;
        line-height: 1.6;
    }

    @media print {
        body {
            padding: 0;
            margin: 0;
            background: #fff;
        }

        .no-print {
            display: none !important;
        }

        .invoice-container {
            border: none !important;
            box-shadow: none !important;
            margin: 0 !important;
            width: 100% !important;
            padding: 0 !important;
        }
    }

    .invoice-container {
        width: 100%;
        max-width: 850px;
        margin: auto;
        background: #fff;
        border: 1px solid #ddd;
        padding: 25px;
        box-shadow: 0 0 8px rgba(0,0,0,0.1);
    }

    .header-section {
        text-align: center;
        margin-bottom: 25px;
        padding-bottom: 12px;
        border-bottom: 2px solid #444;
    }

    .company-name {
        font-size: 26px;
        font-weight: bold;
        color: #1565C0;
        margin-bottom: 6px;
    }

    .invoice-title {
        font-size: 20px;
        font-weight: bold;
        margin: 12px 0;
        color: #333;
    }

    .invoice-info {
        display: flex;
        justify-content: space-between;
        margin: 18px 0;
        gap: 15px;
    }

    .info-box {
        flex: 1;
        padding: 8px 12px;
        background: #f2f7ff;
        border: 1px solid #cce0ff;
        border-radius: 4px;
    }

    .info-label {
        font-weight: bold;
        margin-bottom: 4px;
        display: block;
        color: #444;
    }

    .dotted-line {
        border-bottom: 1px dotted #444;
        flex-grow: 1;
        margin: 0 6px;
    }

    .client-info {
        margin: 15px 0;
        padding: 12px;
        background: #fafafa;
        border: 1px solid #eee;
        border-radius: 4px;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
        font-size: 14px;
    }

    .items-table th {
        padding: 10px;
        text-align: center;
        border: 1px solid #ccc;
        font-weight: bold;
        background: #1565C0;
        color: #fff;
    }

    .items-table td {
        padding: 8px;
        border: 1px solid #ccc;
        text-align: center;
    }

    .items-table tr:nth-child(even) {
        background: #f9f9f9;
    }

    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .text-left { text-align: left; }

    .summary-section {
        margin-top: 25px;
        padding: 12px;
        background: #f8f8f8;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
    }

    .summary-label {
        font-weight: bold;
    }

    .signatures {
        margin-top: 50px;
        display: flex;
        justify-content: space-between;
    }

    .signature-box {
        text-align: center;
        width: 45%;
    }

    .signature-line {
        border-top: 1px solid #000;
        margin-top: 50px;
        padding-top: 6px;
        font-size: 13px;
    }

    .footer {
        margin-top: 30px;
        text-align: center;
        font-size: 13px;
        border-top: 1px solid #ccc;
        padding-top: 10px;
        color: #555;
    }

    .print-actions {
        text-align: center;
        margin: 20px 0;
        padding: 10px;
    }

    .btn {
        padding: 9px 18px;
        border: none;
        border-radius: 4px;
        font-size: 14px;
        cursor: pointer;
        margin: 0 8px;
        display: inline-flex;
        align-items: center;
        text-decoration: none;
        transition: background 0.2s ease-in-out;
    }

    .btn-primary {
        background: #1565C0;
        color: white;
    }

    .btn-primary:hover {
        background: #0d47a1;
    }

    .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background: #5a6268;
    }

    .dotted-field {
        display: flex;
        align-items: center;
        margin-bottom: 12px;
    }

    .field-label {
        min-width: 120px;
        font-weight: bold;
        color: #333;
    }

    .dotted-space {
        border-bottom: 1px dotted #000;
        flex-grow: 1;
        margin: 0 6px;
        height: 20px;
    }
</style>

</head>
<body>
    <div class="print-actions no-print">
        <button onclick="window.print()" class="btn btn-primary">
            طباعة الفاتورة
        </button>
        <a href="view_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-secondary">
            العودة للفاتورة
        </a>
    </div>
    
    <div class="invoice-container">
        <!-- الرأس -->
        <div class="header-section">
            <div class="company-name">شركة المطبعة الحديثة</div>
            <div class="invoice-title">فاتورة</div>
        </div>
        
        <!-- معلومات الفاتورة -->
        <div class="invoice-info">
            <div class="info-box">
                <div class="dotted-field">
                    <span class="field-label">الترابط:</span>
                    <div class="dotted-space"></div>
                    <span>/</span>
                    <div class="dotted-space"></div>
                    <span>/ التدخلات الإعلانية</span>
                </div>
                
                <div class="dotted-field">
                    <span class="field-label">الإسم:</span>
                    <div class="dotted-space"></div>
                </div>
                
                <div class="dotted-field">
                    <span class="field-label">رقم الفاتورة:</span>
                    <span><?php echo $invoice['invoice_number']; ?></span>
                </div>
            </div>
            
            <div class="info-box">
                <div class="dotted-field">
                    <span class="field-label">التاريخ:</span>
                    <span><?php echo date('Y/m/d', strtotime($invoice['issue_date'])); ?></span>
                </div>
            </div>
        </div>
        
        <!-- معلومات العميل -->
        <div class="client-info">
            <div class="dotted-field">
                <span class="field-label">اسم العميل:</span>
                <div class="dotted-space"></div>
                <span><?php echo htmlspecialchars($invoice['customer_name']); ?></span>
            </div>
            
            <?php if (!empty($invoice['customer_phone'])): ?>
            <div class="dotted-field">
                <span class="field-label">الهاتف:</span>
                <div class="dotted-space"></div>
                <span><?php echo htmlspecialchars($invoice['customer_phone']); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- جدول العناصر -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="60%">الوصف بالتفاصيل</th>
                    <th width="15%">العدد</th>
                    <th width="25%">سعر الوحدة</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($invoice_items)): ?>
                    <?php foreach ($invoice_items as $index => $item): ?>
                        <tr>
                            <td class="text-right"><?php echo htmlspecialchars($item['item_name'] ?? 'عنصر غير محدد'); ?></td>
                            <td class="text-center"><?php echo number_format($item['quantity'], 2); ?></td>
                            <td class="text-center"><?php echo number_format($item['unit_price'], 2); ?> د.ل</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">لا توجد عناصر في هذه الفاتورة</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- المجموع -->
        <div class="summary-section">
            <div class="summary-row">
                <span class="summary-label">المجموع:</span>
                <span><?php echo number_format($invoice['total_amount'], 2); ?> د.ل</span>
            </div>
        </div>
        
        <!-- معلومات الدفع -->
        <div style="margin: 20px 0;">
            <div class="dotted-field">
                <span class="field-label">عربون تحميم تأكيد التنظيم</span>
                <div class="dotted-space"></div>
            </div>
            
            <div class="dotted-field">
                <span class="field-label">خالص تم التسليم</span>
                <div class="dotted-space"></div>
            </div>
            
            <div class="dotted-field">
                <span class="field-label">أمر تشغيل</span>
                <div class="dotted-space"></div>
            </div>
        </div>
        
        <!-- التوقيعات -->
        <div class="signatures">
            <div class="signature-box">
                <div>الخطابي</div>
                <div class="signature-line">التوقيع</div>
            </div>
            
            <div class="signature-box">
                <div>الجماعي</div>
                <div class="signature-line">التوقيع</div>
            </div>
        </div>
        
        <!-- معلومات إضافية -->
        <div style="margin-top: 30px;">
            <div class="dotted-field">
                <span class="field-label">المصوم:</span>
                <div class="dotted-space"></div>
                <span class="field-label">تسليم:</span>
                <div class="dotted-space"></div>
            </div>
            
            <div class="dotted-field">
                <span class="field-label">الدفوع الثاني</span>
                <div class="dotted-space"></div>
            </div>
        </div>
        
        <!-- التذييل -->
        <div class="footer">
            <div>شكراً لتعاملكم معنا</div>
            <div>هاتف: 021-1234567</div>
        </div>
    </div>

    <script>
        // الطباعة التلقائية عند تحميل الصفحة
        window.onload = function() {
            // يمكن تفعيل هذا السطر للطباعة التلقائية
            // window.print();
        };
    </script>
</body>
</html>