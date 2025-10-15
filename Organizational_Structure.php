<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (الموارد البشرية أو الإدارة)
if ($_SESSION['role'] != 'hr' && $_SESSION['role'] != 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// معالجة عمليات إدارة الأقسام
$message = '';
$error = '';

// تعريف المتغيرات لتجنب تحذيرات undefined
$departments = [];
$employees = [];
$employee_counts = [];

// إضافة قسم جديد
if (isset($_POST['add_department'])) {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $manager_id = $_POST['manager_id'] ?? null;
    
    if (!empty($name)) {
        try {
            global $db;
            $stmt = $db->prepare("INSERT INTO departments (name, description, manager_id) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $manager_id]);
            
            $message = "تم إضافة القسم بنجاح";
            logActivity($_SESSION['user_id'], 'add_department', "إضافة قسم جديد: $name");
        } catch (PDOException $e) {
            error_log('Add Department Error: ' . $e->getMessage());
            $error = "حدث خطأ أثناء إضافة القسم";
        }
    } else {
        $error = "اسم القسم مطلوب";
    }
}

// تعديل قسم
if (isset($_POST['edit_department'])) {
    $department_id = $_POST['department_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $manager_id = $_POST['manager_id'] ?? null;
    
    if (!empty($department_id) && !empty($name)) {
        try {
            global $db;
            $stmt = $db->prepare("UPDATE departments SET name = ?, description = ?, manager_id = ? WHERE department_id = ?");
            $stmt->execute([$name, $description, $manager_id, $department_id]);
            
            $message = "تم تعديل القسم بنجاح";
            logActivity($_SESSION['user_id'], 'edit_department', "تعديل قسم: $name");
        } catch (PDOException $e) {
            error_log('Edit Department Error: ' . $e->getMessage());
            $error = "حدث خطأ أثناء تعديل القسم";
        }
    } else {
        $error = "بيانات غير كافية للتعديل";
    }
}

// حذف قسم
if (isset($_GET['delete_department'])) {
    $department_id = $_GET['delete_department'] ?? '';
    
    if (!empty($department_id)) {
        try {
            global $db;
            
            // التحقق إذا كان القسم مرتبط بموظفين
            $stmt = $db->prepare("SELECT COUNT(*) as employee_count FROM employees WHERE department_id = ?");
            $stmt->execute([$department_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['employee_count'] > 0) {
                $error = "لا يمكن حذف القسم لأنه مرتبط بموظفين";
            } else {
                $stmt = $db->prepare("DELETE FROM departments WHERE department_id = ?");
                $stmt->execute([$department_id]);
                
                $message = "تم حذف القسم بنجاح";
                logActivity($_SESSION['user_id'], 'delete_department', "حذف قسم: $department_id");
            }
        } catch (PDOException $e) {
            error_log('Delete Department Error: ' . $e->getMessage());
            $error = "حدث خطأ أثناء حذف القسم";
        }
    }
}

// جلب بيانات الأقسام والموظفين
try {
    global $db;
    
    // جلب جميع الأقسام مع مديريها
    $stmt = $db->query("
        SELECT d.*, e.name as manager_name 
        FROM departments d 
        LEFT JOIN employees e ON d.manager_id = e.employee_id 
        ORDER BY d.name
    ");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب جميع الموظفين لاستخدامهم كمديرين
    $stmt = $db->query("SELECT employee_id, name, position FROM employees ORDER BY name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب عدد الموظفين في كل قسم
    $stmt = $db->query("
        SELECT department_id, COUNT(*) as employee_count 
        FROM employees 
        WHERE department_id IS NOT NULL 
        GROUP BY department_id
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $employee_counts[$row['department_id']] = $row['employee_count'];
    }
    
    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_org_structure', 'عرض الهيكل التنظيمي');
    
} catch (PDOException $e) {
    error_log('Organization Structure Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}

// استخدام foreach بأمان
if(!empty($departments)){
    foreach($departments as $dept){
        // عرض الأقسام
    }
}

if(!empty($employees)){
    foreach($employees as $emp){
        // عرض الموظفين
    }
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - الهيكل التنظيمي</title>
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
            padding: 15px 20px;
            cursor: pointer;
            flex: 1;
            text-align: center;
            transition: background-color 0.3s;
        }
        
        .tab.active {
            background-color: var(--primary-color);
            color: white;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .org-chart {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .org-level {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .org-item {
            background-color: var(--light-color);
            border-radius: var(--border-radius);
            padding: 15px;
            margin: 10px;
            text-align: center;
            min-width: 200px;
            position: relative;
            box-shadow: var(--box-shadow);
        }
        
        .org-item.manager {
            background-color: var(--primary-color);
            color: white;
        }
        
        .org-item.department {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .org-item::after {
            content: "";
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            height: 15px;
            background-color: #ccc;
        }
        
        .org-item:last-child::after {
            display: none;
        }
        
        .org-employees {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .employee-card {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            padding: 15px;
            margin: 10px;
            width: 200px;
            text-align: center;
            box-shadow: var(--box-shadow);
        }
        
        .department-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .department-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .department-name {
            font-size: 20px;
            font-weight: bold;
            color: var(--secondary-color);
        }
        
        .employee-count {
            background-color: var(--primary-color);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .department-manager {
            margin-bottom: 15px;
            padding: 10px;
            background-color: var(--light-color);
            border-radius: var(--border-radius);
        }
        
        .department-description {
            margin-bottom: 15px;
            color: #666;
        }
        
        .department-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
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
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            width: 500px;
            max-width: 90%;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: bold;
        }
        
        .close {
            font-size: 24px;
            cursor: pointer;
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
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
        }
        
        .form-textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            min-height: 100px;
            resize: vertical;
        }
        
        .form-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
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
            .tabs {
                flex-direction: column;
            }
            
            .department-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .org-level {
                flex-direction: column;
                align-items: center;
            }
            
            .org-item {
                width: 100%;
                max-width: 300px;
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
        
        <h1 class="page-title">الهيكل التنظيمي وإدارة الأقسام</h1>
        
        <?php if (!empty($message)): ?>
        <div class="alert alert-success">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('org-chart-tab')">الهيكل التنظيمي</div>
            <div class="tab" onclick="switchTab('departments-tab')">إدارة الأقسام</div>
            <div class="tab" onclick="switchTab('employees-tab')">توزيع الموظفين</div>
        </div>
        
        <div id="org-chart-tab" class="tab-content active">
            <div class="org-chart">
                <h2 style="text-align: center; margin-bottom: 20px;">الهيكل التنظيمي للشركة</h2>
                
                <!-- مستوى الإدارة العليا -->
                <div class="org-level">
                    <div class="org-item manager">
                        <h3>الإدارة العليا</h3>
                        <p>المدير العام</p>
                    </div>
                </div>
                
                <!-- مستوى الأقسام -->
                <div class="org-level">
                    <?php foreach ($departments as $department): ?>
                    <div class="org-item department">
                        <h3><?php echo $department['name']; ?></h3>
                        <p><?php echo $department['manager_name'] ?? 'لم يتم تعيين مدير'; ?></p>
                        <p><?php echo $employee_counts[$department['department_id']] ?? 0; ?> موظف</p>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- مستوى الموظفين -->
                <div class="org-level">
                    <div class="org-employees">
                        <?php foreach ($employees as $employee): ?>
                        <div class="employee-card">
                            <h4><?php echo $employee['name']; ?></h4>
                            <p><?php echo $employee['position']; ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="departments-tab" class="tab-content">
            <div style="text-align: right; margin-bottom: 20px;">
                <button class="btn btn-primary" onclick="openAddDepartmentModal()">
                    <i class="fas fa-plus"></i> إضافة قسم جديد
                </button>
            </div>
            
            <?php foreach ($departments as $department): ?>
            <div class="department-card">
                <div class="department-header">
                    <div class="department-name"><?php echo $department['name']; ?></div>
                    <div class="employee-count"><?php echo $employee_counts[$department['department_id']] ?? 0; ?> موظف</div>
                </div>
                
                <div class="department-manager">
                    <strong>مدير القسم:</strong> <?php echo $department['manager_name'] ?? 'لم يتم تعيين مدير'; ?>
                </div>
                
                <div class="department-description">
                    <?php echo $department['description'] ?? 'لا يوجد وصف للقسم'; ?>
                </div>
                
                <div class="department-actions">
                    <button class="btn btn-primary" onclick="openEditDepartmentModal(
                        '<?php echo $department['department_id']; ?>',
                        '<?php echo $department['name']; ?>',
                        '<?php echo $department['description'] ?? ''; ?>',
                        '<?php echo $department['manager_id'] ?? ''; ?>'
                    )">تعديل</button>
                    
                    <a href="?delete_department=<?php echo $department['department_id']; ?>" class="btn btn-danger" 
                       onclick="return confirm('هل أنت متأكد من حذف هذا القسم؟')">حذف</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div id="employees-tab" class="tab-content">
            <h2 style="text-align: center; margin-bottom: 20px;">توزيع الموظفين على الأقسام</h2>
            
            <?php foreach ($departments as $department): ?>
            <div class="department-card">
                <div class="department-header">
                    <div class="department-name"><?php echo $department['name']; ?></div>
                    <div class="employee-count"><?php echo $employee_counts[$department['department_id']] ?? 0; ?> موظف</div>
                </div>
                
                <div class="department-manager">
                    <strong>مدير القسم:</strong> <?php echo $department['manager_name'] ?? 'لم يتم تعيين مدير'; ?>
                </div>
                
                <h3 style="margin: 15px 0;">موظفو القسم:</h3>
                <div class="org-employees">
                    <?php
                    // جلب موظفي هذا القسم
                    try {
                        $stmt = $db->prepare("
                            SELECT employee_id, name, position 
                            FROM employees 
                            WHERE department_id = ? 
                            ORDER BY name
                        ");
                        $stmt->execute([$department['department_id']]);
                        $dept_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($dept_employees as $emp) {
                            echo "
                            <div class='employee-card'>
                                <h4>{$emp['name']}</h4>
                                <p>{$emp['position']}</p>
                            </div>
                            ";
                        }
                        
                        if (count($dept_employees) === 0) {
                            echo "<p>لا يوجد موظفين في هذا القسم</p>";
                        }
                    } catch (PDOException $e) {
                        echo "<p>حدث خطأ في جلب بيانات الموظفين</p>";
                    }
                    ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Modal إضافة قسم -->
    <div id="addDepartmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">إضافة قسم جديد</h2>
                <span class="close" onclick="closeModal('addDepartmentModal')">&times;</span>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">اسم القسم</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">وصف القسم</label>
                    <textarea name="description" class="form-textarea"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">مدير القسم</label>
                    <select name="manager_id" class="form-select">
                        <option value="">اختر مدير للقسم</option>
                        <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo $employee['employee_id']; ?>"><?php echo $employee['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="text-align: left;">
                    <button type="submit" name="add_department" class="btn btn-success">إضافة</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('addDepartmentModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal تعديل قسم -->
    <div id="editDepartmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">تعديل القسم</h2>
                <span class="close" onclick="closeModal('editDepartmentModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" id="edit_department_id" name="department_id">
                
                <div class="form-group">
                    <label class="form-label">اسم القسم</label>
                    <input type="text" id="edit_department_name" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">وصف القسم</label>
                    <textarea id="edit_department_description" name="description" class="form-textarea"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">مدير القسم</label>
                    <select id="edit_department_manager" name="manager_id" class="form-select">
                        <option value="">اختر مدير للقسم</option>
                        <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo $employee['employee_id']; ?>"><?php echo $employee['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="text-align: left;">
                    <button type="submit" name="edit_department" class="btn btn-success">تحديث</button>
                    <button type="button" class="btn btn-danger" onclick="closeModal('editDepartmentModal')">إلغاء</button>
                </div>
            </form>
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
            
            // إظهار المحتوى المحدد وتنشيط التبويب
            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        function openAddDepartmentModal() {
            document.getElementById('addDepartmentModal').style.display = 'flex';
        }
        
        function openEditDepartmentModal(id, name, description, managerId) {
            document.getElementById('edit_department_id').value = id;
            document.getElementById('edit_department_name').value = name;
            document.getElementById('edit_department_description').value = description || '';
            document.getElementById('edit_department_manager').value = managerId || '';
            
            document.getElementById('editDepartmentModal').style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // إغلاق Modal عند النقر خارج المحتوى
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>