<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (مدير النظام فقط)
if ($_SESSION['role'] != 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// تعريف المتغيرات
$backup_dir = __DIR__ . "/backups/";
$success_msg = "";
$error_msg = "";

// إنشاء مجلد النسخ الاحتياطي إذا لم يكن موجوداً
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// التحقق من ملف config.php
$config_file = __DIR__ . '/includes/config.php';
if (!file_exists($config_file)) {
    $error_msg = "ملف الإعدادات config.php غير موجود. تأكد من وجوده في مجلد includes.";
}

// معالجة طلب النسخ الاحتياطي
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_backup']) && empty($error_msg)) {
    try {
        $backup_file = $backup_dir . 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // تضمين ملف config.php إذا موجود
        require_once $config_file;
        
        // تنفيذ أمر النسخ الاحتياطي باستخدام mysqldump
        $command = "mysqldump --user=" . DB_USER . " --password=" . DB_PASS . " --host=" . DB_HOST . " " . DB_NAME . " > " . escapeshellarg($backup_file);
        system($command, $output);
        
        if ($output === 0) {
            $success_msg = "تم إنشاء النسخ الاحتياطي بنجاح: " . basename($backup_file);
            logActivity($_SESSION['user_id'], 'backup_created', 'إنشاء نسخة احتياطية: ' . basename($backup_file));
        } else {
            $error_msg = "فشل في إنشاء النسخ الاحتياطي. تأكد من صلاحيات المجلد وتوفر أداة mysqldump.";
        }
    } catch (Exception $e) {
        $error_msg = "حدث خطأ: " . $e->getMessage();
    }
}

// معالجة طلب الاستعادة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restore_backup']) && empty($error_msg)) {
    if (!empty($_POST['backup_file'])) {
        try {
            $backup_file = $backup_dir . $_POST['backup_file'];
            
            if (file_exists($backup_file)) {
                require_once $config_file;
                
                $command = "mysql --user=" . DB_USER . " --password=" . DB_PASS . " --host=" . DB_HOST . " " . DB_NAME . " < " . escapeshellarg($backup_file);
                system($command, $output);
                
                if ($output === 0) {
                    $success_msg = "تم استعادة النسخ الاحتياطي بنجاح: " . $_POST['backup_file'];
                    logActivity($_SESSION['user_id'], 'backup_restored', 'استعادة نسخة احتياطية: ' . $_POST['backup_file']);
                } else {
                    $error_msg = "فشل في استعادة النسخ الاحتياطي.";
                }
            } else {
                $error_msg = "ملف النسخ الاحتياطي غير موجود.";
            }
        } catch (Exception $e) {
            $error_msg = "حدث خطأ: " . $e->getMessage();
        }
    } else {
        $error_msg = "يرجى اختيار ملف نسخ احتياطي.";
    }
}

// معالجة طلب جدولة النسخ الاحتياطي
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['schedule_backup'])) {
    $schedule_type = $_POST['schedule_type'] ?? '';
    $backup_time = $_POST['backup_time'] ?? '';
    
    if (!empty($schedule_type)) {
        try {
            $stmt = $db->prepare("INSERT INTO backup_schedules (schedule_type, backup_time, is_active, created_by) VALUES (?, ?, 1, ?)");
            $stmt->execute([$schedule_type, $backup_time, $_SESSION['user_id']]);
            
            $success_msg = "تم جدولة النسخ الاحتياطي بنجاح.";
            logActivity($_SESSION['user_id'], 'backup_scheduled', 'جدولة نسخ احتياطي: ' . $schedule_type);
        } catch (Exception $e) {
            $error_msg = "حدث خطأ في حفظ الجدولة: " . $e->getMessage();
        }
    } else {
        $error_msg = "يرجى اختيار نوع الجدولة.";
    }
}

// جلب قائمة ملفات النسخ الاحتياطي
$backup_files = [];
if (file_exists($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
            $backup_files[] = $file;
        }
    }
    rsort($backup_files);
}

// جلب الجداول المجدولة
$scheduled_backups = [];
try {
    $stmt = $db->query("SELECT * FROM backup_schedules WHERE is_active = 1 ORDER BY created_at DESC");
    $scheduled_backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // الجدول قد لا يكون موجوداً بعد
}

// تسجيل النشاط
logActivity($_SESSION['user_id'], 'view_backup_page', 'عرض صفحة النسخ الاحتياطي');
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - النسخ الاحتياطي</title>
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
        
        .backup-section {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
            color: var(--secondary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--primary-color);
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
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .btn-success {
            background-color: var(--success-color);
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
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
        
        .backup-files {
            list-style: none;
            margin-top: 15px;
        }
        
        .backup-file {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background-color: var(--light-color);
            border-radius: var(--border-radius);
            margin-bottom: 10px;
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .file-icon {
            color: var(--primary-color);
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
        }
        
        .schedule-list {
            list-style: none;
            margin-top: 15px;
        }
        
        .schedule-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            background-color: var(--light-color);
            border-radius: var(--border-radius);
            margin-bottom: 10px;
        }
        
        .schedule-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .schedule-icon {
            color: var(--warning-color);
        }
        
        .schedule-actions {
            display: flex;
            gap: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #777;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        @media (max-width: 768px) {
            .file-actions, .schedule-actions {
                flex-direction: column;
            }
            
            .backup-file, .schedule-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .file-actions, .schedule-actions {
                width: 100%;
            }
            
            .btn {
                width: 100%;
                text-align: center;
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
        
        <h1 class="page-title">إدارة النسخ الاحتياطي</h1>
        
        <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
        </div>
        <?php endif; ?>
        
        <!-- قسم إنشاء نسخة احتياطية -->
        <div class="backup-section">
            <h2 class="section-title">
                <i class="fas fa-database"></i> إنشاء نسخة احتياطية
            </h2>
            <p>إنشاء نسخة احتياطية كاملة من قاعدة البيانات.</p>
            <form method="POST">
                <button type="submit" name="create_backup" class="btn btn-success">
                    <i class="fas fa-plus-circle"></i> إنشاء نسخة احتياطية الآن
                </button>
            </form>
        </div>
        
        <!-- قسم استعادة نسخة احتياطية -->
        <div class="backup-section">
            <h2 class="section-title">
                <i class="fas fa-undo"></i> استعادة نسخة احتياطية
            </h2>
            
            <?php if (!empty($backup_files)): ?>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">اختر ملف النسخ الاحتياطي:</label>
                    <select name="backup_file" class="form-select" required>
                        <option value="">-- اختر ملف --</option>
                        <?php foreach ($backup_files as $file): ?>
                        <option value="<?php echo $file; ?>"><?php echo $file; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="restore_backup" class="btn btn-warning">
                    <i class="fas fa-undo"></i> استعادة النسخة الاحتياطية
                </button>
            </form>
            
            <h3 style="margin-top: 20px;">النسخ الاحتياطية المتاحة:</h3>
            <ul class="backup-files">
                <?php foreach ($backup_files as $file): ?>
                <li class="backup-file">
                    <div class="file-info">
                        <i class="fas fa-file-archive file-icon"></i>
                        <span><?php echo $file; ?></span>
                    </div>
                    <div class="file-actions">
                        <a href="<?php echo $backup_dir . $file; ?>" download class="btn">
                            <i class="fas fa-download"></i> تحميل
                        </a>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="backup_file" value="<?php echo $file; ?>">
                            <button type="submit" name="restore_backup" class="btn btn-warning">
                                <i class="fas fa-undo"></i> استعادة
                            </button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>لا توجد نسخ احتياطية متاحة حالياً</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- قسم جدولة النسخ الاحتياطي -->
        <div class="backup-section">
            <h2 class="section-title">
                <i class="fas fa-calendar-alt"></i> جدولة النسخ الاحتياطي
            </h2>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">نوع الجدولة:</label>
                    <select name="schedule_type" class="form-select" required>
                        <option value="">-- اختر نوع الجدولة --</option>
                        <option value="daily">يومي</option>
                        <option value="weekly">أسبوعي</option>
                        <option value="monthly">شهري</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">وقت النسخ الاحتياطي:</label>
                    <input type="time" name="backup_time" class="form-control" value="02:00">
                </div>
                
                <button type="submit" name="schedule_backup" class="btn">
                    <i class="fas fa-calendar-plus"></i> حفظ الجدولة
                </button>
            </form>
            
            <h3 style="margin-top: 20px;">الجداول المجدولة:</h3>
            
            <?php if (!empty($scheduled_backups)): ?>
            <ul class="schedule-list">
                <?php foreach ($scheduled_backups as $schedule): ?>
                <li class="schedule-item">
                    <div class="schedule-info">
                        <i class="fas fa-clock schedule-icon"></i>
                        <span>
                            <?php 
                            $type = '';
                            if ($schedule['schedule_type'] == 'daily') $type = 'يومي';
                            elseif ($schedule['schedule_type'] == 'weekly') $type = 'أسبوعي';
                            elseif ($schedule['schedule_type'] == 'monthly') $type = 'شهري';
                            echo $type . ' - الساعة: ' . $schedule['backup_time']; 
                            ?>
                        </span>
                    </div>
                    <div class="schedule-actions">
                        <button class="btn btn-warning">
                            <i class="fas fa-edit"></i> تعديل
                        </button>
                        <button class="btn btn-danger">
                            <i class="fas fa-trash"></i> حذف
                        </button>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <p>لا توجد جداول مجدولة حالياً</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>