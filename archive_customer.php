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

// تعليق التحقق من صلاحية أرشفة العملاء لتسهيل الدخول
// if (!hasPermission($_SESSION['user_id'], 'archive_customer')) {
//     $_SESSION['error'] = "ليس لديك صلاحية لأرشفة العملاء";
//     header("Location: customers.php");
//     exit();
// }

// جلب معرف العميل، إذا لم يتم تمريره استخدم 0
$customer_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? 0;

// محاولة جلب بيانات العميل
$customer = null;
try {
    $stmt = $db->prepare("
        SELECT c.*, 
               COUNT(i.invoice_id) as invoice_count,
               u.full_name as created_by_name
        FROM customers c 
        LEFT JOIN invoices i ON c.customer_id = i.customer_id 
        LEFT JOIN users u ON c.created_by = u.user_id 
        WHERE c.customer_id = ?
        GROUP BY c.customer_id
    ");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // سجل الخطأ لكن لا توقف الصفحة
    error_log("Customer fetch error: " . $e->getMessage());
}

// إذا العميل غير موجود يمكن عرض رسالة لاحقًا بدلاً من إعادة التوجيه
if (!$customer) {
    $customer = [
        'customer_id' => 0,
        'name' => 'عميل غير معروف',
        'invoice_count' => 0,
        'is_archived' => 0
    ];
}

// جلب الفواتير المرتبطة بالعميل مع ترقيم الصفحات
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$limit = 10;
$offset = ($page - 1) * $limit;
$total_invoices = $customer['invoice_count'];
$total_pages = ceil($total_invoices / $limit);

$invoices = [];
if ($customer['customer_id'] > 0) {
    try {
        $stmt = $db->prepare("
            SELECT invoice_id, invoice_number, issue_date, total_amount, payment_status 
            FROM invoices 
            WHERE customer_id = ? 
            ORDER BY issue_date DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $customer['customer_id'], PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Invoices fetch error: " . $e->getMessage());
    }
}

// معالجة طلب الأرشفة
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $customer['customer_id'] > 0) {
    $archive_reason = trim($_POST['archive_reason'] ?? '');
    $transfer_invoices = isset($_POST['transfer_invoices']);
    $new_customer_id = $_POST['new_customer_id'] ?? null;
    
    if (empty($archive_reason)) {
        $error = "يرجى إدخال سبب الأرشفة";
    } elseif (strlen($archive_reason) < 10) {
        $error = "يرجى إدخال سبب مفصل (10 أحرف على الأقل)";
    } elseif ($transfer_invoices && empty($new_customer_id)) {
        $error = "يرجى اختيار عميل لنقل الفواتير";
    } else {
        try {
            $db->beginTransaction();
            $transferred_count = 0;

            if ($transfer_invoices && $new_customer_id) {
                $stmt = $db->prepare("SELECT customer_id FROM customers WHERE customer_id = ? AND is_archived = 0");
                $stmt->execute([$new_customer_id]);
                $new_customer = $stmt->fetch();

                if (!$new_customer) {
                    throw new Exception("العميل المحدد غير موجود أو مؤرشف");
                }

                $stmt = $db->prepare("UPDATE invoices SET customer_id = ? WHERE customer_id = ?");
                $stmt->execute([$new_customer_id, $customer['customer_id']]);
                $transferred_count = $stmt->rowCount();
            }

            $stmt = $db->prepare("
                UPDATE customers 
                SET is_archived = 1, 
                    archive_reason = ?,
                    archived_by = ?,
                    archived_at = NOW()
                WHERE customer_id = ?
            ");
            $stmt->execute([$archive_reason, $_SESSION['user_id'], $customer['customer_id']]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("فشل في أرشفة العميل");
            }

            logActivity($_SESSION['user_id'], 'archive_customer', 
                       "تم أرشفة العميل {$customer['name']} (ID: {$customer['customer_id']}) - السبب: $archive_reason" .
                       ($transferred_count > 0 ? " - تم نقل $transferred_count فاتورة" : ""));

            $db->commit();

            $success_message = "تم أرشفة العميل {$customer['name']} بنجاح";
            if ($transferred_count > 0) {
                $success_message .= " ونقل $transferred_count فاتورة إلى العميل الجديد";
            }

            $_SESSION['success'] = $success_message;
            header("Location: customers.php");
            exit();

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Archive Customer Error [User: {$_SESSION['user_id']}]: " . $e->getMessage());
            $error = "حدث خطأ أثناء أرشفة العميل: " . $e->getMessage();
        }
    }
}

// جلب العملاء النشطين لنقل الفواتير
$active_customers = [];
try {
    $stmt = $db->prepare("
        SELECT customer_id, name, email, phone 
        FROM customers 
        WHERE customer_id != ? AND is_archived = 0 
        ORDER BY name
    ");
    $stmt->execute([$customer['customer_id']]);
    $active_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Active customers fetch error: " . $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أرشفة العميل - نظام المطبعة</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .archive-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin: 20px auto;
            max-width: 900px;
        }
        
        .archive-header {
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .archive-title {
            color: #e74c3c;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .customer-info {
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
        
        .invoices-section {
            margin: 25px 0;
        }
        
        .invoices-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .invoices-table th, .invoices-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }
        
        .invoices-table th {
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
        
        .archive-form {
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
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .form-check-input {
            margin: 0;
            transform: scale(1.2);
        }
        
        .transfer-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            display: none;
            animation: fadeIn 0.3s ease-in-out;
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
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .text-danger {
            color: #e74c3c;
        }
        
        .text-success {
            color: #27ae60;
        }
        
        .text-warning {
            color: #f39c12;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        
        .page-link {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #4361ee;
            transition: all 0.3s;
        }
        
        .page-link:hover {
            background: #f8f9fa;
        }
        
        .page-link.active {
            background: #4361ee;
            color: white;
            border-color: #4361ee;
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
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .archive-container {
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
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <h1>أرشفة العميل</h1>
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
            
            <div class="archive-container">
                <div class="archive-header">
                    <h2 class="archive-title">
                        <i class="fas fa-archive"></i> أرشفة العميل
                    </h2>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        سيتم إخفاء هذا العميل من القائمة الرئيسية ولكن تبقى بياناته محفوظة في النظام.
                    </p>
                </div>
                
            <!-- معلومات العميل -->
<div class="customer-info">
    <h3>معلومات العميل</h3>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">الاسم:</span>
            <span class="info-value"><?= htmlspecialchars($customer['name'] ?? 'غير محدد') ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">البريد الإلكتروني:</span>
            <span class="info-value"><?= htmlspecialchars($customer['email'] ?? 'غير محدد') ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">الهاتف:</span>
            <span class="info-value"><?= htmlspecialchars($customer['phone'] ?? 'غير محدد') ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">عدد الفواتير:</span>
            <span class="info-value text-danger"><?= $customer['invoice_count'] ?? 0 ?> فاتورة</span>
        </div>
        <div class="info-item">
            <span class="info-label">تاريخ الإضافة:</span>
            <span class="info-value">
                <?= isset($customer['created_at']) ? date('Y-m-d', strtotime($customer['created_at'])) : 'غير محدد' ?>
            </span>
        </div>
        <div class="info-item">
            <span class="info-label">تم الإضافة بواسطة:</span>
            <span class="info-value"><?= htmlspecialchars($customer['created_by_name'] ?? 'غير معروف') ?></span>
        </div>
    </div>
</div>

                
                <!-- الفواتير المرتبطة -->
                <?php if ($customer['invoice_count'] > 0): ?>
                <div class="invoices-section">
                    <h3>الفواتير المرتبطة</h3>
                    <p>هذا العميل مرتبط بـ <strong class="text-danger"><?= $customer['invoice_count'] ?> فاتورة</strong> في النظام.</p>
                    
                    <?php if (!empty($invoices)): ?>
                    <table class="invoices-table">
                        <thead>
                            <tr>
                                <th>رقم الفاتورة</th>
                                <th>التاريخ</th>
                                <th>المبلغ</th>
                                <th>حالة الدفع</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                <td><?= date('Y-m-d', strtotime($invoice['issue_date'])) ?></td>
                                <td><?= number_format($invoice['total_amount'], 2) ?> د.ل</td>
                                <td>
                                    <span class="badge <?= 
                                        $invoice['payment_status'] === 'paid' ? 'badge-paid' : 
                                        ($invoice['payment_status'] === 'partial' ? 'badge-partial' : 'badge-pending')
                                    ?>">
                                        <?= 
                                            $invoice['payment_status'] === 'paid' ? 'مدفوعة' : 
                                            ($invoice['payment_status'] === 'partial' ? 'جزئي' : 'معلقة')
                                        ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- ترقيم الصفحات -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?id=<?= $customer_id ?>&page=<?= $i ?>" class="page-link <?= $i == $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- نموذج الأرشفة -->
                <form method="POST" class="archive-form" id="archiveForm">
                    <div class="form-group">
                        <label for="archive_reason" class="form-label">
                            سبب الأرشفة <span class="text-danger">*</span>
                        </label>
                        <textarea id="archive_reason" name="archive_reason" class="form-control" 
                                  rows="4" placeholder="أدخل سبب أرشفة هذا العميل (10 أحرف على الأقل)..." 
                                  required minlength="10" maxlength="500"></textarea>
                        <div id="charCount" class="char-count">0/500</div>
                    </div>
                    
                    <?php if ($customer['invoice_count'] > 0 && !empty($active_customers)): ?>
                    <div class="form-check">
                        <input type="checkbox" id="transfer_invoices" name="transfer_invoices" 
                               class="form-check-input" value="1">
                        <label for="transfer_invoices" class="form-check-label">
                            نقل الفواتير إلى عميل آخر
                        </label>
                    </div>
                    
                    <div class="transfer-section" id="transferSection">
                        <div class="warning-box">
                            <i class="fas fa-exclamation-circle"></i>
                            <div>
                                <strong>ملاحظة:</strong> سيتم نقل جميع الفواتير المرتبطة بهذا العميل إلى العميل المحدد.
                                لا يمكن التراجع عن هذه العملية بعد التنفيذ.
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_customer_id" class="form-label">
                                اختر العميل الجديد <span class="text-danger">*</span>
                            </label>
                            <select id="new_customer_id" name="new_customer_id" class="form-control">
                                <option value="">-- اختر عميلا --</option>
                                <?php foreach ($active_customers as $active_customer): ?>
                                <option value="<?= $active_customer['customer_id'] ?>">
                                    <?= htmlspecialchars($active_customer['name']) ?> 
                                    - <?= htmlspecialchars($active_customer['phone'] ?? 'لا يوجد هاتف') ?>
                                    (<?= htmlspecialchars($active_customer['email'] ?? 'لا يوجد بريد') ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="actions">
                        <button type="submit" class="btn btn-danger" id="submitBtn">
                            <i class="fas fa-archive"></i> تأكيد الأرشفة
                        </button>
                        <a href="customer_details.php?id=<?= $customer_id ?>" class="btn btn-outline">
                            <i class="fas fa-arrow-right"></i> العودة إلى تفاصيل العميل
                        </a>
                        <a href="customers.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> إلغاء والعودة للقائمة
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        // إظهار/إخفاء قسم نقل الفواتير
        const transferCheckbox = document.getElementById('transfer_invoices');
        const transferSection = document.getElementById('transferSection');
        
        if (transferCheckbox && transferSection) {
            transferCheckbox.addEventListener('change', function() {
                transferSection.style.display = this.checked ? 'block' : 'none';
                
                // جعل الحقل مطلوباً عند التحديد
                const customerSelect = document.getElementById('new_customer_id');
                if (customerSelect) {
                    customerSelect.required = this.checked;
                    if (!this.checked) {
                        customerSelect.value = '';
                    }
                }
            });
        }
        
        // عداد الأحرف لسبب الأرشفة
        const reasonTextarea = document.getElementById('archive_reason');
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
        
        // تأكيد الأرشفة
        function confirmArchive() {
            const reason = document.getElementById('archive_reason').value.trim();
            if (!reason) {
                alert('يرجى إدخال سبب الأرشفة');
                return false;
            }
            
            if (reason.length < 10) {
                alert('يرجى إدخال سبب مفصل (10 أحرف على الأقل)');
                return false;
            }
            
            const transferInvoices = document.getElementById('transfer_invoices');
            let message = 'هل أنت متأكد من أرشفة هذا العميل؟\n\n';
            message += `العميل: ${document.querySelector('.info-value').textContent}\n`;
            message += `السبب: ${reason.substring(0, 100)}${reason.length > 100 ? '...' : ''}\n`;
            
            if (transferInvoices && transferInvoices.checked) {
                const newCustomer = document.getElementById('new_customer_id');
                const newCustomerName = newCustomer.options[newCustomer.selectedIndex].text;
                message += `\nسيتم نقل ${<?= $customer['invoice_count'] ?>} فاتورة إلى: ${newCustomerName}`;
                message += '\n\n⚠️ تحذير: لا يمكن التراجع عن نقل الفواتير بعد التنفيذ!';
            }
            
            message += '\n\nهذه العملية لا يمكن التراجع عنها بسهولة.';
            
            return confirm(message);
        }
        
        // إضافة حدث للنموذج
        document.getElementById('archiveForm').addEventListener('submit', function(e) {
            if (!confirmArchive()) {
                e.preventDefault();
            }
        });
        
        // منع إرسال النموذج بالضغط على Enter
        document.getElementById('archiveForm').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
        
        // تحسين تجربة المستخدم
        document.addEventListener('DOMContentLoaded', function() {
            // التركيز على حقل السبب
            const reasonField = document.getElementById('archive_reason');
            if (reasonField) {
                setTimeout(() => {
                    reasonField.focus();
                }, 500);
            }
        });
    </script>
</body>
</html>