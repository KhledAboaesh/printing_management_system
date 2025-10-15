<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// ✅ التحقق من الجلسة والدور
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'workshop') {
    header("Location: login.php");
    exit();
}

define('INCLUDED', true);

// متغيرات لتخزين البيانات
$invoices = [];
$stats = [
    'total'     => 0,
    'pending'   => 0,
    'in_progress' => 0,
    'completed'  => 0,
    'cancelled' => 0
];

try {
    // ✅ جلب الفواتير المسندة للورشة الحالية فقط
    $stmt = $db->prepare("
        SELECT i.*, 
               c.name AS customer_name, 
               c.company_name, 
               u.full_name AS created_by_name,
               ua.full_name AS assigned_by_name,
               iw.workshop_status, 
               iw.workshop_notes, 
               iw.assigned_at, 
               iw.estimated_completion,
               ia.notes AS assignment_notes
        FROM invoice_workshop iw
        JOIN invoices i ON iw.invoice_id = i.invoice_id
        JOIN customers c ON i.customer_id = c.customer_id
        JOIN users u ON i.created_by = u.user_id
        JOIN users ua ON iw.assigned_by = ua.user_id
        LEFT JOIN invoice_assignments ia ON i.invoice_id = ia.invoice_id
        WHERE iw.workshop_id = ? 
          AND i.status != 'cancelled'
        ORDER BY iw.assigned_at DESC, i.issue_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ حساب الإحصائيات
    foreach ($invoices as $invoice) {
        $stats['total']++;
        $status = $invoice['workshop_status'] ?? 'pending';
        if ($status === 'pending')       $stats['pending']++;
        elseif ($status === 'in_progress') $stats['in_progress']++;
        elseif ($status === 'completed')   $stats['completed']++;
        elseif ($status === 'cancelled')   $stats['cancelled']++;
    }

} catch (PDOException $e) {
    $error = "خطأ في جلب بيانات الفواتير: " . $e->getMessage();
}

// ✅ معالجة تحديث حالة الفاتورة في الورشة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $invoice_id = (int) $_POST['invoice_id'];
        $action     = $_POST['action'];
        $notes      = trim($_POST['workshop_notes'] ?? '');
        $completion_date = $_POST['estimated_completion'] ?? null;

        // التحقق من أن الفاتورة مسندة للورشة الحالية
        $stmt = $db->prepare("
            SELECT * FROM invoice_workshop 
            WHERE invoice_id = ? AND workshop_id = ?
        ");
        $stmt->execute([$invoice_id, $_SESSION['user_id']]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assignment) {
            throw new Exception("ليس لديك صلاحية للتعامل مع هذه الفاتورة");
        }

        // تحديث حالة الورشة
        $stmt = $db->prepare("
            UPDATE invoice_workshop 
            SET workshop_status = ?, 
                workshop_notes = ?, 
                estimated_completion = ?,
                updated_at = NOW() 
            WHERE invoice_id = ? AND workshop_id = ?
        ");
        $stmt->execute([$action, $notes, $completion_date, $invoice_id, $_SESSION['user_id']]);

        // إذا كانت الحالة مكتملة، تحديث حالة الفاتورة الرئيسية
        if ($action === 'completed') {
            $stmt = $db->prepare("
                UPDATE invoices 
                SET status = 'completed' 
                WHERE invoice_id = ?
            ");
            $stmt->execute([$invoice_id]);
        }

        // تسجيل النشاط
        logActivity($_SESSION['user_id'], 'update_workshop_status', 
            "تم تحديث حالة الفاتورة #$invoice_id في الورشة إلى: $action"
        );

        // إعادة تحميل الصفحة بعد التحديث
        header("Location: workshop_dashboard.php?updated=1");
        exit();

    } catch (Exception $e) {
        $error = "حدث خطأ أثناء تحديث حالة الفاتورة: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم الورشة - نظام المطبعة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .dashboard-container {
            padding: 20px;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            text-align: center;
        }
        
        .stat-card.total { border-top: 4px solid #4361EE; }
        .stat-card.pending { border-top: 4px solid #FF9F1C; }
        .stat-card.in-progress { border-top: 4px solid #4361EE; }
        .stat-card.completed { border-top: 4px solid #2EC4B6; }
        .stat-card.cancelled { border-top: 4px solid #6C757D; }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-title {
            color: #6C757D;
            font-size: 0.9rem;
        }
        
        .invoices-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .invoices-table th, 
        .invoices-table td {
            padding: 15px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }
        
        .invoices-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-pending { background: #FFF3CD; color: #856404; }
        .status-in-progress { background: #CCE5FF; color: #004085; }
        .status-completed { background: #D1ECF1; color: #0C5460; }
        .status-cancelled { background: #E2E3E5; color: #383D41; }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-start { background: #4361EE; color: white; }
        .btn-complete { background: #2EC4B6; color: white; }
        .btn-hold { background: #FF9F1C; color: white; }
        .btn-view { background: #6C757D; color: white; }
        
        .btn-sm:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 500px;
            max-width: 90%;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6C757D;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .assignment-info {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .completion-date {
            font-size: 0.8rem;
            color: #28a745;
            margin-top: 3px;
        }
        
        .priority-high { border-left: 4px solid #E71D36; }
        .priority-medium { border-left: 4px solid #FF9F1C; }
        .priority-low { border-left: 4px solid #2EC4B6; }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <h1>لوحة تحكم الورشة</h1>
                <?php include 'includes/user-menu.php'; ?>
            </div>
            
            <div class="dashboard-container">
                <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> تم تحديث حالة الفاتورة بنجاح
                </div>
                <?php endif; ?>
                
                <!-- بطاقات الإحصائيات -->
                <div class="stats-cards">
                    <div class="stat-card total">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-title">إجمالي المهام</div>
                        <i class="fas fa-tasks stat-icon"></i>
                    </div>
                    
                    <div class="stat-card pending">
                        <div class="stat-number"><?php echo $stats['pending']; ?></div>
                        <div class="stat-title">في الانتظار</div>
                        <i class="fas fa-clock stat-icon"></i>
                    </div>
                    
                    <div class="stat-card in-progress">
                        <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
                        <div class="stat-title">قيد التنفيذ</div>
                        <i class="fas fa-cog stat-icon"></i>
                    </div>
                    
                    <div class="stat-card completed">
                        <div class="stat-number"><?php echo $stats['completed']; ?></div>
                        <div class="stat-title">مكتملة</div>
                        <i class="fas fa-check-circle stat-icon"></i>
                    </div>
                </div>
                
                <!-- جدول الفواتير -->
                <h2>المهام المسندة إلى الورشة</h2>
                <div class="table-responsive">
                    <table class="invoices-table">
                        <thead>
                            <tr>
                                <th>رقم الفاتورة</th>
                                <th>العميل</th>
                                <th>تاريخ الإصدار</th>
                                <th>تاريخ الإرسال</th>
                                <th>مرسل بواسطة</th>
                                <th>المبلغ</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px;">
                                    <i class="fas fa-inbox" style="font-size: 3rem; color: #dee2e6; margin-bottom: 15px;"></i>
                                    <p>لا توجد مهام مسندة إلى الورشة بعد</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $invoice): 
                                    $status = $invoice['workshop_status'] ?? 'pending';
                                    $statusClass = "status-{$status}";
                                ?>
                                <tr class="priority-<?php echo $invoice['priority'] ?? 'medium'; ?>">
                                    <td>
                                        <?php echo $invoice['invoice_number']; ?>
                                        <?php if (!empty($invoice['estimated_completion'])): ?>
                                        <div class="completion-date">
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php echo date('Y-m-d', strtotime($invoice['estimated_completion'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($invoice['customer_name']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($invoice['company_name'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo $invoice['issue_date']; ?></td>
                                    <td>
                                        <?php echo date('Y-m-d', strtotime($invoice['assigned_at'])); ?>
                                        <div class="assignment-info">
                                            <?php echo date('H:i', strtotime($invoice['assigned_at'])); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($invoice['assigned_by_name']); ?></td>
                                    <td><?php echo number_format($invoice['total_amount'], 2); ?> د.ل</td>
                                    <td>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php 
                                            $statusText = [
                                                'pending' => 'في الانتظار',
                                                'in_progress' => 'قيد التنفيذ',
                                                'completed' => 'مكتملة',
                                                'cancelled' => 'ملغاة'
                                            ];
                                            echo $statusText[$status] ?? $status;
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-sm btn-view" onclick="viewInvoice(<?php echo $invoice['invoice_id']; ?>)">
                                                <i class="fas fa-eye"></i> عرض
                                            </button>
                                            
                                            <?php if ($status === 'pending'): ?>
                                            <button class="btn-sm btn-start" onclick="openModal(<?php echo $invoice['invoice_id']; ?>, 'in_progress')">
                                                <i class="fas fa-play"></i> بدء
                                            </button>
                                            <?php elseif ($status === 'in_progress'): ?>
                                            <button class="btn-sm btn-complete" onclick="openModal(<?php echo $invoice['invoice_id']; ?>, 'completed')">
                                                <i class="fas fa-check"></i> إكمال
                                            </button>
                                            <button class="btn-sm btn-hold" onclick="openModal(<?php echo $invoice['invoice_id']; ?>, 'pending')">
                                                <i class="fas fa-pause"></i> إيقاف
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal لتحديث حالة المهمة في الورشة -->
    <div class="modal" id="statusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">تحديث حالة المهمة</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            
            <form id="statusForm" method="POST">
                <input type="hidden" name="invoice_id" id="modalInvoiceId">
                <input type="hidden" name="action" id="modalAction">
                
                <div class="form-group">
                    <label for="estimated_completion" class="form-label">التاريخ المتوقع للإنجاز</label>
                    <input type="date" name="estimated_completion" id="estimated_completion" class="form-control"
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="workshop_notes" class="form-label">ملاحظات الورشة</label>
                    <textarea name="workshop_notes" id="workshop_notes" class="form-control" rows="4" 
                              placeholder="أضف ملاحظاتك حول سير العمل (اختياري)"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">إلغاء</button>
                    <button type="submit" class="btn btn-primary" id="modalSubmitBtn">تأكيد</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // فتح modal لتحديث حالة المهمة
        function openModal(invoiceId, action) {
            const modal = document.getElementById('statusModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalInvoiceId = document.getElementById('modalInvoiceId');
            const modalAction = document.getElementById('modalAction');
            const modalSubmitBtn = document.getElementById('modalSubmitBtn');
            
            modalInvoiceId.value = invoiceId;
            modalAction.value = action;
            
            // تحديث النص بناءً على الإجراء
            const actionTexts = {
                'in_progress': 'بدء التنفيذ',
                'completed': 'إكمال المهمة',
                'pending': 'إيقاف المهمة'
            };
            
            modalTitle.textContent = actionTexts[action] + ' - الفاتورة #' + invoiceId;
            modalSubmitBtn.innerHTML = '<i class="fas fa-check"></i> ' + actionTexts[action];
            modalSubmitBtn.className = 'btn btn-primary';
            
            // إظهار/إخفاء حقل التاريخ المتوقع
            const completionField = document.getElementById('estimated_completion');
            if (action === 'in_progress') {
                completionField.style.display = 'block';
                completionField.previousElementSibling.style.display = 'block';
            } else {
                completionField.style.display = 'none';
                completionField.previousElementSibling.style.display = 'none';
            }
            
            modal.style.display = 'flex';
        }
        
        // إغلاق modal
        function closeModal() {
            document.getElementById('statusModal').style.display = 'none';
            document.getElementById('workshop_notes').value = '';
            document.getElementById('estimated_completion').value = '';
        }
        
        // عرض الفاتورة
        function viewInvoice(invoiceId) {
            window.open('view_invoice.php?id=' + invoiceId, '_blank');
        }
        
        // إغلاق modal عند النقر خارج المحتوى
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target === modal) {
                closeModal();
            }
        });
        
        // تعيين الحد الأدنى لتاريخ الإنجاز إلى اليوم
        document.getElementById('estimated_completion').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>