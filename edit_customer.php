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
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: customers.php?error=invalid_id");
    exit();
}

$customer_id = intval($_GET['id']);

// جلب بيانات العميل الحالية
$customer = [];
try {
    $stmt = $db->prepare("SELECT * FROM customers WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        header("Location: customers.php?error=customer_not_found");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching customer: " . $e->getMessage());
    header("Location: customers.php?error=db_error");
    exit();
}

// معالجة إرسال النموذج
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // جمع البيانات من النموذج
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $is_vip = isset($_POST['is_vip']) ? 1 : 0;
        $notes = trim($_POST['notes'] ?? '');
        
        // التحقق من البيانات المطلوبة
        if (empty($name) || empty($phone)) {
            throw new Exception("الاسم ورقم الهاتف حقول مطلوبة");
        }
        
        // معالجة ملف PDF إذا تم رفعه
        $pdf_path = $customer['id_proof_path'];
        if (isset($_FILES['id_proof']) && $_FILES['id_proof']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['application/pdf'];
            $file_type = $_FILES['id_proof']['type'];
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("يجب رفع ملف PDF فقط");
            }
            
            $upload_dir = __DIR__ . '/uploads/customers_docs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // حذف الملف القديم إذا كان موجوداً
            if (!empty($pdf_path) && file_exists(__DIR__ . '/' . $pdf_path)) {
                unlink(__DIR__ . '/' . $pdf_path);
            }
            
            $file_name = uniqid() . '.pdf';
            $pdf_path = 'uploads/customers_docs/' . $file_name;
            
            if (!move_uploaded_file($_FILES['id_proof']['tmp_name'], __DIR__ . '/' . $pdf_path)) {
                throw new Exception("حدث خطأ أثناء رفع الملف");
            }
        }
        
        // تحديث بيانات العميل في قاعدة البيانات
        $stmt = $db->prepare("UPDATE customers SET 
                            name = ?, 
                            phone = ?, 
                            email = ?, 
                            address = ?, 
                            company_name = ?, 
                            is_vip = ?, 
                            notes = ?, 
                            id_proof_path = ?,
                            updated_at = NOW()
                            WHERE customer_id = ?");
        
        $stmt->execute([
            $name, $phone, $email, $address, $company, $is_vip, $notes, $pdf_path, $customer_id
        ]);
        
        $success = "تم تحديث بيانات العميل بنجاح!";
        logActivity($_SESSION['user_id'], 'update_customer', "تم تحديث بيانات العميل: $name (ID: $customer_id)");
        
        // جلب البيانات المحدثة
        $stmt = $db->prepare("SELECT * FROM customers WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل عميل | نظام إدارة العملاء</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root {
        --primary: #4361ee;
        --primary-light: #e0e7ff;
        --secondary: #3f37c9;
        --success: #4cc9f0;
        --success-light: #e6f7fe;
        --warning: #f8961e;
        --warning-light: #fff4e6;
        --danger: #f72585;
        --danger-light: #ffebf3;
        --light: #f8f9fa;
        --dark: #212529;
        --gray: #6c757d;
        --light-gray: #f1f3f5;
        --border-color: #e0e0e0;
        --border-radius: 12px;
        --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        --transition: all 0.3s ease;
    }
    
    body {
        font-family: 'Tajawal', sans-serif;
        background-color: #f5f7fb;
        color: var(--dark);
        line-height: 1.6;
    }
    
    .edit-container {
        max-width: 850px;
        margin: 2rem auto;
        padding: 0 1rem;
    }
    
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border-color);
    }
    
    .header h1 {
        color: var(--primary);
        font-size: 1.75rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .header h1 i {
        font-size: 1.5rem;
    }
    
    .back-btn {
        background-color: var(--primary);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: var(--border-radius);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: var(--transition);
        font-weight: 500;
        box-shadow: 0 2px 5px rgba(67, 97, 238, 0.2);
    }
    
    .back-btn:hover {
        background-color: var(--secondary);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(67, 97, 238, 0.3);
    }
    
    .customer-id-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        background-color: var(--light-gray);
        border-radius: 6px;
        font-size: 0.9rem;
        margin-bottom: 1.5rem;
        color: var(--gray);
        font-weight: 500;
    }
    
    .customer-id-badge span {
        color: var(--primary);
        font-weight: 700;
    }
    
    .customer-form {
        background: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        padding: 2rem;
        margin-bottom: 2rem;
    }
    
    .form-section {
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px dashed var(--border-color);
    }
    
    .form-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .form-section h3 {
        color: var(--primary);
        margin-bottom: 1.5rem;
        font-size: 1.25rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .form-section h3 i {
        font-size: 1.1rem;
    }
    
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-label {
        display: block;
        margin-bottom: 0.75rem;
        font-weight: 600;
        color: var(--dark);
        font-size: 0.95rem;
    }
    
    .form-label.required:after {
        content: " *";
        color: var(--danger);
    }
    
    .form-control {
        width: 100%;
        padding: 0.85rem 1rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.95rem;
        transition: var(--transition);
        font-family: 'Tajawal', sans-serif;
    }
    
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-light);
        outline: none;
    }
    
    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }
    
    .form-check {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .form-check-input {
        width: 20px;
        height: 20px;
        accent-color: var(--primary);
        cursor: pointer;
    }
    
    .form-check-label {
        font-weight: 500;
        cursor: pointer;
    }
    
    .file-upload {
        position: relative;
        overflow: hidden;
        display: inline-block;
        width: 100%;
    }
    
    .file-upload-btn {
        width: 100%;
        padding: 1rem;
        border: 1px dashed var(--border-color);
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        background-color: var(--light-gray);
        transition: var(--transition);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
    }
    
    .file-upload-btn:hover {
        background-color: #e9ecef;
        border-color: var(--primary);
    }
    
    .file-upload-btn i {
        font-size: 1.5rem;
        color: var(--primary);
    }
    
    .file-upload-input {
        position: absolute;
        left: 0;
        top: 0;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
    }
    
    .file-name {
        margin-top: 0.5rem;
        font-size: 0.85rem;
        color: var(--gray);
        text-align: center;
    }
    
    .document-link {
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: var(--transition);
    }
    
    .document-link:hover {
        color: var(--secondary);
        text-decoration: underline;
    }
    
    .document-link i {
        font-size: 1.1rem;
    }
    
    .btn-group {
        display: flex;
        gap: 1rem;
        margin-top: 2.5rem;
    }
    
    .btn {
        padding: 0.85rem 1.75rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        font-size: 0.95rem;
        font-family: 'Tajawal', sans-serif;
    }
    
    .btn-primary {
        background-color: var(--primary);
        color: white;
        box-shadow: 0 2px 5px rgba(67, 97, 238, 0.2);
        flex: 1;
    }
    
    .btn-primary:hover {
        background-color: var(--secondary);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(67, 97, 238, 0.3);
    }
    
    .btn-outline {
        background-color: white;
        color: var(--primary);
        border: 1px solid var(--primary);
        flex: 1;
    }
    
    .btn-outline:hover {
        background-color: var(--primary-light);
    }
    
    .alert {
        padding: 1rem 1.25rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        border: 1px solid transparent;
    }
    
    .alert i {
        font-size: 1.2rem;
        margin-top: 2px;
    }
    
    .alert-success {
        background-color: var(--success-light);
        color: var(--success);
        border-color: rgba(76, 201, 240, 0.3);
    }
    
    .alert-danger {
        background-color: var(--danger-light);
        color: var(--danger);
        border-color: rgba(247, 37, 133, 0.3);
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .header h1 {
            font-size: 1.5rem;
        }
        
        .back-btn {
            width: 100%;
            justify-content: center;
        }
        
        .btn-group {
            flex-direction: column;
        }
        
        .customer-form {
            padding: 1.5rem;
        }
    }
    
    @media (max-width: 576px) {
        .edit-container {
            padding: 0 0.75rem;
        }
        
        .form-section h3 {
            font-size: 1.1rem;
        }
        
        .form-control {
            padding: 0.75rem;
        }
    }
</style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="edit-container">
                <div class="header">
                    <h1><i class="fas fa-user-edit"></i> تعديل بيانات العميل</h1>
                    <a href="view_customer.php?id=<?= $customer_id ?>" class="back-btn">
                        <i class="fas fa-arrow-right"></i> رجوع إلى بيانات العميل
                    </a>
                </div>
                
                <div class="customer-id-badge">
                    رقم العميل: #<?= str_pad($customer_id, 5, '0', STR_PAD_LEFT) ?>
                </div>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" class="customer-form">
                    <div class="form-section">
                        <h3><i class="fas fa-user"></i> المعلومات الأساسية</h3>
                        
                        <div class="form-group">
                            <label for="name" class="form-label">الاسم الكامل <span style="color: var(--danger);">*</span></label>
                            <input type="text" id="name" name="name" class="form-control" required 
                                   value="<?= htmlspecialchars($customer['name']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone" class="form-label">رقم الهاتف <span style="color: var(--danger);">*</span></label>
                            <input type="tel" id="phone" name="phone" class="form-control" required
                                   value="<?= htmlspecialchars($customer['phone']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">البريد الإلكتروني</label>
                            <input type="email" id="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($customer['email'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-building"></i> معلومات إضافية</h3>
                        
                        <div class="form-group">
                            <label for="company" class="form-label">اسم الشركة (إذا كان عميل شركة)</label>
                            <input type="text" id="company" name="company" class="form-control"
                                   value="<?= htmlspecialchars($customer['company_name'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address" class="form-label">العنوان</label>
                            <textarea id="address" name="address" class="form-control" rows="3"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-check">
                            <input type="checkbox" id="is_vip" name="is_vip" class="form-check-input"
                                   <?= $customer['is_vip'] ? 'checked' : '' ?>>
                            <label for="is_vip" class="form-label">عميل مميز (VIP)</label>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3><i class="fas fa-file-alt"></i> المستندات والملاحظات</h3>
                        
                        <div class="form-group">
                            <label class="form-label">إثبات الهوية (PDF فقط)</label>
                            <div class="file-upload">
                                <div class="file-upload-btn">
                                    <i class="fas fa-cloud-upload-alt"></i> اختر ملف PDF
                                    <div class="file-name" id="file-name">
                                        <?php if (!empty($customer['id_proof_path'])): ?>
                                        <a href="<?= htmlspecialchars($customer['id_proof_path']) ?>" class="document-link" target="_blank">
                                            <i class="fas fa-file-pdf"></i> عرض الملف الحالي
                                        </a>
                                        <?php else: ?>
                                        لم يتم اختيار ملف
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <input type="file" name="id_proof" id="id_proof" class="file-upload-input" accept=".pdf">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes" class="form-label">ملاحظات إضافية</label>
                            <textarea id="notes" name="notes" class="form-control" rows="3"><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ التعديلات
                        </button>
                        <a href="view_customer.php?id=<?= $customer_id ?>" class="btn btn-outline">
                            <i class="fas fa-times"></i> إلغاء
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // عرض اسم الملف المختار
        document.getElementById('id_proof').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'لم يتم اختيار ملف';
            document.getElementById('file-name').textContent = fileName;
        });
        
        // التحقق من النموذج قبل الإرسال
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const phone = document.getElementById('phone').value.trim();
            
            if (!name || !phone) {
                e.preventDefault();
                alert('الاسم ورقم الهاتف حقول مطلوبة');
            }
        });
    </script>
</body>
</html>