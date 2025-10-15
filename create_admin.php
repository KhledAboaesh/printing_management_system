<?php
// في أعلى ملف create_admin.php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// لا يتطلب تسجيل دخول - متاح للجميع

// معالجة إنشاء الحساب
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // جمع البيانات من النموذج
        $full_name = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = $_POST['role']; // اختيار الدور من النموذج

        // التحقق من صحة البيانات
        if (empty($full_name)) {
            $errors[] = "الاسم الكامل مطلوب.";
        }
        
        if (empty($username)) {
            $errors[] = "اسم المستخدم مطلوب.";
        } else {
            // التحقق من عدم تكرار اسم المستخدم
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "اسم المستخدم موجود مسبقاً.";
            }
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "البريد الإلكتروني غير صحيح.";
        } else if (!empty($email)) {
            // التحقق من عدم تكرار البريد الإلكتروني
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "البريد الإلكتروني موجود مسبقاً.";
            }
        }
        
        if (empty($password)) {
            $errors[] = "كلمة المرور مطلوبة.";
        } elseif (strlen($password) < 6) {
            $errors[] = "كلمة المرور يجب أن تكون على الأقل 6 أحرف.";
        } elseif ($password !== $confirm_password) {
            $errors[] = "كلمتا المرور غير متطابقتين.";
        }

        if (empty($role)) {
            $errors[] = "يجب اختيار دور المستخدم.";
        }

        // إذا لم تكن هناك أخطاء، قم بإنشاء الحساب
        if (empty($errors)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, phone, role, is_active) 
                                  VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$username, $hashed_password, $full_name, $email, $phone, $role]);
            
            $success = true;
            
            // إرسال إلى صفحة تسجيل الدخول بعد النجاح
            header("Refresh: 3; url=login.php");
        }
    } catch (PDOException $e) {
        $errors[] = "حدث خطأ أثناء إنشاء الحساب: " . $e->getMessage();
    }
}

// جلب الأدوار المتاحة من قاعدة البيانات
$available_roles = [];
try {
    $stmt = $db->query("SELECT DISTINCT role FROM users WHERE role IS NOT NULL AND role != ''");
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // إضافة الأدوار الأساسية إذا لم تكن موجودة
    $default_roles = ['admin', 'sales', 'designer', 'workshop', 'accountant', 'hr'];
    $available_roles = array_unique(array_merge($roles, $default_roles));
    sort($available_roles);
} catch (PDOException $e) {
    $available_roles = ['admin', 'sales', 'designer', 'workshop', 'accountant', 'hr'];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب مستخدم - نظام المطبعة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .auth-container {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }
        
        .auth-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .auth-logo {
            width: 80px;
            height: 80px;
            background-color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .auth-logo i {
            font-size: 36px;
            color: var(--primary);
        }
        
        .auth-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .auth-subtitle {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .auth-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%236c757d'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: left 15px center;
            background-size: 16px;
            padding-left: 45px;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle .form-control {
            padding-right: 50px;
        }
        
        .toggle-password {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            padding: 5px;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }
        
        .btn i {
            margin-left: 8px;
        }
        
        .auth-footer {
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }
        
        .auth-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .auth-link:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .alert-danger {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--danger);
            border: 1px solid rgba(247, 37, 133, 0.2);
        }
        
        .alert-success {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success);
            border: 1px solid rgba(76, 201, 240, 0.2);
        }
        
        .alert i {
            margin-left: 10px;
        }
        
        .password-strength {
            height: 5px;
            background-color: #e9ecef;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-meter {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
            border-radius: 3px;
        }
        
        .strength-weak {
            background-color: var(--danger);
            width: 33%;
        }
        
        .strength-medium {
            background-color: var(--warning);
            width: 66%;
        }
        
        .strength-strong {
            background-color: var(--success);
            width: 100%;
        }
        
        .password-feedback {
            font-size: 12px;
            color: var(--gray);
            margin-top: 5px;
        }
        
        @media (max-width: 576px) {
            .auth-container {
                margin: 10px;
            }
            
            .auth-header {
                padding: 20px;
            }
            
            .auth-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <div class="auth-logo">
                <i class="fas fa-print"></i>
            </div>
            <h1 class="auth-title">إنشاء حساب مستخدم</h1>
            <p class="auth-subtitle">نظام إدارة المطبعة المتكامل</p>
        </div>
        
        <div class="auth-body">
            <!-- الرسائل -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo $error; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    تم إنشاء الحساب بنجاح! سيتم توجيهك إلى صفحة تسجيل الدخول...
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="full_name">الاسم الكامل *</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           value="<?php echo $_POST['full_name'] ?? ''; ?>" required
                           placeholder="أدخل الاسم الكامل">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="username">اسم المستخدم *</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?php echo $_POST['username'] ?? ''; ?>" required
                           placeholder="اختر اسم مستخدم فريد">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">البريد الإلكتروني</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo $_POST['email'] ?? ''; ?>"
                           placeholder="example@email.com">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="phone">رقم الهاتف</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?php echo $_POST['phone'] ?? ''; ?>"
                           placeholder="09XXXXXXXX">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="role">دور المستخدم *</label>
                    <select class="form-control" id="role" name="role" required>
                        <option value="">اختر دور المستخدم</option>
                        <?php foreach ($available_roles as $role): ?>
                            <option value="<?php echo $role; ?>" <?php echo (($_POST['role'] ?? '') == $role) ? 'selected' : ''; ?>>
                                <?php 
                                $role_names = [
                                    'admin' => 'مدير النظام',
                                    'sales' => 'مبيعات',
                                    'designer' => 'مصمم',
                                    'workshop' => 'ورشة عمل',
                                    'accountant' => 'محاسب',
                                    'hr' => 'موارد بشرية'
                                ];
                                echo $role_names[$role] ?? $role; 
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">كلمة المرور *</label>
                    <div class="password-toggle">
                        <input type="password" class="form-control" id="password" name="password" 
                               required placeholder="كلمة المرور (6 أحرف على الأقل)"
                               oninput="checkPasswordStrength(this.value)">
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility()">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div class="strength-meter" id="passwordStrength"></div>
                    </div>
                    <div class="password-feedback" id="passwordFeedback"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="confirm_password">تأكيد كلمة المرور *</label>
                    <div class="password-toggle">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               required placeholder="أعد إدخال كلمة المرور">
                        <button type="button" class="toggle-password" onclick="toggleConfirmPasswordVisibility()">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> إنشاء حساب
                </button>
            </form>
        </div>
        
        <div class="auth-footer">
            <p>هل لديك حساب بالفعل؟ <a href="login.php" class="auth-link">تسجيل الدخول</a></p>
        </div>
    </div>

    <script>
        // إظهار/إخفاء كلمة المرور
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.querySelector('.toggle-password i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleButton.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleButton.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
        
        function toggleConfirmPasswordVisibility() {
            const confirmInput = document.getElementById('confirm_password');
            const toggleButton = document.querySelectorAll('.toggle-password i')[1];
            
            if (confirmInput.type === 'password') {
                confirmInput.type = 'text';
                toggleButton.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                confirmInput.type = 'password';
                toggleButton.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
        
        // التحقق من قوة كلمة المرور
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('passwordStrength');
            const feedback = document.getElementById('passwordFeedback');
            
            let strength = 0;
            let feedbackText = '';
            
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // إزالة جميع classes الحالية
            strengthBar.className = 'strength-meter';
            
            if (password.length === 0) {
                feedbackText = '';
            } else if (password.length < 6) {
                feedbackText = 'كلمة المرور قصيرة جداً';
            } else if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
                feedbackText = 'كلمة المرور ضعيفة';
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
                feedbackText = 'كلمة المرور متوسطة';
            } else {
                strengthBar.classList.add('strength-strong');
                feedbackText = 'كلمة المرور قوية';
            }
            
            feedback.textContent = feedbackText;
        }
        
        // التحقق من تطابق كلمات المرور أثناء الكتابة
        document.getElementById('password').addEventListener('input', validatePassword);
        document.getElementById('confirm_password').addEventListener('input', validatePassword);
        
        function validatePassword() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('كلمتا المرور غير متطابقتين');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
        
        // منع إرسال النموذج إذا كانت هناك أخطاء
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const role = document.getElementById('role');
            
            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                alert('كلمتا المرور غير متطابقتين. يرجى التأكد من تطابق كلمات المرور.');
                confirmPassword.focus();
            }
            
            if (password.value.length < 6) {
                e.preventDefault();
                alert('كلمة المرور يجب أن تكون على الأقل 6 أحرف.');
                password.focus();
            }
            
            if (!role.value) {
                e.preventDefault();
                alert('يجب اختيار دور المستخدم.');
                role.focus();
            }
        });
    </script>
</body>
</html>