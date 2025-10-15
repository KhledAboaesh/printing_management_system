<?php
// dashboard.php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التأكد من تسجيل الدخول
checkLogin();

// الحصول على معرف الدور والمعرف الخاص بالمستخدم
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'guest';

if (!$user_id) {
    header("Location: login.php");
    exit();
}

// =======================
// إحصائيات لوحة التحكم
// =======================
$stats = [
    'customers'       => getCustomerCount(),                  // عدد العملاء
    'orders'          => getOrderCount(),                     // عدد الطلبات
    'inventory'       => getLowInventoryCount(),              // المنتجات منخفضة المخزون
    'sales'           => getTotalSales(),                     // إجمالي المبيعات
    'monthly_sales'   => getMonthlySales(),                   // مبيعات الشهر الحالي
    'sales_growth'    => getSalesGrowth(),                    // نسبة نمو المبيعات
    'total_invoices'  => getTotalInvoicesCount(),             // عدد الفواتير
    'average_invoice' => getTotalInvoicesCount() > 0 
                         ? getTotalSales() / getTotalInvoicesCount() 
                         : 0,
    'total_profit'    => getTotalProfit()                     // ✅ إجمالي أرباح الطلبات
];

// =======================
// الحصول على التنبيهات
// =======================
$alerts = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = 0
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log('Dashboard Notifications Error: ' . $e->getMessage());
}

// =======================
// الحصول على آخر الطلبات
// =======================
$recent_orders = [];
try {
    $stmt = $db->prepare("
        SELECT o.order_id, o.order_date, o.status, c.name as customer_name, o.total_amount, o.total_profit
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        ORDER BY o.order_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log('Dashboard Orders Error: ' . $e->getMessage());
}

// =======================
// بيانات إضافية للوحة التحكم
// =======================
$monthly_sales_data = getMonthlySalesData(); // بيانات رسوم بيانية للمبيعات الشهرية
$top_customers      = getTopCustomers();     // أفضل العملاء
$top_products       = getTopProducts();      // أكثر المنتجات مبيعًا
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - نظام المطبعة</title>
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
        
        /* بطاقات الإحصائيات */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stat-card-title {
            font-size: 14px;
            color: var(--gray);
        }
        
        .stat-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .stat-card-value {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .stat-card-footer {
            font-size: 12px;
            color: var(--gray);
        }
        
        /* الأقسام السريعة */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .quick-action {
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .quick-action:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-3px);
        }
        
        .quick-action i {
            font-size: 24px;
            margin-bottom: 10px;
            display: block;
        }
        
        /* التنبيهات */
        .alerts-section {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .section-title {
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-left: 10px;
        }
        
        .alert {
            display: flex;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            background-color: #f8f9fa;
            align-items: center;
        }
        
        .alert-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 15px;
            flex-shrink: 0;
        }
        
        .alert-danger .alert-icon {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--danger);
        }
        
        .alert-warning .alert-icon {
            background-color: rgba(248, 150, 30, 0.1);
            color: var(--warning);
        }
        
        .alert-success .alert-icon {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .alert-time {
            font-size: 12px;
            color: var(--gray);
        }
        
        .alert-close {
            color: var(--gray);
            cursor: pointer;
            margin-right: 10px;
        }


        /* تنسيقات قسم أفضل العملاء والمنتجات */
.top-customers-section,
.top-products-section {
    background-color: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 30px;
    border: 1px solid #f0f0f0;
    transition: all 0.3s ease;
}

.top-customers-section:hover,
.top-products-section:hover {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
}

.section-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i {
    color: var(--primary);
    font-size: 22px;
}

.table-responsive {
    overflow-x: auto;
    border-radius: 8px;
    margin-top: 15px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.data-table th {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    font-weight: 600;
    padding: 15px 12px;
    text-align: right;
    font-size: 14px;
    border: none;
    position: sticky;
    top: 0;
}

.data-table th:first-child {
    border-top-right-radius: 8px;
}

.data-table th:last-child {
    border-top-left-radius: 8px;
}

.data-table td {
    padding: 14px 12px;
    text-align: right;
    border-bottom: 1px solid #f8f9fa;
    font-size: 14px;
    color: var(--dark);
    transition: background-color 0.2s ease;
}

.data-table tr:last-child td {
    border-bottom: none;
}

.data-table tr:hover td {
    background-color: rgba(67, 97, 238, 0.05);
}

.data-table tr:nth-child(even) {
    background-color: #fafbff;
}

.data-table tr:nth-child(even):hover {
    background-color: rgba(67, 97, 238, 0.03);
}

/* تنسيقات خاصة للعمود الأول */
.data-table td:first-child {
    font-weight: 600;
    color: var(--dark);
}

/* تنسيقات الأرقام */
.data-table td:nth-child(2),
.data-table td:nth-child(3) {
    font-family: 'Courier New', monospace;
    font-weight: 500;
    color: var(--gray);
}

.data-table td:nth-child(3) {
    color: var(--success);
    font-weight: 600;
}

/* تنسيقات للجدول على الشاشات الصغيرة */
@media (max-width: 768px) {
    .top-customers-section,
    .top-products-section {
        padding: 20px 15px;
        margin-bottom: 25px;
    }
    
    .section-title {
        font-size: 18px;
        margin-bottom: 15px;
    }
    
    .data-table {
        font-size: 13px;
    }
    
    .data-table th,
    .data-table td {
        padding: 12px 8px;
        font-size: 13px;
    }
    
    .data-table th {
        font-size: 13px;
    }
}

@media (max-width: 480px) {
    .top-customers-section,
    .top-products-section {
        padding: 15px 10px;
    }
    
    .section-title {
        font-size: 16px;
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .data-table {
        min-width: 300px;
    }
    
    .data-table th,
    .data-table td {
        padding: 10px 6px;
        font-size: 12px;
    }
    
    .table-responsive {
        margin-left: -10px;
        margin-right: -10px;
        width: calc(100% + 20px);
    }
}

/* تأثيرات التحميل والظهور */
.top-customers-section,
.top-products-section {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* تنسيقات للبيانات الفارغة */
.data-table tr.no-data td {
    text-align: center;
    padding: 40px;
    color: var(--gray);
    font-style: italic;
    background-color: #fafafa;
}

.data-table tr.no-data:hover td {
    background-color: #fafafa;
}

/* تنسيقات للترتيب في الجداول */
.data-table tr:first-child {
    background-color: rgba(255, 215, 0, 0.1);
}

.data-table tr:first-child td {
    font-weight: 700;
}

.data-table tr:first-child td:first-child::before {
    content: "🥇 ";
    margin-left: 5px;
}

.data-table tr:nth-child(2) {
    background-color: rgba(192, 192, 192, 0.1);
}

.data-table tr:nth-child(2) td:first-child::before {
    content: "🥈 ";
    margin-left: 5px;
}

.data-table tr:nth-child(3) {
    background-color: rgba(205, 127, 50, 0.1);
}

.data-table tr:nth-child(3) td:first-child::before {
    content: "🥉 ";
    margin-left: 5px;
}

/* تنسيقات للروابط في الجداول */
.data-table a {
    color: var(--primary);
    text-decoration: none;
    transition: color 0.3s ease;
}

.data-table a:hover {
    color: var(--secondary);
    text-decoration: underline;
}

/* تنسيقات للأيقونات في الجداول */
.data-table .table-icon {
    margin-left: 8px;
    color: var(--primary);
    font-size: 14px;
}

/* تنسيقات للشريط المتردد */
.table-responsive::-webkit-scrollbar {
    height: 6px;
}

.table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
}

/* تأثيرات خاصة للجوائز */
@keyframes goldGlow {
    0%, 100% { background-color: rgba(255, 215, 0, 0.1); }
    50% { background-color: rgba(255, 215, 0, 0.2); }
}

.data-table tr:first-child {
    animation: goldGlow 2s ease-in-out infinite;
}

/* تنسيقات للهواتف في الوضع الأفقي */
@media (max-width: 768px) and (orientation: landscape) {
    .top-customers-section,
    .top-products-section {
        padding: 15px;
    }
    
    .data-table {
        font-size: 12px;
    }
    
    .data-table th,
    .data-table td {
        padding: 8px 6px;
    }
}

/* تنسيقات للطباعة */
@media print {
    .top-customers-section,
    .top-products-section {
        box-shadow: none;
        border: 1px solid #ddd;
        page-break-inside: avoid;
    }
    
    .data-table {
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    .data-table th {
        background: #f0f0f0 !important;
        color: black !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
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
            
            .stats-cards, .quick-actions {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .stats-cards, .quick-actions {
                grid-template-columns: 1fr;
            }
        }











        /* تنسيقات التنبيهات التفاعلية */
.alerts-section {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 30px;
    border: 1px solid #f0f0f0;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f8f9fa;
}

.section-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i {
    color: var(--primary);
}

.btn-sm {
    padding: 8px 16px;
    font-size: 14px;
    border-radius: 6px;
    border: 1px solid var(--primary);
    background: transparent;
    color: var(--primary);
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-sm:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-1px);
}

.btn-sm:active {
    transform: translateY(0);
}

#alerts-container {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 5px;
}

.alert {
    display: flex;
    align-items: center;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 12px;
    background: #f8f9fa;
    border: 1px solid transparent;
    transition: all 0.3s ease;
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.alert:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.alert-danger {
    background: linear-gradient(135deg, rgba(247, 37, 133, 0.1) 0%, rgba(247, 37, 133, 0.05) 100%);
    border-color: rgba(247, 37, 133, 0.2);
}

.alert-warning {
    background: linear-gradient(135deg, rgba(248, 150, 30, 0.1) 0%, rgba(248, 150, 30, 0.05) 100%);
    border-color: rgba(248, 150, 30, 0.2);
}

.alert-success {
    background: linear-gradient(135deg, rgba(76, 201, 240, 0.1) 0%, rgba(76, 201, 240, 0.05) 100%);
    border-color: rgba(76, 201, 240, 0.2);
}

.alert-info {
    background: linear-gradient(135deg, rgba(67, 97, 238, 0.1) 0%, rgba(67, 97, 238, 0.05) 100%);
    border-color: rgba(67, 97, 238, 0.2);
}

.alert-icon {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-left: 15px;
    flex-shrink: 0;
    font-size: 18px;
}

.alert-danger .alert-icon {
    background: rgba(247, 37, 133, 0.1);
    color: var(--danger);
}

.alert-warning .alert-icon {
    background: rgba(248, 150, 30, 0.1);
    color: var(--warning);
}

.alert-success .alert-icon {
    background: rgba(76, 201, 240, 0.1);
    color: var(--success);
}

.alert-info .alert-icon {
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary);
}

.alert-content {
    flex: 1;
    min-width: 0;
}

.alert-title {
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 16px;
    color: var(--dark);
}

.alert-link {
    color: inherit;
    text-decoration: none;
    transition: color 0.3s ease;
}

.alert-link:hover {
    color: var(--secondary);
    text-decoration: underline;
}

.alert-message {
    font-size: 14px;
    margin-bottom: 5px;
    color: var(--gray);
    line-height: 1.4;
}

.alert-time {
    font-size: 12px;
    color: var(--gray);
    opacity: 0.8;
}

.alert-actions {
    display: flex;
    gap: 8px;
    margin-right: 10px;
    flex-shrink: 0;
}

.btn-action {
    background: transparent;
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 6px;
    padding: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-action:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.1);
}

.alert-close:hover {
    color: var(--danger);
    border-color: var(--danger);
}

.alerts-footer {
    text-align: center;
    padding-top: 15px;
    margin-top: 15px;
    border-top: 1px solid #f0f0f0;
    color: var(--gray);
    font-size: 12px;
}

/* تأثير الإخفاء */
.alert.hiding {
    animation: slideOut 0.3s ease-in forwards;
}

@keyframes slideOut {
    to {
        opacity: 0;
        transform: translateX(-100%);
        height: 0;
        padding: 0;
        margin: 0;
    }
}

/* شريط التمرير */
#alerts-container::-webkit-scrollbar {
    width: 6px;
}

#alerts-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

#alerts-container::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}

#alerts-container::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
}

/* التجاوب مع الشاشات الصغيرة */
@media (max-width: 768px) {
    .alerts-section {
        padding: 20px;
    }
    
    .section-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .alert {
        flex-direction: column;
        text-align: center;
        padding: 20px;
    }
    
    .alert-icon {
        margin-left: 0;
        margin-bottom: 15px;
    }
    
    .alert-actions {
        margin-right: 0;
        margin-top: 15px;
        justify-content: center;
    }
    
    .btn-sm {
        align-self: flex-start;
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
                <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> لوحة التحكم</a></li>
                <li><a href="customers.php"><i class="fas fa-users"></i> العملاء</a></li>
                <li><a href="orders.php"><i class="fas fa-shopping-cart"></i> الطلبات</a></li>
                <li><a href="inventory.php"><i class="fas fa-boxes"></i> المخزون</a></li>
                <li><a href="hr.php"><i class="fas fa-user-tie"></i> الموارد البشرية</a></li>
                <li><a href="invoices.php"><i class="fas fa-file-invoice-dollar"></i> الفواتير</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> التقارير</a></li>
                <li><a href="unauthorized.php"><i class="fas fa-chart-bar"></i> المحاسبة</a></li>
                <li><a href="archive.php"><i class="fas fa-chart-bar"></i> الارشفة</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a></li>
            </ul>
        </aside>
        
        <!-- المحتوى الرئيسي -->
        <main class="main-content">
            <div class="header">
                <h1>لوحة التحكم</h1>
                
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
            







            <!-- قسم إحصائيات المبيعات -->
<div class="sales-section" style="background-color: white; border-radius: 10px; padding: 20px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); margin-bottom: 30px;">
    <h2 class="section-title"><i class="fas fa-chart-bar"></i> إحصائيات المبيعات</h2>
    
   <div class="stats-cards">
    <!-- كارد إجمالي المبيعات -->
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-title">إجمالي المبيعات</div>
            <div class="stat-card-icon" style="background-color: rgba(247, 37, 133, 0.1); color: var(--danger);">
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
        <div class="stat-card-value"><?php echo number_format($stats['sales']); ?> د.ل</div>
        <div class="stat-card-footer">
            <?php
            $growth_class = $stats['sales_growth'] >= 0 ? 'text-success' : 'text-danger';
            $growth_icon = $stats['sales_growth'] >= 0 ? '▲' : '▼';
            ?>
            <span class="<?php echo $growth_class; ?>">
                <?php echo $growth_icon; ?> <?php echo abs($stats['sales_growth']); ?>% عن الشهر الماضي
            </span>
        </div>
    </div>

    <!-- كارد مبيعات الشهر -->
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-title">مبيعات الشهر</div>
            <div class="stat-card-icon" style="background-color: rgba(76, 201, 240, 0.1); color: var(--success);">
                <i class="fas fa-calendar-alt"></i>
            </div>
        </div>
        <div class="stat-card-value"><?php echo number_format($stats['monthly_sales']); ?> د.ل</div>
        <div class="stat-card-footer">شهر <?php echo date('F'); ?></div>
    </div>

    <!-- كارد متوسط الفاتورة -->
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-title">متوسط الفاتورة</div>
            <div class="stat-card-icon" style="background-color: rgba(248, 150, 30, 0.1); color: var(--warning);">
                <i class="fas fa-receipt"></i>
            </div>
        </div>
        <div class="stat-card-value">
            <?php echo number_format($stats['average_invoice'], 2); ?> د.ل
        </div>
        <div class="stat-card-footer">متوسط قيمة الفاتورة</div>
    </div>

    <!-- كارد عدد الفواتير -->
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-title">عدد الفواتير</div>
            <div class="stat-card-icon" style="background-color: rgba(67, 97, 238, 0.1); color: var(--primary);">
                <i class="fas fa-file-invoice"></i>
            </div>
        </div>
        <div class="stat-card-value"><?php echo $stats['total_invoices']; ?></div>
        <div class="stat-card-footer">فواتير مدفوعة</div>
    </div>

    <!-- كارد إجمالي أرباح الطلبات -->
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-title">إجمالي أرباح الطلبات</div>
            <div class="stat-card-icon" style="background-color: rgba(40, 167, 69, 0.1); color: var(--success);">
                <i class="fas fa-dollar-sign"></i>
            </div>
        </div>
        <div class="stat-card-value">
            <?php echo number_format($stats['total_profit'], 2); ?> د.ل
        </div>
        <div class="stat-card-footer">
            <?php
            $profit_growth_class = $stats['total_profit'] >= 0 ? 'text-success' : 'text-danger';
            $profit_icon = $stats['total_profit'] >= 0 ? '▲' : '▼';
            ?>
            <span class="<?php echo $profit_growth_class; ?>">
                <?php echo $profit_icon; ?> مقارنة بالشهر الماضي
            </span>
        </div>
    </div>
</div>

</div>

<!-- أفضل العملاء -->
<div class="top-customers-section" style="background-color: white; border-radius: 10px; padding: 20px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); margin-bottom: 30px;">
    <h3 class="section-title"><i class="fas fa-users"></i> أفضل العملاء</h3>
    
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>العميل</th>
                    <th>عدد الفواتير</th>
                    <th>إجمالي المشتريات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_customers as $customer): ?>
                <tr>
                    <td><?php echo htmlspecialchars($customer['name']); ?></td>
                    <td><?php echo $customer['invoice_count']; ?></td>
                    <td><?php echo number_format($customer['total_spent'], 2); ?> د.ل</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- أفضل المنتجات -->
<div class="top-products-section" style="background-color: white; border-radius: 10px; padding: 20px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); margin-bottom: 30px;">
    <h3 class="section-title"><i class="fas fa-box"></i> أفضل المنتجات مبيعاً</h3>
    
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>المنتج</th>
                    <th>الكمية المباعة</th>
                    <th>الإيرادات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_products as $product): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td><?php echo $product['total_sold']; ?></td>
                    <td><?php echo number_format($product['total_revenue'], 2); ?> د.ل</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>










            <!-- بطاقات الإحصائيات -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">العملاء</div>
                        <div class="stat-card-icon" style="background-color: rgba(67, 97, 238, 0.1); color: var(--primary);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $stats['customers']; ?></div>
                    <div class="stat-card-footer">+5 هذا الشهر</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">الطلبات</div>
                        <div class="stat-card-icon" style="background-color: rgba(76, 201, 240, 0.1); color: var(--success);">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $stats['orders']; ?></div>
                    <div class="stat-card-footer">+12 هذا الشهر</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">نواقص المخزون</div>
                        <div class="stat-card-icon" style="background-color: rgba(248, 150, 30, 0.1); color: var(--warning);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo $stats['inventory']; ?></div>
                    <div class="stat-card-footer">تحتاج إلى إعادة طلب</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-card-title">إجمالي المبيعات</div>
                        <div class="stat-card-icon" style="background-color: rgba(247, 37, 133, 0.1); color: var(--danger);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-card-value"><?php echo number_format($stats['sales']); ?> د.ل</div>
                    <div class="stat-card-footer">+15% عن الشهر الماضي</div>
                </div>
            </div>
            
            <!-- الأقسام السريعة -->
            <div class="quick-actions">
                <div class="quick-action" onclick="window.location.href='add_customer.php'">
                    <i class="fas fa-user-plus"></i>
                    إضافة عميل جديد
                </div>
                
                <div class="quick-action" onclick="window.location.href='add_order.php'">
                    <i class="fas fa-cart-plus"></i>
                    طلب جديد
                </div>
                
                <div class="quick-action" onclick="window.location.href='add_inventory.php'">
                    <i class="fas fa-box-open"></i>
                    إضافة صنف جديد
                </div>
                
                <div class="quick-action" onclick="window.location.href='generate_invoice.php'">
                    <i class="fas fa-file-invoice"></i>
                    إنشاء فاتورة
                </div>
            </div>
            
            <!-- التنبيهات -->
            <div class="alerts-section">
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-bell"></i> التنبيهات الحديثة</h2>
        <button class="btn btn-sm btn-outline-primary" onclick="refreshAlerts()">
            <i class="fas fa-sync-alt"></i> تحديث
        </button>
    </div>
    
    <div id="alerts-container">
        <?php
        $system_alerts = getSystemAlerts();
        
        if (empty($system_alerts)): ?>
            <div class="alert alert-info">
                <div class="alert-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="alert-content">
                    <div class="alert-title">لا توجد تنبيهات حالية</div>
                    <div class="alert-time">كل شيء على ما يرام!</div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($system_alerts as $alert): ?>
                <div class="alert alert-<?php echo $alert['type']; ?>" data-alert-id="<?php echo md5($alert['title'] . $alert['time']); ?>">
                    <div class="alert-icon">
                        <i class="fas fa-<?php echo $alert['icon']; ?>"></i>
                    </div>
                    <div class="alert-content">
                        <div class="alert-title">
                            <?php if (!empty($alert['link'])): ?>
                                <a href="<?php echo $alert['link']; ?>" class="alert-link">
                                    <?php echo htmlspecialchars($alert['title']); ?>
                                </a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($alert['title']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="alert-message"><?php echo htmlspecialchars($alert['message']); ?></div>
                        <div class="alert-time"><?php echo $alert['time']; ?></div>
                    </div>
                    <div class="alert-actions">
                        <?php if (!empty($alert['link'])): ?>
                            <a href="<?php echo $alert['link']; ?>" class="btn-action" title="عرض التفاصيل">
                                <i class="fas fa-eye"></i>
                            </a>
                        <?php endif; ?>
                        <button class="btn-action alert-close" title="إغلاق التنبيه">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="alerts-footer">
        <small>يتم تحديث التنبيهات تلقائياً كل 5 دقائق</small>
    </div>
</div>
        </main>
    </div>

    <script>
// إغلاق التنبيهات
document.addEventListener('DOMContentLoaded', function() {
    // إغلاق التنبيهات عند النقر على الزر
    document.querySelectorAll('.alert-close').forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
            const alert = this.closest('.alert');
            alert.classList.add('hidden');
            
            // حفظ حالة التنبيه في localStorage
            const alertId = alert.getAttribute('data-alert-id');
            if (alertId) {
                const dismissedAlerts = JSON.parse(localStorage.getItem('dismissedAlerts') || '[]');
                if (!dismissedAlerts.includes(alertId)) {
                    dismissedAlerts.push(alertId);
                    localStorage.setItem('dismissedAlerts', JSON.stringify(dismissedAlerts));
                }
            }
            
            setTimeout(() => {
                alert.remove();
                checkEmptyAlerts();
            }, 300);
        });
    });
    
    // إخفاء التنبيهات التي تم إغلاقها مسبقاً
    const dismissedAlerts = JSON.parse(localStorage.getItem('dismissedAlerts') || '[]');
    dismissedAlerts.forEach(alertId => {
        const alert = document.querySelector(`[data-alert-id="${alertId}"]`);
        if (alert) {
            alert.remove();
        }
    });
    
    checkEmptyAlerts();
});

// التحقق من وجود تنبيهات
function checkEmptyAlerts() {
    const container = document.getElementById('alerts-container');
    if (container.children.length === 0) {
        container.innerHTML = `
            <div class="alert alert-info">
                <div class="alert-icon">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div class="alert-content">
                    <div class="alert-title">لا توجد تنبيهات حالية</div>
                    <div class="alert-time">كل شيء على ما يرام!</div>
                </div>
            </div>
        `;
    }
}

// تحديث التنبيهات
function refreshAlerts() {
    const btn = event.target;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحديث...';
    btn.disabled = true;
    
    // محاكاة عملية التحديث
    setTimeout(() => {
        location.reload();
    }, 1000);
}

// تحديث تلقائي كل 5 دقائق
setInterval(() => {
    const refreshBtn = document.querySelector('[onclick="refreshAlerts()"]');
    if (refreshBtn) {
        refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> جاري التحديث...';
        setTimeout(() => location.reload(), 2000);
    }
}, 300000); // 5 دقائق
</script>
</body>
</html>