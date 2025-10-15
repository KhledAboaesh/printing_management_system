<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من وجود معرف الموظف في الرابط
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: employees.php");
    exit();
}

$employee_id = intval($_GET['id']);

// جلب بيانات الموظف
try {
    global $db;
    
    // معلومات الموظف الأساسية
    $stmt = $db->prepare("
        SELECT e.*, d.name as department_name, u.username 
        FROM employees e 
        LEFT JOIN departments d ON e.department_id = d.department_id 
        LEFT JOIN users u ON e.user_id = u.user_id 
        WHERE e.employee_id = ?
    ");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        header("Location: employees.php");
        exit();
    }
    
    // سجل الحضور للشهر الحالي
    $current_month = date('Y-m');
    $stmt = $db->prepare("
        SELECT * FROM attendance 
        WHERE employee_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
        ORDER BY date DESC
    ");
    $stmt->execute([$employee_id, $current_month]);
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // طلبات الإجازة
    $stmt = $db->prepare("
        SELECT lr.*, u.username as approved_by_name 
        FROM leave_requests lr 
        LEFT JOIN users u ON lr.approved_by = u.user_id 
        WHERE lr.employee_id = ? 
        ORDER BY lr.start_date DESC
    ");
    $stmt->execute([$employee_id]);
    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // المستندات المرفقة
    $stmt = $db->prepare("
        SELECT cd.*, u.username as uploaded_by_name 
        FROM customer_documents cd 
        LEFT JOIN users u ON cd.uploaded_by = u.user_id 
        WHERE cd.customer_id = ? 
        ORDER BY cd.uploaded_at DESC
    ");
    $stmt->execute([$employee_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_employee', 'عرض تفاصيل الموظف: ' . $employee['first_name'] . ' ' . $employee['last_name']);
    
} catch (PDOException $e) {
    error_log('Employee Details Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب بيانات الموظف";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الموظف - نظام إدارة الطباعة</title>
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
        }
        
        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 15px;
            background-color: var(--light-color);
            color: var(--dark-color);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: background-color 0.3s;
        }
        
        .back-button:hover {
            background-color: #dde4e6;
        }
        
        .employee-header {
            display: flex;
            align-items: center;
            gap: 20px;
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .employee-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
        }
        
        .employee-info h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .employee-info p {
            color: #777;
            margin-bottom: 3px;
        }
        
        .tabs {
            display: flex;
            background-color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            overflow: hidden;
            margin-bottom: 0;
        }
        
        .tab {
            padding: 15px 20px;
            cursor: pointer;
            background-color: #f1f1f1;
            transition: background-color 0.3s;
            text-align: center;
            flex: 1;
        }
        
        .tab.active {
            background-color: white;
            border-bottom: 3px solid var(--primary-color);
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
            background-color: white;
            padding: 20px;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .info-card {
            background-color: var(--light-color);
            padding: 15px;
            border-radius: var(--border-radius);
        }
        
        .info-card h3 {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ccc;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
        }
        
        .table-container {
            overflow-x: auto;
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
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-present {
            background-color: #e7f6e7;
            color: var(--success-color);
        }
        
        .status-absent {
            background-color: #fde9e9;
            color: var(--danger-color);
        }
        
        .status-pending {
            background-color: #fef5e6;
            color: var(--warning-color);
        }
        
        .status-approved {
            background-color: #e7f6e7;
            color: var(--success-color);
        }
        
        .status-rejected {
            background-color: #fde9e9;
            color: var(--danger-color);
        }
        
        .document-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .document-item:last-child {
            border-bottom: none;
        }
        
        .document-actions a {
            margin-left: 10px;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .no-data {
            text-align: center;
            padding: 20px;
            color: #777;
        }
        
        @media (max-width: 768px) {
            .employee-header {
                flex-direction: column;
                text-align: center;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .info-item {
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
        
        <a href="employees.php" class="back-button">
            <i class="fas fa-arrow-right"></i> العودة إلى قائمة الموظفين
        </a>
        
        <h1 class="page-title">تفاصيل الموظف</h1>
        
        <?php if (isset($error)): ?>
        <div style="background-color: #ffebee; color: #d32f2f; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="employee-header">
            <div class="employee-avatar">
                <?php echo substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1); ?>
            </div>
            <div class="employee-info">
                <h2><?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?></h2>
                <p><i class="fas fa-briefcase"></i> <?php echo $employee['department_name'] ?? 'غير محدد'; ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo $employee['email']; ?></p>
                <p><i class="fas fa-phone"></i> <?php echo $employee['phone']; ?></p>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab active" onclick="openTab('personal')">المعلومات الشخصية</div>
            <div class="tab" onclick="openTab('attendance')">سجل الحضور</div>
            <div class="tab" onclick="openTab('leaves')">طلبات الإجازة</div>
            <div class="tab" onclick="openTab('documents')">المستندات</div>
        </div>
        
        <!-- تبويب المعلومات الشخصية -->
        <div id="personal" class="tab-content active">
            <div class="info-grid">
                <div class="info-card">
                    <h3>المعلومات الأساسية</h3>
                    <div class="info-item">
                        <span class="info-label">الاسم الكامل:</span>
                        <span><?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">البريد الإلكتروني:</span>
                        <span><?php echo $employee['email']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">رقم الهاتف:</span>
                        <span><?php echo $employee['phone']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">العنوان:</span>
                        <span><?php echo $employee['address'] ?? 'غير محدد'; ?></span>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3>المعلومات الوظيفية</h3>
                    <div class="info-item">
                        <span class="info-label">القسم:</span>
                        <span><?php echo $employee['department_name'] ?? 'غير محدد'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">الوظيفة:</span>
                        <span><?php echo $employee['position'] ?? 'غير محدد'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">تاريخ التعيين:</span>
                        <span><?php echo $employee['hire_date'] ?? 'غير محدد'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">الراتب الأساسي:</span>
                        <span><?php echo $employee['salary'] ? number_format($employee['salary']) . ' د.ل' : 'غير محدد'; ?></span>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3>معلومات إضافية</h3>
                    <div class="info-item">
                        <span class="info-label">رقم الهوية:</span>
                        <span><?php echo $employee['id_number'] ?? 'غير محدد'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">تاريخ الميلاد:</span>
                        <span><?php echo $employee['birth_date'] ?? 'غير محدد'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">الجنس:</span>
                        <span><?php echo $employee['gender'] == 'male' ? 'ذكر' : ($employee['gender'] == 'female' ? 'أنثى' : 'غير محدد'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">الحالة الاجتماعية:</span>
                        <span>
                            <?php 
                            if ($employee['marital_status'] == 'single') echo 'أعزب';
                            elseif ($employee['marital_status'] == 'married') echo 'متزوج';
                            elseif ($employee['marital_status'] == 'divorced') echo 'مطلق';
                            else echo 'غير محدد';
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- تبويب سجل الحضور -->
        <div id="attendance" class="tab-content">
            <h3>سجل الحضور لشهر <?php echo date('F Y'); ?></h3>
            
            <?php if (!empty($attendance)): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>يوم الأسبوع</th>
                            <th>حالة الحضور</th>
                            <th>وقت الدخول</th>
                            <th>وقت الخروج</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance as $record): 
                            $day_of_week = date('l', strtotime($record['date']));
                            $day_names = [
                                'Saturday' => 'السبت',
                                'Sunday' => 'الأحد',
                                'Monday' => 'الاثنين',
                                'Tuesday' => 'الثلاثاء',
                                'Wednesday' => 'الأربعاء',
                                'Thursday' => 'الخميس',
                                'Friday' => 'الجمعة'
                            ];
                        ?>
                        <tr>
                            <td><?php echo $record['date']; ?></td>
                            <td><?php echo $day_names[$day_of_week] ?? $day_of_week; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $record['status']; ?>">
                                    <?php 
                                    if ($record['status'] == 'present') echo 'حاضر';
                                    elseif ($record['status'] == 'absent') echo 'غائب';
                                    else echo $record['status'];
                                    ?>
                                </span>
                            </td>
                            <td><?php echo $record['check_in'] ?? '--:--'; ?></td>
                            <td><?php echo $record['check_out'] ?? '--:--'; ?></td>
                            <td><?php echo $record['notes'] ?? 'لا توجد ملاحظات'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-calendar-times" style="font-size: 48px; margin-bottom: 15px;"></i>
                <p>لا توجد سجلات حضور لهذا الشهر</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- تبويب طلبات الإجازة -->
        <div id="leaves" class="tab-content">
            <h3>طلبات الإجازة</h3>
            
            <?php if (!empty($leave_requests)): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>نوع الإجازة</th>
                            <th>من تاريخ</th>
                            <th>إلى تاريخ</th>
                            <th>عدد الأيام</th>
                            <th>الحالة</th>
                            <th>تمت الموافقة بواسطة</th>
                            <th>سبب الإجازة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leave_requests as $leave): ?>
                        <tr>
                            <td><?php echo $leave['leave_type']; ?></td>
                            <td><?php echo $leave['start_date']; ?></td>
                            <td><?php echo $leave['end_date']; ?></td>
                            <td><?php echo $leave['days_count']; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $leave['status']; ?>">
                                    <?php 
                                    if ($leave['status'] == 'pending') echo 'قيد الانتظار';
                                    elseif ($leave['status'] == 'approved') echo 'تم الموافقة';
                                    elseif ($leave['status'] == 'rejected') echo 'مرفوض';
                                    else echo $leave['status'];
                                    ?>
                                </span>
                            </td>
                            <td><?php echo $leave['approved_by_name'] ?? 'لم يتم المراجعة بعد'; ?></td>
                            <td><?php echo $leave['reason'] ?? 'لا يوجد سبب'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-file-alt" style="font-size: 48px; margin-bottom: 15px;"></i>
                <p>لا توجد طلبات إجازة</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- تبويب المستندات -->
        <div id="documents" class="tab-content">
            <h3>المستندات المرفقة</h3>
            
            <?php if (!empty($documents)): ?>
                <?php foreach ($documents as $doc): ?>
                <div class="document-item">
                    <div class="document-info">
                        <div><strong><?php echo $doc['document_name']; ?></strong></div>
                        <div>تم الرفع بواسطة: <?php echo $doc['uploaded_by_name']; ?></div>
                        <div>في: <?php echo $doc['uploaded_at']; ?></div>
                    </div>
                    <div class="document-actions">
                        <a href="<?php echo $doc['file_path']; ?>" download><i class="fas fa-download"></i> تحميل</a>
                        <a href="<?php echo $doc['file_path']; ?>" target="_blank"><i class="fas fa-eye"></i> معاينة</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="no-data">
                <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 15px;"></i>
                <p>لا توجد مستندات مرفقة</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function openTab(tabName) {
            // إخفاء جميع محتويات التبويبات
            var tabContents = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove("active");
            }
            
            // إلغاء تنشيط جميع التبويبات
            var tabs = document.getElementsByClassName("tab");
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove("active");
            }
            
            // تفعيل التبويب المحدد
            document.getElementById(tabName).classList.add("active");
            
            // البحث عن التبويب المناسب وتفعيله
            for (var i = 0; i < tabs.length; i++) {
                if (tabs[i].textContent.trim() === document.querySelector(`#${tabName} .tab.active`)) {
                    // هذا ليس التنفيذ الصحيح، سنقوم بتصحيحه
                    break;
                }
            }
            
            // بدلاً من ذلك، سنضيف فئة active للتبويب المناسب بناءً على النص
            for (var i = 0; i < tabs.length; i++) {
                if (tabs[i].getAttribute('onclick') === `openTab('${tabName}')`) {
                    tabs[i].classList.add("active");
                    break;
                }
            }
        }
    </script>
</body>
</html>