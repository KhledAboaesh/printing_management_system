<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$roles_info = [
    'admin' => [
        'title' => 'مدير النظام',
        'description' => 'وصول كامل إلى جميع أقسام النظام مع صلاحيات التعديل والحذف والإعدادات',
        'icon' => 'fas fa-shield-alt'
    ],
    'hr_manager' => [
        'title' => 'مدير موارد بشرية',
        'description' => 'إدارة الموارد البشرية والموظفين والتقارير مع صلاحيات التعيين والترقية',
        'icon' => 'fas fa-user-tie'
    ],
    'hr' => [
        'title' => 'موظف موارد بشرية',
        'description' => 'عرض بيانات الموظفين والتقارير الأساسية مع صلاحيات محدودة للتعديل',
        'icon' => 'fas fa-user-edit'
    ],
    'accountant' => [
        'title' => 'محاسب',
        'description' => 'إدارة الفواتير والحسابات المالية والتقارير المالية',
        'icon' => 'fas fa-calculator'
    ],
    'employee' => [
        'title' => 'موظف',
        'description' => 'الوصول المحدود لملفهم الشخصي وطلبات الإجازة',
        'icon' => 'fas fa-user'
    ]
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>معلومات الصلاحيات</title>
    <!-- نفس أنماط dashboard.php -->
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <h1><i class="fas fa-user-shield"></i> معلومات الصلاحيات</h1>
            
            <div class="roles-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 30px;">
                <?php foreach ($roles_info as $role => $info): ?>
                <div class="role-card" style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                        <div style="width: 50px; height: 50px; background-color: rgba(67, 97, 238, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-left: 15px; color: var(--primary); font-size: 20px;">
                            <i class="<?php echo $info['icon']; ?>"></i>
                        </div>
                        <h2><?php echo $info['title']; ?></h2>
                    </div>
                    <p><?php echo $info['description']; ?></p>
                    
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <div style="margin-top: 15px;">
                        <a href="edit_role.php?role=<?php echo $role; ?>" class="btn" style="padding: 8px 15px; background-color: rgba(67, 97, 238, 0.1); color: var(--primary); border-radius: 4px; text-decoration: none;">تعديل الصلاحيات</a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</body>
</html>