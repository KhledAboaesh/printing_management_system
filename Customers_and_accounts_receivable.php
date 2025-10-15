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
$action = $_GET['action'] ?? '';
$customer_id = $_GET['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        global $db;
        
        if ($action == 'add') {
            // إضافة عميل جديد
            $stmt = $db->prepare("INSERT INTO customers (name, phone, email, address, company_name, tax_number, created_by) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['name'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['address'],
                $_POST['company_name'],
                $_POST['tax_number'],
                $_SESSION['user_id']
            ]);
            
            $customer_id = $db->lastInsertId();
            logActivity($_SESSION['user_id'], 'add_customer', 'تم إضافة عميل جديد: ' . $_POST['name']);
            $success = "تم إضافة العميل بنجاح";
            
        } elseif ($action == 'edit' && $customer_id > 0) {
            // تعديل بيانات العميل
            $stmt = $db->prepare("UPDATE customers SET name=?, phone=?, email=?, address=?, company_name=?, tax_number=? 
                                  WHERE customer_id=?");
            $stmt->execute([
                $_POST['name'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['address'],
                $_POST['company_name'],
                $_POST['tax_number'],
                $customer_id
            ]);
            
            logActivity($_SESSION['user_id'], 'edit_customer', 'تم تعديل بيانات العميل: ' . $_POST['name']);
            $success = "تم تعديل بيانات العميل بنجاح";
            
        } elseif ($action == 'delete' && $customer_id > 0) {
            // حذف العميل
            $stmt = $db->prepare("DELETE FROM customers WHERE customer_id = ?");
            $stmt->execute([$customer_id]);
            
            logActivity($_SESSION['user_id'], 'delete_customer', 'تم حذف العميل: ' . $customer_id);
            $success = "تم حذف العميل بنجاح";
        }
    } catch (PDOException $e) {
        error_log('Customer Management Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء عملية الحفظ: " . $e->getMessage();
    }
}

// جلب بيانات العملاء
try {
    global $db;
    
    // جلب جميع العملاء
    $stmt = $db->query("
        SELECT c.*, u.username as created_by_name, 
               (SELECT COUNT(*) FROM invoices i WHERE i.customer_id = c.customer_id) as invoice_count,
               (SELECT SUM(total_amount - paid_amount) FROM invoices i WHERE i.customer_id = c.customer_id) as total_debt
        FROM customers c 
        LEFT JOIN users u ON c.created_by = u.user_id 
        ORDER BY c.customer_id DESC
    ");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب بيانات عميل محدد للتعديل
    $edit_customer = null;
    if ($action == 'edit' && $customer_id > 0) {
        $stmt = $db->prepare("SELECT * FROM customers WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $edit_customer = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // جلب تقرير Aging للعملاء
    $stmt = $db->query("
        SELECT 
            c.customer_id,
            c.name,
            c.phone,
            SUM(i.total_amount - i.paid_amount) as total_debt,
            SUM(CASE WHEN i.due_date < CURDATE() THEN i.total_amount - i.paid_amount ELSE 0 END) as overdue_debt,
            SUM(CASE WHEN i.due_date >= CURDATE() AND i.due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN i.total_amount - i.paid_amount ELSE 0 END) as due_soon_debt,
            MAX(i.due_date) as last_due_date
        FROM customers c
        LEFT JOIN invoices i ON c.customer_id = i.customer_id
        WHERE i.total_amount > i.paid_amount OR i.paid_amount IS NULL
        GROUP BY c.customer_id, c.name, c.phone
        HAVING total_debt > 0
        ORDER BY total_debt DESC
    ");
    $aging_report = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جذب الفواتير غير المسددة
    $stmt = $db->query("
        SELECT i.*, c.name as customer_name
        FROM invoices i
        JOIN customers c ON i.customer_id = c.customer_id
        WHERE i.total_amount > i.paid_amount OR i.paid_amount IS NULL
        ORDER BY i.due_date ASC
    ");
    $unpaid_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'view_customers', 'عرض صفحة العملاء والحسابات المدينة');
    
} catch (PDOException $e) {
    error_log('Customers Page Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب البيانات من النظام";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - العملاء والحسابات المدينة</title>
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
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .tab {
            padding: 15px 20px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-align: center;
            flex: 1;
        }
        
        .tab.active {
            background-color: var(--primary-color);
            color: white;
            font-weight: bold;
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
        
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .card-title {
            font-size: 20px;
            color: var(--secondary-color);
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: var(--border-radius);
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
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
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            color: white;
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
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .badge-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .badge-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .badge-info {
            background-color: var(--primary-color);
            color: white;
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
        
        .form-control:focus {
            outline: none;
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
            width: 80%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .close {
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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
        
        .debt-high {
            background-color: #ffebee;
        }
        
        .debt-medium {
            background-color: #fff8e1;
        }
        
        .debt-low {
            background-color: #e8f5e9;
        }
        
        @media (max-width: 992px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
            }
            
            table {
                display: block;
                overflow-x: auto;
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
        
        <h1 class="dashboard-title">إدارة العملاء والحسابات المدينة</h1>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('customers')">إدارة العملاء</div>
            <div class="tab" onclick="switchTab('receivables')">الحسابات المدينة</div>
            <div class="tab" onclick="switchTab('aging')">تقرير Aging</div>
            <div class="tab" onclick="switchTab('invoices')">الفواتير غير المسددة</div>
        </div>
        
        <!-- تبويب إدارة العملاء -->
        <div id="customers" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">قائمة العملاء</h2>
                    <button class="btn btn-primary" onclick="openModal('addCustomerModal')">
                        <i class="fas fa-plus"></i> إضافة عميل جديد
                    </button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم العميل</th>
                            <th>الهاتف</th>
                            <th>البريد الإلكتروني</th>
                            <th>الشركة</th>
                            <th>الرقم الضريبي</th>
                            <th>عدد الفواتير</th>
                            <th>الحساب المدينة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $index => $customer): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            <td><?php echo htmlspecialchars($customer['email'] ?? 'غير محدد'); ?></td>
                            <td><?php echo htmlspecialchars($customer['company_name'] ?? 'غير محدد'); ?></td>
                            <td><?php echo htmlspecialchars($customer['tax_number'] ?? 'غير محدد'); ?></td>
                            <td><?php echo $customer['invoice_count']; ?></td>
                            <td>
                                <?php if ($customer['total_debt'] > 0): ?>
                                <span class="badge badge-danger"><?php echo number_format($customer['total_debt'], 2); ?> ريال</span>
                                <?php else: ?>
                                <span class="badge badge-success">لا يوجد ديون</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-primary" onclick="openEditModal(<?php echo $customer['customer_id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?action=delete&id=<?php echo $customer['customer_id']; ?>" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا العميل؟');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- تبويب الحسابات المدينة -->
        <div id="receivables" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">الحسابات المدينة</h2>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم العميل</th>
                            <th>إجمالي الديون</th>
                            <th>عدد الفواتير</th>
                            <th>آخر معاملة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_receivables = 0;
                        foreach ($customers as $index => $customer): 
                            if ($customer['total_debt'] > 0):
                                $total_receivables += $customer['total_debt'];
                        ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                            <td>
                                <span class="badge badge-danger"><?php echo number_format($customer['total_debt'], 2); ?> ريال</span>
                            </td>
                            <td><?php echo $customer['invoice_count']; ?></td>
                            <td>--</td>
                            <td>
                                <button class="btn btn-primary">
                                    <i class="fas fa-eye"></i> التفاصيل
                                </button>
                                <button class="btn btn-success">
                                    <i class="fas fa-money-bill-wave"></i> سداد
                                </button>
                            </td>
                        </tr>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2">الإجمالي</th>
                            <th colspan="4"><?php echo number_format($total_receivables, 2); ?> ريال</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <!-- تبويب تقرير Aging -->
        <div id="aging" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">تقرير Aging للعملاء</h2>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم العميل</th>
                            <th>إجمالي الديون</th>
                            <th>متأخرات</th>
                            <th>ستستحق قريباً</th>
                            <th>آخر تاريخ استحقاق</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($aging_report as $index => $report): 
                            $status = '';
                            $class = '';
                            if ($report['overdue_debt'] > 0) {
                                $status = 'متأخر';
                                $class = 'debt-high';
                            } elseif ($report['due_soon_debt'] > 0) {
                                $status = 'قريب الاستحقاق';
                                $class = 'debt-medium';
                            } else {
                                $status = 'جيد';
                                $class = 'debt-low';
                            }
                        ?>
                        <tr class="<?php echo $class; ?>">
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($report['name']); ?></td>
                            <td><?php echo number_format($report['total_debt'], 2); ?> ريال</td>
                            <td><?php echo number_format($report['overdue_debt'], 2); ?> ريال</td>
                            <td><?php echo number_format($report['due_soon_debt'], 2); ?> ريال</td>
                            <td><?php echo $report['last_due_date'] ?? 'غير محدد'; ?></td>
                            <td>
                                <?php if ($status == 'متأخر'): ?>
                                <span class="badge badge-danger"><?php echo $status; ?></span>
                                <?php elseif ($status == 'قريب الاستحقاق'): ?>
                                <span class="badge badge-warning"><?php echo $status; ?></span>
                                <?php else: ?>
                                <span class="badge badge-success"><?php echo $status; ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- تبويب الفواتير غير المسددة -->
        <div id="invoices" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">الفواتير غير المسددة</h2>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>رقم الفاتورة</th>
                            <th>اسم العميل</th>
                            <th>تاريخ الفاتورة</th>
                            <th>تاريخ الاستحقاق</th>
                            <th>الإجمالي</th>
                            <th>المدفوع</th>
                            <th>المتبقي</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unpaid_invoices as $index => $invoice): 
                            $remaining = $invoice['total_amount'] - $invoice['paid_amount'];
                            $status = '';
                            $badge_class = '';
                            
                            if ($remaining <= 0) {
                                $status = 'مسددة';
                                $badge_class = 'badge-success';
                            } elseif (strtotime($invoice['due_date']) < time()) {
                                $status = 'متأخرة';
                                $badge_class = 'badge-danger';
                            } else {
                                $status = 'غير مسددة';
                                $badge_class = 'badge-warning';
                            }
                        ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($invoice['invoice_number'] ?? $invoice['invoice_id']); ?></td>
                            <td><?php echo htmlspecialchars($invoice['customer_name']); ?></td>
                            <td><?php echo $invoice['invoice_date']; ?></td>
                            <td><?php echo $invoice['due_date']; ?></td>
                            <td><?php echo number_format($invoice['total_amount'], 2); ?> ريال</td>
                            <td><?php echo number_format($invoice['paid_amount'], 2); ?> ريال</td>
                            <td><?php echo number_format($remaining, 2); ?> ريال</td>
                            <td>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo $status; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal لإضافة عميل -->
    <div id="addCustomerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>إضافة عميل جديد</h2>
                <span class="close" onclick="closeModal('addCustomerModal')">&times;</span>
            </div>
            
            <form method="POST" action="?action=add">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">اسم العميل *</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الهاتف *</label>
                        <input type="text" class="form-control" name="phone" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" class="form-control" name="email">
                </div>
                
                <div class="form-group">
                    <label class="form-label">العنوان</label>
                    <textarea class="form-control" name="address" rows="2"></textarea>
                </div>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">اسم الشركة</label>
                        <input type="text" class="form-control" name="company_name">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الرقم الضريبي</label>
                        <input type="text" class="form-control" name="tax_number">
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">حفظ العميل</button>
                    <button type="button" class="btn" onclick="closeModal('addCustomerModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal لتعديل عميل -->
    <div id="editCustomerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>تعديل بيانات العميل</h2>
                <span class="close" onclick="closeModal('editCustomerModal')">&times;</span>
            </div>
            
            <form method="POST" action="?action=edit&id=<?php echo $edit_customer['customer_id'] ?? ''; ?>">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">اسم العميل *</label>
                        <input type="text" class="form-control" name="name" value="<?php echo $edit_customer['name'] ?? ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الهاتف *</label>
                        <input type="text" class="form-control" name="phone" value="<?php echo $edit_customer['phone'] ?? ''; ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" class="form-control" name="email" value="<?php echo $edit_customer['email'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">العنوان</label>
                    <textarea class="form-control" name="address" rows="2"><?php echo $edit_customer['address'] ?? ''; ?></textarea>
                </div>
                
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">اسم الشركة</label>
                        <input type="text" class="form-control" name="company_name" value="<?php echo $edit_customer['company_name'] ?? ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الرقم الضريبي</label>
                        <input type="text" class="form-control" name="tax_number" value="<?php echo $edit_customer['tax_number'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">حفظ التعديلات</button>
                    <button type="button" class="btn" onclick="closeModal('editCustomerModal')">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // إخفاء جميع محتويات التبويبات
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // إلغاء تنشيط جميع التبويبات
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // تفعيل التبويب المحدد
            document.getElementById(tabName).classList.add('active');
            
            // البحث عن التبويب المناسب وتفعيله
            document.querySelectorAll('.tab').forEach(tab => {
                if (tab.textContent.trim() === 
                    document.querySelector(`#${tabName} .card-title`).textContent.trim().replace('قائمة ', '').replace('تقرير ', '').replace('إدارة ', '')) {
                    tab.classList.add('active');
                }
            });
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function openEditModal(customerId) {
            // في تطبيق حقيقي، سنقوم بجلب بيانات العميل عبر AJAX
            // ولكن هنا سنفتح Modal التعديل مباشرة
            window.location.href = `?action=edit&id=${customerId}`;
        }
        
        // إذا كان هناك action لتعديل عميل، نفتح Modal التعديل تلقائياً
        <?php if ($action == 'edit' && !empty($edit_customer)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openModal('editCustomerModal');
        });
        <?php endif; ?>
        
        // إغلاق Modal عند النقر خارج المحتوى
        window.onclick = function(event) {
            document.querySelectorAll('.modal').forEach(modal => {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            });
        }
    </script>
</body>
</html>