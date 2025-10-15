<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

checkLogin();

// دور المستخدم مع قيمة افتراضية
$user_role = $_SESSION['user_role'] ?? 'guest';


// معرف كشف الراتب
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: payroll.php");
    exit();
}
$payroll_id = intval($_GET['id']);

// أسماء الأشهر بالعربية
$months = [
    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
    9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
];

// جلب بيانات كشف الراتب مع بيانات الموظف واسم القسم
$payroll = [];
try {
    $stmt = $db->prepare("
        SELECT 
            p.*,
            e.full_name,
            e.position,
            e.national_id,
            d.name AS department_name
        FROM payroll p
        INNER JOIN employees e ON p.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE p.payroll_id = ?
    ");
    $stmt->execute([$payroll_id]);
    $payroll = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payroll) {
        header("Location: payroll.php");
        exit();
    }
} catch (PDOException $e) {
    die("خطأ في جلب بيانات كشف الراتب: " . $e->getMessage());
}

// تعيين القيم الافتراضية لتجنب التحذيرات
$full_name       = $payroll['full_name'] ?? 'غير محدد';
$position        = $payroll['position'] ?? 'غير محدد';
$department      = $payroll['department_name'] ?? 'غير محدد';
$month_key       = $payroll['month'] ?? null;
$month           = $months[$month_key] ?? 'غير محدد';
$year            = $payroll['year'] ?? date('Y');
$basic_salary    = $payroll['basic_salary'] ?? 0;
$bonuses         = $payroll['bonuses'] ?? 0;
$allowances      = $payroll['allowances'] ?? 0;
$deductions      = $payroll['deductions'] ?? 0;
$tax             = $payroll['tax'] ?? 0;
$net_salary      = $payroll['net_salary'] ?? ($basic_salary + $bonuses + $allowances - ($deductions + $tax));
$status          = $payroll['status'] ?? 'pending';
$payment_date    = $payroll['payment_date'] ?? null;
$notes           = $payroll['notes'] ?? 'لا يوجد';
$national_id     = $payroll['national_id'] ?? 'غير محدد';

// حساب الإجماليات
$gross_income       = $basic_salary + $bonuses + $allowances;
$total_deductions   = $deductions + $tax;
$calculated_net_salary = $gross_income - $total_deductions;

// دور المستخدم
$user_role = $_SESSION['user_role'] ?? 'guest';
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كشف الراتب - نظام المطبعة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --warning: #f8961e;
            --danger: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: var(--dark);
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* الشريط الجانبي */
        .sidebar {
            width: 250px;
            background-color: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            padding: 20px 0;
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        
        .sidebar-header img {
            width: 100%;
            max-width: 150px;
            display: block;
            margin: 0 auto;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border-right: 3px solid var(--primary);
        }
        
        .sidebar-menu i {
            margin-left: 10px;
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        
        /* المحتوى الرئيسي */
        .main-content {
            flex: 1;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .header h1 {
            font-size: 24px;
            color: var(--dark);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
        }
        
        .user-menu img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-left: 10px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
        }
        
        .user-role {
            font-size: 12px;
            color: var(--gray);
        }
        
        /* بطاقة كشف الراتب */
        .payroll-card {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .payroll-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .company-info {
            text-align: right;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .company-details {
            color: var(--gray);
            font-size: 14px;
        }
        
        .payroll-title {
            text-align: left;
        }
        
        .document-title {
            font-size: 28px;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .payroll-period {
            color: var(--gray);
            font-size: 16px;
        }
        
        /* معلومات الموظف */
        .employee-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-group {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .info-value {
            color: var(--dark);
            font-size: 16px;
        }
        
        /* تفاصيل الراتب */
        .salary-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .earnings-section, .deductions-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-left: 10px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .detail-label {
            color: var(--gray);
        }
        
        .detail-value {
            font-weight: 600;
            color: var(--dark);
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            font-size: 18px;
            padding-top: 15px;
            margin-top: 15px;
            border-top: 2px solid #ddd;
        }
        
        .net-salary {
            background-color: var(--primary);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-top: 30px;
        }
        
        .net-label {
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .net-amount {
            font-size: 32px;
            font-weight: 600;
        }
        
        /* حالة الدفع */
        .payment-status {
            text-align: center;
            margin: 30px 0;
        }
        
        .status-badge {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 16px;
        }
        
        .status-paid {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }
        
        .status-pending {
            background-color: rgba(248, 150, 30, 0.1);
            color: var(--warning);
        }
        
        /* أزرار الإجراءات */
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #3aa8c9;
        }
        
        .btn-secondary {
            background-color: var(--gray);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn i {
            margin-left: 8px;
        }
        
        /* نموذج تحديث الحالة */
        .status-form {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        /* الطباعة */
        @media print {
            .container {
                display: block;
            }
            
            .sidebar, .header, .action-buttons, .status-form {
                display: none;
            }
            
            .main-content {
                padding: 0;
            }
            
            .payroll-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            body {
                background-color: white;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .payroll-header {
                flex-direction: column;
                text-align: center;
            }
            
            .payroll-title {
                text-align: center;
                margin-top: 20px;
            }
            
            .salary-details {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- الشريط الجانبي -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="images/logo.png" alt="Logo">
            </div>
            
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-home"></i> لوحة التحكم</a></li>
                <li><a href="customers.php"><i class="fas fa-users"></i> العملاء</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> الطلبات</a></li>
                <li><a href="inventory.php"><i class="fas fa-boxes"></i> المخزون</a></li>
                <li><a href="hr.php"><i class="fas fa-user-tie"></i> الموارد البشرية</a></li>
                <li><a href="payroll.php" class="active"><i class="fas fa-money-bill-wave"></i> كشوف المرتبات</a></li>
                <li><a href="invoices.php"><i class="fas fa-file-invoice-dollar"></i> الفواتير</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> التقارير</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a></li>
            </ul>
        </aside>
        
        <!-- المحتوى الرئيسي -->
        <main class="main-content">
            <div class="header">
                <h1>كشف الراتب التفصيلي</h1>
                
                <div class="user-menu">
                    <div class="user-info">
                        <div class="user-name"><?php echo $_SESSION['user_id']; ?></div>
                        <div class="user-role">
                            <?php echo ($_SESSION['user_role'] ?? 'موظف') == 'admin' ? 'مدير النظام' : 'موظف مبيعات'; ?>
                        </div>
                    </div>
                    <img src="images/user.png" alt="User">
                </div>
            </div>
            
            <!-- بطاقة كشف الراتب -->
            <div class="payroll-card">
                <div class="payroll-header">
                    <div class="company-info">
                        <div class="company-name">شركة المطبعة الحديثة</div>
                        <div class="company-details">
                            طرابلس - ليبيا<br>
                            هاتف: 021-1234567<br>
                            البريد الإلكتروني: info@modern-print.ly
                        </div>
                    </div>
                    
                    <div class="payroll-title">
                        <div class="document-title">كشف الراتب</div>
                        <div class="payroll-period">
                            <?php echo $months[$payroll['month']] . ' ' . $payroll['year']; ?>
                        </div>
                        <div class="payroll-period">
                            رقم الكشف: #<?php echo $payroll_id; ?>
                        </div>
                    </div>
                </div>
                
                <!-- معلومات الموظف -->
                <div class="employee-info">
                    <div>
                        <div class="info-group">
                            <div class="info-label">اسم الموظف</div>
                            <div class="info-value"><?php echo htmlspecialchars($payroll['full_name']); ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">الرقم الوطني</div>
                            <div class="info-value"><?php echo htmlspecialchars($payroll['national_id']); ?></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="info-group">
                            <div class="info-label">المسمى الوظيفي</div>
                            <div class="info-value"><?php echo htmlspecialchars($payroll['position']); ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">القسم</div>
                            <div class="info-value"><?php echo $departments[$payroll['department']] ?? $payroll['department']; ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- تفاصيل الراتب -->
                <div class="salary-details">
                    <!-- الإيرادات -->
                    <div class="earnings-section">
                        <h3 class="section-title"><i class="fas fa-plus-circle"></i> الإيرادات</h3>
                        
                        <div class="detail-item">
                            <span class="detail-label">الراتب الأساسي</span>
                            <span class="detail-value"><?php echo number_format($payroll['basic_salary'], 2); ?> د.ل</span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">المكافآت والحوافز</span>
                            <span class="detail-value"><?php echo number_format($payroll['bonuses'], 2); ?> د.ل</span>
                        </div>
                        
                        <div class="total-row">
                            <span class="detail-label">إجمالي الدخل</span>
                            <span class="detail-value"><?php echo number_format($gross_income, 2); ?> د.ل</span>
                        </div>
                    </div>
                    
                    <!-- الخصومات -->
                    <div class="deductions-section">
                        <h3 class="section-title"><i class="fas fa-minus-circle"></i> الخصومات</h3>
                        
                        <div class="detail-item">
                            <span class="detail-label">الخصومات المختلفة</span>
                            <span class="detail-value"><?php echo number_format($payroll['deductions'], 2); ?> د.ل</span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">الضريبة</span>
                            <span class="detail-value"><?php echo number_format($payroll['tax'], 2); ?> د.ل</span>
                        </div>
                        
                        <div class="total-row">
                            <span class="detail-label">إجمالي الخصومات</span>
                            <span class="detail-value"><?php echo number_format($total_deductions, 2); ?> د.ل</span>
                        </div>
                    </div>
                </div>
                
                <!-- صافي الراتب -->
                <div class="net-salary">
                    <div class="net-label">صافي الراتب المستحق</div>
                    <div class="net-amount"><?php echo number_format($payroll['net_salary'], 2); ?> دينار ليبي</div>
                </div>
                
                <!-- حالة الدفع -->
                <div class="payment-status">
                    <span class="status-badge <?php echo $payroll['status'] === 'paid' ? 'status-paid' : 'status-pending'; ?>">
                        <?php echo $payroll['status'] === 'paid' ? 'تم الدفع' : 'قيد الانتظار'; ?>
                    </span>
                    
                    <?php if ($payroll['status'] === 'paid' && $payroll['payment_date']): ?>
                        <div style="margin-top: 10px; color: var(--gray);">
                            تم الدفع في: <?php echo date('Y/m/d', strtotime($payroll['payment_date'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- ملاحظات -->
                <?php if (!empty($payroll['notes'])): ?>
                    <div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 6px;">
                        <strong>ملاحظات:</strong><br>
                        <?php echo htmlspecialchars($payroll['notes']); ?>
                    </div>
                <?php endif; ?>
                
                <!-- أزرار الإجراءات -->
                <div class="action-buttons">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> طباعة الكشف
                    </button>
                    
                    <a href="payroll.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i> العودة للقائمة
                    </a>
                    
                    <?php if (($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'accountant') && $payroll['status'] === 'pending'): ?>
                        <button onclick="document.getElementById('statusForm').style.display='block'" class="btn btn-success">
                            <i class="fas fa-check-circle"></i> تحديث حالة الدفع
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- نموذج تحديث حالة الدفع -->
                <?php if (($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'accountant') && $payroll['status'] === 'pending'): ?>
                    <div id="statusForm" class="status-form" style="display: none;">
                        <h3 style="margin-bottom: 20px; color: var(--primary);">تحديث حالة الدفع</h3>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="update_status" value="1">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="status">حالة الدفع</label>
                                    <select class="form-control" id="status" name="status" required>
                                        <option value="pending" <?php echo $payroll['status'] === 'pending' ? 'selected' : ''; ?>>قيد الانتظار</option>
                                        <option value="paid">تم الدفع</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="payment_date">تاريخ الدفع</label>
                                    <input type="date" class="form-control" id="payment_date" name="payment_date" 
                                           value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 10px; margin-top: 20px;">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> حفظ التعديلات
                                </button>
                                <button type="button" onclick="document.getElementById('statusForm').style.display='none'" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> إلغاء
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // عند تحديد حالة "تم الدفع"، اجعل تاريخ الدفع مطلوباً
        document.getElementById('status')?.addEventListener('change', function() {
            const paymentDate = document.getElementById('payment_date');
            if (this.value === 'paid') {
                paymentDate.setAttribute('required', 'required');
            } else {
                paymentDate.removeAttribute('required');
            }
        });
        
        // تعيين تاريخ اليوم كقيمة افتراضية لتاريخ الدفع
        document.getElementById('payment_date')?.setAttribute('max', new Date().toISOString().split('T')[0]);
    </script>
</body>
</html>