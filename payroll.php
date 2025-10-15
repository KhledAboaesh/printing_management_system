<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (HR أو Accounting أو Admin)
if (!in_array($_SESSION['role'], ['hr', 'accounting', 'admin'])) {
    header("Location: unauthorized.php");
    exit();
}

// رسالة الحالة الافتراضية
$message = '';
$message_type = '';

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // إضافة كشف المرتبات
    if (isset($_POST['add_payroll'])) {
        try {
            $employee_id = $_POST['employee_id'];
            $month = $_POST['month'];
            $year = $_POST['year'];
            $basic_salary = $_POST['basic_salary'];
            $allowances = $_POST['allowances'] ?? 0;
            $deductions = $_POST['deductions'] ?? 0;
            $net_salary = $basic_salary + $allowances - $deductions;
            $status = $_POST['status'];
            $notes = $_POST['notes'] ?? '';

            // التأكد من أن الموظف موجود
            $checkEmp = $db->prepare("SELECT * FROM employees WHERE employee_id = ?");
            $checkEmp->execute([$employee_id]);
            if (!$checkEmp->fetch()) {
                throw new Exception("الموظف المختار غير موجود في قاعدة البيانات.");
            }

            // التأكد من أن المستخدم موجود
            $checkUser = $db->prepare("SELECT * FROM users WHERE user_id = ?");
            $checkUser->execute([$_SESSION['user_id']]);
            if (!$checkUser->fetch()) {
                throw new Exception("المستخدم غير موجود في قاعدة البيانات.");
            }

            // إدراج كشف المرتبات
            $stmt = $db->prepare("
                INSERT INTO payroll 
                (employee_id, month, year, basic_salary, allowances, deductions, net_salary, status, notes, prepared_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $employee_id, $month, $year, $basic_salary, $allowances, $deductions,
                $net_salary, $status, $notes, $_SESSION['user_id']
            ]);

            $message = "تم إضافة كشف المرتبات بنجاح";
            $message_type = "success";
            logActivity($_SESSION['user_id'], 'add_payroll', "إضافة كشف مرتبات للموظف ID $employee_id");

        } catch (Exception $e) {
            error_log('Payroll Add Error: ' . $e->getMessage());
            $message = "حدث خطأ أثناء إضافة كشف المرتبات: " . $e->getMessage();
            $message_type = "error";
        }
    }

    // تحديث كشف المرتبات
    elseif (isset($_POST['update_payroll'])) {
        try {
            $payroll_id = $_POST['payroll_id'];
            $basic_salary = $_POST['basic_salary'];
            $allowances = $_POST['allowances'] ?? 0;
            $deductions = $_POST['deductions'] ?? 0;
            $net_salary = $basic_salary + $allowances - $deductions;
            $status = $_POST['status'];
            $notes = $_POST['notes'] ?? '';

            $stmt = $db->prepare("
                UPDATE payroll 
                SET basic_salary = ?, allowances = ?, deductions = ?, net_salary = ?, status = ?, notes = ? 
                WHERE payroll_id = ?
            ");
            $stmt->execute([$basic_salary, $allowances, $deductions, $net_salary, $status, $notes, $payroll_id]);

            $message = "تم تحديث كشف المرتبات بنجاح";
            $message_type = "success";
            logActivity($_SESSION['user_id'], 'update_payroll', "تحديث كشف مرتبات ID $payroll_id");

        } catch (PDOException $e) {
            error_log('Payroll Update Error: ' . $e->getMessage());
            $message = "حدث خطأ أثناء تحديث كشف المرتبات";
            $message_type = "error";
        }
    }

    // تصدير كشف المرتبات
    elseif (isset($_POST['export_payroll'])) {
        $export_month = $_POST['export_month'];
        $export_year = $_POST['export_year'];
        $message = "تم تصدير كشوف المرتبات لشهر $export_month سنة $export_year";
        $message_type = "success";
        logActivity($_SESSION['user_id'], 'export_payroll', "تصدير كشوف المرتبات لشهر $export_month سنة $export_year");
    }
}

// جلب كشوف المرتبات
try {
    global $db;

    // جلب جميع كشوف المرتبات مع اسم الموظف الكامل
    $stmt = $db->query("
        SELECT p.*, e.full_name AS employee_name
        FROM payroll p
        INNER JOIN employees e ON p.employee_id = e.employee_id
        ORDER BY p.year DESC, p.month DESC, p.created_at DESC
    ");
    $payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب الموظفين للقائمة المنسدلة
    $stmt = $db->query("SELECT employee_id, full_name FROM employees WHERE is_active = 1");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // إحصائيات المرتبات للشهر الحالي
    $current_month = date('n');
    $current_year = date('Y');
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) AS total_payrolls,
            SUM(net_salary) AS total_salaries,
            AVG(net_salary) AS average_salary
        FROM payroll
        WHERE month = ? AND year = ?
    ");
    $stmt->execute([$current_month, $current_year]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Payroll Page Error: ' . $e->getMessage());
    $message = "حدث خطأ في جلب البيانات من النظام";
    $message_type = "error";
}
?>






<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - كشوف المرتبات</title>
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
        }
        
        .stat-icon {
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .total-icon { color: var(--primary-color); }
        .salary-icon { color: var(--success-color); }
        .average-icon { color: var(--warning-color); }
        
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
        
        .form-section, .list-section {
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
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
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
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 14px;
        }
        
        .status-paid {
            color: var(--success-color);
            font-weight: 600;
        }
        
        .status-pending {
            color: var(--warning-color);
            font-weight: 600;
        }
        
        .export-section {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }
        
        .export-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .export-group {
            flex: 1;
        }
        
        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .export-form {
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            header {
                flex-direction: column;
                gap: 15px;
            }
            
            .action-buttons {
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
                    <div style="font-size: 12px; color: #777;"><?php echo $_SESSION['role'] == 'hr' ? 'موارد بشرية' : 'محاسبة'; ?></div>
                </div>
            </div>
        </header>
        
        <h1 class="page-title">إدارة كشوف المرتبات</h1>
        
        <?php if (!empty($message)): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_payrolls'] ?? 0; ?></div>
                <div class="stat-label">إجمالي الكشوف لهذا الشهر</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon salary-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_salaries'] ?? 0); ?></div>
                <div class="stat-label">إجمالي المرتبات لهذا الشهر</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon average-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['average_salary'] ?? 0); ?></div>
                <div class="stat-label">متوسط الراتب لهذا الشهر</div>
            </div>
        </div>
        
        <div class="export-section">
            <h2 class="section-title">تصدير كشوف المرتبات</h2>
            <form method="POST" class="export-form">
                <div class="form-group export-group">
                    <label class="form-label">الشهر</label>
                    <select class="form-select" name="export_month" required>
                        <option value="1">يناير</option>
                        <option value="2">فبراير</option>
                        <option value="3">مارس</option>
                        <option value="4">أبريل</option>
                        <option value="5">مايو</option>
                        <option value="6">يونيو</option>
                        <option value="7">يوليو</option>
                        <option value="8">أغسطس</option>
                        <option value="9">سبتمبر</option>
                        <option value="10">أكتوبر</option>
                        <option value="11">نوفمبر</option>
                        <option value="12">ديسمبر</option>
                    </select>
                </div>
                
                <div class="form-group export-group">
                    <label class="form-label">السنة</label>
                    <select class="form-select" name="export_year" required>
                        <?php
                        $current_year = date('Y');
                        for ($year = $current_year; $year >= $current_year - 5; $year--) {
                            echo "<option value='$year'>$year</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group export-group">
                    <button type="submit" name="export_payroll" class="btn btn-warning">
                        <i class="fas fa-download"></i> تصدير إلى Excel
                    </button>
                </div>
            </form>
        </div>
        
        <div class="content-grid">
            <div class="form-section">
                <h2 class="section-title"><?php echo isset($_GET['edit']) ? 'تعديل كشف المرتبات' : 'إضافة كشف مرتبات جديد'; ?></h2>
                
                <?php
                $edit_mode = false;
                $editing_payroll = null;
                
                if (isset($_GET['edit'])) {
                    $edit_mode = true;
                    $payroll_id = $_GET['edit'];
                    
                    try {
                        $stmt = $db->prepare("SELECT * FROM payroll WHERE payroll_id = ?");
                        $stmt->execute([$payroll_id]);
                        $editing_payroll = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        error_log('Payroll Edit Error: ' . $e->getMessage());
                    }
                }
                ?>
                
                <form method="POST">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="payroll_id" value="<?php echo $editing_payroll['payroll_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">الموظف</label>
<select class="form-select" name="employee_id" <?php echo $edit_mode ? 'disabled' : 'required'; ?>>
    <option value="">اختر الموظف</option>
    <?php foreach ($employees as $employee): ?>
        <option value="<?php echo $employee['employee_id']; ?>"
            <?php 
            if ($edit_mode && $editing_payroll['employee_id'] == $employee['employee_id']) echo 'selected';
            elseif (!$edit_mode && isset($_POST['employee_id']) && $_POST['employee_id'] == $employee['employee_id']) echo 'selected';
            ?>>
            <?php echo $employee['full_name']; ?>
        </option>
    <?php endforeach; ?>
</select>




                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الشهر</label>
                        <select class="form-select" name="month" required>
                            <option value="">اختر الشهر</option>
                            <?php
                            $months = [
                                1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
                                5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
                                9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
                            ];
                            
                            foreach ($months as $num => $name) {
                                $selected = '';
                                if ($edit_mode && $editing_payroll['month'] == $num) {
                                    $selected = 'selected';
                                } elseif (!$edit_mode && isset($_POST['month']) && $_POST['month'] == $num) {
                                    $selected = 'selected';
                                } elseif (!$edit_mode && !isset($_POST['month']) && $num == date('n')) {
                                    $selected = 'selected';
                                }
                                
                                echo "<option value='$num' $selected>$name</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">السنة</label>
                        <select class="form-select" name="year" required>
                            <option value="">اختر السنة</option>
                            <?php
                            $current_year = date('Y');
                            for ($year = $current_year; $year >= $current_year - 5; $year--) {
                                $selected = '';
                                if ($edit_mode && $editing_payroll['year'] == $year) {
                                    $selected = 'selected';
                                } elseif (!$edit_mode && isset($_POST['year']) && $_POST['year'] == $year) {
                                    $selected = 'selected';
                                } elseif (!$edit_mode && !isset($_POST['year']) && $year == $current_year) {
                                    $selected = 'selected';
                                }
                                
                                echo "<option value='$year' $selected>$year</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الراتب الأساسي</label>
                        <input type="number" class="form-input" name="basic_salary" 
                            value="<?php 
                            if ($edit_mode) echo $editing_payroll['basic_salary'];
                            elseif (isset($_POST['basic_salary'])) echo $_POST['basic_salary'];
                            else echo $employees[0]['basic_salary'] ?? 0;
                            ?>" 
                            required step="0.01" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">البدلات</label>
                        <input type="number" class="form-input" name="allowances" 
                            value="<?php 
                            if ($edit_mode) echo $editing_payroll['allowances'];
                            elseif (isset($_POST['allowances'])) echo $_POST['allowances'];
                            else echo 0;
                            ?>" 
                            step="0.01" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الاستقطاعات</label>
                        <input type="number" class="form-input" name="deductions" 
                            value="<?php 
                            if ($edit_mode) echo $editing_payroll['deductions'];
                            elseif (isset($_POST['deductions'])) echo $_POST['deductions'];
                            else echo 0;
                            ?>" 
                            step="0.01" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">صافي الراتب</label>
                        <input type="number" class="form-input" id="net_salary" 
                            value="<?php 
                            if ($edit_mode) echo $editing_payroll['net_salary'];
                            else {
                                $basic = $_POST['basic_salary'] ?? $employees[0]['basic_salary'] ?? 0;
                                $allowances = $_POST['allowances'] ?? 0;
                                $deductions = $_POST['deductions'] ?? 0;
                                echo $basic + $allowances - $deductions;
                            }
                            ?>" 
                            disabled>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الحالة</label>
                        <select class="form-select" name="status" required>
                            <option value="pending" <?php 
                            if ($edit_mode && $editing_payroll['status'] == 'pending') echo 'selected';
                            elseif (isset($_POST['status']) && $_POST['status'] == 'pending') echo 'selected';
                            else echo 'selected';
                            ?>>قيد الانتظار</option>
                            <option value="paid" <?php 
                            if ($edit_mode && $editing_payroll['status'] == 'paid') echo 'selected';
                            elseif (isset($_POST['status']) && $_POST['status'] == 'paid') echo 'selected';
                            ?>>تم الدفع</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ملاحظات</label>
                        <textarea class="form-textarea" name="notes"><?php 
                        if ($edit_mode) echo $editing_payroll['notes'];
                        elseif (isset($_POST['notes'])) echo $_POST['notes'];
                        ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <?php if ($edit_mode): ?>
                            <button type="submit" name="update_payroll" class="btn btn-success">
                                <i class="fas fa-save"></i> تحديث كشف المرتبات
                            </button>
                            <a href="payroll.php" class="btn btn-danger">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                        <?php else: ?>
                            <button type="submit" name="add_payroll" class="btn">
                                <i class="fas fa-plus"></i> إضافة كشف المرتبات
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div class="list-section">
                <h2 class="section-title">كشوف المرتبات السابقة</h2>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>الموظف</th>
                                <th>الشهر/السنة</th>
                                <th>الراتب الأساسي</th>
                                <th>البدلات</th>
                                <th>الاستقطاعات</th>
                                <th>الصافي</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($payrolls)): ?>
                                <?php foreach ($payrolls as $payroll): ?>
                                <tr>
<td><?php echo $payroll['employee_name']; ?></td>
                                    <td><?php echo $months[$payroll['month']] . ' / ' . $payroll['year']; ?></td>
                                    <td><?php echo number_format($payroll['basic_salary'], 2); ?></td>
                                    <td><?php echo number_format($payroll['allowances'], 2); ?></td>
                                    <td><?php echo number_format($payroll['deductions'], 2); ?></td>
                                    <td><?php echo number_format($payroll['net_salary'], 2); ?></td>
                                    <td>
                                        <span class="<?php echo $payroll['status'] == 'paid' ? 'status-paid' : 'status-pending'; ?>">
                                            <?php echo $payroll['status'] == 'paid' ? 'تم الدفع' : 'قيد الانتظار'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="payroll.php?edit=<?php echo $payroll['payroll_id']; ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="payroll_details.php?id=<?php echo $payroll['payroll_id']; ?>" class="btn btn-sm">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">لا توجد كشوف مرتبات</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // حساب صافي الراتب تلقائياً
        document.addEventListener('DOMContentLoaded', function() {
            const basicSalary = document.querySelector('input[name="basic_salary"]');
            const allowances = document.querySelector('input[name="allowances"]');
            const deductions = document.querySelector('input[name="deductions"]');
            const netSalary = document.getElementById('net_salary');
            
            function calculateNetSalary() {
                const basic = parseFloat(basicSalary.value) || 0;
                const allow = parseFloat(allowances.value) || 0;
                const deduct = parseFloat(deductions.value) || 0;
                
                netSalary.value = (basic + allow - deduct).toFixed(2);
            }
            
            if (basicSalary && allowances && deductions) {
                basicSalary.addEventListener('input', calculateNetSalary);
                allowances.addEventListener('input', calculateNetSalary);
                deductions.addEventListener('input', calculateNetSalary);
            }
        });
    </script>
</body>
</html>