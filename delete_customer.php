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

// التحقق من وجود معرف العميل
$customer_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

if (!$customer_id) {
    $_SESSION['error'] = "معرف العميل غير صالح";
    header("Location: customers.php");
    exit();
}

// جلب بيانات العميل
try {
    $stmt = $db->prepare("
        SELECT c.*, COUNT(i.invoice_id) as invoice_count 
        FROM customers c 
        LEFT JOIN invoices i ON c.customer_id = i.customer_id 
        WHERE c.customer_id = ?
        GROUP BY c.customer_id
    ");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "خطأ في جلب بيانات العميل: " . $e->getMessage();
    header("Location: customers.php");
    exit();
}

if (!$customer) {
    $_SESSION['error'] = "العميل غير موجود";
    header("Location: customers.php");
    exit();
}

// معالجة حذف العميل عند تأكيد الإجراء
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = $_POST['confirm'] ?? '';
    
    if ($confirm === 'نعم') {
        try {
            // بدء المعاملة
            $db->beginTransaction();

            // التحقق من وجود فواتير مرتبطة بالعميل
            if ($customer['invoice_count'] > 0) {
                throw new Exception("لا يمكن حذف العميل لأنه مرتبط بفواتير موجودة. يمكنك أرشفة العميل بدلاً من ذلك.");
            }

            // حذف العميل
            $stmt = $db->prepare("DELETE FROM customers WHERE customer_id = ?");
            $stmt->execute([$customer_id]);

            // تسجيل النشاط
            logActivity($_SESSION['user_id'], 'delete_customer', 
                "تم حذف العميل: {$customer['name']} (ID: {$customer_id})");

            $db->commit();

            $_SESSION['success'] = "تم حذف العميل بنجاح";
            header("Location: customers.php");
            exit();

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error'] = "حدث خطأ أثناء حذف العميل: " . $e->getMessage();
            header("Location: delete_customer.php?id=" . $customer_id);
            exit();
        }
    } else {
        $_SESSION['error'] = "لم يتم تأكيد الحذف";
        header("Location: customers.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حذف العميل - نظام المطبعة</title>
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
            max-width: 600px;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .warning-icon {
            color: #f39c12;
            font-size: 24px;
            margin-bottom: 10px;
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
            margin-top: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
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
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
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
        
        .confirmation-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 5px;
            font-size: 14px;
            text-align: center;
            margin: 15px 0;
        }
        
        .confirmation-input:focus {
            border-color: #dc3545;
            outline: none;
        }
        
        .instructions {
            text-align: center;
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
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
            .delete-container {
                margin: 10px;
                padding: 20px;
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
                <h1>حذف العميل</h1>
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
            
            <div class="delete-container">
                <div class="warning-box">
                    <div style="text-align: center;">
                        <div class="warning-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3 style="color: #856404; margin: 0;">تحذير!</h3>
                    </div>
                    <p style="text-align: center; margin: 15px 0; line-height: 1.6;">
                        أنت على وشك حذف عميل من النظام. هذا الإجراء لا يمكن التراجع عنه.
                        يرجى التأكد تماماً من أنك تريد متابعة هذا الإجراء.
                    </p>
                </div>
                
                <div class="customer-info">
                    <h3 style="margin-top: 0; color: #4361ee;">معلومات العميل</h3>
                    <div class="info-grid">
                        <div>
                            <div class="info-item">
                                <span>الاسم:</span>
                                <span style="font-weight: 500;"><?= htmlspecialchars($customer['name']) ?></span>
                            </div>
                            <div class="info-item">
                                <span>البريد الإلكتروني:</span>
                                <span><?= htmlspecialchars($customer['email'] ?: 'غير محدد') ?></span>
                            </div>
                            <div class="info-item">
                                <span>الهاتف:</span>
                                <span><?= htmlspecialchars($customer['phone'] ?: 'غير محدد') ?></span>
                            </div>
                        </div>
                        <div>
                            <div class="info-item">
                                <span>الشركة:</span>
                                <span><?= htmlspecialchars($customer['company_name'] ?: 'غير محدد') ?></span>
                            </div>
                            <div class="info-item">
                                <span>عدد الفواتير:</span>
                                <span style="font-weight: 500; color: <?= $customer['invoice_count'] > 0 ? '#dc3545' : '#28a745' ?>">
                                    <?= $customer['invoice_count'] ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span>تاريخ التسجيل:</span>
                                <span><?= date('Y-m-d', strtotime($customer['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($customer['invoice_count'] > 0): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-ban"></i>
                    <strong>لا يمكن حذف هذا العميل!</strong>
                    <p style="margin: 10px 0 0 0;">
                        هذا العميل مرتبط بـ <?= $customer['invoice_count'] ?> فاتورة. 
                        للحفاظ على سلامة البيانات، يرجى أرشفة العميل بدلاً من حذفه.
                    </p>
                </div>
                <?php else: ?>
                <form method="POST" id="delete-form">
                    <div class="instructions">
                        <p>لكي تتمكن من حذف هذا العميل، يرجى كتابة كلمة <strong>"نعم"</strong> في الحقل أدناه:</p>
                    </div>
                    
                    <input type="text" 
                           name="confirm" 
                           class="confirmation-input" 
                           placeholder="اكتب 'نعم' هنا" 
                           required
                           autocomplete="off">
                    
                    <div class="actions">
                        <button type="submit" class="btn btn-danger" id="delete-btn">
                            <i class="fas fa-trash"></i> حذف العميل
                        </button>
                        <a href="customers.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> إلغاء والعودة
                        </a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const deleteForm = document.getElementById('delete-form');
            const confirmInput = document.querySelector('input[name="confirm"]');
            const deleteBtn = document.getElementById('delete-btn');
            
            if (deleteForm) {
                // تعطيل الزر حتى يتم كتابة "نعم"
                deleteBtn.disabled = true;
                deleteBtn.style.opacity = '0.6';
                deleteBtn.style.cursor = 'not-allowed';
                
                confirmInput.addEventListener('input', function() {
                    if (this.value.trim() === 'نعم') {
                        deleteBtn.disabled = false;
                        deleteBtn.style.opacity = '1';
                        deleteBtn.style.cursor = 'pointer';
                    } else {
                        deleteBtn.disabled = true;
                        deleteBtn.style.opacity = '0.6';
                        deleteBtn.style.cursor = 'not-allowed';
                    }
                });
                
                deleteForm.addEventListener('submit', function(e) {
                    if (confirmInput.value.trim() !== 'نعم') {
                        e.preventDefault();
                        alert('يرجى كتابة "نعم" للتأكيد');
                        return;
                    }
                    
                    if (!confirm('هل أنت متأكد تماماً من أنك تريد حذف هذا العميل؟ هذا الإجراء لا يمكن التراجع عنه.')) {
                        e.preventDefault();
                    }
                });
            }
            
            // إضافة تأثير عند التمرير
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>