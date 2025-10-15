<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

define('INCLUDED', true);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// --- إضافة دالة time_elapsed_string ---
if (!function_exists('time_elapsed_string')) {
    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = [
            'y' => 'سنة',
            'm' => 'شهر',
            'w' => 'أسبوع',
            'd' => 'يوم',
            'h' => 'ساعة',
            'i' => 'دقيقة',
            's' => 'ثانية',
        ];
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? '' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' مضت' : 'الآن';
    }
}

// التحقق من وجود معرف العميل
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: customers.php");
    exit();
}

$customer_id = intval($_GET['id']);

// استعلام لجلب بيانات العميل
$stmt = $db->prepare("SELECT * FROM customers WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header("Location: customers.php?error=Customer not found");
    exit();
}

// جلب اسم المستخدم الذي أضاف العميل (إن وجد)
$created_by_name = "غير معروف";
if (isset($customer['created_by'])) {
    try {
        $user_stmt = $db->prepare("SELECT username FROM users WHERE user_id = ?");
        $user_stmt->execute([$customer['created_by']]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && isset($user['username'])) {
            $created_by_name = htmlspecialchars($user['username']);
        }
    } catch (PDOException $e) {
        error_log("Error fetching user info: " . $e->getMessage());
    }
}

// جلب إحصائيات العميل
$orders_count = 0;
$total_purchases = 0;
$last_order_text = 'لا يوجد طلبات';

try {
    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $orders_count = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT SUM(total_amount) FROM orders WHERE customer_id = ?");
    $stmt->execute([$customer_id]);
    $total_purchases = $stmt->fetchColumn() ?? 0;

    $stmt = $db->prepare("SELECT order_date FROM orders WHERE customer_id = ? ORDER BY order_date DESC LIMIT 1");
    $stmt->execute([$customer_id]);
    $last_order_date = $stmt->fetchColumn();
    $last_order_text = $last_order_date ? time_elapsed_string($last_order_date) : 'لا يوجد طلبات';
} catch (PDOException $e) {
    error_log("Error fetching customer stats: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل العميل | نظام إدارة العملاء</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
         :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4cc9f0;
            --warning-color: #f72585;
            --danger-color: #d32f2f;
        }
        
        .customer-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .customer-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
        }
        
        .customer-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 20px;
            font-size: 36px;
            color: var(--primary-color);
            font-weight: bold;
        }
        
        .customer-info {
            flex: 1;
        }
        
        .customer-name {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .customer-id {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .customer-body {
            padding: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .info-section {
            margin-bottom: 20px;
        }
        
        .info-section h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #e0e0e0;
            font-size: 18px;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 12px;
        }
        
        .info-label {
            font-weight: bold;
            width: 120px;
            color: #666;
        }
        
        .info-value {
            flex: 1;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-active {
            background-color: #e3faf2;
            color: #20c997;
        }
        
        .status-inactive {
            background-color: #fff3bf;
            color: #f08c00;
        }
        
        .vip-badge {
            background-color: #f3e5ff;
            color: #8a2be2;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn i {
            margin-left: 8px;
        }
        
        .btn-edit {
            background-color: var(--accent-color);
            color: white;
        }
        
        .btn-edit:hover {
            background-color: #3a7bd5;
        }
        
        .btn-delete {
            background-color: #ffebee;
            color: var(--danger-color);
        }
        
        .btn-delete:hover {
            background-color: #ffcdd2;
        }
        
        .btn-orders {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-orders:hover {
            background-color: #3ab7d8;
        }
        
        .btn-pdf {
            background-color: #e3f2fd;
            color: #1565c0;
        }
        
        .btn-pdf:hover {
            background-color: #bbdefb;
        }
        
        .document-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .document-link:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .customer-body {
                grid-template-columns: 1fr;
            }
            
            .customer-header {
                flex-direction: column;
                text-align: center;
            }
            
            .customer-avatar {
                margin-left: 0;
                margin-bottom: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* بقية الأنماط تبقى كما هي */
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <h1>تفاصيل العميل</h1>
                <?php include 'includes/user-menu.php'; ?>
            </div>
            
            <div class="customer-card">
                <div class="customer-header">
                    <div class="customer-avatar"><?php 
                     // دالة بديلة إذا لم تكن getInitials موجودة
    if (!function_exists('getInitials')) {
        function tempGetInitials($name) {
            $initials = '';
            $parts = explode(' ', $name);
            foreach ($parts as $part) {
                if (!empty($part)) {
                    $initials .= mb_substr($part, 0, 1, 'UTF-8');
                }
            }
            return $initials ?: '?';
        }
    }
    echo function_exists('getInitials') ? getInitials($customer['name']) : tempGetInitials($customer['name']);
                    ?></div>
                    <div class="customer-info">
                        <h2 class="customer-name">
                            <?php echo htmlspecialchars($customer['name']); ?>
                            <?php if (!empty($customer['is_vip']) && $customer['is_vip']): ?>
                                <span class="status-badge vip-badge">VIP</span>
                            <?php endif; ?>
                        </h2>
                        <div class="customer-id">رقم العميل: #<?php echo str_pad($customer['customer_id'], 5, '0', STR_PAD_LEFT); ?></div>
                    </div>
                </div>
                
                <div class="customer-body">
                    <div class="info-section">
                        <h3><i class="fas fa-info-circle"></i> المعلومات الأساسية</h3>
                        <div class="info-row">
                            <div class="info-label">رقم الهاتف:</div>
                            <div class="info-value"><?php echo htmlspecialchars($customer['phone']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">البريد الإلكتروني:</div>
                            <div class="info-value"><?php echo !empty($customer['email']) ? htmlspecialchars($customer['email']) : 'غير متوفر'; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">اسم الشركة:</div>
                            <div class="info-value"><?php echo !empty($customer['company_name']) ? htmlspecialchars($customer['company_name']) : 'غير متوفر'; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">تاريخ التسجيل:</div>
                            <div class="info-value"><?php echo date('Y/m/d', strtotime($customer['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h3><i class="fas fa-map-marker-alt"></i> معلومات العنوان</h3>
                        <div class="info-row">
                            <div class="info-label">العنوان:</div>
                            <div class="info-value"><?php echo !empty($customer['address']) ? htmlspecialchars($customer['address']) : 'غير متوفر'; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">إثبات الهوية:</div>
                            <div class="info-value">
                                <?php if (!empty($customer['id_proof_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($customer['id_proof_path']); ?>" class="document-link" target="_blank">
                                        <i class="fas fa-file-pdf"></i> عرض الوثيقة
                                    </a>
                                <?php else: ?>
                                    غير متوفر
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">أضيف بواسطة:</div>
                            <div class="info-value"><?php echo $created_by_name; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">آخر تحديث:</div>
                            <div class="info-value"><?php echo !empty($customer['updated_at']) ? date('Y/m/d H:i', strtotime($customer['updated_at'])) : 'لم يتم التحديث'; ?></div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h3><i class="fas fa-chart-line"></i> الإحصائيات</h3>
                        <div class="info-row">
                            <div class="info-label">عدد الطلبات:</div>
                            <div class="info-value"><?php echo $orders_count; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">إجمالي المشتريات:</div>
                            <div class="info-value"><?php echo number_format($total_purchases, 2); ?> د.ل</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">آخر طلب:</div>
                            <div class="info-value"><?php echo $last_order_text; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">ملاحظات:</div>
                            <div class="info-value"><?php echo !empty($customer['notes']) ? nl2br(htmlspecialchars($customer['notes'])) : 'لا توجد ملاحظات'; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="edit_customer.php?id=<?php echo $customer_id; ?>" class="btn btn-edit">
                    <i class="fas fa-edit"></i> تعديل البيانات
                </a>
                <a href="orders.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-orders">
                    <i class="fas fa-shopping-cart"></i> عرض الطلبات
                </a>
                <?php if (!empty($customer['id_proof_path'])): ?>
                <a href="<?php echo htmlspecialchars($customer['id_proof_path']); ?>" class="btn btn-pdf" target="_blank">
                    <i class="fas fa-file-pdf"></i> وثيقة الهوية
                </a>
                <?php endif; ?>
                <a href="#" class="btn btn-delete" onclick="confirmDelete(<?php echo $customer_id; ?>)">
                    <i class="fas fa-trash-alt"></i> حذف العميل
                </a>
            </div>
        </main>
    </div>
    
    <script>
        function confirmDelete(customerId) {
            if (confirm('هل أنت متأكد من رغبتك في حذف هذا العميل؟ سيتم حذف جميع البيانات المرتبطة به ولا يمكن التراجع عن هذه العملية.')) {
                window.location.href = 'delete_customer.php?id=' + customerId;
            }
        }
    </script>
</body>
</html>