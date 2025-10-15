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

$error = '';
$success = '';

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
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
        $pdf_path = '';
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
            
            $file_name = uniqid() . '.pdf';
            $pdf_path = 'uploads/customers_docs/' . $file_name;
            
            if (!move_uploaded_file($_FILES['id_proof']['tmp_name'], __DIR__ . '/' . $pdf_path)) {
                throw new Exception("حدث خطأ أثناء رفع الملف");
            }
        }
        
        // إدراج العميل في قاعدة البيانات
        $stmt = $db->prepare("INSERT INTO customers 
                            (name, phone, email, address, company_name, is_vip, notes, created_by, id_proof_path) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $name, $phone, $email, $address, $company, $is_vip, $notes, 
            $_SESSION['user_id'], $pdf_path
        ]);
        
        $success = "تم إضافة العميل بنجاح!";
        logActivity($_SESSION['user_id'], 'add_customer', "تم إضافة عميل جديد: $name");
        
        // إعادة توجيه أو إفراغ النموذج
        if (isset($_POST['save_and_new'])) {
            header("Location: add_customer.php?success=1");
            exit();
        } elseif (isset($_POST['save_and_list'])) {
            header("Location: customers.php?success=1");
            exit();
        }
        
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
    <title>إضافة عميل جديد - نظام المطبعة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        
        .form-title {
            color: var(--primary);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            font-size: 22px;
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
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
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
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
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
        
        .file-upload {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload-btn {
            width: 100%;
            padding: 12px;
            border: 1px dashed #ccc;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            background-color: #f9f9f9;
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
            margin-top: 5px;
            font-size: 13px;
            color: var(--gray);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success);
            border: 1px solid rgba(76, 201, 240, 0.3);
        }
        
        .alert-danger {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--danger);
            border: 1px solid rgba(247, 37, 133, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <h1>إضافة عميل جديد</h1>
                <?php include 'includes/user-menu.php'; ?>
            </div>
            
            <div class="form-container">
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <h2 class="form-title"><i class="fas fa-user-plus"></i> بيانات العميل الأساسية</h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name" class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" required 
                               value="<?php echo $_POST['name'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone" class="form-label">رقم الهاتف <span class="text-danger">*</span></label>
                        <input type="tel" id="phone" name="phone" class="form-control" required
                               value="<?php echo $_POST['phone'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">البريد الإلكتروني</label>
                        <input type="email" id="email" name="email" class="form-control"
                               value="<?php echo $_POST['email'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="company" class="form-label">اسم الشركة (إذا كان عميل شركة)</label>
                        <input type="text" id="company" name="company" class="form-control"
                               value="<?php echo $_POST['company'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="address" class="form-label">العنوان</label>
                        <textarea id="address" name="address" class="form-control" rows="3"><?php echo $_POST['address'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" id="is_vip" name="is_vip" class="form-check-input"
                               <?php echo isset($_POST['is_vip']) ? 'checked' : ''; ?>>
                        <label for="is_vip" class="form-label">عميل مميز (VIP)</label>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes" class="form-label">ملاحظات إضافية</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"><?php echo $_POST['notes'] ?? ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">إثبات الهوية (PDF فقط)</label>
                        <div class="file-upload">
                            <div class="file-upload-btn">
                                <i class="fas fa-cloud-upload-alt"></i> اختر ملف PDF
                                <div class="file-name" id="file-name">لم يتم اختيار ملف</div>
                            </div>
                            <input type="file" name="id_proof" id="id_proof" class="file-upload-input" accept=".pdf">
                        </div>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" name="save_and_list" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ وعرض القائمة
                        </button>
                        <button type="submit" name="save_and_new" class="btn btn-outline">
                            <i class="fas fa-plus-circle"></i> حفظ وإضافة جديد
                        </button>
                        <a href="customers.php" class="btn btn-outline">
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