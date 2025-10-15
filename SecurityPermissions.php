<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (مدير أو مسؤول أمن)
if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'security_manager') {
    header("Location: unauthorized.php");
    exit();
}

// معالجة عمليات إدارة الصلاحيات
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        global $db;
        
        if (isset($_POST['update_role'])) {
            $user_id = $_POST['user_id'];
            $new_role = $_POST['role'];
            
            $stmt = $db->prepare("UPDATE users SET role = ? WHERE user_id = ?");
            $stmt->execute([$new_role, $user_id]);
            
            $message = "تم تحديث صلاحية المستخدم بنجاح";
            $message_type = "success";
            
            // تسجيل النشاط
            logActivity($_SESSION['user_id'], 'update_role', "تحديث صلاحية المستخدم $user_id إلى $new_role");
        }
        
        if (isset($_POST['update_password_policy'])) {
            $min_length = $_POST['min_length'];
            $require_uppercase = isset($_POST['require_uppercase']) ? 1 : 0;
            $require_lowercase = isset($_POST['require_lowercase']) ? 1 : 0;
            $require_numbers = isset($_POST['require_numbers']) ? 1 : 0;
            $require_special_chars = isset($_POST['require_special_chars']) ? 1 : 0;
            $expiry_days = $_POST['expiry_days'];
            
            // هنا يمكنك حفظ إعدادات سياسة كلمة المرور في جدول الإعدادات
            // هذا مثال افتراضي حيث نعتقد أن لديك جدول settings
            $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES 
                                ('password_min_length', ?),
                                ('password_require_uppercase', ?),
                                ('password_require_lowercase', ?),
                                ('password_require_numbers', ?),
                                ('password_require_special_chars', ?),
                                ('password_expiry_days', ?)");
            $stmt->execute([$min_length, $require_uppercase, $require_lowercase, 
                          $require_numbers, $require_special_chars, $expiry_days]);
            
            $message = "تم تحديث سياسة كلمات المرور بنجاح";
            $message_type = "success";
            
            // تسجيل النشاط
            logActivity($_SESSION['user_id'], 'update_password_policy', "تحديث سياسة كلمات المرور");
        }
    } catch (PDOException $e) {
        error_log('Security Management Error: ' . $e->getMessage());
        $message = "حدث خطأ أثناء عملية الحفظ: " . $e->getMessage();
        $message_type = "error";
    }
}

// جلب بيانات المستخدمين والأدوار
try {
    global $db;
    
    // جلب جميع المستخدمين مع أدوارهم
    $stmt = $db->query("SELECT user_id, username, role, is_active, last_login FROM users ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب سجلات الأمان (نستخدم جدول activity_log الموجود لديك)
    $stmt = $db->query("SELECT al.*, u.username 
                       FROM activity_log al 
                       LEFT JOIN users u ON al.user_id = u.user_id 
                       ORDER BY al.created_at DESC 
                       LIMIT 100");
    $security_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب إعدادات سياسة كلمة المرور (افتراضي)
    $password_policy = [
        'min_length' => 8,
        'require_uppercase' => 1,
        'require_lowercase' => 1,
        'require_numbers' => 1,
        'require_special_chars' => 1,
        'expiry_days' => 90
    ];
    
    // محاولة جلب الإعدادات من جدول الإعدادات إذا كان موجوداً
    try {
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings 
                           WHERE setting_key LIKE 'password_%' OR setting_key = 'min_length'");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if (!empty($settings)) {
            $password_policy = [
                'min_length' => $settings['password_min_length'] ?? 8,
                'require_uppercase' => $settings['password_require_uppercase'] ?? 1,
                'require_lowercase' => $settings['password_require_lowercase'] ?? 1,
                'require_numbers' => $settings['password_require_numbers'] ?? 1,
                'require_special_chars' => $settings['password_require_special_chars'] ?? 1,
                'expiry_days' => $settings['password_expiry_days'] ?? 90
            ];
        }
    } catch (PDOException $e) {
        // الجدول غير موجود، نستخدم القيم الافتراضية
        error_log('Settings table not available: ' . $e->getMessage());
    }
    
    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_security_page', 'عرض صفحة إدارة الصلاحيات والأمان');
    
} catch (PDOException $e) {
    error_log('Security Page Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - الصلاحيات والأمان</title>
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
            max-width: 1400px;
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
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .tab {
            padding: 15px 25px;
            cursor: pointer;
            text-align: center;
            flex: 1;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .tab:hover {
            background-color: #f5f5f5;
        }
        
        .tab.active {
            border-bottom: 3px solid var(--primary-color);
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .section-title {
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
            color: var(--secondary-color);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: var(--light-color);
            font-weight: bold;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .badge-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .badge-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .badge-info {
            background-color: var(--primary-color);
            color: white;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
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
        
        .log-row {
            cursor: pointer;
        }
        
        .log-details {
            display: none;
            background-color: #f9f9f9;
            padding: 15px;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 10px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .pagination button {
            margin: 0 5px;
            padding: 8px 15px;
            background-color: var(--light-color);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
        }
        
        .pagination button.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
            }
            
            table {
                display: block;
                overflow-x: auto;
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
        
        <h1 class="page-title">إدارة الصلاحيات والأمان</h1>
        
        <?php if (!empty($message)): ?>
        <div class="message message-<?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" data-tab="roles">إدارة الأدوار والصلاحيات</div>
            <div class="tab" data-tab="security">سجلات الأمان</div>
            <div class="tab" data-tab="password">سياسات كلمات المرور</div>
        </div>
        
        <div class="tab-content active" id="roles-tab">
            <h2 class="section-title">إدارة أدوار المستخدمين</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>اسم المستخدم</th>
                        <th>الدور الحالي</th>
                        <th>الحالة</th>
                        <th>آخر دخول</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['username']; ?></td>
                        <td>
                            <span class="badge 
                                <?php 
                                switch($user['role']) {
                                    case 'admin': echo 'badge-danger'; break;
                                    case 'hr': echo 'badge-warning'; break;
                                    case 'accounting': echo 'badge-info'; break;
                                    default: echo 'badge-success';
                                }
                                ?>">
                                <?php echo $user['role']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $user['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo $user['is_active'] ? 'نشط' : 'غير نشط'; ?>
                            </span>
                        </td>
                        <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'لم يسجل دخول'; ?></td>
                        <td>
                            <form method="POST" style="display: flex; gap: 10px;">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <select name="role" style="width: auto;">
                                    <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>مستخدم عادي</option>
                                    <option value="hr" <?php echo $user['role'] == 'hr' ? 'selected' : ''; ?>>موارد بشرية</option>
                                    <option value="accounting" <?php echo $user['role'] == 'accounting' ? 'selected' : ''; ?>>محاسبة</option>
                                    <option value="security_manager" <?php echo $user['role'] == 'security_manager' ? 'selected' : ''; ?>>مدير أمن</option>
                                    <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>مدير نظام</option>
                                </select>
                                <button type="submit" name="update_role" class="btn btn-primary">تحديث</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="tab-content" id="security-tab">
            <h2 class="section-title">سجلات الأمان</h2>
            <p>عرض آخر 100 حدث في سجلات النظام</p>
            
            <table>
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>المستخدم</th>
                        <th>الإجراء</th>
                        <th>التفاصيل</th>
                        <th>عنوان IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($security_logs as $log): ?>
                    <tr class="log-row" data-log-id="<?php echo $log['log_id']; ?>">
                        <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                        <td><?php echo $log['username'] ?? 'مستخدم محذوف'; ?></td>
                        <td><?php echo $log['action']; ?></td>
                        <td><?php echo substr($log['description'], 0, 50) . '...'; ?></td>
                        <td><?php echo $log['ip_address']; ?></td>
                    </tr>
                    <tr class="log-details" id="log-<?php echo $log['log_id']; ?>">
                        <td colspan="5">
                            <strong>التفاصيل الكاملة:</strong><br>
                            <?php echo $log['description']; ?><br><br>
                            <strong>معلومات المتصفح:</strong><br>
                            <?php echo $log['user_agent']; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="pagination">
                <button>1</button>
                <button>2</button>
                <button>3</button>
                <button>→</button>
            </div>
        </div>
        
        <div class="tab-content" id="password-tab">
            <h2 class="section-title">سياسات كلمات المرور</h2>
            
            <form method="POST">
                <div class="form-group">
                    <label>الحد الأدنى لطول كلمة المرور:</label>
                    <input type="number" name="min_length" value="<?php echo $password_policy['min_length']; ?>" min="6" max="20" required>
                </div>
                
                <div class="form-group">
                    <label>متطلبات كلمة المرور:</label>
                    <div class="checkbox-group">
                        <input type="checkbox" name="require_uppercase" id="require_uppercase" value="1" <?php echo $password_policy['require_uppercase'] ? 'checked' : ''; ?>>
                        <label for="require_uppercase">تتضمن حروف كبيرة (A-Z)</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="require_lowercase" id="require_lowercase" value="1" <?php echo $password_policy['require_lowercase'] ? 'checked' : ''; ?>>
                        <label for="require_lowercase">تتضمن حروف صغيرة (a-z)</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="require_numbers" id="require_numbers" value="1" <?php echo $password_policy['require_numbers'] ? 'checked' : ''; ?>>
                        <label for="require_numbers">تتضمن أرقام (0-9)</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" name="require_special_chars" id="require_special_chars" value="1" <?php echo $password_policy['require_special_chars'] ? 'checked' : ''; ?>>
                        <label for="require_special_chars">تتضمن رموز خاصة (!@#$%^&*)</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>مدة انتهاء صلاحية كلمة المرور (بالأيام):</label>
                    <input type="number" name="expiry_days" value="<?php echo $password_policy['expiry_days']; ?>" min="30" max="365" required>
                    <small>سيتم طلب تغيير كلمة المرور بعد انتهاء هذه المدة</small>
                </div>
                
                <button type="submit" name="update_password_policy" class="btn btn-primary">حفظ التغييرات</button>
            </form>
        </div>
    </div>

    <script>
        // تبويب الصفحة
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // إزالة النشاط من جميع الألسنة
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // إضافة النشاط للسان المحدد
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab + '-tab').classList.add('active');
            });
        });
        
        // عرض تفاصيل سجلات الأمان
        document.querySelectorAll('.log-row').forEach(row => {
            row.addEventListener('click', () => {
                const logId = row.dataset.logId;
                const details = document.getElementById('log-' + logId);
                details.style.display = details.style.display === 'table-row' ? 'none' : 'table-row';
            });
        });
    </script>
</body>
</html>