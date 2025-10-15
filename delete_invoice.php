<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
               c.phone as customer_phone, u.full_name as created_by_name,
               (SELECT COUNT(*) FROM invoice_items WHERE invoice_id = i.invoice_id) as items_count
        FROM invoices i 
        LEFT JOIN customers c ON i.customer_id = c.customer_id 
        LEFT JOIN users u ON i.created_by = u.user_id 
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

// التحقق إذا كانت الفاتورة مدفوعة
if ($invoice['payment_status'] === 'paid') {
    $_SESSION['warning'] = "لا يمكن حذف فاتورة مدفوعة. يمكنك إلغاؤها بدلاً من ذلك.";
    header("Location: invoice_details.php?id=" . $invoice_id);
    exit();
}

// جلب تفاصيل العناصر
$invoice_items = [];
try {
    $stmt = $db->prepare("
        SELECT ii.*, s.name as service_name 
        FROM invoice_items ii 
        LEFT JOIN services s ON ii.service_id = s.service_id 
        WHERE ii.invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // يمكن تجاهل الخطأ
}

// معالجة طلب الحذف
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delete_reason = trim($_POST['delete_reason'] ?? '');
    $confirm_invoice_number = trim($_POST['confirm_invoice_number'] ?? '');
    
    if (empty($delete_reason)) {
        $error = "يرجى إدخال سبب الحذف";
    } elseif (strlen($delete_reason) < 10) {
        $error = "يرجى إدخال سبب مفصل (10 أحرف على الأقل)";
    } elseif ($confirm_invoice_number !== $invoice['invoice_number']) {
        $error = "رقم الفاتورة غير متطابق. يرجى إدخال رقم الفاتورة بشكل صحيح للتأكيد.";
    } else {
        try {
            $db->beginTransaction();
            
            // حفظ نسخة احتياطية في جدول المحذوفات
            $stmt = $db->prepare("
                INSERT INTO deleted_invoices 
                (original_invoice_id, invoice_number, customer_id, customer_name, 
                 total_amount, issue_date, due_date, payment_status, items_data,
                 delete_reason, deleted_by, deleted_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $items_json = json_encode($invoice_items, JSON_UNESCAPED_UNICODE);
            
            $stmt->execute([
                $invoice_id,
                $invoice['invoice_number'],
                $invoice['customer_id'],
                $invoice['customer_name'],
                $invoice['total_amount'],
                $invoice['issue_date'],
                $invoice['due_date'],
                $invoice['payment_status'],
                $items_json,
                $delete_reason,
                0 // استخدام 0 بدلاً من NULL لتجنب الخطأ
            ]);
            
            // حذف العناصر المرتبطة
            $stmt = $db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
            $stmt->execute([$invoice_id]);
            
            // حذف الفاتورة
            $stmt = $db->prepare("DELETE FROM invoices WHERE invoice_id = ?");
            $stmt->execute([$invoice_id]);
            
            $db->commit();
            $_SESSION['success'] = "تم حذف الفاتورة رقم {$invoice['invoice_number']} بنجاح";
            header("Location: invoices.php");
            exit();
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = "حدث خطأ أثناء حذف الفاتورة: " . $e->getMessage();
        }
    }
}

// جلب آخر 5 عمليات حذف لنفس العميل (إن وجدت)
$recent_deletions = [];
if ($invoice['customer_id']) {
    try {
        $stmt = $db->prepare("
            SELECT di.invoice_number, di.total_amount, di.delete_reason, 
                   di.deleted_at, u.full_name as deleted_by_name
            FROM deleted_invoices di
            LEFT JOIN users u ON di.deleted_by = u.user_id
            WHERE di.customer_id = ? AND di.original_invoice_id != ?
            ORDER BY di.deleted_at DESC
            LIMIT 5
        ");
        $stmt->execute([$invoice['customer_id'], $invoice_id]);
        $recent_deletions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // يمكن تجاهل الخطأ
    }
}
?>



<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حذف الفاتورة - نظام المطبعة</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .delete-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin: 20px auto;
            max-width: 900px;
        }
        
        .delete-header {
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .delete-title {
            color: #e74c3c;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .invoice-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .info-label {
            font-weight: 500;
            color: #666;
            font-size: 14px;
        }
        
        .info-value {
            font-weight: 600;
            color: #333;
        }
        
        .items-section {
            margin: 25px 0;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .items-table th, .items-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }
        
        .items-table th {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
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
        
        .delete-form {
            margin-top: 30px;
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
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #4361ee;
            outline: none;
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.2);
        }
        
        .confirmation-section {
            background: #ffeaa7;
            border: 1px solid #fdcb6e;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .warning-box {
            background: #ffeaa7;
            border: 1px solid #fdcb6e;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .danger-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
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
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
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
        
        .text-danger {
            color: #e74c3c;
        }
        
        .text-success {
            color: #27ae60;
        }
        
        .char-count {
            font-size: 12px;
            color: #666;
            text-align: left;
            margin-top: 5px;
        }
        
        .char-count.warning {
            color: #e74c3c;
        }
        
        .recent-deletions {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .deletion-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .deletion-item:last-child {
            border-bottom: none;
        }
        
        @media (max-width: 768px) {
            .delete-container {
                padding: 20px;
                margin: 10px;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                justify-content: center;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .deletion-item {
                flex-direction: column;
                align-items: start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <h1>حذف الفاتورة</h1>
                <?php include 'includes/user-menu.php'; ?>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error; ?>
                </div>
            <?php endif; ?>
            
            <div class="delete-container">
                <div class="delete-header">
                    <h2 class="delete-title">
                        <i class="fas fa-trash-alt"></i> حذف الفاتورة
                    </h2>
                    <div class="danger-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>تحذير:</strong> هذه العملية لا يمكن التراجع عنها. سيتم حذف الفاتورة بشكل دائم 
                            مع جميع البيانات المرتبطة بها. يرجى التأكد قبل المتابعة.
                        </div>
                    </div>
                </div>
                
                <!-- معلومات الفاتورة -->
                <div class="invoice-info">
                    <h3>معلومات الفاتورة</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">رقم الفاتورة:</span>
                            <span class="info-value"><?= htmlspecialchars($invoice['invoice_number']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">العميل:</span>
                            <span class="info-value"><?= htmlspecialchars($invoice['customer_name']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">المبلغ الإجمالي:</span>
                            <span class="info-value"><?= number_format($invoice['total_amount'], 2) ?> د.ل</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">تاريخ الإصدار:</span>
                            <span class="info-value"><?= date('Y-m-d', strtotime($invoice['issue_date'])) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">حالة الدفع:</span>
                            <span class="info-value">
                                <span class="badge <?= 
                                    $invoice['payment_status'] === 'paid' ? 'badge-paid' : 
                                    ($invoice['payment_status'] === 'partial' ? 'badge-partial' : 'badge-pending')
                                ?>">
                                    <?= 
                                        $invoice['payment_status'] === 'paid' ? 'مدفوعة' : 
                                        ($invoice['payment_status'] === 'partial' ? 'جزئي' : 'معلقة')
                                    ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">عدد العناصر:</span>
                            <span class="info-value"><?= $invoice['items_count'] ?> عنصر</span>
                        </div>
                    </div>
                </div>
                
                <!-- عناصر الفاتورة -->
                <?php if (!empty($invoice_items)): ?>
                <div class="items-section">
                    <h3>عناصر الفاتورة</h3>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>الخدمة</th>
                                <th>الكمية</th>
                                <th>سعر الوحدة</th>
                                <th>المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoice_items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['service_name'] ?? $item['description']) ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td><?= number_format($item['unit_price'], 2) ?> د.ل</td>
                                <td><?= number_format($item['quantity'] * $item['unit_price'], 2) ?> د.ل</td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="background: #f8f9fa; font-weight: bold;">
                                <td colspan="3" style="text-align: left;">الإجمالي:</td>
                                <td><?= number_format($invoice['total_amount'], 2) ?> د.ل</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <!-- العمليات الحذف السابقة -->
                <?php if (!empty($recent_deletions)): ?>
                <div class="recent-deletions">
                    <h3>عمليات الحذف السابقة لنفس العميل</h3>
                    <?php foreach ($recent_deletions as $deletion): ?>
                    <div class="deletion-item">
                        <div>
                            <strong>فاتورة #<?= htmlspecialchars($deletion['invoice_number']) ?></strong>
                            - <?= number_format($deletion['total_amount'], 2) ?> د.ل
                            <br>
                            <small>السبب: <?= htmlspecialchars($deletion['delete_reason']) ?></small>
                        </div>
                        <div style="text-align: left;">
                            <small>بواسطة: <?= htmlspecialchars($deletion['deleted_by_name']) ?></small>
                            <br>
                            <small><?= date('Y-m-d H:i', strtotime($deletion['deleted_at'])) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- نموذج الحذف -->
                <form method="POST" class="delete-form" id="deleteForm">
                    <div class="form-group">
                        <label for="delete_reason" class="form-label">
                            سبب الحذف <span class="text-danger">*</span>
                        </label>
                        <textarea id="delete_reason" name="delete_reason" class="form-control" 
                                  rows="4" placeholder="أدخل سبب حذف هذه الفاتورة (10 أحرف على الأقل)..." 
                                  required minlength="10" maxlength="500"></textarea>
                        <div id="charCount" class="char-count">0/500</div>
                    </div>
                    
                    <div class="confirmation-section">
                        <h4><i class="fas fa-shield-alt"></i> تأكيد الحذف</h4>
                        <p>للتأكد من أنك تريد حذف هذه الفاتورة، يرجى إدخال رقم الفاتورة أدناه:</p>
                        
                        <div class="form-group">
                            <label for="confirm_invoice_number" class="form-label">
                                أدخل رقم الفاتورة للتأكيد: <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="confirm_invoice_number" name="confirm_invoice_number" 
                                   class="form-control" placeholder="<?= htmlspecialchars($invoice['invoice_number']) ?>" 
                                   required>
                            <small class="text-muted">رقم الفاتورة: <strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></small>
                        </div>
                    </div>
                    
                    <div class="actions">
                        <button type="submit" class="btn btn-danger" id="submitBtn">
                            <i class="fas fa-trash-alt"></i> تأكيد الحذف
                        </button>
                        <a href="invoice_details.php?id=<?= $invoice_id ?>" class="btn btn-outline">
                            <i class="fas fa-arrow-right"></i> العودة إلى الفاتورة
                        </a>
                        <a href="invoices.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> إلغاء والعودة للقائمة
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        // عداد الأحchar لسبب الحذف
        const reasonTextarea = document.getElementById('delete_reason');
        const charCount = document.getElementById('charCount');
        
        if (reasonTextarea && charCount) {
            reasonTextarea.addEventListener('input', function() {
                const length = this.value.length;
                charCount.textContent = `${length}/500`;
                
                if (length < 10) {
                    charCount.classList.add('warning');
                } else {
                    charCount.classList.remove('warning');
                }
            });
            
            // تهيئة العداد
            charCount.textContent = `${reasonTextarea.value.length}/500`;
            if (reasonTextarea.value.length < 10) {
                charCount.classList.add('warning');
            }
        }
        
        // تأكيد الحذف
        function confirmDelete() {
            const reason = document.getElementById('delete_reason').value.trim();
            const confirmNumber = document.getElementById('confirm_invoice_number').value.trim();
            const actualNumber = '<?= $invoice['invoice_number'] ?>';
            
            if (!reason) {
                alert('يرجى إدخال سبب الحذف');
                return false;
            }
            
            if (reason.length < 10) {
                alert('يرجى إدخال سبب مفصل (10 أحرف على الأقل)');
                return false;
            }
            
            if (confirmNumber !== actualNumber) {
                alert('رقم الفاتورة غير متطابق. يرجى إدخال الرقم بشكل صحيح.');
                return false;
            }
            
            let message = '⚠️ تحذير: هذه العملية لا يمكن التراجع عنها!\n\n';
            message += 'هل أنت متأكد من حذف هذه الفاتورة؟\n\n';
            message += `رقم الفاتورة: ${actualNumber}\n`;
            message += `العميل: ${document.querySelector('.info-value').textContent}\n`;
            message += `المبلغ: <?= number_format($invoice['total_amount'], 2) ?> د.ل\n`;
            message += `السبب: ${reason.substring(0, 100)}${reason.length > 100 ? '...' : ''}\n\n`;
            message += 'سيتم حذف جميع البيانات المرتبطة بالفاتورة بشكل دائم.';
            
            return confirm(message);
        }
        
        // إضافة حدث للنموذج
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            if (!confirmDelete()) {
                e.preventDefault();
            }
        });
        
        // منع إرسال النموذج بالضغط على Enter
        document.getElementById('deleteForm').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
        
        // تحسين تجربة المستخدم
        document.addEventListener('DOMContentLoaded', function() {
            // التركيز على حقل السبب
            const reasonField = document.getElementById('delete_reason');
            if (reasonField) {
                setTimeout(() => {
                    reasonField.focus();
                }, 500);
            }
        });
    </script>
</body>
</html>