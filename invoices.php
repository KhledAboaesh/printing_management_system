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

// جلب الفواتير مع معلومات العميل
$invoices = [];
$search_term = '';

// التحقق من وجود بحث
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
    
    try {
        $query = "SELECT i.*, c.name as customer_name, c.email as customer_email, u.username as created_by_name
                  FROM invoices i
                  LEFT JOIN customers c ON i.customer_id = c.customer_id
                  LEFT JOIN users u ON i.created_by = u.user_id
                  WHERE i.invoice_number LIKE :search 
                     OR c.name LIKE :search 
                     OR c.email LIKE :search 
                     OR i.total_amount LIKE :search 
                     OR i.notes LIKE :search
                     OR u.username LIKE :search
                  ORDER BY i.issue_date DESC";
        
        $stmt = $db->prepare($query);
        $search_param = "%" . $search_term . "%";
        $stmt->bindParam(':search', $search_param);
        $stmt->execute();
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error searching invoices: " . $e->getMessage());
        $error = "حدث خطأ في البحث عن الفواتير";
    }
} else {
    // جلب جميع الفواتير إذا لم يكن هناك بحث
    try {
        $query = "SELECT i.*, c.name as customer_name, u.username as created_by_name
                  FROM invoices i
                  LEFT JOIN customers c ON i.customer_id = c.customer_id
                  LEFT JOIN users u ON i.created_by = u.user_id
                  ORDER BY i.issue_date DESC";
        $stmt = $db->query($query);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching invoices: " . $e->getMessage());
        $error = "حدث خطأ في جلب بيانات الفواتير";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الفواتير | نظام الفواتير الإلكتروني</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --warning: #f72585;
            --danger: #d32f2f;
            --info: #17a2b8;
            --light: #f8f9fa;
            --dark: #212529;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f5f7fb;
        }

        .invoice-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: var(--primary);
            font-size: 28px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.2);
        }

        .search-container {
            margin-bottom: 30px;
            position: relative;
        }

        .search-box {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Tajawal', sans-serif;
            transition: all 0.3s;
        }

        .search-box:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
        }

        .search-results-info {
            margin-top: 10px;
            color: var(--dark);
            font-size: 14px;
        }

        .invoice-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            overflow: hidden;
            transition: transform 0.3s;
        }

        .invoice-card:hover {
            transform: translateY(-5px);
        }

        .invoice-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .invoice-number {
            font-size: 18px;
            font-weight: 700;
        }

        .invoice-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-draft {
            background-color: #e3f2fd;
            color: #1565c0;
        }

        .status-paid {
            background-color: #e3faf2;
            color: #20c997;
        }

        .status-pending {
            background-color: #fff3bf;
            color: #f08c00;
        }

        .status-cancelled {
            background-color: #ffebee;
            color: var(--danger);
        }

        .invoice-body {
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .invoice-detail {
            margin-bottom: 15px;
        }

        .detail-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .detail-value {
            color: var(--dark);
        }

        .invoice-actions {
            display: flex;
            gap: 10px;
            padding: 15px 20px;
            border-top: 1px solid #f0f0f0;
            background-color: #f9f9f9;
        }

        .action-btn {
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .view-btn {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .view-btn:hover {
            background-color: var(--primary);
            color: white;
        }

        .print-btn {
            background-color: rgba(108, 117, 125, 0.1);
            color: var(--dark);
        }

        .print-btn:hover {
            background-color: var(--dark);
            color: white;
        }

        .edit-btn {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success);
        }

        .edit-btn:hover {
            background-color: var(--success);
            color: white;
        }

        .delete-btn {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--warning);
        }

        .delete-btn:hover {
            background-color: var(--warning);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .empty-state i {
            font-size: 50px;
            color: var(--primary);
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .invoice-body {
                grid-template-columns: 1fr;
            }
            
            .invoice-actions {
                flex-wrap: wrap;
            }
            
            .action-btn {
                flex: 1;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="invoice-container">
                <div class="header">
                    <h1><i class="fas fa-file-invoice"></i> إدارة الفواتير</h1>
                    <a href="generate_invoice.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> فاتورة جديدة
                    </a>
                </div>

                <!-- صندوق البحث -->
                <div class="search-container">
                    <form method="GET" action="" id="searchForm">
                        <div style="position: relative;">
                            <input type="text" 
                                   name="search" 
                                   id="searchInput" 
                                   class="search-box" 
                                   placeholder="ابحث في الفواتير (رقم الفاتورة، اسم العميل، البريد الإلكتروني، المبلغ، الملاحظات...)" 
                                   value="<?= htmlspecialchars($search_term) ?>"
                                   autocomplete="off">
                            <i class="fas fa-search search-icon"></i>
                        </div>
                    </form>
                    <?php if (!empty($search_term)): ?>
                        <div class="search-results-info">
                            <p>نتائج البحث عن "<?= htmlspecialchars($search_term) ?>" - 
                               <?= count($invoices) ?> نتيجة
                               <a href="?" style="margin-right: 15px; color: var(--primary);">عرض الكل</a>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($invoices)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <?php if (!empty($search_term)): ?>
                            <h2>لا توجد نتائج للبحث</h2>
                            <p>لم يتم العثور على فواتير تطابق "<?= htmlspecialchars($search_term) ?>"</p>
                            <a href="?" class="btn btn-primary">
                                <i class="fas fa-list"></i> عرض جميع الفواتير
                            </a>
                        <?php else: ?>
                            <h2>لا توجد فواتير مسجلة</h2>
                            <p>لم يتم إنشاء أي فواتير بعد. يمكنك البدء بإنشاء فاتورة جديدة.</p>
                            <a href="generate_invoice.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> إنشاء فاتورة جديدة
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <div class="invoice-card">
                            <div class="invoice-header">
                                <div class="invoice-number"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                                <div class="invoice-status status-<?= $invoice['status'] ?>">
                                    <?php 
                                    echo match($invoice['status']) {
                                        'draft' => 'مسودة',
                                        'paid' => 'مدفوعة',
                                        'pending' => 'قيد الانتظار',
                                        default => 'ملغاة'
                                    };
                                    ?>
                                </div>
                            </div>
                            
                            <div class="invoice-body">
                                <div class="invoice-detail">
                                    <div class="detail-label">العميل</div>
                                    <div class="detail-value">
                                        <?= !empty($invoice['customer_name']) ? htmlspecialchars($invoice['customer_name']) : 'عميل نقدي' ?>
                                    </div>
                                </div>
                                
                                <div class="invoice-detail">
                                    <div class="detail-label">تاريخ الإصدار</div>
                                    <div class="detail-value">
                                        <?= date('Y/m/d', strtotime($invoice['issue_date'])) ?>
                                    </div>
                                </div>
                                
                                <div class="invoice-detail">
                                    <div class="detail-label">تاريخ الاستحقاق</div>
                                    <div class="detail-value">
                                        <?= date('Y/m/d', strtotime($invoice['due_date'])) ?>
                                    </div>
                                </div>
                                
                                <div class="invoice-detail">
                                    <div class="detail-label">المبلغ الإجمالي</div>
                                    <div class="detail-value">
                                        <?= number_format($invoice['total_amount'], 2) ?> د.ل
                                    </div>
                                </div>
                                
                                <div class="invoice-detail">
                                    <div class="detail-label">أنشئت بواسطة</div>
                                    <div class="detail-value">
                                        <?= htmlspecialchars($invoice['created_by_name'] ?? 'غير معروف') ?>
                                    </div>
                                </div>
                                
                                <div class="invoice-detail">
                                    <div class="detail-label">ملاحظات</div>
                                    <div class="detail-value">
                                        <?= !empty($invoice['notes']) ? htmlspecialchars($invoice['notes']) : 'لا توجد ملاحظات' ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="invoice-actions">
                                <a href="view_invoice.php?id=<?= $invoice['invoice_id'] ?>" class="action-btn view-btn">
                                    <i class="fas fa-eye"></i> عرض
                                </a>
                                <a href="print_invoice.php?id=<?= $invoice['invoice_id'] ?>" class="action-btn print-btn" target="_blank">
                                    <i class="fas fa-print"></i> طباعة
                                </a>
                                <a href="edit_invoice.php?id=<?= $invoice['invoice_id'] ?>" class="action-btn edit-btn">
                                    <i class="fas fa-edit"></i> تعديل
                                </a>
                                <a href="#" class="action-btn delete-btn" onclick="confirmDelete(<?= $invoice['invoice_id'] ?>)">
                                    <i class="fas fa-trash-alt"></i> حذف
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function confirmDelete(invoiceId) {
            if (confirm('هل أنت متأكد من رغبتك في حذف هذه الفاتورة؟ لا يمكن التراجع عن هذه العملية.')) {
                window.location.href = 'delete_invoice.php?id=' + invoiceId;
            }
        }

        // البحث الديناميكي أثناء الكتابة
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const searchForm = document.getElementById('searchForm');
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    if (searchInput.value.trim().length >= 2 || searchInput.value.trim().length === 0) {
                        searchForm.submit();
                    }
                }, 500); // تأخير 500 مللي ثانية قبل إرسال النموذج
            });
            
            // السماح بالبحث بالضغط على Enter مباشرة
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimeout);
                    searchForm.submit();
                }
            });
        });
    </script>
</body>
</html>