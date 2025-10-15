<?php
// ملف edit_employee.php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
checkLogin();

// الحصول على دور المستخدم من الجلسة بطريقة آمنة
$user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'guest';

// التحقق من الصلاحية - فقط مدير النظام ومسؤول الموارد البشرية
if ($user_role !== 'admin' && $user_role !== 'hr') {
    header("Location: dashboard.php");
    exit();
}

// التحقق من وجود معرف الموظف
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: employees_report.php");
    exit();
}

$employee_id = intval($_GET['id']);

// أسماء الأقسام بالعربية حسب department_id
$departments = [
    1 => 'الإدارة',
    2 => 'المبيعات',
    3 => 'الإنتاج',
    4 => 'التصميم',
    5 => 'المحاسبة',
    6 => 'الموارد البشرية'
];

// جلب بيانات الموظف الحالية
$employee = [];
try {
    $stmt = $db->prepare("
        SELECT 
            u.user_id, u.full_name, u.username, u.email, u.phone, u.role, u.is_active,
            e.employee_id, e.national_id, e.hire_date, e.position, e.department_id, e.salary,
            e.emergency_contact, e.emergency_phone
        FROM users u
        INNER JOIN employees e ON u.user_id = e.user_id
        WHERE e.employee_id = ?
    ");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        header("Location: employees_report.php");
        exit();
    }

    // اسم القسم الحالي للموظف
    $employee_department = $departments[$employee['department_id']] ?? 'غير محدد';

} catch (PDOException $e) {
    die("خطأ في جلب بيانات الموظف: " . $e->getMessage());
}

// معالجة إرسال النموذج
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // جمع البيانات من النموذج
        $full_name = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $role = $_POST['role'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $national_id = trim($_POST['national_id']);
        $hire_date = $_POST['hire_date'];
        $position = trim($_POST['position']);
        $department = $_POST['department'];
        $salary = $_POST['salary'];
        $emergency_contact = trim($_POST['emergency_contact']);
        $emergency_phone = trim($_POST['emergency_phone']);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // التحقق من صحة البيانات
        if (empty($full_name)) $errors[] = "الاسم الكامل مطلوب.";
        if (empty($username)) $errors[] = "اسم المستخدم مطلوب.";
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "البريد الإلكتروني غير صحيح.";
        if (!empty($password) && strlen($password) < 6) $errors[] = "كلمة المرور يجب أن تكون على الأقل 6 أحرف.";
        if (!empty($password) && $password !== $confirm_password) $errors[] = "كلمتا المرور غير متطابقتين.";
        if (empty($national_id)) $errors[] = "الرقم الوطني مطلوب.";
        if (empty($hire_date)) $errors[] = "تاريخ التعيين مطلوب.";
        if (empty($position)) $errors[] = "المسمى الوظيفي مطلوب.";
        if (empty($salary) || $salary <= 0) $errors[] = "الراتب يجب أن يكون قيمة صحيحة أكبر من الصفر.";

        // التحقق من التكرار باستثناء الموظف الحالي
        if (empty($errors)) {
            // اسم المستخدم
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
            $stmt->execute([$username, $employee['user_id']]);
            if ($stmt->fetchColumn() > 0) $errors[] = "اسم المستخدم موجود مسبقاً.";

            // البريد الإلكتروني
            if (!empty($email)) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
                $stmt->execute([$email, $employee['user_id']]);
                if ($stmt->fetchColumn() > 0) $errors[] = "البريد الإلكتروني موجود مسبقاً.";
            }

            // الرقم الوطني
            $stmt = $db->prepare("SELECT COUNT(*) FROM employees WHERE national_id = ? AND employee_id != ?");
            $stmt->execute([$national_id, $employee_id]);
            if ($stmt->fetchColumn() > 0) $errors[] = "الرقم الوطني موجود مسبقاً.";
        }

        // إذا لم توجد أخطاء، تحديث البيانات
        if (empty($errors)) {
            $db->beginTransaction();

            // تحديث بيانات المستخدم
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, role = ?, is_active = ?, password = ? WHERE user_id = ?");
                $stmt->execute([$full_name, $username, $email, $phone, $role, $is_active, $hashed_password, $employee['user_id']]);
            } else {
                $stmt = $db->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, role = ?, is_active = ? WHERE user_id = ?");
                $stmt->execute([$full_name, $username, $email, $phone, $role, $is_active, $employee['user_id']]);
            }

            // تحديث بيانات الموظف
            $stmt = $db->prepare("UPDATE employees SET national_id = ?, hire_date = ?, position = ?, department_id = ?, salary = ?, emergency_contact = ?, emergency_phone = ? WHERE employee_id = ?");
            $stmt->execute([$national_id, $hire_date, $position, $department, $salary, $emergency_contact, $emergency_phone, $employee_id]);

            $db->commit();

            // تسجيل النشاط
            logActivity($_SESSION['user_id'], 'update_employee', "تم تحديث بيانات الموظف: $full_name (ID: $employee_id)");

            $success = true;
            $_SESSION['success_message'] = "تم تحديث بيانات الموظف بنجاح!";

            // إعادة تحميل بيانات الموظف بعد التحديث
            $stmt = $db->prepare("
                SELECT 
                    u.user_id, u.full_name, u.username, u.email, u.phone, u.role, u.is_active,
                    e.employee_id, e.national_id, e.hire_date, e.position, e.department_id, e.salary,
                    e.emergency_contact, e.emergency_phone
                FROM users u
                INNER JOIN employees e ON u.user_id = e.user_id
                WHERE e.employee_id = ?
            ");
            $stmt->execute([$employee_id]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            $employee_department = $departments[$employee['department_id']] ?? 'غير محدد';
        }

    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        $errors[] = "حدث خطأ أثناء تحديث بيانات الموظف: " . $e->getMessage();
    }
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل بيانات الموظف - نظام المطبعة</title>
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
        
        /* نموذج التعديل */
        .form-container {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            max-width: 1000px;
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .section-title {
            font-size: 18px;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-left: 10px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-left: 10px;
            width: 18px;
            height: 18px;
        }
        
        .checkbox-group label {
            margin: 0;
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
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #3aa8c9;
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
        
        .password-note {
            font-size: 12px;
            color: var(--gray);
            margin-top: 5px;
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
            
            .btn {
                margin-bottom: 10px;
                width: 100%;
                justify-content: center;
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
                <h1>تعديل بيانات الموظف</h1>
                
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
                    تم تحديث بيانات الموظف بنجاح!
                </div>
            <?php endif; ?>
            
            <!-- نموذج تعديل الموظف -->
            <div class="form-container">
                <h2 class="form-title"><i class="fas fa-user-edit"></i> تعديل بيانات الموظف</h2>
                
                <form method="POST" action="">
                    <!-- المعلومات الأساسية -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-info-circle"></i> المعلومات الأساسية</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="full_name">الاسم الكامل *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($employee['full_name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="username">اسم المستخدم *</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($employee['username']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="email">البريد الإلكتروني</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="phone">رقم الهاتف</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="role">الدور *</label>
                                <select class="form-control" id="role" name="role" required>
                                    <option value="admin" <?php echo ($employee['role'] == 'admin') ? 'selected' : ''; ?>>مدير النظام</option>
                                    <option value="sales" <?php echo ($employee['role'] == 'sales') ? 'selected' : ''; ?>>مبيعات</option>
                                    <option value="designer" <?php echo ($employee['role'] == 'designer') ? 'selected' : ''; ?>>مصمم</option>
                                    <option value="workshop" <?php echo ($employee['role'] == 'workshop') ? 'selected' : ''; ?>>ورشة عمل</option>
                                    <option value="accountant" <?php echo ($employee['role'] == 'accountant') ? 'selected' : ''; ?>>محاسب</option>
                                    <option value="hr" <?php echo ($employee['role'] == 'hr') ? 'selected' : ''; ?>>موارد بشرية</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="national_id">الرقم الوطني *</label>
                                <input type="text" class="form-control" id="national_id" name="national_id" 
                                       value="<?php echo htmlspecialchars($employee['national_id']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($employee['is_active'] == 1) ? 'checked' : ''; ?>>
                            <label for="is_active">الحساب نشط</label>
                        </div>
                    </div>
                    
                    <!-- المعلومات الوظيفية -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-briefcase"></i> المعلومات الوظيفية</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="hire_date">تاريخ التعيين *</label>
                                <input type="date" class="form-control" id="hire_date" name="hire_date" 
                                       value="<?php echo $employee['hire_date']; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="position">المسمى الوظيفي *</label>
                                <input type="text" class="form-control" id="position" name="position" 
                                       value="<?php echo htmlspecialchars($employee['position']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="department">القسم *</label>
                              <select class="form-control" id="department" name="department" required>
    <option value="1" <?php echo ($employee['department_id'] == 1) ? 'selected' : ''; ?>>الإدارة</option>
    <option value="2" <?php echo ($employee['department_id'] == 2) ? 'selected' : ''; ?>>المبيعات</option>
    <option value="3" <?php echo ($employee['department_id'] == 3) ? 'selected' : ''; ?>>الإنتاج</option>
    <option value="4" <?php echo ($employee['department_id'] == 4) ? 'selected' : ''; ?>>التصميم</option>
    <option value="5" <?php echo ($employee['department_id'] == 5) ? 'selected' : ''; ?>>المحاسبة</option>
    <option value="6" <?php echo ($employee['department_id'] == 6) ? 'selected' : ''; ?>>الموارد البشرية</option>
</select>

                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="salary">الراتب *</label>
                                <input type="number" class="form-control" id="salary" name="salary" 
                                       value="<?php echo $employee['salary']; ?>" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- معلومات الطوارئ -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-phone-alt"></i> معلومات الطوارئ</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="emergency_contact">جهة الاتصال في حالات الطوارئ</label>
                                <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                       value="<?php echo htmlspecialchars($employee['emergency_contact'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="emergency_phone">هاتف الطوارئ</label>
                                <input type="tel" class="form-control" id="emergency_phone" name="emergency_phone" 
                                       value="<?php echo htmlspecialchars($employee['emergency_phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- كلمة المرور -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-lock"></i> كلمة المرور</h3>
                        <p class="password-note">اترك الحقول فارغة إذا لم ترد تغيير كلمة المرور</p>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="password">كلمة المرور الجديدة</label>
                                <input type="password" class="form-control" id="password" name="password">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="confirm_password">تأكيد كلمة المرور الجديدة</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                    </div>
                    
                    <!-- أزرار الإجراءات -->
                    <div class="form-group" style="margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ التعديلات
                        </button>
                        <a href="view_employee.php?id=<?php echo $employee_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> إلغاء
                        </a>
                        <a href="employees_report.php" class="btn btn-success">
                            <i class="fas fa-list"></i> العودة للقائمة
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
            
            if (password.value !== '' && password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('كلمتا المرور غير متطابقتين');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
        
        // إظهار تاريخ اليوم كحد أقصى لتاريخ التعيين
        document.getElementById('hire_date').max = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>