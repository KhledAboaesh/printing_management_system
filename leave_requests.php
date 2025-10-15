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
if (!in_array($_SESSION['role'], ['hr','manager','admin'])) {
    header("Location: unauthorized.php");
    exit();
}

// جلب بيانات الموظفين النشطين
$employees = [];
try {
    $stmt = $db->query("SELECT employee_id, full_name FROM employees WHERE is_active = 1 ORDER BY full_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Employees Fetch Error: ' . $e->getMessage());
}

// جلب طلبات الإجازة
$leave_requests = [];
$filter = $_GET['filter'] ?? 'all';
try {
    $query = "SELECT lr.*, e.full_name, u.username AS approved_by_name
              FROM leave_requests lr
              JOIN employees e ON lr.employee_id = e.employee_id
              LEFT JOIN users u ON lr.approved_by = u.user_id";

    if (in_array($filter, ['pending','approved','rejected'])) {
        $query .= " WHERE lr.status = :status";
        $stmt = $db->prepare($query);
        $stmt->execute(['status' => $filter]);
    } else {
        $stmt = $db->query($query);
    }

    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Leave Requests Fetch Error: ' . $e->getMessage());
}

// إضافة طلب إجازة جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_leave'])) {
    $employee_id = $_POST['employee_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    // تحقق من الأعمدة الموجودة في جدول leave_requests
    $leave_type = $_POST['leave_type'] ?? null; // ضع null إذا لا يوجد
    try {
        $stmt = $db->prepare("INSERT INTO leave_requests (employee_id, start_date, end_date, status, leave_type) 
                              VALUES (?, ?, ?, 'pending', ?)");
        $stmt->execute([$employee_id, $start_date, $end_date, $leave_type]);

        $request_id = $db->lastInsertId();
        logActivity($_SESSION['user_id'], 'add_leave_request', "تم إضافة طلب إجازة جديد رقم: $request_id");

        header("Location: leave_requests.php?success=تم إضافة طلب الإجازة بنجاح");
        exit();
    } catch (PDOException $e) {
        error_log('Add Leave Request Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء إضافة طلب الإجازة";
    }
}

// الموافقة أو الرفض
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['approve']) || isset($_POST['reject']))) {
    $request_id = $_POST['request_id'];
    $action = isset($_POST['approve']) ? 'approved' : 'rejected';
    $notes = $_POST['notes'] ?? null;

    try {
        $stmt = $db->prepare("UPDATE leave_requests SET status = ?, approved_by = ?, approved_at = NOW(), notes = ? WHERE request_id = ?");
        $stmt->execute([$action, $_SESSION['user_id'], $notes, $request_id]);

        logActivity($_SESSION['user_id'], 'update_leave_request', "تم $action طلب الإجازة رقم: $request_id");

        header("Location: leave_requests.php?success=تم تحديث حالة طلب الإجازة بنجاح");
        exit();
    } catch (PDOException $e) {
        error_log('Update Leave Request Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء تحديث حالة طلب الإجازة";
    }
}

// جلب رصيد الإجازات (إذا كان موجودًا)
$leave_balances = [];
try {
    $stmt = $db->query("
        SELECT e.employee_id, e.full_name,
               COALESCE(SUM(CASE WHEN lr.status = 'approved' THEN DATEDIFF(lr.end_date, lr.start_date) + 1 ELSE 0 END), 0) AS taken_leaves
        FROM employees e
        LEFT JOIN leave_requests lr ON e.employee_id = lr.employee_id
        WHERE e.is_active = 1
        GROUP BY e.employee_id, e.full_name
    ");
    $leave_balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Leave Balances Fetch Error: ' . $e->getMessage());
}

// تسجيل النشاط
logActivity($_SESSION['user_id'], 'view_leave_requests', 'عرض صفحة طلبات الإجازة');
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - طلبات الإجازة</title>
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
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            overflow-x: auto;
        }
        
        .tab {
            padding: 10px 20px;
            background-color: white;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .tab.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .card-title {
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
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
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
        
        .table-responsive {
            overflow-x: auto;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
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
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: var(--warning-color);
            color: white;
        }
        
        .status-approved {
            background-color: var(--success-color);
            color: white;
        }
        
        .status-rejected {
            background-color: var(--danger-color);
            color: white;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            background-color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 600px;
            position: relative;
        }
        
        .close {
            position: absolute;
            top: 10px;
            left: 10px;
            font-size: 24px;
            cursor: pointer;
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
            .container {
                padding: 10px;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
            }
            
            .filter-buttons {
                flex-direction: column;
            }
            
            th, td {
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
                    <div style="font-size: 12px; color: #777;"><?php echo $_SESSION['role'] ?? 'دور'; ?></div>
                </div>
            </div>
        </header>
        
        <h1 class="page-title">إدارة طلبات الإجازة</h1>
        
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php echo $_GET['success']; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" data-tab="requests">طلبات الإجازة</div>
            <div class="tab" data-tab="new">طلب إجازة جديد</div>
            <div class="tab" data-tab="balances">رصيد الإجازات</div>
        </div>
        
        <div class="tab-content active" id="requests-tab">
            <div class="filter-buttons">
                <a href="leave_requests.php?filter=all" class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">جميع الطلبات</a>
                <a href="leave_requests.php?filter=pending" class="filter-btn <?php echo $filter == 'pending' ? 'active' : ''; ?>">الطلبات المعلقة</a>
                <a href="leave_requests.php?filter=approved" class="filter-btn <?php echo $filter == 'approved' ? 'active' : ''; ?>">الطلبات المقبولة</a>
                <a href="leave_requests.php?filter=rejected" class="filter-btn <?php echo $filter == 'rejected' ? 'active' : ''; ?>">الطلبات المرفوضة</a>
            </div>
            
            <div class="card">
                <div class="card-title">قائمة طلبات الإجازة</div>
                <div class="table-responsive">
          <table>
    <thead>
        <tr>
            <th>الموظف</th>
            <th>نوع الإجازة</th>
            <th>تاريخ البدء</th>
            <th>تاريخ الانتهاء</th>
            <th>عدد الأيام</th>
            <th>الحالة</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($leave_requests)): ?>
            <?php foreach ($leave_requests as $request): ?>
                <tr>
                    <td>
                        <?php
                        $employee_name = '';
                        foreach ($employees as $emp) {
                            if ($emp['employee_id'] == $request['employee_id']) {
                                $employee_name = $emp['full_name'];
                                break;
                            }
                        }
                        echo htmlspecialchars($employee_name ?: 'غير معروف');
                        ?>
                    </td>
                    <td><?= htmlspecialchars($request['leave_type'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($request['start_date'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($request['end_date'] ?? '-') ?></td>
                    <td>
                        <?php 
                        if (!empty($request['start_date']) && !empty($request['end_date'])) {
                            $start = new DateTime($request['start_date']);
                            $end = new DateTime($request['end_date']);
                            $diff = $start->diff($end);
                            echo $diff->days + 1;
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?= htmlspecialchars($request['status'] ?? '') ?>">
                            <?= match($request['status'] ?? '') {
                                'pending' => 'معلق',
                                'approved' => 'مقبول',
                                'rejected' => 'مرفوض',
                                default => '-'
                            } ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" style="text-align: center;">لا توجد طلبات إجازة</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>


                </div>
            </div>
        </div>
        
        <div class="tab-content" id="new-tab">
            <div class="card">
                <div class="card-title">طلب إجازة جديد</div>
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">الموظف</label>
                      <select name="employee_id" required>
    <option value="">-- اختر الموظف --</option>
    <?php foreach($employees as $emp): ?>
        <option value="<?= $emp['employee_id'] ?>">
            <?= htmlspecialchars($emp['full_name']) ?>
        </option>
    <?php endforeach; ?>
</select>

                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">نوع الإجازة</label>
                        <select class="form-control" name="leave_type" required>
                            <option value="">اختر نوع الإجازة</option>
                            <option value="annual">إجازة سنوية</option>
                            <option value="sick">إجازة مرضية</option>
                            <option value="emergency">إجازة طارئة</option>
                            <option value="unpaid">إجازة بدون راتب</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">تاريخ البدء</label>
                        <input type="date" class="form-control" name="start_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">تاريخ الانتهاء</label>
                        <input type="date" class="form-control" name="end_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">السبب</label>
                        <textarea class="form-control" name="reason" rows="3" required></textarea>
                    </div>
                    
                    <button type="submit" name="add_leave" class="btn btn-primary">إضافة طلب الإجازة</button>
                </form>
            </div>
        </div>
        
        <div class="tab-content" id="balances-tab">
            <div class="card">
                <div class="card-title">رصيد الإجازات للموظفين</div>
                <div class="table-responsive">
                   <table>
    <thead>
        <tr>
            <th>الموظف</th>
            <th>رصيد الإجازات السنوي</th>
            <th>الإجازات المستخدمة</th>
            <th>الإجازات المتبقية</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($leave_balances)): ?>
            <?php foreach ($leave_balances as $balance): ?>
                <?php 
                    // استخدام full_name بدل first_name و last_name
                    $full_name = $balance['full_name'] ?? 'غير معروف';
                    
                    // إذا لم يكن لديك annual_leave_balance، نفترض 0 أو قيمة افتراضية
                    $annual_balance = $balance['annual_leave_balance'] ?? 0;
                    
                    // الإجازات المستخدمة
                    $taken_leaves = $balance['taken_leaves'] ?? 0;
                    
                    // الإجازات المتبقية
                    $remaining = max($annual_balance - $taken_leaves, 0);
                ?>
                <tr>
                    <td><?= htmlspecialchars($full_name) ?></td>
                    <td><?= $annual_balance ?> يوم</td>
                    <td><?= $taken_leaves ?> يوم</td>
                    <td>
                        <?= $remaining > 0 ? $remaining . ' يوم' : '<span style="color: red;">0 يوم</span>' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4" style="text-align: center;">لا توجد بيانات</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

                </div>
            </div>
        </div>
    </div>

    <!-- Modal للتفاصيل -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>تفاصيل طلب الإجازة</h2>
            <div id="modalBody"></div>
        </div>
    </div>

    <!-- Modal للموافقة أو الرفض -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle">تنفيذ إجراء</h2>
            <form method="POST" action="">
                <input type="hidden" name="request_id" id="requestId">
                <div class="form-group">
                    <label class="form-label">ملاحظات</label>
                    <textarea class="form-control" name="notes" rows="3"></textarea>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="approve" class="btn btn-success">موافقة</button>
                    <button type="submit" name="reject" class="btn btn-danger">رفض</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // إدارة التبويبات
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab + '-tab').classList.add('active');
            });
        });
        
        // فتح وإغلاق الـ Modals
        const modals = document.querySelectorAll('.modal');
        const closeButtons = document.querySelectorAll('.close');
        
        // عرض تفاصيل طلب الإجازة
        document.querySelectorAll('.view-details').forEach(button => {
            button.addEventListener('click', () => {
                const requestId = button.dataset.id;
                // هنا يمكن جلب البيانات من الخادم عبر AJAX
                document.getElementById('modalBody').innerHTML = `
                    <p>جاري تحميل التفاصيل لطلب الإجازة رقم ${requestId}...</p>
                    <p>في التطبيق الحقيقي، سيتم جلب البيانات من الخادم وعرضها هنا.</p>
                `;
                document.getElementById('detailsModal').style.display = 'block';
            });
        });
        
        // فتح نافذة الموافقة أو الرفض
        document.querySelectorAll('.approve-btn, .reject-btn').forEach(button => {
            button.addEventListener('click', () => {
                const requestId = button.dataset.id;
                document.getElementById('requestId').value = requestId;
                
                if (button.classList.contains('approve-btn')) {
                    document.getElementById('modalTitle').textContent = 'موافقة على طلب الإجازة';
                } else {
                    document.getElementById('modalTitle').textContent = 'رفض طلب الإجازة';
                }
                
                document.getElementById('actionModal').style.display = 'block';
            });
        });
        
        // إغلاق الـ Modals
        closeButtons.forEach(button => {
            button.addEventListener('click', () => {
                modals.forEach(modal => modal.style.display = 'none');
            });
        });
        
        // إغلاق الـ Modal عند النقر خارج المحتوى
        window.addEventListener('click', (event) => {
            modals.forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>