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

// جلب بيانات العملاء مع معلومات الفواتير
try {
    $stmt = $db->prepare("
        SELECT 
            c.customer_id,
            c.name,
            c.email,
            c.phone,
            c.company_name,
            c.is_vip,
            c.created_at,
            c.is_archived,
            COUNT(i.invoice_id) as invoice_count,
            MAX(i.issue_date) as last_order_date
        FROM customers c 
        LEFT JOIN invoices i ON c.customer_id = i.customer_id 
        WHERE c.is_archived = 0
        GROUP BY c.customer_id 
        ORDER BY c.created_at DESC
    ");
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "خطأ في جلب بيانات العملاء: " . $e->getMessage();
    $customers = [];
}

// معالجة البحث والتصفية
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

if ($search || $filter) {
    $query = "
        SELECT 
            c.customer_id,
            c.name,
            c.email,
            c.phone,
            c.company_name,
            c.is_vip,
            c.created_at,
            c.is_archived,
            COUNT(i.invoice_id) as invoice_count,
            MAX(i.issue_date) as last_order_date
        FROM customers c 
        LEFT JOIN invoices i ON c.customer_id = i.customer_id 
        WHERE c.is_archived = 0
    ";
    
    $params = [];
    
    if ($search) {
        $query .= " AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($filter) {
        switch ($filter) {
            case 'vip':
                $query .= " AND c.is_vip = 1";
                break;
            case 'new':
                $query .= " AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'active':
                $query .= " AND COUNT(i.invoice_id) > 0";
                break;
            case 'inactive':
                $query .= " AND COUNT(i.invoice_id) = 0";
                break;
        }
    }
    
    $query .= " GROUP BY c.customer_id ORDER BY c.created_at DESC";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "خطأ في البحث: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة العملاء - نظام المطبعة</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .content-box {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .search-filter {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            outline: none;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .filter-select {
            min-width: 200px;
        }

        .filter-select select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: white;
            font-size: 14px;
            cursor: pointer;
        }

        .add-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
        }

        .add-btn:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-top: 20px;
        }

        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
            padding: 15px;
            text-align: right;
            position: sticky;
            top: 0;
            border-bottom: 2px solid #e0e0e0;
        }

        .data-table td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #f0f0f0;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover {
            background-color: #f8fafd;
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-primary {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .badge-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .badge-warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .badge-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background-color: #f8f9fa;
            color: var(--gray);
            margin-right: 5px;
            transition: all 0.3s;
            text-decoration: none;
        }

        .action-btn:hover {
            background-color: var(--primary);
            color: white;
            transform: scale(1.1);
        }

        .btn-danger {
            background-color: #e74c3c;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 8px;
        }

        .pagination a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.3s;
        }

        .pagination a.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination a:hover:not(.active) {
            background-color: #f0f0f0;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #e0e0e0;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--gray);
            font-size: 14px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .data-table tbody tr {
            animation: fadeIn 0.3s ease-out;
            animation-fill-mode: both;
        }

        .data-table tbody tr:nth-child(1) { animation-delay: 0.1s; }
        .data-table tbody tr:nth-child(2) { animation-delay: 0.2s; }
        .data-table tbody tr:nth-child(3) { animation-delay: 0.3s; }
        .data-table tbody tr:nth-child(4) { animation-delay: 0.4s; }
        .data-table tbody tr:nth-child(5) { animation-delay: 0.5s; }

        @media (max-width: 768px) {
            .search-filter {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: auto;
            }
            
            .data-table {
                font-size: 12px;
            }
            
            .data-table th,
            .data-table td {
                padding: 8px 10px;
            }
            
            .action-btn {
                width: 30px;
                height: 30px;
                margin-right: 3px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <h1>إدارة العملاء</h1>
                <?php include 'includes/user-menu.php'; ?>
            </div>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success animate__animated animate__fadeIn">
                    <i class="fas fa-check-circle"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger animate__animated animate__fadeIn">
                    <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger animate__animated animate__fadeIn">
                    <i class="fas fa-exclamation-circle"></i> <?= $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- بطاقات الإحصائيات -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-number"><?= count($customers) ?></div>
                    <div class="stat-label">إجمالي العملاء</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?= count(array_filter($customers, function($c) { return $c['is_vip']; })) ?>
                    </div>
                    <div class="stat-label">عملاء مميزون</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?= count(array_filter($customers, function($c) { return $c['invoice_count'] > 0; })) ?>
                    </div>
                    <div class="stat-label">عملاء نشطون</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?= count(array_filter($customers, function($c) { return $c['invoice_count'] == 0; })) ?>
                    </div>
                    <div class="stat-label">عملاء جدد</div>
                </div>
            </div>
            
            <div class="content-box">
                <form method="GET" action="" class="search-filter">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="ابحث بالاسم، الهاتف أو البريد..." 
                               value="<?= htmlspecialchars($search) ?>">
                        <i class="fas fa-search"></i>
                    </div>
                    
                    <div class="filter-select">
                        <select name="filter">
                            <option value="">جميع العملاء</option>
                            <option value="vip" <?= $filter === 'vip' ? 'selected' : '' ?>>العملاء المميزون</option>
                            <option value="new" <?= $filter === 'new' ? 'selected' : '' ?>>العملاء الجدد</option>
                            <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>النشطون</option>
                            <option value="inactive" <?= $filter === 'inactive' ? 'selected' : '' ?>>غير النشطون</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="add-btn">
                        <i class="fas fa-filter"></i> تطبيق
                    </button>
                    
                    <a href="add_customer.php" class="add-btn">
                        <i class="fas fa-plus"></i> إضافة عميل
                    </a>
                    
                    <?php if ($search || $filter): ?>
                    <a href="customers.php" class="add-btn" style="background-color: #6c757d;">
                        <i class="fas fa-times"></i> إلغاء البحث
                    </a>
                    <?php endif; ?>
                </form>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>اسم العميل</th>
                                <th>الهاتف</th>
                                <th>البريد الإلكتروني</th>
                                <th>الشركة</th>
                                <th>النوع</th>
                                <th>عدد الفواتير</th>
                                <th>آخر طلب</th>
                                <th>إجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr>
                                    <td colspan="9" class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <h3>لا توجد عملاء</h3>
                                        <p><?= $search ? 'لم يتم العثور على عملاء مطابقين لبحثك' : 'لم يتم إضافة أي عملاء بعد' ?></p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($customers as $index => $customer): ?>
                                <tr class="animate__animated animate__fadeIn">
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($customer['name']) ?></strong>
                                        <?php if ($customer['is_vip']): ?>
                                            <i class="fas fa-crown" style="color: #ffd700; margin-right: 5px;"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($customer['phone'] ?? '---') ?></td>
                                    <td><?= htmlspecialchars($customer['email'] ?? '---') ?></td>
                                    <td><?= htmlspecialchars($customer['company_name'] ?? '---') ?></td>
                                    <td>
                                        <span class="badge <?= $customer['is_vip'] ? 'badge-primary' : 'badge-success' ?>">
                                            <?= $customer['is_vip'] ? 'مميز' : 'عادي' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $customer['invoice_count'] > 0 ? 'badge-warning' : 'badge-danger' ?>">
                                            <?= $customer['invoice_count'] ?> فاتورة
                                        </span>
                                    </td>
                                    <td>
                                        <?= $customer['last_order_date'] ? 
                                            date('Y-m-d', strtotime($customer['last_order_date'])) : 
                                            '---' ?>
                                    </td>
                                    <td>
                                        <!-- زر العرض -->
                                        <a href="view_customer.php?id=<?= $customer['customer_id'] ?>" 
                                           class="action-btn" 
                                           title="عرض العميل">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <!-- زر التعديل -->
                                        <a href="edit_customer.php?id=<?= $customer['customer_id'] ?>" 
                                           class="action-btn" 
                                           title="تعديل العميل">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <!-- زر الأرشفة بدلاً من الحذف -->
                                        <a href="#" 
                                           onclick="confirmArchive(<?= $customer['customer_id'] ?>, '<?= htmlspecialchars($customer['name']) ?>', <?= $customer['invoice_count'] ?>)" 
                                           class="action-btn btn-danger" 
                                           title="أرشفة العميل">
                                            <i class="fas fa-archive"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- يمكن إضافة ترقيم الصفحات هنا لاحقاً -->
            </div>
        </main>
    </div>

    <script>
        // تأكيد الأرشفة بدلاً من الحذف
        function confirmArchive(id, name, invoiceCount) {
            if (invoiceCount > 0) {
                Swal.fire({
                    title: 'لا يمكن حذف هذا العميل!',
                    html: `هذا العميل مرتبط بـ <strong>${invoiceCount} فاتورة</strong>. للحفاظ على سلامة البيانات، يرجى أرشفة العميل بدلاً من حذفه.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e74c3c',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'نقل إلى الأرشيف',
                    cancelButtonText: 'إلغاء',
                    showDenyButton: true,
                    denyButtonText: 'عرض التفاصيل'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `archive_customer.php?id=${id}`;
                    } else if (result.isDenied) {
                        window.location.href = `view_customer.php?id=${id}`;
                    }
                });
            } else {
                Swal.fire({
                    title: 'أرشفة العميل',
                    text: `هل تريد أرشفة العميل "${name}"؟`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#e74c3c',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'نعم، أرشف',
                    cancelButtonText: 'إلغاء'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `archive_customer.php?id=${id}`;
                    }
                });
            }
        }

        // البحث الفوري مع تحسينات
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('input', debounce(function() {
                this.form.submit();
            }, 500));
        }

        // التصفية التلقائية
        const filterSelect = document.querySelector('select[name="filter"]');
        if (filterSelect) {
            filterSelect.addEventListener('change', function() {
                this.form.submit();
            });
        }

        // دالة للتأخير في البحث
        function debounce(func, wait) {
            let timeout;
            return function() {
                const context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    func.apply(context, args);
                }, wait);
            };
        }

        // رسائل التنبيه الجميلة
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['success'])): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'تم بنجاح',
                    text: '<?= $_SESSION['success'] ?>',
                    timer: 3000,
                    showConfirmButton: false
                });
            <?php unset($_SESSION['success']); endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'خطأ',
                    text: '<?= $_SESSION['error'] ?>',
                    timer: 4000,
                    showConfirmButton: true
                });
            <?php unset($_SESSION['error']); endif; ?>
        });
    </script>
</body>
</html>