<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (الموارد البشرية)
if ($_SESSION['role'] != 'hr' && $_SESSION['role'] != 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// جلب إحصائيات الموارد البشرية
try {
    global $db;
    
    // عدد الموظفين الإجمالي
    $stmt = $db->query("SELECT COUNT(*) as total_employees FROM employees");
    $total_employees = $stmt->fetch(PDO::FETCH_ASSOC)['total_employees'];
    
    // عدد الموظفين الحاليين في العمل
    $current_date = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(*) as present_employees FROM attendance WHERE date = ? AND status = 'present'");
    $stmt->execute([$current_date]);
    $present_employees = $stmt->fetch(PDO::FETCH_ASSOC)['present_employees'];
    
    // عدد طلبات الإجازة المنتظرة
    $stmt = $db->query("SELECT COUNT(*) as pending_leaves FROM leave_requests WHERE status = 'pending'");
    $pending_leaves = $stmt->fetch(PDO::FETCH_ASSOC)['pending_leaves'];
    
    // عدد أيام الغياب لهذا الشهر
    $first_day_month = date('Y-m-01');
    $stmt = $db->prepare("SELECT COUNT(*) as absences_this_month FROM attendance WHERE date >= ? AND status = 'absent'");
    $stmt->execute([$first_day_month]);
    $absences_this_month = $stmt->fetch(PDO::FETCH_ASSOC)['absences_this_month'];
    
    // جلب الإشعارات المهمة
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? OR is_global = 1 ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_hr_dashboard', 'عرض لوحة تحكم الموارد البشرية');
    
} catch (PDOException $e) {
    error_log('HR Dashboard Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - الموارد البشرية</title>
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
        
        .employees-icon { color: var(--primary-color); }
        .attendance-icon { color: var(--success-color); }
        .leaves-icon { color: var(--warning-color); }
        .absences-icon { color: var(--danger-color); }
        
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
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .notifications-section, .quick-links-section {
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
        
        .notification-list {
            list-style: none;
        }
        
        .notification-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--light-color);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .notification-content {
            flex-grow: 1;
        }
        
        .notification-time {
            font-size: 12px;
            color: #777;
        }
        
        .links-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .quick-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background-color: var(--light-color);
            padding: 15px;
            border-radius: var(--border-radius);
            text-align: center;
            text-decoration: none;
            color: var(--dark-color);
            transition: all 0.3s ease;
        }
        
        .quick-link:hover {
            background-color: var(--primary-color);
            color: white;
            transform: translateY(-3px);
        }
        
        .link-icon {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .link-text {
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .links-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .links-grid {
                grid-template-columns: 1fr;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
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
        
        <h1 class="dashboard-title">لوحة تحكم الموارد البشرية</h1>
        
        <?php if (isset($error)): ?>
        <div style="background-color: #ffebee; color: #d32f2f; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon employees-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $total_employees ?? 0; ?></div>
                <div class="stat-label">الموظفين الإجمالي</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon attendance-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-value"><?php echo $present_employees ?? 0; ?></div>
                <div class="stat-label">موظفين حاضرين اليوم</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon leaves-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-value"><?php echo $pending_leaves ?? 0; ?></div>
                <div class="stat-label">طلبات إجازة منتظرة</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon absences-icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-value"><?php echo $absences_this_month ?? 0; ?></div>
                <div class="stat-label">غياب هذا الشهر</div>
            </div>
        </div>
        
        <div class="content-grid">
            <div class="notifications-section">
                <h2 class="section-title">الإشعارات المهمة</h2>
                <ul class="notification-list">
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $notification): ?>
                        <li class="notification-item">
                            <div class="notification-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="notification-content">
                                <div><?php echo $notification['message']; ?></div>
                                <div class="notification-time">
                                    <?php echo date('Y-m-d H:i', strtotime($notification['created_at'])); ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="notification-item">
                            <div class="notification-content">لا توجد إشعارات حالياً</div>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="quick-links-section">
                <h2 class="section-title">روابط سريعة</h2>
                <div class="links-grid">
                    <a href="employees.php" class="quick-link">
                        <div class="link-icon"><i class="fas fa-users-cog"></i></div>
                        <div class="link-text">إدارة الموظفين</div>
                    </a>
                    
                    <a href="attendance.php" class="quick-link">
                        <div class="link-icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="link-text">الحضور والانصراف</div>
                    </a>
                    
                    <a href="leave_requests.php" class="quick-link">
                        <div class="link-icon"><i class="fas fa-calendar-minus"></i></div>
                        <div class="link-text">طلبات الإجازة</div>
                    </a>
                    
                    <a href="payroll.php" class="quick-link">
                        <div class="link-icon"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="link-text">كشوف المرتبات</div>
                    </a>
                    
                    <a href="reports_hr.php" class="quick-link">
                        <div class="link-icon"><i class="fas fa-chart-bar"></i></div>
                        <div class="link-text">التقارير</div>
                    </a>
                    
                    <a href="settings_hr.php" class="quick-link">
                        <div class="link-icon"><i class="fas fa-cog"></i></div>
                        <div class="link-text">الإعدادات</div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>