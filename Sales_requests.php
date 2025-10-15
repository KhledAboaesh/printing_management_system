<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (المحاسبة أو المبيعات)
if ($_SESSION['role'] != 'accounting' && $_SESSION['role'] != 'sales' && $_SESSION['role'] != 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// معالجة العمليات
$action = $_GET['action'] ?? '';
$order_id = $_GET['id'] ?? 0;

if ($action == 'convert_to_invoice' && $order_id > 0) {
    // تحويل الطلب إلى فاتورة
    try {
        $db->beginTransaction();
        
        // جلب بيانات الطلب
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // إنشاء الفاتورة
            $stmt = $db->prepare("INSERT INTO invoices (order_id, customer_id, invoice_date, total_amount, status, created_by) 
                                 VALUES (?, ?, NOW(), ?, 'pending', ?)");
            $stmt->execute([$order_id, $order['customer_id'], $order['total_amount'], $_SESSION['user_id']]);
            $invoice_id = $db->lastInsertId();
            
            // جلب بنود الطلب
            $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // إضافة بنود الفاتورة
            foreach ($order_items as $item) {
                $stmt = $db->prepare("INSERT INTO invoice_items (invoice_id, product_id, quantity, unit_price, total_price) 
                                     VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$invoice_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['total_price']]);
            }
            
            // تحديث حالة الطلب
            $stmt = $db->prepare("UPDATE orders SET status = 'converted' WHERE order_id = ?");
            $stmt->execute([$order_id]);
            
            $db->commit();
            
            // تسجيل النشاط
            logActivity($_SESSION['user_id'], 'convert_to_invoice', "تحويل الطلب #$order_id إلى فاتورة #$invoice_id");
            
            $_SESSION['success'] = "تم تحويل الطلب إلى فاتورة بنجاح";
        }
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('Convert to Invoice Error: ' . $e->getMessage());
        $_SESSION['error'] = "حدث خطأ أثناء تحويل الطلب إلى فاتورة";
    }
    
    header("Location: orders_sales.php");
    exit();
}

// جلب بيانات الطلبات
try {
    global $db;
    
    // عوامل التصفية
    $status_filter = $_GET['status'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    $query = "SELECT o.*, c.name as customer_name, u.username as created_by_name 
              FROM orders o 
              LEFT JOIN customers c ON o.customer_id = c.customer_id 
              LEFT JOIN users u ON o.created_by = u.user_id 
              WHERE 1=1";
    
    $params = [];
    
    if (!empty($status_filter)) {
        $query .= " AND o.status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($date_from)) {
        $query .= " AND o.order_date >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $query .= " AND o.order_date <= ?";
        $params[] = $date_to;
    }
    
    $query .= " ORDER BY o.order_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب إحصائيات المبيعات
    $stmt = $db->query("SELECT COUNT(*) as total_orders, 
                               SUM(total_amount) as total_sales,
                               AVG(total_amount) as avg_order_value
                        FROM orders 
                        WHERE status != 'cancelled'");
    $sales_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // جلب عدد الطلبات حسب الحالة
    $stmt = $db->query("SELECT status, COUNT(*) as count 
                        FROM orders 
                        GROUP BY status");
    $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_orders_sales', 'عرض صفحة الطلبات والمبيعات');
    
} catch (PDOException $e) {
    error_log('Orders Sales Page Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - الطلبات والمبيعات</title>
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
        
        .orders-icon { color: var(--primary-color); }
        .sales-icon { color: var(--success-color); }
        .avg-icon { color: var(--warning-color); }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #777;
            font-size: 16px;
        }
        
        .filters {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
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
        
        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .table-container {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            overflow-x: auto;
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
            font-weight: 700;
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
            background-color: #ffeaa7;
            color: #d35400;
        }
        
        .status-processing {
            background-color: #81ecec;
            color: #00cec9;
        }
        
        .status-completed {
            background-color: #55efc4;
            color: #00b894;
        }
        
        .status-cancelled {
            background-color: #fab1a0;
            color: #d63031;
        }
        
        .status-converted {
            background-color: #74b9ff;
            color: #0984e3;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 14px;
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
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .filters form {
                grid-template-columns: 1fr;
            }
            
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
                    <div style="font-size: 12px; color: #777;"><?php echo $_SESSION['role'] ?? 'دور غير محدد'; ?></div>
                </div>
            </div>
        </header>
        
        <h1 class="page-title">إدارة الطلبات والمبيعات</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon orders-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-value"><?php echo $sales_stats['total_orders'] ?? 0; ?></div>
                <div class="stat-label">إجمالي الطلبات</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon sales-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value"><?php echo number_format($sales_stats['total_sales'] ?? 0, 2); ?> د.ل</div>
                <div class="stat-label">إجمالي المبيعات</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon avg-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="stat-value"><?php echo number_format($sales_stats['avg_order_value'] ?? 0, 2); ?> د.ل</div>
                <div class="stat-label">متوسط قيمة الطلب</div>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" action="">
                <div class="form-group">
                    <label for="status">حالة الطلب</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">جميع الحالات</option>
                        <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>قيد الانتظار</option>
                        <option value="processing" <?php echo ($status_filter == 'processing') ? 'selected' : ''; ?>>قيد المعالجة</option>
                        <option value="completed" <?php echo ($status_filter == 'completed') ? 'selected' : ''; ?>>مكتمل</option>
                        <option value="cancelled" <?php echo ($status_filter == 'cancelled') ? 'selected' : ''; ?>>ملغي</option>
                        <option value="converted" <?php echo ($status_filter == 'converted') ? 'selected' : ''; ?>>تم تحويله إلى فاتورة</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="date_from">من تاريخ</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_to">إلى تاريخ</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">تصفية</button>
                    <a href="orders_sales.php" class="btn btn-danger">إعادة تعيين</a>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>العميل</th>
                        <th>تاريخ الطلب</th>
                        <th>المبلغ الإجمالي</th>
                        <th>الحالة</th>
                        <th>تم الإنشاء بواسطة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo $order['order_id']; ?></td>
                            <td><?php echo $order['customer_name']; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($order['order_date'])); ?></td>
                            <td><?php echo number_format($order['total_amount'], 2); ?> د.ل</td>
                            <td>
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php 
                                    $status_labels = [
                                        'pending' => 'قيد الانتظار',
                                        'processing' => 'قيد المعالجة',
                                        'completed' => 'مكتمل',
                                        'cancelled' => 'ملغي',
                                        'converted' => 'تم التحويل إلى فاتورة'
                                    ];
                                    echo $status_labels[$order['status']] ?? $order['status']; 
                                    ?>
                                </span>
                            </td>
                            <td><?php echo $order['created_by_name']; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> تفاصيل
                                    </a>
                                    
                                    <?php if ($order['status'] == 'completed' || $order['status'] == 'processing'): ?>
                                        <a href="orders_sales.php?action=convert_to_invoice&id=<?php echo $order['order_id']; ?>" 
                                           class="btn btn-success btn-sm"
                                           onclick="return confirm('هل أنت متأكد من تحويل هذا الطلب إلى فاتورة؟')">
                                            <i class="fas fa-file-invoice"></i> تحويل إلى فاتورة
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">لا توجد طلبات</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // تحديد تاريخ اليوم كحد أقصى لحقل التاريخ
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date_from').max = today;
            document.getElementById('date_to').max = today;
        });
    </script>
</body>
</html>