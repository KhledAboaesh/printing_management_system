<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم
if ($_SESSION['role'] != 'hr' && $_SESSION['role'] != 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// معالجة إرسال نموذج الإشعار الجديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notification'])) {
    $title = $_POST['title'] ?? '';
    $message = $_POST['message'] ?? '';
    $recipient_type = $_POST['recipient_type'] ?? 'all';
    $specific_recipient = $_POST['specific_recipient'] ?? null;
    $is_important = isset($_POST['is_important']) ? 1 : 0;
    $send_email = isset($_POST['send_email']) ? 1 : 0;
    
    try {
        global $db;
        
        // تحديد المستلمين
        $recipients = [];
        if ($recipient_type == 'all') {
            $stmt = $db->query("SELECT user_id FROM users WHERE is_active = 1");
            $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($recipient_type == 'specific' && !empty($specific_recipient)) {
            $recipients = [$specific_recipient];
        } elseif ($recipient_type == 'role') {
            $stmt = $db->prepare("SELECT user_id FROM users WHERE role = ? AND is_active = 1");
            $stmt->execute([$specific_recipient]);
            $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // إرسال الإشعار لكل مستلم
        $sent_count = 0;
        foreach ($recipients as $recipient_id) {
            $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, is_important, created_by) 
                                 VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$recipient_id, $title, $message, $is_important, $_SESSION['user_id']]);
            $sent_count++;
            
            // إذا كان الخيار لإرسال بريد إلكتروني مفعل
            if ($send_email) {
                // هنا يمكنك إضافة كود إرسال البريد الإلكتروني
                // sendEmailNotification($recipient_id, $title, $message);
            }
        }
        
        // تسجيل النشاط
        logActivity($_SESSION['user_id'], 'send_notifications', "إرسال إشعار إلى $sent_count مستخدم");
        
        $success = "تم إرسال الإشعار بنجاح إلى $sent_count مستخدم";
    } catch (PDOException $e) {
        error_log('Send Notification Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء إرسال الإشعار";
    }
}

// معالجة حذف الإشعار
if (isset($_GET['delete'])) {
    $notification_id = $_GET['delete'];
    
    try {
        global $db;
        $stmt = $db->prepare("DELETE FROM notifications WHERE notification_id = ?");
        $stmt->execute([$notification_id]);
        
        // تسجيل النشاط
        logActivity($_SESSION['user_id'], 'delete_notification', "حذف إشعار #$notification_id");
        
        $success = "تم حذف الإشعار بنجاح";
    } catch (PDOException $e) {
        error_log('Delete Notification Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء حذف الإشعار";
    }
}

// معالجة标记 الإشعار كمقروء
if (isset($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    
    try {
        global $db;
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
        $stmt->execute([$notification_id]);
        
        $success = "تم标记 الإشعار كمقروء";
    } catch (PDOException $e) {
        error_log('Mark Notification Read Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء标记 الإشعار";
    }
}

// جلب جميع الإشعارات
try {
    global $db;
    
    // جلب الإشعارات مع معلومات المرسل
    $query = "SELECT n.*, u.username as sender_name 
              FROM notifications n 
              LEFT JOIN users u ON n.created_by = u.user_id 
              ORDER BY n.created_at DESC";
    $stmt = $db->query($query);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب المستخدمين للإرسال المحدد
    $stmt = $db->query("SELECT user_id, username, role FROM users WHERE is_active = 1 ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب إحصائيات الإشعارات
    $stmt = $db->query("SELECT COUNT(*) as total FROM notifications");
    $total_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as unread FROM notifications WHERE is_read = 0");
    $unread_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['unread'];
    
    $stmt = $db->query("SELECT COUNT(*) as important FROM notifications WHERE is_important = 1");
    $important_notifications = $stmt->fetch(PDO::FETCH_ASSOC)['important'];
    
} catch (PDOException $e) {
    error_log('Fetch Notifications Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - إدارة الإشعارات</title>
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
        
        .dashboard-title {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--secondary-color);
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .total-icon { color: var(--primary-color); }
        .unread-icon { color: var(--warning-color); }
        .important-icon { color: var(--danger-color); }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #777;
            font-size: 16px;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .send-notification-section, .notifications-list-section {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .section-title {
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
            color: var(--secondary-color);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
            background-color: white;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
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
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        .notification-list {
            list-style: none;
        }
        
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            gap: 15px;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item.unread {
            background-color: #f8f9fa;
            border-left: 4px solid var(--primary-color);
        }
        
        .notification-item.important {
            background-color: #fff4f4;
            border-left: 4px solid var(--danger-color);
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--light-color);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .important .notification-icon {
            background-color: #ffecec;
            color: var(--danger-color);
        }
        
        .notification-content {
            flex-grow: 1;
        }
        
        .notification-title {
            font-weight: 700;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
        }
        
        .notification-message {
            margin-bottom: 5px;
        }
        
        .notification-meta {
            font-size: 12px;
            color: #777;
            display: flex;
            justify-content: space-between;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 14px;
        }
        
        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            background-color: white;
            cursor: pointer;
        }
        
        .filter-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
            }
            
            .notification-item {
                flex-direction: column;
            }
            
            .filter-bar {
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
                    <div style="font-size: 12px; color: #777;">موارد بشرية</div>
                </div>
            </div>
        </header>
        
        <h1 class="dashboard-title">إدارة الإشعارات</h1>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="stat-value"><?php echo $total_notifications ?? 0; ?></div>
                <div class="stat-label">إجمالي الإشعارات</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon unread-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-value"><?php echo $unread_notifications ?? 0; ?></div>
                <div class="stat-label">إشعارات غير مقروءة</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon important-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-value"><?php echo $important_notifications ?? 0; ?></div>
                <div class="stat-label">إشعارات مهمة</div>
            </div>
        </div>
        
        <div class="content-grid">
            <div class="send-notification-section">
                <h2 class="section-title">إرسال إشعار جديد</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="title">عنوان الإشعار</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="message">نص الإشعار</label>
                        <textarea class="form-control" id="message" name="message" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="recipient_type">نوع المستلم</label>
                        <select class="form-select" id="recipient_type" name="recipient_type" onchange="toggleRecipientField()">
                            <option value="all">جميع المستخدمين</option>
                            <option value="role">جميع مستخدمين دور محدد</option>
                            <option value="specific">مستخدم محدد</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="specific_recipient_group" style="display: none;">
                        <label class="form-label" for="specific_recipient">المستخدم المحدد</label>
                        <select class="form-select" id="specific_recipient" name="specific_recipient">
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>">
                                <?php echo $user['username'] . ' (' . $user['role'] . ')'; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" id="role_recipient_group" style="display: none;">
                        <label class="form-label" for="role_recipient">الدور</label>
                        <select class="form-select" id="role_recipient" name="specific_recipient">
                            <option value="employee">موظف</option>
                            <option value="hr">موارد بشرية</option>
                            <option value="accountant">محاسب</option>
                            <option value="admin">مدير النظام</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_important" name="is_important">
                            <label class="form-label" for="is_important">إشعار مهم</label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="send_email" name="send_email">
                            <label class="form-label" for="send_email">إرسال بريد إلكتروني أيضًا</label>
                        </div>
                    </div>
                    
                    <button type="submit" name="send_notification" class="btn btn-primary">إرسال الإشعار</button>
                </form>
            </div>
            
            <div class="notifications-list-section">
                <h2 class="section-title">قائمة الإشعارات</h2>
                
                <div class="filter-bar">
                    <button class="filter-btn active" onclick="filterNotifications('all')">الكل</button>
                    <button class="filter-btn" onclick="filterNotifications('unread')">غير المقروء</button>
                    <button class="filter-btn" onclick="filterNotifications('important')">المهمة</button>
                </div>
                
                <ul class="notification-list">
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $notification): ?>
                        <li class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?> <?php echo $notification['is_important'] ? 'important' : ''; ?>">
                            <div class="notification-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">
                                    <span><?php echo $notification['title']; ?></span>
                                    <?php if ($notification['is_important']): ?>
                                    <span class="badge" style="color: var(--danger-color);">
                                        <i class="fas fa-exclamation-circle"></i> مهم
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-message">
                                    <?php echo $notification['message']; ?>
                                </div>
                                <div class="notification-meta">
                                    <span>مرسل: <?php echo $notification['sender_name'] ?? 'نظام'; ?></span>
                                    <span><?php echo date('Y-m-d H:i', strtotime($notification['created_at'])); ?></span>
                                </div>
                                <div class="notification-actions">
                                    <?php if (!$notification['is_read']): ?>
                                    <a href="?mark_read=<?php echo $notification['notification_id']; ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i>标记 مقروء
                                    </a>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $notification['notification_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('هل أنت متأكد من حذف هذا الإشعار؟')">
                                        <i class="fas fa-trash"></i> حذف
                                    </a>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="notification-item">
                            <div class="notification-content">لا توجد إشعارات</div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <script>
        function toggleRecipientField() {
            const recipientType = document.getElementById('recipient_type').value;
            const specificGroup = document.getElementById('specific_recipient_group');
            const roleGroup = document.getElementById('role_recipient_group');
            
            if (recipientType === 'specific') {
                specificGroup.style.display = 'block';
                roleGroup.style.display = 'none';
            } else if (recipientType === 'role') {
                specificGroup.style.display = 'none';
                roleGroup.style.display = 'block';
            } else {
                specificGroup.style.display = 'none';
                roleGroup.style.display = 'none';
            }
        }
        
        function filterNotifications(type) {
            // هذا الكود للعرض فقط، في تطبيق حقيقي سيتم جلب البيانات من الخادم
            const notifications = document.querySelectorAll('.notification-item');
            const filterButtons = document.querySelectorAll('.filter-btn');
            
            // تحديث حالة أزرار التصفية
            filterButtons.forEach(btn => {
                if (btn.textContent === type) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            
            // تصفية الإشعارات
            notifications.forEach(notification => {
                if (type === 'all') {
                    notification.style.display = 'flex';
                } else if (type === 'unread') {
                    if (notification.classList.contains('unread')) {
                        notification.style.display = 'flex';
                    } else {
                        notification.style.display = 'none';
                    }
                } else if (type === 'important') {
                    if (notification.classList.contains('important')) {
                        notification.style.display = 'flex';
                    } else {
                        notification.style.display = 'none';
                    }
                }
            });
        }
    </script>
</body>
</html>