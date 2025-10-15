<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (المحاسبة)
if ($_SESSION['role'] != 'accounting' && $_SESSION['role'] != 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// معالجة عمليات النموذج
$error = '';
$success = '';

// جلب الحسابات لاستخدامها في القيود
try {
    global $db;
    $stmt = $db->query("SELECT account_id, name, type FROM accounts ORDER BY type, name");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Accounts Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب بيانات الحسابات";
}

// جلب قيود اليومية
$entries = [];
$entry_details = [];
$selected_entry = null;

try {
    // جلب جميع قيود اليومية
    $stmt = $db->query("
        SELECT je.*, u.username as created_by_name 
        FROM journal_entries je 
        LEFT JOIN users u ON je.created_by = u.user_id 
        ORDER BY je.entry_date DESC, je.entry_id DESC
    ");
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إذا كان هناك معرف قيد محدد، جلب تفاصيله
    if (isset($_GET['view']) && is_numeric($_GET['view'])) {
        $entry_id = $_GET['view'];
        
        // جلب بيانات القيد
        $stmt = $db->prepare("
            SELECT je.*, u.username as created_by_name 
            FROM journal_entries je 
            LEFT JOIN users u ON je.created_by = u.user_id 
            WHERE je.entry_id = ?
        ");
        $stmt->execute([$entry_id]);
        $selected_entry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // جلب تفاصيل القيد
        if ($selected_entry) {
            $stmt = $db->prepare("
                SELECT jed.*, a.name as account_name, a.type as account_type 
                FROM journal_entry_details jed 
                LEFT JOIN accounts a ON jed.account_id = a.account_id 
                WHERE jed.entry_id = ?
            ");
            $stmt->execute([$entry_id]);
            $entry_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    // معالجة إنشاء قيد جديد
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_entry'])) {
        $entry_date = $_POST['entry_date'];
        $description = $_POST['description'];
        $debit_accounts = $_POST['debit_account'] ?? [];
        $credit_accounts = $_POST['credit_account'] ?? [];
        $debit_amounts = $_POST['debit_amount'] ?? [];
        $credit_amounts = $_POST['credit_amount'] ?? [];
        
        // التحقق من التوازن (المدين = الدائن)
        $total_debit = array_sum($debit_amounts);
        $total_credit = array_sum($credit_amounts);
        
        if (abs($total_debit - $total_credit) > 0.01) {
            $error = "القيد غير متوازن! المجموع المدين ($total_debit) لا يساوي المجموع الدائن ($total_credit)";
        } elseif (count($debit_accounts) == 0 || count($credit_accounts) == 0) {
            $error = "يجب إدخال مدين ودائن واحد على الأقل";
        } else {
            // بدء معاملة قاعدة البيانات
            $db->beginTransaction();
            
            try {
                // إدخال القيد الرئيسي
                $stmt = $db->prepare("
                    INSERT INTO journal_entries (entry_date, description, total_debit, total_credit, created_by, status) 
                    VALUES (?, ?, ?, ?, ?, 'draft')
                ");
                $stmt->execute([$entry_date, $description, $total_debit, $total_credit, $_SESSION['user_id']]);
                $entry_id = $db->lastInsertId();
                
                // إدخال التفاصيل (المدين)
                foreach ($debit_accounts as $index => $account_id) {
                    if (!empty($account_id) && !empty($debit_amounts[$index]) && $debit_amounts[$index] > 0) {
                        $stmt = $db->prepare("
                            INSERT INTO journal_entry_details (entry_id, account_id, debit, credit) 
                            VALUES (?, ?, ?, 0)
                        ");
                        $stmt->execute([$entry_id, $account_id, $debit_amounts[$index]]);
                    }
                }
                
                // إدخال التفاصيل (الدائن)
                foreach ($credit_accounts as $index => $account_id) {
                    if (!empty($account_id) && !empty($credit_amounts[$index]) && $credit_amounts[$index] > 0) {
                        $stmt = $db->prepare("
                            INSERT INTO journal_entry_details (entry_id, account_id, debit, credit) 
                            VALUES (?, ?, 0, ?)
                        ");
                        $stmt->execute([$entry_id, $account_id, $credit_amounts[$index]]);
                    }
                }
                
                // تسجيل النشاط
                logActivity($_SESSION['user_id'], 'add_journal_entry', "تم إنشاء قيد يومية جديد رقم $entry_id");
                
                $db->commit();
                $success = "تم إنشاء القيد بنجاح برقم $entry_id";
                
                // إعادة التوجيه لعرض القيد الجديد
                header("Location: journal_entries.php?view=$entry_id");
                exit();
                
            } catch (PDOException $e) {
                $db->rollBack();
                error_log('Journal Entry Error: ' . $e->getMessage());
                $error = "حدث خطأ في حفظ القيد: " . $e->getMessage();
            }
        }
    }
    
    // معالجة ترحيل القيد
    if (isset($_POST['post_entry']) && isset($_POST['entry_id'])) {
        $entry_id = $_POST['entry_id'];
        
        try {
            $db->beginTransaction();
            
            // تحديث حالة القيد إلى "مرحل"
            $stmt = $db->prepare("UPDATE journal_entries SET status = 'posted', posted_at = NOW() WHERE entry_id = ?");
            $stmt->execute([$entry_id]);
            
            // ترحيل القيد إلى الحسابات (هنا يمكن إضافة المنطق المناسب)
            
            // تسجيل النشاط
            logActivity($_SESSION['user_id'], 'post_journal_entry', "تم ترحيل قيد يومية رقم $entry_id");
            
            $db->commit();
            $success = "تم ترحيل القيد بنجاح";
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Post Entry Error: ' . $e->getMessage());
            $error = "حدث خطأ في ترحيل القيد: " . $e->getMessage();
        }
    }
    
} catch (PDOException $e) {
    error_log('Journal Entries Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب بيانات قيود اليومية";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - قيود اليومية</title>
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
        
        .dashboard-title {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--secondary-color);
            text-align: center;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }
        
        .tab {
            padding: 15px 20px;
            cursor: pointer;
            flex: 1;
            text-align: center;
            transition: background 0.3s;
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
        
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
            color: var(--secondary-color);
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
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-draft {
            background-color: var(--warning-color);
            color: white;
        }
        
        .status-posted {
            background-color: var(--success-color);
            color: white;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        .debit-credit-table {
            width: 100%;
            margin-bottom: 20px;
        }
        
        .debit-credit-table th {
            background-color: var(--light-color);
        }
        
        .debit-header {
            background-color: #ffebee !important;
        }
        
        .credit-header {
            background-color: #e8f5e9 !important;
        }
        
        .debit-row {
            background-color: #fff5f5;
        }
        
        .credit-row {
            background-color: #f5fff5;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
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
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .alert-error {
            background-color: #ffebee;
            color: #d32f2f;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .entry-details {
            margin-top: 20px;
        }
        
        .entry-summary {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px;
            background-color: var(--light-color);
            border-radius: var(--border-radius);
        }
        
        .summary-item {
            text-align: center;
        }
        
        .summary-value {
            font-size: 18px;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
            }
            
            .entry-summary {
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
                    <div style="font-size: 12px; color: #777;">محاسبة</div>
                </div>
            </div>
        </header>
        
        <h1 class="dashboard-title">إدارة قيود اليومية</h1>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('entries-list')">سجل القيود</div>
            <div class="tab" onclick="switchTab('add-entry')">تسجيل قيد جديد</div>
            <?php if ($selected_entry): ?>
            <div class="tab" onclick="switchTab('view-entry')">عرض القيد</div>
            <?php endif; ?>
        </div>
        
        <!-- قائمة قيود اليومية -->
        <div id="entries-list" class="tab-content active">
            <div class="card">
                <h2 class="section-title">قيود اليومية</h2>
                
                <table>
                    <thead>
                        <tr>
                            <th>رقم القيد</th>
                            <th>التاريخ</th>
                            <th>الوصف</th>
                            <th>المدين</th>
                            <th>الدائن</th>
                            <th>الحالة</th>
                            <th>تم الإنشاء بواسطة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($entries)): ?>
                            <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td><?php echo $entry['entry_id']; ?></td>
                                <td><?php echo $entry['entry_date']; ?></td>
                                <td><?php echo $entry['description']; ?></td>
                                <td><?php echo number_format($entry['total_debit'], 2); ?></td>
                                <td><?php echo number_format($entry['total_credit'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $entry['status']; ?>">
                                        <?php echo $entry['status'] == 'draft' ? 'مسودة' : 'مرحل'; ?>
                                    </span>
                                </td>
                                <td><?php echo $entry['created_by_name']; ?></td>
                                <td>
                                    <a href="journal_entries.php?view=<?php echo $entry['entry_id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> عرض
                                    </a>
                                    <?php if ($entry['status'] == 'draft'): ?>
                                    <a href="journal_entries.php?edit=<?php echo $entry['entry_id']; ?>" class="btn btn-warning">
                                        <i class="fas fa-edit"></i> تعديل
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">لا توجد قيود مسجلة</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- إضافة قيد جديد -->
        <div id="add-entry" class="tab-content">
            <div class="card">
                <h2 class="section-title">تسجيل قيد يومية جديد</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="add_entry" value="1">
                    
                    <div class="form-group">
                        <label for="entry_date">تاريخ القيد:</label>
                        <input type="date" id="entry_date" name="entry_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">وصف القيد:</label>
                        <textarea id="description" name="description" rows="3" required></textarea>
                    </div>
                    
                    <h3>تفاصيل القيد (المدين)</h3>
                    <table class="debit-credit-table">
                        <thead>
                            <tr>
                                <th class="debit-header">الحساب</th>
                                <th class="debit-header">المبلغ</th>
                                <th class="debit-header">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody id="debit-rows">
                            <tr class="debit-row">
                                <td>
                                    <select name="debit_account[]" required>
                                        <option value="">اختر الحساب</option>
                                        <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo $account['account_id']; ?>">
                                            <?php echo $account['name']; ?> (<?php echo $account['type']; ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="debit_amount[]" step="0.01" min="0" required>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger" onclick="removeRow(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3">
                                    <button type="button" class="btn btn-primary" onclick="addDebitRow()">
                                        <i class="fas fa-plus"></i> إضافة مدين
                                    </button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <h3>تفاصيل القيد (الدائن)</h3>
                    <table class="debit-credit-table">
                        <thead>
                            <tr>
                                <th class="credit-header">الحساب</th>
                                <th class="credit-header">المبلغ</th>
                                <th class="credit-header">الإجراء</th>
                            </tr>
                        </thead>
                        <tbody id="credit-rows">
                            <tr class="credit-row">
                                <td>
                                    <select name="credit_account[]" required>
                                        <option value="">اختر الحساب</option>
                                        <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo $account['account_id']; ?>">
                                            <?php echo $account['name']; ?> (<?php echo $account['type']; ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="credit_amount[]" step="0.01" min="0" required>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger" onclick="removeRow(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3">
                                    <button type="button" class="btn btn-primary" onclick="addCreditRow()">
                                        <i class="fas fa-plus"></i> إضافة دائن
                                    </button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> حفظ القيد
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- عرض القيد المحدد -->
        <?php if ($selected_entry): ?>
        <div id="view-entry" class="tab-content">
            <div class="card">
                <h2 class="section-title">تفاصيل القيد رقم <?php echo $selected_entry['entry_id']; ?></h2>
                
                <div class="entry-summary">
                    <div class="summary-item">
                        <div class="summary-label">التاريخ</div>
                        <div class="summary-value"><?php echo $selected_entry['entry_date']; ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">الحالة</div>
                        <div class="summary-value">
                            <span class="status-badge status-<?php echo $selected_entry['status']; ?>">
                                <?php echo $selected_entry['status'] == 'draft' ? 'مسودة' : 'مرحل'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">المجموع المدين</div>
                        <div class="summary-value"><?php echo number_format($selected_entry['total_debit'], 2); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">المجموع الدائن</div>
                        <div class="summary-value"><?php echo number_format($selected_entry['total_credit'], 2); ?></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>وصف القيد:</label>
                    <p><?php echo $selected_entry['description']; ?></p>
                </div>
                
                <h3>تفاصيل القيد</h3>
                <table>
                    <thead>
                        <tr>
                            <th>الحساب</th>
                            <th>نوع الحساب</th>
                            <th>مدين</th>
                            <th>دائن</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($entry_details)): ?>
                            <?php foreach ($entry_details as $detail): ?>
                            <tr>
                                <td><?php echo $detail['account_name']; ?></td>
                                <td><?php echo $detail['account_type']; ?></td>
                                <td><?php echo number_format($detail['debit'], 2); ?></td>
                                <td><?php echo number_format($detail['credit'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">لا توجد تفاصيل للقيد</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="form-group" style="margin-top: 20px;">
                    <?php if ($selected_entry['status'] == 'draft'): ?>
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="entry_id" value="<?php echo $selected_entry['entry_id']; ?>">
                        <button type="submit" name="post_entry" class="btn btn-success">
                            <i class="fas fa-check"></i> ترحيل القيد
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <a href="journal_entries.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> العودة للقائمة
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function switchTab(tabId) {
            // إخفاء جميع محتويات التبويبات
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // إظهار المحتوى المحدد
            document.getElementById(tabId).classList.add('active');
            
            // تحديث التبويبات النشطة
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // لا يمكننا تفعيل التبويب المناسب مباشرة لأنه ليس لدينا معرف التبويب
            // لذلك سنقوم بتفعيله يدويًا في كل حالة
            if (tabId === 'entries-list') {
                document.querySelectorAll('.tab')[0].classList.add('active');
            } else if (tabId === 'add-entry') {
                document.querySelectorAll('.tab')[1].classList.add('active');
            } else if (tabId === 'view-entry') {
                document.querySelectorAll('.tab')[2].classList.add('active');
            }
        }
        
        function addDebitRow() {
            const tbody = document.getElementById('debit-rows');
            const newRow = tbody.rows[0].cloneNode(true);
            
            // مسح القيم من الحقول الجديدة
            newRow.querySelector('select').selectedIndex = 0;
            newRow.querySelector('input').value = '';
            
            tbody.appendChild(newRow);
        }
        
        function addCreditRow() {
            const tbody = document.getElementById('credit-rows');
            const newRow = tbody.rows[0].cloneNode(true);
            
            // مسح القيم من الحقول الجديدة
            newRow.querySelector('select').selectedIndex = 0;
            newRow.querySelector('input').value = '';
            
            tbody.appendChild(newRow);
        }
        
        function removeRow(button) {
            const row = button.closest('tr');
            if (row.parentElement.rows.length > 1) {
                row.remove();
            }
        }
        
        // إذا كان هناك خطأ في النموذج، نفتح تبويب الإضافة تلقائيًا
        <?php if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_entry']) && !empty($error)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            switchTab('add-entry');
        });
        <?php endif; ?>
    </script>
</body>
</html>