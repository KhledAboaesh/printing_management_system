<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (الموارد البشرية أو المدير أو المدير العام)
if ($_SESSION['role'] != 'hr' && $_SESSION['role'] != 'manager' && $_SESSION['role'] != 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// معالجة تسجيل الحضور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['check_in'])) {
    $employee_id = $_POST['employee_id'];

    try {
        global $db;

        // التحقق مما إذا تم تسجيل الحضور مسبقاً اليوم
        $current_date = date('Y-m-d');
        $stmt = $db->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$employee_id, $current_date]);
        $existing_attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_attendance) {
            $error = "تم تسجيل الحضور مسبقاً لهذا الموظف اليوم";
        } else {
            // تسجيل الحضور
            $stmt = $db->prepare("INSERT INTO attendance (employee_id, date, check_in, status) VALUES (?, ?, NOW(), 'present')");
            $stmt->execute([$employee_id, $current_date]);

            // تسجيل النشاط
            logActivity($_SESSION['user_id'], 'check_in', "تسجيل حضور للموظف رقم $employee_id");

            $success = "تم تسجيل الحضور بنجاح";
        }
    } catch (PDOException $e) {
        error_log('Check-in Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء تسجيل الحضور";
    }
}

// معالجة تسجيل الانصراف
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['check_out'])) {
    $attendance_id = $_POST['attendance_id'];

    try {
        global $db;

        // تسجيل الانصراف
        $stmt = $db->prepare("UPDATE attendance SET check_out = NOW() WHERE attendance_id = ?");
        $stmt->execute([$attendance_id]);

        // تسجيل النشاط
        logActivity($_SESSION['user_id'], 'check_out', "تسجيل انصراف للسجل رقم $attendance_id");

        $success = "تم تسجيل الانصراف بنجاح";
    } catch (PDOException $e) {
        error_log('Check-out Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء تسجيل الانصراف";
    }
}

// معالجة تحديث حالة الحضور
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $attendance_id = $_POST['attendance_id'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];

    try {
        global $db;

        // تحديث حالة الحضور
        $stmt = $db->prepare("UPDATE attendance SET status = ?, notes = ? WHERE attendance_id = ?");
        $stmt->execute([$status, $notes, $attendance_id]);

        // تسجيل النشاط
        logActivity($_SESSION['user_id'], 'update_attendance', "تحديث حالة الحضور للسجل رقم $attendance_id");

        $success = "تم تحديث حالة الحضور بنجاح";
    } catch (PDOException $e) {
        error_log('Update Attendance Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء تحديث حالة الحضور";
    }
}

// جلب سجل الحضور اليومي والإحصائيات
$current_date = date('Y-m-d');
try {
    global $db;

    // سجل الحضور اليومي
    $stmt = $db->prepare("
        SELECT a.*, u.full_name as employee_name, d.name as department_name
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        JOIN users u ON e.user_id = u.user_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE a.date = ?
        ORDER BY a.check_in DESC
    ");
    $stmt->execute([$current_date]);
    $today_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب الموظفين لتسجيل الحضور
    $stmt = $db->query("
        SELECT e.employee_id, u.full_name as employee_name, d.name as department_name
        FROM employees e
        JOIN users u ON e.user_id = u.user_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE e.is_active = 1
        ORDER BY u.full_name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب إحصائيات الحضور
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_employees,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN a.status = 'on_leave' THEN 1 ELSE 0 END) as on_leave_count
        FROM employees e
        LEFT JOIN attendance a ON e.employee_id = a.employee_id AND a.date = ?
        WHERE e.is_active = 1
    ");
    $stmt->execute([$current_date]);
    $attendance_stats = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Attendance Data Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب بيانات الحضور";
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - إدارة الحضور والانصراف</title>
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 15px;
            box-shadow: var(--box-shadow);
            text-align: center;
        }
        
        .stat-icon {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .total-icon { color: var(--primary-color); }
        .present-icon { color: var(--success-color); }
        .absent-icon { color: var(--danger-color); }
        .late-icon { color: var(--warning-color); }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #777;
            font-size: 14px;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .checkin-section, .attendance-section {
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
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        textarea {
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
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn-success {
            background-color: var(--success-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-warning {
            background-color: var(--warning-color);
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .btn-block {
            display: block;
            width: 100%;
            text-align: center;
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .attendance-table th,
        .attendance-table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }
        
        .attendance-table th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        .attendance-table tr:hover {
            background-color: #f5f5f5;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-present {
            background-color: #e6f7ee;
            color: var(--success-color);
        }
        
        .status-absent {
            background-color: #fde9e9;
            color: var(--danger-color);
        }
        
        .status-late {
            background-color: #fef5e7;
            color: var(--warning-color);
        }
        
        .status-on_leave {
            background-color: #ebf5ff;
            color: var(--primary-color);
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
            font-size: 12px;
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #e6f7ee;
            color: var(--success-color);
            border: 1px solid #a3e9c7;
        }
        
        .alert-error {
            background-color: #fde9e9;
            color: var(--danger-color);
            border: 1px solid #f8b3b3;
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
        }
        
        .tab.active {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
        }
        
        .tab:hover:not(.active) {
            background-color: var(--light-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @media (max-width: 992px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons {
                flex-direction: column;
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
                    <div style="font-size: 12px; color: #777;">إدارة الحضور والانصراف</div>
                </div>
            </div>
        </header>
        
        <h1 class="page-title">إدارة الحضور والانصراف</h1>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $attendance_stats['total_employees'] ?? 0; ?></div>
                <div class="stat-label">إجمالي الموظفين</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon present-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-value"><?php echo $attendance_stats['present_count'] ?? 0; ?></div>
                <div class="stat-label">حاضرين</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon absent-icon">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-value"><?php echo $attendance_stats['absent_count'] ?? 0; ?></div>
                <div class="stat-label">غائبين</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon late-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $attendance_stats['late_count'] ?? 0; ?></div>
                <div class="stat-label">متأخرين</div>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('today')">الحضور اليومي</div>
            <div class="tab" onclick="switchTab('monthly')">التقارير الشهرية</div>
            <div class="tab" onclick="switchTab('manage')">إدارة الغياب</div>
        </div>
        
        <div id="today-tab" class="tab-content active">
            <div class="content-grid">
                <div class="checkin-section">
                    <h2 class="section-title">تسجيل حضور</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="employee_id">اختر الموظف</label>
                           <select name="employee_id">
    <option value="">-- اختر الموظف --</option>
    <?php foreach($employees as $employee): ?>
        <option value="<?= $employee['employee_id'] ?>">
            <?= $employee['employee_name'] ?> - <?= $employee['department_name'] ?>
        </option>
    <?php endforeach; ?>
</select>

                        </div>
                        <button type="submit" name="check_in" class="btn btn-success btn-block">
                            <i class="fas fa-sign-in-alt"></i> تسجيل حضور
                        </button>
                    </form>
                    
                    <h2 class="section-title" style="margin-top: 30px;">تسجيل انصراف</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="attendance_id">اختر سجل الحضور</label>
                            <select id="attendance_id" name="attendance_id" required>
                                <option value="">-- اختر سجل الحضور --</option>
                                <?php foreach ($today_attendance as $record): ?>
                                    <?php if (empty($record['check_out'])): ?>
                                    <option value="<?php echo $record['attendance_id']; ?>">
                                        <?php echo $record['employee_name']; ?> - <?php echo date('H:i', strtotime($record['check_in'])); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="check_out" class="btn btn-danger btn-block">
                            <i class="fas fa-sign-out-alt"></i> تسجيل انصراف
                        </button>
                    </form>
                </div>
                
                <div class="attendance-section">
                    <h2 class="section-title">سجل الحضور اليومي - <?php echo date('Y-m-d'); ?></h2>
                    
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>الموظف</th>
                                <th>القسم</th>
                                <th>الحضور</th>
                                <th>الانصراف</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($today_attendance)): ?>
                                <?php foreach ($today_attendance as $record): ?>
                                <tr>
                                    <td><?php echo $record['employee_name']; ?></td>
                                    <td><?php echo $record['department_name']; ?></td>
                                    <td><?php echo $record['check_in'] ? date('H:i', strtotime($record['check_in'])) : '--'; ?></td>
                                    <td><?php echo $record['check_out'] ? date('H:i', strtotime($record['check_out'])) : '--'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $record['status']; ?>">
                                            <?php 
                                            $statuses = [
                                                'present' => 'حاضر',
                                                'absent' => 'غائب',
                                                'late' => 'متأخر',
                                                'on_leave' => 'إجازة'
                                            ];
                                            echo $statuses[$record['status']] ?? $record['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-warning action-btn" onclick="openEditModal(<?php echo $record['attendance_id']; ?>, '<?php echo $record['status']; ?>', `<?php echo $record['notes']; ?>`)">
                                                <i class="fas fa-edit"></i> تعديل
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">لا توجد سجلات حضور لهذا اليوم</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div id="monthly-tab" class="tab-content">
            <div class="attendance-section">
                <h2 class="section-title">تقارير الحضور الشهرية</h2>
                <p>هنا سيتم عرض تقارير الحضور الشهرية مع إمكانية التصفية حسب الشهر والموظف.</p>
                <!-- سيتم إضافة محتوى التقارير الشهرية هنا -->
            </div>
        </div>
        
        <div id="manage-tab" class="tab-content">
            <div class="attendance-section">
                <h2 class="section-title">إدارة الغياب</h2>
                <p>هنا سيتم عرض إدارة الغياب وإضافة الملاحظات على سجلات الحضور.</p>
                <!-- سيتم إضافة محتوى إدارة الغياب هنا -->
            </div>
        </div>
    </div>

    <!-- Modal for editing attendance -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: white; padding: 20px; border-radius: 8px; width: 90%; max-width: 500px;">
            <h2 style="margin-bottom: 15px;">تعديل حالة الحضور</h2>
            <form method="POST" action="">
                <input type="hidden" id="edit_attendance_id" name="attendance_id">
                
                <div class="form-group">
                    <label for="edit_status">الحالة</label>
                    <select id="edit_status" name="status" required>
                        <option value="present">حاضر</option>
                        <option value="absent">غائب</option>
                        <option value="late">متأخر</option>
                        <option value="on_leave">إجازة</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_notes">ملاحظات</label>
                    <textarea id="edit_notes" name="notes"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeEditModal()">إلغاء</button>
                    <button type="submit" name="update_status" class="btn btn-success">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show the selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Activate the clicked tab
            event.target.classList.add('active');
        }
        
        function openEditModal(attendanceId, status, notes) {
            document.getElementById('edit_attendance_id').value = attendanceId;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_notes').value = notes;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('editModal')) {
                closeEditModal();
            }
        };
    </script>
</body>
</html>