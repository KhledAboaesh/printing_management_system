<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (الموارد البشرية أو المدير)
if ($_SESSION['role'] != 'hr' && $_SESSION['role'] != 'admin') {
    header("Location: unauthorized.php");
    exit();
}

try {
    global $db;

    // جلب جميع الموظفين مع معلومات المستخدمين المرتبطة والأقسام
    $stmt = $db->query("
        SELECT 
            e.employee_id,
            e.full_name,
            e.national_id,
            e.hire_date,
            e.position,
            e.salary,
            e.phone,
            e.is_active,
            e.department_id,
            u.username,
            u.email AS user_email,
            d.name AS department_name
        FROM employees e
        LEFT JOIN users u ON e.user_id = u.user_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        ORDER BY e.employee_id DESC
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب الأقسام للقائمة المنسدلة
    $stmt = $db->query("SELECT department_id, name FROM departments ORDER BY name ASC");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Employees Management Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}

// معالجة حذف أو تعطيل موظف
if (isset($_GET['delete_id'])) {
    try {
        $employee_id = intval($_GET['delete_id']);

        // التحقق مما إذا كان الموظف مرتبطًا بسجلات أخرى قبل الحذف
        $stmt = $db->prepare("SELECT COUNT(*) FROM attendance WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        $attendance_count = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM leave_requests WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        $leaves_count = $stmt->fetchColumn();

        if ($attendance_count > 0 || $leaves_count > 0) {
            // تعطيل الموظف إذا كان مرتبطًا بسجلات
            $stmt = $db->prepare("UPDATE employees SET is_active = 0 WHERE employee_id = ?");
            $stmt->execute([$employee_id]);
            $success = "تم تعطيل الموظف بنجاح لأنه يحتوي على سجلات مرتبطة";
        } else {
            // حذف الموظف إذا لم يكن مرتبطًا
            $stmt = $db->prepare("DELETE FROM employees WHERE employee_id = ?");
            $stmt->execute([$employee_id]);
            $success = "تم حذف الموظف بنجاح";
        }

        logActivity($_SESSION['user_id'], 'delete_employee', 'حذف/تعطيل موظف: ' . $employee_id);

        header("Location: employees.php?success=" . urlencode($success));
        exit();

    } catch (PDOException $e) {
        error_log('Delete Employee Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء حذف الموظف";
    }
}

// معالجة تصدير البيانات CSV
if (isset($_GET['export'])) {
    try {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=employees_' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');

        // رأس الملف
        fputcsv($output, ['ID', 'الاسم', 'البريد الإلكتروني', 'الهاتف', 'القسم', 'الراتب', 'تاريخ التعيين', 'الحالة']);

        // بيانات الموظفين
        foreach ($employees as $employee) {
            $status = isset($employee['is_active']) && $employee['is_active'] ? 'نشط' : 'غير نشط';
            fputcsv($output, [
                $employee['employee_id'] ?? 'N/A',
                $employee['full_name'] ?? 'غير معروف',
                $employee['user_email'] ?? 'N/A',
                $employee['phone'] ?? 'غير محدد',
                $employee['department_name'] ?? 'غير محدد',
                $employee['salary'] ?? 0,
                $employee['hire_date'] ?? 'N/A',
                $status
            ]);
        }

        fclose($output);
        exit();

    } catch (PDOException $e) {
        error_log('Export Employees Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء تصدير البيانات";
    }
}
?>



<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - إدارة الموظفين</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .employees-table {
            width: 100%;
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .employees-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .employees-table th, 
        .employees-table td {
            padding: 15px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }
        
        .employees-table th {
            background-color: var(--light-color);
            font-weight: 700;
        }
        
        .employees-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .status-active {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background-color: var(--success-color);
            color: white;
            font-size: 12px;
        }
        
        .status-inactive {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background-color: var(--danger-color);
            color: white;
            font-size: 12px;
        }
        
        .action-icons {
            display: flex;
            gap: 10px;
        }
        
        .action-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .edit-icon {
            background-color: var(--primary-color);
            color: white;
        }
        
        .delete-icon {
            background-color: var(--danger-color);
            color: white;
        }
        
        .action-icon:hover {
            transform: scale(1.1);
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
            border-radius: var(--border-radius);
            width: 500px;
            max-width: 90%;
            padding: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .close-modal {
            font-size: 24px;
            cursor: pointer;
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
            font-size: 16px;
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
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .pagination a {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            text-decoration: none;
            color: var(--primary-color);
        }
        
        .pagination a.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .employees-table {
                overflow-x: auto;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .page-title {
                flex-direction: column;
                align-items: flex-start;
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
        
        <div class="page-title">
            <h1>إدارة الموظفين</h1>
            <a href="hr_dashboard.php" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i> العودة للرئيسية
            </a>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> 
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <a href="#" class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> إضافة موظف جديد
            </a>
            <a href="?export=1" class="btn btn-success">
                <i class="fas fa-file-export"></i> تصدير البيانات
            </a>
            <a href="employees_report.php" class="btn btn-warning">
                <i class="fas fa-chart-bar"></i> تقرير الموظفين
            </a>
        </div>
        
        <div class="employees-table">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم الكامل</th>
                        <th>البريد الإلكتروني</th>
                        <th>الهاتف</th>
                        <th>القسم</th>
                        <th>الراتب</th>
                        <th>تاريخ التعيين</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($employees)): ?>
                        <?php foreach ($employees as $index => $employee): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($employee['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($employee['user_email'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($employee['phone']); ?></td>
                            <td><?php echo htmlspecialchars($employee['department_name'] ?? 'غير محدد'); ?></td>
                            <td><?php echo number_format($employee['salary'], 2); ?> د.ل</td>
                            <td><?php echo $employee['hire_date']; ?></td>
                            <td>
                                <span class="<?php echo $employee['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $employee['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-icons">
                                    <a href="edit_employee.php?id=<?php echo $employee['employee_id']; ?>" class="action-icon edit-icon">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" onclick="confirmDelete(<?php echo $employee['employee_id']; ?>, '<?php echo htmlspecialchars($employee['full_name']); ?>')" class="action-icon delete-icon">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 20px;">
                                لا يوجد موظفين مسجلين في النظام
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="pagination">
            <a href="#" class="active">1</a>
            <a href="#">2</a>
            <a href="#">3</a>
            <a href="#">→</a>
        </div>
    </div>
    
    <!-- Modal لإضافة موظف -->
    <div id="addEmployeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>إضافة موظف جديد</h2>
                <span class="close-modal" onclick="closeAddModal()">&times;</span>
            </div>
            <form action="add_employee.php" method="POST">
                <div class="form-group">
                    <label for="full_name">الاسم الكامل</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="email">البريد الإلكتروني</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">الهاتف</label>
                    <input type="tel" id="phone" name="phone" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="department_id">القسم</label>
                   <select id="department_id" name="department_id" class="form-control" required>
    <option value="">اختر القسم</option>
    <?php if (!empty($departments)): ?>
        <?php foreach ($departments as $dep): ?>
            <option value="<?php echo $dep['department_id']; ?>"
                <?php echo (isset($employee['department_id']) && $employee['department_id'] == $dep['department_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($dep['name']); ?>
            </option>
        <?php endforeach; ?>
    <?php endif; ?>
</select>

                </div>
                
                <div class="form-group">
                    <label for="salary">الراتب</label>
                    <input type="number" id="salary" name="salary" class="form-control" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="hire_date">تاريخ التعيين</label>
                    <input type="date" id="hire_date" name="hire_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-save"></i> حفظ الموظف
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // فتح Modal إضافة موظف
        function openAddModal() {
            document.getElementById('addEmployeeModal').style.display = 'flex';
        }
        
        // إغلاق Modal إضافة موظف
        function closeAddModal() {
            document.getElementById('addEmployeeModal').style.display = 'none';
        }
        
        // تأكيد حذف موظف
        function confirmDelete(id, name) {
            if (confirm(`هل أنت متأكد من حذف الموظف "${name}"؟`)) {
                window.location.href = `?delete_id=${id}`;
            }
        }
        
        // إغلاق Modal عند النقر خارج المحتوى
        window.onclick = function(event) {
            const modal = document.getElementById('addEmployeeModal');
            if (event.target === modal) {
                closeAddModal();
            }
        }
    </script>
</body>
</html>