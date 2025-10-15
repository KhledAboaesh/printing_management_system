<?php
// في أعلى ملف login.php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        global $db;
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_active']) {
                // تخزين بيانات المستخدم في الجلسة
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role']    = $user['role']; // ✅ تم التوحيد هنا
                $_SESSION['lang']    = $_POST['lang'] ?? 'ar';

                // تسجيل النشاط وتحديث آخر دخول
                try {
                    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $stmt->execute([$user['user_id']]);

                    logActivity($user['user_id'], 'login', 'تسجيل دخول ناجح');
                } catch (PDOException $e) {
                    error_log('Login Update Error: ' . $e->getMessage());
                }

                // ✅ إعادة التوجيه حسب الدور
               switch ($user['role']) {
    case 'admin':
    case 'manager':
    case 'accountant':
        header("Location: dashboard.php");
        break;
    case 'designer':
        header("Location: designer_dashboard.php");
        break;
    case 'workshop':
        header("Location: workshop_dashboard.php");
        break;
    default:
        header("Location: dashboard.php");
}

                exit();

            } else {
                $error = "الحساب غير مفعل. الرجاء التواصل مع الإدارة.";
            }
        } else {
            $error = "اسم المستخدم أو كلمة المرور غير صحيحة";
            logActivity(0, 'login_failed', "محاولة دخول فاشلة لاسم مستخدم: $username");
        }
    } catch (PDOException $e) {
        error_log('Login Error: ' . $e->getMessage());
        $error = "حدث خطأ تقني. الرجاء المحاولة لاحقًا.";
    }
}
?>



<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام المطبعة</title>
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --danger: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 400px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .login-container:hover {
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .logo {
            width: 120px;
            margin-bottom: 20px;
        }
        
        h1 {
            color: var(--dark);
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: right;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
        }
        
        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .lang-selector {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .lang-btn {
            padding: 8px 20px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .lang-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            width: 100%;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background-color: var(--secondary);
        }
        
        .error-message {
            color: var(--danger);
            margin-top: 15px;
            padding: 10px;
            background-color: rgba(247, 37, 133, 0.1);
            border-radius: 6px;
            display: none;
        }
        
        .footer {
            margin-top: 30px;
            color: #6c757d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="images/logo.png" alt="Logo" class="logo">
        <h1>تسجيل الدخول</h1>
        
        <form id="loginForm" method="POST">
            <div class="lang-selector">
                <button type="button" class="lang-btn active" data-lang="ar">العربية</button>
                <button type="button" class="lang-btn" data-lang="en">English</button>
                <input type="hidden" name="lang" id="lang" value="ar">
            </div>
            
            <div class="form-group">
                <label for="username">اسم المستخدم</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn">دخول</button>
            
            <?php if($error): ?>
            <div class="error-message" id="errorMessage" style="display: block;">
                <?php echo $error; ?>
            </div>
            <?php else: ?>
            <div class="error-message" id="errorMessage"></div>
            <?php endif; ?>
        </form>
        
        <div class="footer">
            نظام إدارة المطبعة &copy; <?php echo date('Y'); ?>
        </div>
    </div>

    <script>
        // تغيير اللغة
        document.querySelectorAll('.lang-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.lang-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('lang').value = this.dataset.lang;
                
                // يمكنك هنا تغيير نصوص الصفحة حسب اللغة
                if(this.dataset.lang === 'en') {
                    document.querySelector('h1').textContent = 'Login';
                    document.querySelector('label[for="username"]').textContent = 'Username';
                    document.querySelector('label[for="password"]').textContent = 'Password';
                    document.querySelector('.btn').textContent = 'Login';
                } else {
                    document.querySelector('h1').textContent = 'تسجيل الدخول';
                    document.querySelector('label[for="username"]').textContent = 'اسم المستخدم';
                    document.querySelector('label[for="password"]').textContent = 'كلمة المرور';
                    document.querySelector('.btn').textContent = 'دخول';
                }
            });
        });
        
        // عرض رسائل الخطأ
        const form = document.getElementById('loginForm');
        const errorMessage = document.getElementById('errorMessage');
        
        form.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if(!username || !password) {
                e.preventDefault();
                errorMessage.textContent = 'الرجاء إدخال اسم المستخدم وكلمة المرور';
                errorMessage.style.display = 'block';
            }
        });
    </script>
</body>
</html>