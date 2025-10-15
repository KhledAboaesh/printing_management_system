<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
checkLogin();

// التحقق من صلاحية المستخدم
$user_role = $_SESSION['role'] ?? null;
if ($user_role !== 'admin' && $user_role !== 'hr') {
    header("Location: dashboard.php");
    exit();
}

// جلب الأقسام من قاعدة البيانات للقائمة المنسدلة
$departments = [];
try {
    $stmt = $db->query("SELECT department_id, name FROM departments ORDER BY name ASC");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "فشل في جلب الأقسام: " . $e->getMessage();
}

// معالجة إرسال النموذج
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // جمع البيانات من النموذج مع استخدام isset لتجنب التحذيرات
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'employee';
        $national_id = trim($_POST['national_id'] ?? '');
        $hire_date = $_POST['hire_date'] ?? '';
        $position = trim($_POST['position'] ?? '');
        $department_id = $_POST['department_id'] ?? '';
        $salary = $_POST['salary'] ?? 0;
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');
        $emergency_phone = trim($_POST['emergency_phone'] ?? '');

        // التحقق من صحة البيانات
        if (empty($full_name)) $errors[] = "الاسم الكامل مطلوب.";
        if (empty($username)) $errors[] = "اسم المستخدم مطلوب.";
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "البريد الإلكتروني غير صحيح.";
        if (empty($password)) $errors[] = "كلمة المرور مطلوبة.";
        if ($password !== $confirm_password) $errors[] = "كلمتا المرور غير متطابقتين.";
        if (empty($national_id)) $errors[] = "الرقم الوطني مطلوب.";
        if (empty($hire_date)) $errors[] = "تاريخ التعيين مطلوب.";
        if (empty($position)) $errors[] = "المسمى الوظيفي مطلوب.";
        if (empty($department_id)) $errors[] = "القسم مطلوب.";
        if (empty($salary) || $salary <= 0) $errors[] = "الراتب يجب أن يكون قيمة صحيحة أكبر من الصفر.";

        // التحقق من عدم التكرار في users و employees
        if (empty($errors)) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) $errors[] = "اسم المستخدم موجود مسبقاً.";

            if (!empty($email)) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) $errors[] = "البريد الإلكتروني موجود مسبقاً.";
            }

            $stmt = $db->prepare("SELECT COUNT(*) FROM employees WHERE national_id = ?");
            $stmt->execute([$national_id]);
            if ($stmt->fetchColumn() > 0) $errors[] = "الرقم الوطني موجود مسبقاً.";
        }

        // إذا لم توجد أخطاء، إضافة المستخدم والموظف
        if (empty($errors)) {
            $db->beginTransaction();

            // 1. إنشاء مستخدم جديد
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, phone, role, is_active) 
                                  VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$username, $hashed_password, $full_name, $email, $phone, $role]);
            $user_id = $db->lastInsertId();

            // 2. إضافة بيانات الموظف
            $stmt = $db->prepare("INSERT INTO employees 
                (user_id, national_id, hire_date, position, department_id, salary, emergency_contact, emergency_phone, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$user_id, $national_id, $hire_date, $position, $department_id, $salary, $emergency_contact, $emergency_phone]);

            $db->commit();

            // تسجيل النشاط
            logActivity($_SESSION['user_id'], 'add_employee', "تم إضافة موظف جديد: $full_name");

            $_SESSION['success_message'] = "تم إضافة الموظف بنجاح!";
            header("Location: hr.php");
            exit();
        }

    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        $errors[] = "حدث خطأ أثناء إضافة الموظف: " . $e->getMessage();
    }
}
?>





<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة موظف جديد - نظام المطبعة</title>
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
            background-color: #f5f7fa;
            color: var(--dark);
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* الشريط الجانبي */
        .sidebar {
            width: 250px;
            background-color: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            padding: 20px 0;
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        
        .sidebar-header img {
            width: 100%;
            max-width: 150px;
            display: block;
            margin: 0 auto;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border-right: 3px solid var(--primary);
        }
        
        .sidebar-menu i {
            margin-left: 10px;
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        
        /* المحتوى الرئيسي */
        .main-content {
            flex: 1;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .header h1 {
            font-size: 24px;
            color: var(--dark);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
        }
        
        .user-menu img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-left: 10px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
        }
        
        .user-role {
            font-size: 12px;
            color: var(--gray);
        }
        
        /* نموذج الإضافة */
        .form-container {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-title {
            font-size: 22px;
            margin-bottom: 25px;
            color: var(--primary);
            display: flex;
            align-items: center;
        }
        
        .form-title i {
            margin-left: 10px;
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
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
        }
        
        .btn-secondary {
            background-color: var(--gray);
            color: white;
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn i {
            margin-left: 8px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
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
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- الشريط الجانبي -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="images/logo.png" alt="Logo">
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> لوحة التحكم</a></li>
                <li><a href="customers.php"><i class="fas fa-users"></i> العملاء</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> الطلبات</a></li>
                <li><a href="inventory.php"><i class="fas fa-boxes"></i> المخزون</a></li>
                <li><a href="hr.php" class="active"><i class="fas fa-user-tie"></i> الموارد البشرية</a></li>
                <li><a href="invoices.php"><i class="fas fa-file-invoice-dollar"></i> الفواتير</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> التقارير</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a></li>
            </ul>
        </aside>
        
        <!-- المحتوى الرئيسي -->
        <main class="main-content">
            <div class="header">
                <h1>إضافة موظف جديد</h1>
                
                <div class="user-menu">
                    <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['user_id']; ?></div>
                        <div class="user-role">
                            <?php echo ($_SESSION['user_role'] ?? 'موظف') == 'admin' ? 'مدير النظام' : 'موظف مبيعات'; ?>
                        </div>
                    </div>
                    <img src="images/user.png" alt="User">
                </div>
            </div>
            
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
                    تم إضافة الموظف بنجاح!
                </div>
            <?php endif; ?>
            
            <!-- نموذج إضافة موظف -->
            <div class="form-container">
                <h2 class="form-title"><i class="fas fa-user-plus"></i> بيانات الموظف الجديد</h2>
                
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="full_name">الاسم الكامل *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?php echo $_POST['full_name'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="username">اسم المستخدم *</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo $_POST['username'] ?? ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="email">البريد الإلكتروني</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo $_POST['email'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="phone">رقم الهاتف</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo $_POST['phone'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="password">كلمة المرور *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="confirm_password">تأكيد كلمة المرور *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="role">الدور *</label>
                            <select class="form-control" id="role" name="role" required>
                                <option value="">اختر الدور</option>
                                <option value="admin" <?php echo (($_POST['role'] ?? '') == 'admin') ? 'selected' : ''; ?>>مدير النظام</option>
                                <option value="sales" <?php echo (($_POST['role'] ?? '') == 'sales') ? 'selected' : ''; ?>>مبيعات</option>
                                <option value="designer" <?php echo (($_POST['role'] ?? '') == 'designer') ? 'selected' : ''; ?>>مصمم</option>
                                <option value="workshop" <?php echo (($_POST['role'] ?? '') == 'workshop') ? 'selected' : ''; ?>>ورشة عمل</option>
                                <option value="accountant" <?php echo (($_POST['role'] ?? '') == 'accountant') ? 'selected' : ''; ?>>محاسب</option>
                                <option value="hr" <?php echo (($_POST['role'] ?? '') == 'hr') ? 'selected' : ''; ?>>موارد بشرية</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="national_id">الرقم الوطني *</label>
                            <input type="text" class="form-control" id="national_id" name="national_id" 
                                   value="<?php echo $_POST['national_id'] ?? ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="hire_date">تاريخ التعيين *</label>
                            <input type="date" class="form-control" id="hire_date" name="hire_date" 
                                   value="<?php echo $_POST['hire_date'] ?? date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="position">المسمى الوظيفي *</label>
                            <input type="text" class="form-control" id="position" name="position" 
                                   value="<?php echo $_POST['position'] ?? ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="department">القسم *</label>
          <select id="department_id" name="department_id" class="form-control" required>
    <option value="">اختر القسم</option>
    <?php if(!empty($departments)): ?>
        <?php foreach ($departments as $dep): ?>
            <option value="<?php echo $dep['department_id']; ?>" 
                <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dep['department_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($dep['name']); ?>
            </option>
        <?php endforeach; ?>
    <?php endif; ?>
</select>


                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="salary">الراتب *</label>
                            <input type="number" class="form-control" id="salary" name="salary" 
                                   value="<?php echo $_POST['salary'] ?? ''; ?>" step="0.01" min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="emergency_contact">جهة الاتصال في حالات الطوارئ</label>
                            <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                   value="<?php echo $_POST['emergency_contact'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="emergency_phone">هاتف الطوارئ</label>
                            <input type="tel" class="form-control" id="emergency_phone" name="emergency_phone" 
                                   value="<?php echo $_POST['emergency_phone'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> إضافة الموظف
                        </button>
                        <a href="hr.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> إلغاء
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // التحقق من تطابق كلمات المرور
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
    </script>
</body>
</html>