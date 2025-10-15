<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    echo "يجب تسجيل الدخول للوصول لهذه الصفحة.";
    exit();
}

// التحقق من صلاحيات المستخدم (المحاسبة أو المدير)
// if ($_SESSION['role'] != 'accounting' && $_SESSION['role'] != 'admin') {
//     echo "ليس لديك صلاحية للوصول لهذه الصفحة.";
//     exit();
// }

// التحقق من وجود معرف القيد في الرابط
if (!isset($_GET['entry_id']) || empty($_GET['entry_id'])) {
    echo "رقم القيد غير موجود.";
    exit();
}

$entry_id = intval($_GET['entry_id']);

try {
    global $db;

    // جلب بيانات القيد الرئيسي
    $stmt = $db->prepare("
        SELECT je.*, u.username AS created_by_name 
        FROM journal_entries je
        LEFT JOIN users u ON je.created_by = u.user_id
        WHERE je.entry_id = ?
    ");
    $stmt->execute([$entry_id]);
    $journal_entry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$journal_entry) {
        echo "القيد المطلوب غير موجود.";
        exit();
    }

    // جلب تفاصيل القيد
    $stmt = $db->prepare("
        SELECT jed.*, a.name AS account_name, a.type AS account_type
        FROM journal_entry_details jed
        LEFT JOIN accounts a ON jed.account_id = a.account_id
        WHERE jed.entry_id = ?
        ORDER BY jed.debit DESC, jed.credit DESC
    ");
    $stmt->execute([$entry_id]);
    $entry_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // حساب إجمالي المدين والدائن
    $total_debit = 0;
    $total_credit = 0;
    foreach ($entry_details as $detail) {
        $total_debit += floatval($detail['debit']);
        $total_credit += floatval($detail['credit']);
    }

    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_journal_entry', 'عرض تفاصيل قيد يومية رقم: ' . $entry_id);

} catch (PDOException $e) {
    error_log('Journal Entry Details Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب بيانات القيد";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - تفاصيل قيد اليومية</title>
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
        
        .breadcrumb {
            background-color: white;
            padding: 15px 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .breadcrumb span {
            color: #777;
        }
        
        .entry-header {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .header-item {
            display: flex;
            flex-direction: column;
        }
        
        .header-label {
            font-size: 14px;
            color: #777;
            margin-bottom: 5px;
        }
        
        .header-value {
            font-size: 16px;
            font-weight: 500;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .status-posted {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-draft {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        
        .entry-details {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            overflow-x: auto;
        }
        
        .details-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .details-table th,
        .details-table td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }
        
        .details-table th {
            background-color: #f5f5f5;
            font-weight: 600;
        }
        
        .details-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .debit-amount {
            color: var(--success-color);
            font-weight: 500;
        }
        
        .credit-amount {
            color: var(--danger-color);
            font-weight: 500;
        }
        
        .accounting-summary {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .summary-item {
            text-align: center;
            padding: 15px;
            border-radius: var(--border-radius);
        }
        
        .summary-debit {
            background-color: #e8f5e9;
        }
        
        .summary-credit {
            background-color: #ffebee;
        }
        
        .summary-balanced {
            background-color: #e3f2fd;
        }
        
        .summary-label {
            font-size: 14px;
            margin-bottom: 5px;
            color: #555;
        }
        
        .summary-value {
            font-size: 20px;
            font-weight: 700;
        }
        
        .balanced {
            color: var(--primary-color);
        }
        
        .not-balanced {
            color: var(--danger-color);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-start;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
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
        
        .btn-secondary {
            background-color: #e0e0e0;
            color: #333;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .description-box {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .entry-header {
                grid-template-columns: 1fr;
            }
            
            .accounting-summary {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .details-table {
                font-size: 14px;
            }
            
            .details-table th,
            .details-table td {
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
                    <div style="font-size: 12px; color: #777;">محاسبة</div>
                </div>
            </div>
        </header>
        
        <div class="breadcrumb">
            <a href="dashboard.php">الرئيسية</a> / 
            <a href="journal_entries.php">قيود اليومية</a> / 
            <span>تفاصيل القيد</span>
        </div>
        
        <h1 class="page-title">تفاصيل قيد اليومية</h1>
        
        <?php if (isset($error)): ?>
        <div style="background-color: #ffebee; color: #d32f2f; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="entry-header">
            <div class="header-item">
                <span class="header-label">رقم القيد</span>
                <span class="header-value">#<?php echo $journal_entry['entry_id']; ?></span>
            </div>
            
            <div class="header-item">
                <span class="header-label">تاريخ القيد</span>
                <span class="header-value"><?php echo $journal_entry['entry_date']; ?></span>
            </div>
            
            <div class="header-item">
                <span class="header-label">الحالة</span>
                <span class="header-value">
                    <span class="status-badge <?php echo $journal_entry['status'] == 'posted' ? 'status-posted' : 'status-draft'; ?>">
                        <?php echo $journal_entry['status'] == 'posted' ? 'مرحل' : 'مسودة'; ?>
                    </span>
                </span>
            </div>
            
            <div class="header-item">
                <span class="header-label">تم الإنشاء بواسطة</span>
                <span class="header-value"><?php echo $journal_entry['created_by_name']; ?></span>
            </div>
        </div>
        
        <div class="entry-details">
            <h2 style="margin-bottom: 20px;">تفاصيل الحسابات</h2>
            
            <table class="details-table">
                <thead>
                    <tr>
                        <th>الحساب</th>
                        <th>نوع الحساب</th>
                        <th>الوصف</th>
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
                            <td><?php echo $detail['description'] ?: '---'; ?></td>
                            <td class="debit-amount">
                                <?php echo $detail['debit'] > 0 ? number_format($detail['debit'], 2) : '---'; ?>
                            </td>
                            <td class="credit-amount">
                                <?php echo $detail['credit'] > 0 ? number_format($detail['credit'], 2) : '---'; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px;">لا توجد تفاصيل لهذا القيد</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="accounting-summary">
            <div class="summary-item summary-debit">
                <div class="summary-label">إجمالي المدين</div>
                <div class="summary-value debit-amount"><?php echo number_format($total_debit, 2); ?></div>
            </div>
            
            <div class="summary-item summary-credit">
                <div class="summary-label">إجمالي الدائن</div>
                <div class="summary-value credit-amount"><?php echo number_format($total_credit, 2); ?></div>
            </div>
            
            <div class="summary-item summary-balanced">
                <div class="summary-label">حالة التوازن</div>
                <div class="summary-value <?php echo $total_debit == $total_credit ? 'balanced' : 'not-balanced'; ?>">
                    <?php 
                    if ($total_debit == $total_credit) {
                        echo 'متوازن';
                    } else {
                        echo 'غير متوازن (' . number_format(abs($total_debit - $total_credit), 2) . ')';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($journal_entry['description'])): ?>
        <div class="description-box">
            <h3 style="margin-bottom: 10px;">وصف القيد:</h3>
            <p><?php echo $journal_entry['description']; ?></p>
        </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <a href="journal_entries.php" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> العودة إلى القيود
            </a>
            
            <?php if ($journal_entry['status'] == 'draft' && $_SESSION['role'] == 'admin'): ?>
            <a href="post_journal_entry.php?entry_id=<?php echo $entry_id; ?>" class="btn btn-primary">
                <i class="fas fa-check-circle"></i> ترحيل القيد
            </a>
            
            <a href="edit_journal_entry.php?entry_id=<?php echo $entry_id; ?>" class="btn btn-secondary">
                <i class="fas fa-edit"></i> تعديل القيد
            </a>
            
            <a href="delete_journal_entry.php?entry_id=<?php echo $entry_id; ?>" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا القيد؟');">
                <i class="fas fa-trash"></i> حذف القيد
            </a>
            <?php endif; ?>
            
            <a href="print_journal_entry.php?entry_id=<?php echo $entry_id; ?>" class="btn btn-secondary" target="_blank">
                <i class="fas fa-print"></i> طباعة
            </a>
        </div>
    </div>
</body>

</html>