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
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: invoices.php?error=رقم الفاتورة غير صحيح");
    exit();
}

$invoice_id = (int)$_GET['id'];

// جلب بيانات الفاتورة
$invoice = [];
try {
    $stmt = $db->prepare("
        SELECT i.*, c.name as customer_name, c.company_name, u.full_name as created_by_name
        FROM invoices i
        JOIN customers c ON i.customer_id = c.customer_id
        JOIN users u ON i.created_by = u.user_id
        WHERE i.invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        header("Location: invoices.php?error=الفاتورة غير موجودة");
        exit();
    }
    
    // التحقق من صلاحية المستخدم لإلغاء الفاتورة
    if ($_SESSION['role'] !== 'admin' && $invoice['created_by'] != $_SESSION['user_id']) {
        header("Location: invoices.php?error=ليس لديك صلاحية لإلغاء هذه الفاتورة");
        exit();
    }
    
    // التحقق من أن الفاتورة غير ملغاة مسبقاً
    if ($invoice['status'] === 'cancelled') {
        header("Location: view_invoice.php?id=$invoice_id&error=الفاتورة ملغاة بالفعل");
        exit();
    }
    
} catch (PDOException $e) {
    $error = "خطأ في جلب بيانات الفاتورة: " . $e->getMessage();
}

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $cancellation_reason = trim($_POST['cancellation_reason'] ?? '');
        $notify_customer = isset($_POST['notify_customer']);
        $notify_users = $_POST['notify_users'] ?? [];
        
        if (empty($cancellation_reason)) {
            throw new Exception("يرجى إدخال سبب الإلغاء");
        }
        
        // بدء المعاملة
        $db->beginTransaction();
        
        // تحديث حالة الفاتورة إلى ملغاة
        $stmt = $db->prepare("
            UPDATE invoices 
            SET status = 'cancelled', 
                cancellation_reason = ?,
                cancelled_by = ?,
                cancelled_at = NOW()
            WHERE invoice_id = ?
        ");
        $stmt->execute([$cancellation_reason, $_SESSION['user_id'], $invoice_id]);
        
        // استعادة الكميات إلى المخزون
        $stmt = $db->prepare("
            SELECT ii.item_id, ii.quantity 
            FROM invoice_items ii 
            WHERE ii.invoice_id = ?
        ");
        $stmt->execute([$invoice_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as $item) {
            $stmt = $db->prepare("
                UPDATE inventory 
                SET current_quantity = current_quantity + ? 
                WHERE item_id = ?
            ");
            $stmt->execute([$item['quantity'], $item['item_id']]);
        }
        
        // إلغاء أي تعيينات للمصممين والورشة بدون تحديث أعمدة غير موجودة
        $stmt = $db->prepare("
            UPDATE invoice_workshop 
            SET workshop_status = 'cancelled' 
            WHERE invoice_id = ?
        ");
        $stmt->execute([$invoice_id]);
        
        $stmt = $db->prepare("
            UPDATE invoice_designer 
            SET designer_status = 'cancelled' 
            WHERE invoice_id = ?
        ");
        $stmt->execute([$invoice_id]);
        
        // إرسال إشعارات إذا طلب المستخدم
        if ($notify_customer || !empty($notify_users)) {
            sendCancellationEmail($invoice, $cancellation_reason, $notify_customer, $notify_users);
        }
        
        // تسجيل النشاط
        logActivity($_SESSION['user_id'], 'cancel_invoice', 
            "تم إلغاء الفاتورة #{$invoice['invoice_number']} بسبب: $cancellation_reason"
        );
        
        // إنهاء المعاملة
        $db->commit();
        
        // توجيه إلى صفحة الفاتورة مع رسالة نجاح
        header("Location: view_invoice.php?id=$invoice_id&cancelled=1");
        exit();
        
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = "حدث خطأ أثناء إلغاء الفاتورة: " . $e->getMessage();
    }
}

// جلب المستخدمين للإشعارات
$users = [];
try {
    $stmt = $db->query("SELECT user_id, full_name, email, role FROM users WHERE is_active = 1 AND email IS NOT NULL");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // يمكن تجاهل الخطأ هنا لأنه غير حرج
}

/**
 * دالة إرسال إشعار الإلغاء
 */
function sendCancellationEmail($invoice, $reason, $notifyCustomer, $notifyUsers) {
    global $db;
    
    $subject = "إلغاء الفاتورة #{$invoice['invoice_number']} - نظام المطبعة";
    $message = "<html><body>";
    $message .= "<h2>إشعار إلغاء الفاتورة</h2>";
    $message .= "<p>تم إلغاء الفاتورة التالية:</p>";
    $message .= "<table border='0' cellpadding='5'>";
    $message .= "<tr><td><strong>رقم الفاتورة:</strong></td><td>#{$invoice['invoice_number']}</td></tr>";
    $message .= "<tr><td><strong>العميل:</strong></td><td>{$invoice['customer_name']}</td></tr>";
    $message .= "<tr><td><strong>تاريخ الإصدار:</strong></td><td>{$invoice['issue_date']}</td></tr>";
    $message .= "<tr><td><strong>المبلغ:</strong></td><td>" . number_format($invoice['total_amount'], 2) . " د.ل</td></tr>";
    $message .= "<tr><td><strong>سبب الإلغاء:</strong></td><td>$reason</td></tr>";
    $message .= "<tr><td><strong>تم الإلغاء بواسطة:</strong></td><td>{$invoice['created_by_name']}</td></tr>";
    $message .= "<tr><td><strong>تاريخ الإلغاء:</strong></td><td>" . date('Y-m-d H:i') . "</td></tr>";
    $message .= "</table>";
    $message .= "</body></html>";

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: نظام المطبعة <noreply@print-system.com>'
    ];

    $recipients = [];
    
    // إضافة العميل إذا طُلب ذلك
    if ($notifyCustomer && !empty($invoice['customer_email'])) {
        $recipients[] = $invoice['customer_email'];
    }
    
    // إضافة المستخدمين المختارين
    if (!empty($notifyUsers)) {
        foreach ($notifyUsers as $userId) {
            $stmt = $db->prepare("SELECT email FROM users WHERE user_id = ? AND email IS NOT NULL");
            $stmt->execute([$userId]);
            if ($email = $stmt->fetchColumn()) {
                $recipients[] = $email;
            }
        }
    }
    
    // إرسال البريد
    foreach (array_unique($recipients) as $recipient) {
        if (!empty($recipient)) {
            mail($recipient, $subject, $message, implode("\r\n", $headers));
        }
    }
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إلغاء الفاتورة - نظام المطبعة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .cancel-container {
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .invoice-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
            border-right: 4px solid #E71D36;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .summary-item {
            display: flex;
            flex-direction: column;
        }
        
        .summary-label {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .summary-value {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
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
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            outline: none;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
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
        }
        
        .btn-danger {
            background-color: #E71D36;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #C81D32;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background-color: white;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-outline:hover {
            background-color: #f8f9fa;
        }
        
        .notification-options {
            background: #fff3f3;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ffcdd2;
        }
        
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .form-check {
            margin-bottom: 10px;
        }
        
        .form-check-input {
            margin-left: 8px;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .warning-icon {
            color: #856404;
            font-size: 1.5rem;
        }
        
        .consequences-list {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .consequences-list ul {
            margin: 0;
            padding-right: 20px;
        }
        
        .consequences-list li {
            margin-bottom: 8px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <h1>إلغاء الفاتورة</h1>
                <?php include 'includes/user-menu.php'; ?>
            </div>
            
            <div class="cancel-container">
                <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle warning-icon"></i>
                    <div>
                        <h3 style="margin: 0 0 5px 0; color: #856404;">تحذير مهم</h3>
                        <p style="margin: 0; color: #856404;">عملية الإلغاء لا يمكن التراجع عنها. سيتم استعادة المنتجات إلى المخزون وإلغاء جميع التعيينات المرتبطة بهذه الفاتورة.</p>
                    </div>
                </div>
                
                <!-- ملخص الفاتورة -->
                <div class="invoice-summary">
                    <h3 style="margin-top: 0; color: #E71D36;">فاتورة #<?php echo $invoice['invoice_number']; ?></h3>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <span class="summary-label">العميل</span>
                            <span class="summary-value"><?php echo htmlspecialchars($invoice['customer_name']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">تاريخ الإصدار</span>
                            <span class="summary-value"><?php echo $invoice['issue_date']; ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">المبلغ الإجمالي</span>
                            <span class="summary-value"><?php echo number_format($invoice['total_amount'], 2); ?> د.ل</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">الحالة الحالية</span>
                            <span class="summary-value" style="color: #28a745;"><?php echo $invoice['status']; ?></span>
                        </div>
                    </div>
                </div>
                
                <form method="POST" id="cancelForm">
                    <!-- سبب الإلغاء -->
                    <div class="form-group">
                        <label for="cancellation_reason" class="form-label">سبب الإلغاء <span class="text-danger">*</span></label>
                        <textarea id="cancellation_reason" name="cancellation_reason" class="form-control" 
                                  placeholder="يرجى توضيح سبب إلغاء الفاتورة..." required></textarea>
                    </div>
                    
                    <!-- النتائج المترتبة على الإلغاء -->
                    <div class="consequences-list">
                        <h4>النتائج المترتبة على الإلغاء:</h4>
                        <ul>
                            <li>سيتم تغيير حالة الفاتورة إلى "ملغاة"</li>
                            <li>سيتم استعادة جميع الكميات إلى المخزون</li>
                            <li>سيتم إلغاء أي تعيينات للمصممين والورشة</li>
                            <li>لا يمكن التراجع عن هذه العملية</li>
                        </ul>
                    </div>
                    
                    <!-- خيارات الإشعار -->
                    <div class="notification-options">
                        <h4>خيارات الإشعار</h4>
                        
                        <div class="form-check">
                            <input type="checkbox" id="notify_customer" name="notify_customer" class="form-check-input" checked>
                            <label for="notify_customer" class="form-check-label">
                                <i class="fas fa-envelope"></i> إرسال إشعار إلغاء إلى العميل
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">إرسال إشعار إلى مستخدمين آخرين:</label>
                            <div class="users-grid">
                                <?php foreach ($users as $user): ?>
                                    <?php if ($user['user_id'] != $_SESSION['user_id'] && !empty($user['email'])): ?>
                                        <div class="form-check">
                                            <input type="checkbox" name="notify_users[]" value="<?php echo $user['user_id']; ?>" 
                                                   class="form-check-input" id="user_<?php echo $user['user_id']; ?>" checked>
                                            <label for="user_<?php echo $user['user_id']; ?>" class="form-check-label">
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                                <small class="text-muted">(<?php echo $user['role']; ?>)</small>
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- أزرار الإجراء -->
                    <div class="form-actions" style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px;">
                        <a href="view_invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-outline">
                            <i class="fas fa-times"></i> إلغاء والعودة
                        </a>
                        <button type="submit" class="btn btn-danger" onclick="return confirmCancellation()">
                            <i class="fas fa-ban"></i> تأكيد إلغاء الفاتورة
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // تأكيد الإلغاء
        function confirmCancellation() {
            const reason = document.getElementById('cancellation_reason').value.trim();
            
            if (!reason) {
                alert('يرجى إدخال سبب الإلغاء قبل المتابعة');
                return false;
            }
            
            return confirm(`هل أنت متأكد من أنك تريد إلغاء الفاتورة #<?php echo $invoice['invoice_number']; ?>؟\n\nسبب الإلغاء: ${reason}\n\nهذا الإجراء لا يمكن التراجع عنه.`);
        }
        
        // منع إرسال النموذج بالضغط على Enter في حقل النص
        document.getElementById('cancellation_reason').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>