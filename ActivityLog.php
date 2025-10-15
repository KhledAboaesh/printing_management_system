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
if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'hr') {
    header("Location: unauthorized.php");
    exit();
}

// معالجة معلمات التصفية
$user_filter = $_GET['user_id'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search_term = $_GET['search'] ?? '';

// بناء استعلام SQL مع التصفية
$query = "SELECT al.*, u.username, u.full_name 
          FROM activity_log al 
          LEFT JOIN users u ON al.user_id = u.user_id 
          WHERE 1=1";
$params = [];

if (!empty($user_filter)) {
    $query .= " AND al.user_id = ?";
    $params[] = $user_filter;
}

if (!empty($action_filter)) {
    $query .= " AND al.action = ?";
    $params[] = $action_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(al.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(al.created_at) <= ?";
    $params[] = $date_to;
}

if (!empty($search_term)) {
    $query .= " AND (al.description LIKE ? OR al.action LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY al.created_at DESC";

// جلب سجل النشاطات
try {
    global $db;
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب قائمة المستخدمين للتصفية
    $users_stmt = $db->query("SELECT user_id, username, full_name FROM users ORDER BY username");
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جرد أنواع الأفعال الفريدة للتصفية
    $actions_stmt = $db->query("SELECT DISTINCT action FROM activity_log ORDER BY action");
    $actions = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_activity_log', 'عرض سجل النشاطات');
    
} catch (PDOException $e) {
    error_log('Activity Log Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب سجل النشاطات";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - سجل النشاطات</title>
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
        
        .filters-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .filters-title {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--secondary-color);
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-secondary {
            background-color: var(--light-color);
            color: var(--dark-color);
        }
        
        .btn-secondary:hover {
            background-color: #dde4e6;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
        
        .activities-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        .table tr:hover {
            background-color: #f5f5f5;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .badge-info {
            background-color: var(--primary-color);
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
        
        .action-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .description-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 10px;
        }
        
        .page-link {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--primary-color);
        }
        
        .page-link.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .no-data {
            text-align: center;
            padding: 30px;
            color: #777;
        }
        
        @media (max-width: 768px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .table {
                font-size: 14px;
            }
            
            .table th, .table td {
                padding: 8px 10px;
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
                    <div style="font-size: 12px; color: #777;"><?php echo $_SESSION['role'] == 'admin' ? 'مدير النظام' : 'موارد بشرية'; ?></div>
                </div>
            </div>
        </header>
        
        <h1 class="page-title">سجل النشاطات</h1>
        
        <?php if (isset($error)): ?>
        <div style="background-color: #ffebee; color: #d32f2f; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="filters-card">
            <h2 class="filters-title">تصفية النتائج</h2>
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="user_id">المستخدم</label>
                    <select name="user_id" id="user_id" class="form-control">
                        <option value="">جميع المستخدمين</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>" <?php echo $user_filter == $user['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="action">نوع النشاط</label>
                    <select name="action" id="action" class="form-control">
                        <option value="">جميع الأنواع</option>
                        <?php foreach ($actions as $action): ?>
                        <option value="<?php echo $action; ?>" <?php echo $action_filter == $action ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($action); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_from">من تاريخ</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_to">إلى تاريخ</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                
                <div class="form-group">
                    <label for="search">بحث</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="ابحث في الوصف أو النشاط..." value="<?php echo htmlspecialchars($search_term); ?>">
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> تطبيق التصفية
                    </button>
                    <a href="activity_log.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> إعادة تعيين
                    </a>
                </div>
            </form>
        </div>
        
        <div class="activities-card">
            <h2 style="margin-bottom: 15px;">سجل النشاطات</h2>
            
            <?php if (!empty($activities)): ?>
            <div style="margin-bottom: 15px; color: #777;">
                عرض <?php echo count($activities); ?> سجل نشاط
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>المستخدم</th>
                        <th>نوع النشاط</th>
                        <th>الوصف</th>
                        <th>عنوان IP</th>
                        <th>التاريخ والوقت</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $activity): ?>
                    <tr>
                        <td>
                            <?php if ($activity['user_id']): ?>
                                <?php echo htmlspecialchars($activity['full_name'] ?: $activity['username']); ?>
                            <?php else: ?>
                                <span style="color: #777;">نظام</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge 
                                <?php 
                                if (strpos($activity['action'], 'login') !== false) echo 'badge-success';
                                elseif (strpos($activity['action'], 'failed') !== false) echo 'badge-danger';
                                elseif (strpos($activity['action'], 'delete') !== false) echo 'badge-warning';
                                else echo 'badge-info';
                                ?>">
                                <?php echo htmlspecialchars($activity['action']); ?>
                            </span>
                        </td>
                        <td class="description-cell" title="<?php echo htmlspecialchars($activity['description']); ?>">
                            <?php echo htmlspecialchars($activity['description']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($activity['ip_address']); ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($activity['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="pagination">
                <a href="#" class="page-link">&laquo;</a>
                <a href="#" class="page-link active">1</a>
                <a href="#" class="page-link">2</a>
                <a href="#" class="page-link">3</a>
                <a href="#" class="page-link">&raquo;</a>
            </div>
            
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-info-circle" style="font-size: 48px; margin-bottom: 15px;"></i>
                <h3>لا توجد سجلات نشاط</h3>
                <p>لم يتم العثور على سجلات نشاط تطابق معايير التصفية المحددة.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // إضافة تفاعلية للجدول
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.table tr');
            rows.forEach(row => {
                row.addEventListener('click', function() {
                    const description = this.querySelector('.description-cell').getAttribute('title');
                    if (description) {
                        alert('الوصف الكامل: ' + description);
                    }
                });
            });
            
            // تعيين تاريخ اليوم كحد أقصى لتاريخ النهاية
            document.getElementById('date_to').max = new Date().toISOString().split('T')[0];
        });
    </script>
</body>
</html>