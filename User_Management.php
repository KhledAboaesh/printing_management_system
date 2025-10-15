<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (الإدارة)
if ($_SESSION['role'] != 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// معالجة عمليات إدارة المستخدمين
$message = '';
$error = '';

// إنشاء مستخدم جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'user';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    try {
        // التحقق من البيانات
        if (empty($username) || empty($email) || empty($password)) {
            $error = "جميع الحقول مطلوبة";
        } elseif ($password !== $confirm_password) {
            $error = "كلمات المرور غير متطابقة";
        } else {
            // التحقق من عدم وجود اسم مستخدم مكرر
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() > 0) {
                $error = "اسم المستخدم موجود مسبقاً";
            } else {
                // إنشاء المستخدم
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, email, password, role, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                $stmt->execute([$username, $email, $hashed_password, $role]);
                
                $message = "تم إنشاء المستخدم بنجاح";
                logActivity($_SESSION['user_id'], 'create_user', "إنشاء مستخدم جديد: $username");
            }
        }
    } catch (PDOException $e) {
        error_log('Create User Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء إنشاء المستخدم";
    }
}

// تفعيل/تعطيل حساب
if (isset($_GET['toggle_user'])) {
    $user_id = $_GET['toggle_user'];
    
    try {
        $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        $action = $stmt->rowCount() > 0 ? "تم تغيير حالة المستخدم" : "لم يتم تغيير حالة المستخدم";
        $message = $action;
        logActivity($_SESSION['user_id'], 'toggle_user', "تغيير حالة مستخدم: $user_id");
    } catch (PDOException $e) {
        error_log('Toggle User Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء تغيير حالة المستخدم";
    }
}

// إعادة تعيين كلمة المرور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $user_id = $_POST['user_id'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';
    
    try {
        if (empty($new_password) || empty($confirm_new_password)) {
            $error = "كلمة المرور الجديدة مطلوبة";
        } elseif ($new_password !== $confirm_new_password) {
            $error = "كلمات المرور غير متطابقة";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $message = "تم إعادة تعيين كلمة المرور بنجاح";
            logActivity($_SESSION['user_id'], 'reset_password', "إعادة تعيين كلمة مرور مستخدم: $user_id");
        }
    } catch (PDOException $e) {
        error_log('Reset Password Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء إعادة تعيين كلمة المرور";
    }
}

// جلب جميع المستخدمين
try {
    $stmt = $db->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Fetch Users Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب بيانات المستخدمين";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - إدارة المستخدمين</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --bg-color: #f8f9fa;
            --text-color: #333;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tajawal', sans-serif;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .page-title {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--secondary-color);
            text-align: center;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }
        
        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: background-color 0.3s;
            border-bottom: 3px solid transparent;
        }
        
        .tab.active {
            border-bottom: 3px solid var(--primary-color);
            background-color: #f0f7ff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        .form-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
            background-color: white;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        
        .table th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        .table tr:hover {
            background-color: #f5f5f5;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .badge-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .badge-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .message {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .table {
                display: block;
                overflow-x: auto;
            }
            
            .tabs {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">نظام إدارة الطباعة</div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo substr($_SESSION['username'] ?? 'U', 0, 1); ?>
                </div>
                <div>
                    <div><?php echo $_SESSION['username'] ?? 'مستخدم'; ?></div>
                    <div style="font-size: 12px; color: #777;">مدير النظام</div>
                </div>
            </div>
        </header>
        
        <h1 class="page-title">إدارة المستخدمين</h1>
        
        <?php if (!empty($message)): ?>
            <div class="message message-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="message message-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('users-list')">قائمة المستخدمين</div>
            <div class="tab" onclick="switchTab('create-user')">إنشاء مستخدم جديد</div>
        </div>
        
        <div id="users-list" class="tab-content active">
            <div class="card">
                <h2>جميع المستخدمين</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>اسم المستخدم</th>
                            <th>البريد الإلكتروني</th>
                            <th>الصفة</th>
                            <th>الحالة</th>
                            <th>تاريخ الإنشاء</th>
                            <th>آخر دخول</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? 'غير محدد'); ?></td>
                                <td>
                                    <?php 
                                    $role_labels = [
                                        'admin' => 'مدير النظام',
                                        'hr' => 'موارد بشرية',
                                        'accounting' => 'محاسبة',
                                        'user' => 'مستخدم'
                                    ];
                                    echo $role_labels[$user['role']] ?? $user['role']; 
                                    ?>
                                </td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge badge-success">مفعل</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">معطل</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'لم يسجل دخول'; ?></td>
                                <td class="action-buttons">
                                    <a href="?toggle_user=<?php echo $user['user_id']; ?>" class="action-btn <?php echo $user['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                        <?php echo $user['is_active'] ? 'تعطيل' : 'تفعيل'; ?>
                                    </a>
                                    <button onclick="openResetModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="action-btn btn-primary">إعادة تعيين كلمة المرور</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">لا يوجد مستخدمين</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div id="create-user" class="tab-content">
            <div class="card">
                <h2>إنشاء مستخدم جديد</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">اسم المستخدم</label>
                        <input type="text" name="username" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الصفة</label>
                        <select name="role" class="form-select" required>
                            <option value="user">مستخدم</option>
                            <option value="hr">موارد بشرية</option>
                            <option value="accounting">محاسبة</option>
                            <option value="admin">مدير النظام</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">كلمة المرور</label>
                        <input type="password" name="password" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">تأكيد كلمة المرور</label>
                        <input type="password" name="confirm_password" class="form-input" required>
                    </div>
                    
                    <button type="submit" name="create_user" class="btn btn-primary">إنشاء مستخدم</button>
                </form>
            </div>
        </div>
        
        <!-- Modal لإعادة تعيين كلمة المرور -->
        <div id="resetModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
            <div style="background-color: white; padding: 20px; border-radius: 8px; width: 400px; max-width: 90%;">
                <h2 id="modalTitle">إعادة تعيين كلمة المرور</h2>
                <form method="POST" action="">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    
                    <div class="form-group">
                        <label class="form-label">كلمة المرور الجديدة</label>
                        <input type="password" name="new_password" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">تأكيد كلمة المرور الجديدة</label>
                        <input type="password" name="confirm_new_password" class="form-input" required>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" onclick="closeResetModal()" class="btn btn-warning">إلغاء</button>
                        <button type="submit" name="reset_password" class="btn btn-primary">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabId) {
            // إخفاء جميع محتويات التبويبات
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // إلغاء تنشيط جميع التبويبات
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // تفعيل التبويب المحدد
            document.getElementById(tabId).classList.add('active');
            
            // تفعيل زر التبويب المحدد
            document.querySelectorAll('.tab').forEach(tab => {
                if (tab.textContent === (tabId === 'users-list' ? 'قائمة المستخدمين' : 'إنشاء مستخدم جديد')) {
                    tab.classList.add('active');
                }
            });
        }
        
        function openResetModal(userId, username) {
            document.getElementById('reset_user_id').value = userId;
            document.getElementById('modalTitle').textContent = `إعادة تعيين كلمة المرور للمستخدم: ${username}`;
            document.getElementById('resetModal').style.display = 'flex';
        }
        
        function closeResetModal() {
            document.getElementById('resetModal').style.display = 'none';
        }
        
        // إغلاق المودال عند النقر خارج المحتوى
        document.getElementById('resetModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeResetModal();
            }
        });
    </script>
</body>
</html>