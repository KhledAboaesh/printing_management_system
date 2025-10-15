<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (المحاسبة أو المدير)
if ($_SESSION['role'] != 'accounting' && $_SESSION['role'] != 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// معالجة إضافة مصروف جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    $amount = $_POST['amount'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? '';
    $account_id = $_POST['account_id'] ?? '';
    $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
    $paid_by = $_SESSION['user_id'];
    
    // تحميل ملف إثبات الصرف إذا تم رفعه
    $proof_path = null;
    if (isset($_FILES['proof_file']) && $_FILES['proof_file']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/expenses/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['proof_file']['name']);
        $target_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['proof_file']['tmp_name'], $target_path)) {
            $proof_path = $target_path;
        }
    }
    
    try {
        global $db;
        $stmt = $db->prepare("INSERT INTO expenses (amount, description, category, account_id, expense_date, paid_by, proof_path, status) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$amount, $description, $category, $account_id, $expense_date, $paid_by, $proof_path]);
        
        $expense_id = $db->lastInsertId();
        
        // تسجيل القيد المحاسبي
        $stmt = $db->prepare("INSERT INTO journal_entries (entry_date, description, created_by) 
                             VALUES (?, ?, ?)");
        $stmt->execute([$expense_date, "قيد مصروف: $description", $_SESSION['user_id']]);
        
        $entry_id = $db->lastInsertId();
        
        // تفاصيل القيد (مدين)
        $stmt = $db->prepare("INSERT INTO journal_entry_details (entry_id, account_id, debit, credit) 
                             VALUES (?, ?, ?, 0)");
        $stmt->execute([$entry_id, 5, $amount]); // حساب المصروفات (افتراضيًا 5)
        
        // تفاصيل القيد (دائن)
        $stmt = $db->prepare("INSERT INTO journal_entry_details (entry_id, account_id, debit, credit) 
                             VALUES (?, ?, 0, ?)");
        $stmt->execute([$entry_id, $account_id, $amount]); // حساب الخزينة أو البنك
        
        logActivity($_SESSION['user_id'], 'add_expense', "تم إضافة مصروف جديد: $description");
        
        $_SESSION['success'] = "تم إضافة المصروف بنجاح وانتظار الموافقة";
        header("Location: expenses.php");
        exit();
        
    } catch (PDOException $e) {
        error_log('Add Expense Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء إضافة المصروف";
    }
}

// معالجة الموافقة على المصروف
if (isset($_GET['approve'])) {
    $expense_id = $_GET['approve'];
    
    try {
        global $db;
        $stmt = $db->prepare("UPDATE expenses SET status = 'approved', approved_by = ?, approved_at = NOW() 
                             WHERE expense_id = ?");
        $stmt->execute([$_SESSION['user_id'], $expense_id]);
        
        logActivity($_SESSION['user_id'], 'approve_expense', "تمت الموافقة على المصروف #$expense_id");
        
        $_SESSION['success'] = "تمت الموافقة على المصروف بنجاح";
        header("Location: expenses.php");
        exit();
        
    } catch (PDOException $e) {
        error_log('Approve Expense Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء الموافقة على المصروف";
    }
}

// معالجة رفض المصروف
if (isset($_GET['reject'])) {
    $expense_id = $_GET['reject'];
    $reason = $_GET['reason'] ?? 'غير محدد';
    
    try {
        global $db;
        $stmt = $db->prepare("UPDATE expenses SET status = 'rejected', rejection_reason = ?, approved_by = ?, approved_at = NOW() 
                             WHERE expense_id = ?");
        $stmt->execute([$reason, $_SESSION['user_id'], $expense_id]);
        
        logActivity($_SESSION['user_id'], 'reject_expense', "تم رفض المصروف #$expense_id: $reason");
        
        $_SESSION['success'] = "تم رفض المصروف بنجاح";
        header("Location: expenses.php");
        exit();
        
    } catch (PDOException $e) {
        error_log('Reject Expense Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء رفض المصروف";
    }
}

// جلب جميع المصروفات
try {
    global $db;
    
    // جلب الحسابات للقائمة المنسدلة
    $stmt = $db->query("SELECT account_id, name FROM accounts WHERE type IN ('asset', 'bank')");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب المصروفات مع معلومات المستخدم
    $query = "SELECT e.*, u.username as paid_by_name, a.name as account_name 
              FROM expenses e 
              LEFT JOIN users u ON e.paid_by = u.user_id 
              LEFT JOIN accounts a ON e.account_id = a.account_id 
              ORDER BY e.expense_date DESC";
    
    $stmt = $db->query($query);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب إحصائيات المصروفات
    $stmt = $db->query("SELECT 
                        COUNT(*) as total_expenses,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_expenses,
                        SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_approved_amount,
                        SUM(amount) as total_amount
                        FROM expenses");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_expenses', 'عرض صفحة إدارة المصروفات');
    
} catch (PDOException $e) {
    error_log('Expenses Page Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - إدارة المصروفات</title>
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
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .total-icon { color: var(--primary-color); }
        .pending-icon { color: var(--warning-color); }
        .approved-icon { color: var(--success-color); }
        .amount-icon { color: var(--secondary-color); }
        
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
        
        .form-section, .expenses-section {
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
        
        .form-input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
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
            transition: background-color 0.3s ease;
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
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, .data-table td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        
        .data-table th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        .data-table tr:hover {
            background-color: rgba(0, 0, 0, 0.03);
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
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
            width: 400px;
            max-width: 90%;
        }
        
        .modal-title {
            font-size: 20px;
            margin-bottom: 15px;
        }
        
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
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
                    <div style="font-size: 12px; color: #777;">المحاسبة</div>
                </div>
            </div>
        </header>
        
        <h1 class="page-title">إدارة المصروفات</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
        <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_expenses'] ?? 0; ?></div>
                <div class="stat-label">إجمالي المصروفات</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $stats['pending_expenses'] ?? 0; ?></div>
                <div class="stat-label">مصروفات بانتظار الموافقة</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon approved-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_approved_amount'] ?? 0, 2); ?></div>
                <div class="stat-label">قيمة المصروفات المعتمدة</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon amount-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_amount'] ?? 0, 2); ?></div>
                <div class="stat-label">إجمالي القيمة</div>
            </div>
        </div>
        
        <div class="content-grid">
            <div class="form-section">
                <h2 class="section-title">إضافة مصروف جديد</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">المبلغ</label>
                        <input type="number" step="0.01" name="amount" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الوصف</label>
                        <input type="text" name="description" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">التصنيف</label>
                        <select name="category" class="form-input" required>
                            <option value="">اختر التصنيف</option>
                            <option value="office_supplies">لوازم مكتبية</option>
                            <option value="utilities">مرافق</option>
                            <option value="maintenance">صيانة</option>
                            <option value="transportation">مواصلات</option>
                            <option value="marketing">تسويق</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الحساب المدفوع منه</label>
                        <select name="account_id" class="form-input" required>
    <option value="">اختر الحساب</option>
    <?php foreach ($accounts as $account): ?>
    <option value="<?php echo $account['account_id']; ?>"><?php echo $account['name']; ?></option>
    <?php endforeach; ?>
</select>

                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">تاريخ الصرف</label>
                        <input type="date" name="expense_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">إثبات الصرف (اختياري)</label>
                        <input type="file" name="proof_file" class="form-input" accept="image/*,.pdf,.doc,.docx">
                    </div>
                    
                    <button type="submit" name="add_expense" class="btn" style="width: 100%;">
                        <i class="fas fa-plus"></i> إضافة المصروف
                    </button>
                </form>
            </div>
            
            <div class="expenses-section">
                <h2 class="section-title">قائمة المصروفات</h2>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الوصف</th>
                                <th>المبلغ</th>
                                <th>التصنيف</th>
                                <th>الحساب</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($expenses)): ?>
                                <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?php echo $expense['expense_date']; ?></td>
                                    <td><?php echo $expense['description']; ?></td>
                                    <td><?php echo number_format($expense['amount'], 2); ?></td>
                                    <td><?php echo $expense['category']; ?></td>
                                    <td><?php echo $expense['account_name']; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $expense['status']; ?>">
                                            <?php 
                                            if ($expense['status'] == 'pending') echo 'بانتظار الموافقة';
                                            elseif ($expense['status'] == 'approved') echo 'معتمدة';
                                            elseif ($expense['status'] == 'rejected') echo 'مرفوضة';
                                            else echo $expense['status']; 
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="expense_details.php?id=<?php echo $expense['expense_id']; ?>" class="btn" title="التفاصيل">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($_SESSION['role'] == 'admin' && $expense['status'] == 'pending'): ?>
                                            <a href="expenses.php?approve=<?php echo $expense['expense_id']; ?>" class="btn btn-success" title="موافقة">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <button onclick="openRejectModal(<?php echo $expense['expense_id']; ?>)" class="btn btn-danger" titleرفض">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">لا توجد مصروفات مسجلة</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal لرفض المصروف -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">رفض المصروف</h3>
            <p>يرجى كتابة سبب الرفض:</p>
            <input type="text" id="rejectReason" class="form-input" placeholder="سبب الرفض">
            <div class="modal-buttons">
                <button onclick="closeRejectModal()" class="btn btn-warning">إلغاء</button>
                <button onclick="confirmReject()" class="btn btn-danger">رفض</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentExpenseId = null;
        
        function openRejectModal(expenseId) {
            currentExpenseId = expenseId;
            document.getElementById('rejectModal').style.display = 'flex';
        }
        
        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
            document.getElementById('rejectReason').value = '';
            currentExpenseId = null;
        }
        
        function confirmReject() {
            const reason = document.getElementById('rejectReason').value;
            if (reason.trim() === '') {
                alert('يرجى كتابة سبب الرفض');
                return;
            }
            
            window.location.href = `expenses.php?reject=${currentExpenseId}&reason=${encodeURIComponent(reason)}`;
        }
        
        // إغلاق Modal عند النقر خارج المحتوى
        window.onclick = function(event) {
            const modal = document.getElementById('rejectModal');
            if (event.target === modal) {
                closeRejectModal();
            }
        }
    </script>
</body>
</html>