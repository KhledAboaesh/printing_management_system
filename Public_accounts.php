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

// معالجة عمليات الإضافة والتعديل والحذف
$message = '';
$error = '';

// عملية إضافة حساب جديد
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_account'])) {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $description = trim($_POST['description']);
    
    try {
        global $db;
        $stmt = $db->prepare("INSERT INTO accounts (name, type, description) VALUES (?, ?, ?)");
        $stmt->execute([$name, $type, $description]);
        
        $message = "تم إضافة الحساب بنجاح";
        logActivity($_SESSION['user_id'], 'add_account', "إضافة حساب جديد: $name");
    } catch (PDOException $e) {
        error_log('Add Account Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء إضافة الحساب";
    }
}

// عملية تعديل حساب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_account'])) {
    $account_id = $_POST['account_id'];
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $description = trim($_POST['description']);
    
    try {
        global $db;
        $stmt = $db->prepare("UPDATE accounts SET name = ?, type = ?, description = ? WHERE account_id = ?");
        $stmt->execute([$name, $type, $description, $account_id]);
        
        $message = "تم تعديل الحساب بنجاح";
        logActivity($_SESSION['user_id'], 'edit_account', "تعديل حساب: $name");
    } catch (PDOException $e) {
        error_log('Edit Account Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء تعديل الحساب";
    }
}

// عملية حذف حساب
if (isset($_GET['delete'])) {
    $account_id = $_GET['delete'];
    
    try {
        global $db;
        
        // التحقق من عدم وجود معاملات مرتبطة بالحساب
        $stmt = $db->prepare("SELECT COUNT(*) FROM journal_entry_details WHERE account_id = ?");
        $stmt->execute([$account_id]);
        $has_transactions = $stmt->fetchColumn();
        
        if ($has_transactions > 0) {
            $error = "لا يمكن حذف الحساب لأنه مرتبط بمعاملات مالية";
        } else {
            $stmt = $db->prepare("DELETE FROM accounts WHERE account_id = ?");
            $stmt->execute([$account_id]);
            
            $message = "تم حذف الحساب بنجاح";
            logActivity($_SESSION['user_id'], 'delete_account', "حذف حساب: $account_id");
        }
    } catch (PDOException $e) {
        error_log('Delete Account Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء حذف الحساب";
    }
}

// جلب جميع الحسابات
try {
    global $db;
    $stmt = $db->query("SELECT * FROM accounts ORDER BY type, name");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تجميع الحسابات حسب النوع
    $accounts_by_type = [
        'asset' => [],
        'liability' => [],
        'equity' => [],
        'revenue' => [],
        'expense' => []
    ];
    
    foreach ($accounts as $account) {
        $accounts_by_type[$account['type']][] = $account;
    }
    
    // جلب إحصائيات الحسابات
    $stmt = $db->query("SELECT type, COUNT(*) as count FROM accounts GROUP BY type");
    $account_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_accounts', 'عرض صفحة الحسابات العامة');
    
} catch (PDOException $e) {
    error_log('Accounts Page Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}

// جلب بيانات حساب للتعديل
$edit_account = null;
if (isset($_GET['edit'])) {
    $account_id = $_GET['edit'];
    
    try {
        global $db;
        $stmt = $db->prepare("SELECT * FROM accounts WHERE account_id = ?");
        $stmt->execute([$account_id]);
        $edit_account = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Edit Account Fetch Error: ' . $e->getMessage());
        $error = "حدث خطأ في جلب بيانات الحساب";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - الحسابات العامة</title>
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 15px;
            box-shadow: var(--box-shadow);
            text-align: center;
        }
        
        .stat-icon {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .asset-icon { color: #3498db; }
        .liability-icon { color: #e74c3c; }
        .equity-icon { color: #2ecc71; }
        .revenue-icon { color: #f39c12; }
        .expense-icon { color: #9b59b6; }
        
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
        
        .form-section, .accounts-section {
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
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        .account-type-section {
            margin-bottom: 30px;
        }
        
        .type-title {
            font-size: 18px;
            margin-bottom: 15px;
            color: var(--secondary-color);
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
        
        .account-card {
            background-color: var(--light-color);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .account-info {
            flex-grow: 1;
        }
        
        .account-name {
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .account-desc {
            color: #777;
            font-size: 14px;
        }
        
        .account-actions {
            display: flex;
            gap: 10px;
        }
        
        .message {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #777;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            
            .account-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .account-actions {
                align-self: flex-end;
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
        
        <h1 class="page-title">إدارة الحسابات العامة</h1>
        
        <?php if (!empty($message)): ?>
        <div class="message success">
            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="message error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <?php
            $type_names = [
                'asset' => 'الأصول',
                'liability' => 'الالتزامات',
                'equity' => 'حقوق الملكية',
                'revenue' => 'الإيرادات',
                'expense' => 'المصروفات'
            ];
            
            $type_icons = [
                'asset' => 'fa-landmark',
                'liability' => 'fa-hand-holding-usd',
                'equity' => 'fa-chart-line',
                'revenue' => 'fa-money-bill-wave',
                'expense' => 'fa-file-invoice-dollar'
            ];
            
            foreach ($account_stats as $stat): 
                $count = $stat['count'];
                $type = $stat['type'];
            ?>
            <div class="stat-card">
                <div class="stat-icon <?php echo $type; ?>-icon">
                    <i class="fas <?php echo $type_icons[$type]; ?>"></i>
                </div>
                <div class="stat-value"><?php echo $count; ?></div>
                <div class="stat-label"><?php echo $type_names[$type]; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="content-grid">
            <div class="form-section">
                <h2 class="section-title"><?php echo isset($edit_account) ? 'تعديل حساب' : 'إضافة حساب جديد'; ?></h2>
                
                <form method="POST" action="">
                    <?php if (isset($edit_account)): ?>
                    <input type="hidden" name="account_id" value="<?php echo $edit_account['account_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">اسم الحساب</label>
                        <input type="text" class="form-input" name="name" 
                               value="<?php echo isset($edit_account) ? $edit_account['name'] : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">نوع الحساب</label>
                        <select class="form-select" name="type" required>
                            <option value="">اختر نوع الحساب</option>
                            <option value="asset" <?php echo (isset($edit_account) && $edit_account['type'] == 'asset') ? 'selected' : ''; ?>>أصل</option>
                            <option value="liability" <?php echo (isset($edit_account) && $edit_account['type'] == 'liability') ? 'selected' : ''; ?>>التزام</option>
                            <option value="equity" <?php echo (isset($edit_account) && $edit_account['type'] == 'equity') ? 'selected' : ''; ?>>حق ملكية</option>
                            <option value="revenue" <?php echo (isset($edit_account) && $edit_account['type'] == 'revenue') ? 'selected' : ''; ?>>إيراد</option>
                            <option value="expense" <?php echo (isset($edit_account) && $edit_account['type'] == 'expense') ? 'selected' : ''; ?>>مصروف</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">وصف الحساب (اختياري)</label>
                        <textarea class="form-textarea" name="description"><?php echo isset($edit_account) ? $edit_account['description'] : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <?php if (isset($edit_account)): ?>
                            <button type="submit" name="edit_account" class="btn btn-primary">تعديل الحساب</button>
                            <a href="accounts.php" class="btn btn-danger">إلغاء</a>
                        <?php else: ?>
                            <button type="submit" name="add_account" class="btn btn-primary">إضافة الحساب</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div class="accounts-section">
                <h2 class="section-title">قائمة الحسابات</h2>
                
                <?php if (empty($accounts)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice"></i>
                    <p>لا توجد حسابات مسجلة بعد</p>
                </div>
                <?php else: ?>
                    <?php foreach ($accounts_by_type as $type => $type_accounts): ?>
                        <?php if (!empty($type_accounts)): ?>
                        <div class="account-type-section">
                            <h3 class="type-title"><?php echo $type_names[$type]; ?></h3>
                            
                            <?php foreach ($type_accounts as $account): ?>
                            <div class="account-card">
                                <div class="account-info">
                                    <div class="account-name"><?php echo $account['name']; ?></div>
                                    <?php if (!empty($account['description'])): ?>
                                    <div class="account-desc"><?php echo $account['description']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="account-actions">
                                    <a href="accounts.php?edit=<?php echo $account['account_id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="accounts.php?delete=<?php echo $account['account_id']; ?>" class="btn btn-danger" 
                                       onclick="return confirm('هل أنت متأكد من حذف هذا الحساب؟');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>