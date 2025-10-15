<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    die("الوصول غير مسموح");
}

define('INCLUDED', true);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// التحقق من معرف الطلب
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$order_id) {
    die("معرف الطلب غير صالح");
}

// جلب بيانات الطلب
$order = getOrderDetails($db, $order_id);
if (!$order) {
    die("الطلب غير موجود");
}

// جلب عناصر الطلب
$order_items = getOrderItems($db, $order_id);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طباعة الطلب #<?= $order_id ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            margin: 0;
            padding: 10mm;
            color: #000;
            background: #fff;
            font-size: 14px;
            line-height: 1.4;
        }
        .invoice-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ccc;
            padding: 15px;
            box-sizing: border-box;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            color: #000;
        }
        .invoice-title {
            font-size: 20px;
            margin: 10px 0;
            color: #000;
        }
        .connection-info {
            text-align: center;
            margin: 15px 0;
            font-size: 16px;
        }
        .connection-info .dots {
            display: inline-block;
            width: 100px;
            border-bottom: 1px dotted #000;
            margin: 0 5px;
        }
        .main-sections {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .left-section {
            flex: 1;
            min-width: 300px;
        }
        .right-section {
            flex: 1;
            min-width: 300px;
        }
        .section-box {
            margin-bottom: 20px;
        }
        .section-title {
            margin: 0 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
            font-size: 16px;
            font-weight: bold;
        }
        .info-row {
            display: flex;
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            width: 120px;
        }
        .info-value {
            flex: 1;
        }
        .check-confirmation {
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            border: 1px solid #ddd;
        }
        .check-confirmation .title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .check-confirmation .subtitle {
            font-size: 14px;
            margin-bottom: 15px;
        }
        .checkboxes {
            display: flex;
            justify-content: space-around;
            margin: 15px 0;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
        }
        .checkbox-item span {
            margin-right: 5px;
            font-weight: bold;
        }
        .checkbox-box {
            width: 20px;
            height: 20px;
            border: 1px solid #000;
            display: inline-block;
            margin-left: 5px;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }
        .invoice-table th {
            background: #f0f0f0;
            padding: 8px 5px;
            text-align: center;
            border: 1px solid #ddd;
            font-weight: bold;
        }
        .invoice-table td {
            padding: 8px 5px;
            border: 1px solid #ddd;
            text-align: center;
        }
        .summary-section {
            margin-top: 20px;
            padding-top: 10px;
        }
        .signature-area {
            margin-top: 40px;
            display: flex;
            justify-content: space-around;
        }
        .signature-box {
            text-align: center;
            width: 200px;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 40px;
            padding-top: 5px;
        }
        .description-box {
            margin: 15px 0;
            padding: 10px;
            border: 1px solid #ddd;
        }
        .description-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .invoice-container {
                border: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <h1 class="company-name">شركة بحمة النور</h1>
            <h2 class="invoice-title">أمر تشغيل</h2>
        </div>

        <div class="connection-info">
            <span>الترابط:</span>
            <span class="dots"></span>
            <span>/</span>
            <span class="dots"></span>
            <span>/ التدخلات الإعلانية</span>
        </div>

        <div class="main-sections">
            <div class="left-section">
                <div class="section-box">
                    <h3 class="section-title">معلومات العميل:</h3>
                    <div class="info-row">
                        <span class="info-label">الإسم:</span>
                        <span class="info-value dots-line"><?= htmlspecialchars($order['customer_name']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">رقم المانف:</span>
                        <span class="info-value">0001427</span>
                    </div>
                </div>
            </div>
            
            <div class="right-section">
                <div class="section-box">
                    <h3 class="section-title">معلومات الفاتورة:</h3>
                    <div class="info-row">
                        <span class="info-label">رقم الفاتورة:</span>
                        <span class="info-value"><?= $order_id ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">التاريخ:</span>
                        <span class="info-value"><?= date('Y-m-d') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="check-confirmation">
            <div class="title">عربون تحميم تأكيد التنظيم</div>
            <div class="subtitle">خالص تم التسليم</div>
            
            <div class="checkboxes">
                <div class="checkbox-item">
                    <span>خالص</span>
                    <div class="checkbox-box"></div>
                </div>
                <div class="checkbox-item">
                    <span>تم التسليم</span>
                    <div class="checkbox-box"></div>
                </div>
            </div>
        </div>

        <table class="invoice-table">
            <thead>
                <tr>
                    <th>الخطابي</th>
                    <th>العدد</th>
                    <th>سعر الوحدة</th>
                    <th>الجماعي</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total = 0;
                foreach ($order_items as $index => $item): 
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $total += $itemTotal;
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td><?= number_format($item['unit_price'], 2) ?> د.ل</td>
                    <td><?= number_format($itemTotal, 2) ?> د.ل</td>
                </tr>
                <?php endforeach; ?>
                
                <!-- صف إجمالي -->
                <tr>
                    <td colspan="3" style="text-align: left; font-weight: bold;">الدفوع الثاني</td>
                    <td style="font-weight: bold;"><?= number_format($total, 2) ?> د.ل</td>
                </tr>
            </tbody>
        </table>

        <div class="description-box">
            <div class="description-title">الوصف بالتفاصيل</div>
            <p><?= !empty($order['notes']) ? htmlspecialchars($order['notes']) : '................................................................................' ?></p>
        </div>

        <div class="signature-area">
            <div class="signature-box">
                <p>المصوم: <span class="dots">........................</span></p>
            </div>
            <div class="signature-box">
                <p>تسليم: <span class="dots">........................</span></p>
            </div>
        </div>
    </div>

    <div class="no-print" style="margin-top: 30px; text-align: center;">
        <button onclick="window.print()" style="
            background: #4361ee;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        ">
            <i class="fas fa-print"></i> طباعة
        </button>
        <button onclick="window.close()" style="
            background: #f44336;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        ">
            <i class="fas fa-times"></i> إغلاق
        </button>
    </div>

    <script>
        // طباعة تلقائية عند فتح الصفحة
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };

        // إغلاق النافذة بعد الطباعة
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 500);
        };

        // إضافة خطوط النقاط للحقول الفارغة
        document.addEventListener('DOMContentLoaded', function() {
            const dotsLines = document.querySelectorAll('.dots-line');
            dotsLines.forEach(function(element) {
                if (!element.textContent.trim()) {
                    element.innerHTML = '................................';
                }
            });
        });
    </script>
</body>
</html>