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

// تهيئة المتغيرات لتجنب التحذيرات
$message = '';
$invoices = [];
$recent_payments = [];
$collection_expenses = [];
$due_invoices = [];

// معالجة عمليات الإضافة والحذف
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_payment'])) {
        // إضافة دفعة جديدة
        $invoice_id = $_POST['invoice_id'];
        $amount = $_POST['amount'];
        $payment_method = $_POST['payment_method'];
        $notes = $_POST['notes'] ?? '';

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("INSERT INTO invoice_payments (invoice_id, amount, payment_method, notes, received_by, created_at) 
                                 VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$invoice_id, $amount, $payment_method, $notes, $_SESSION['user_id']]);

            // تحديث حالة الفاتورة إذا تم سدادها بالكامل
            $stmt = $db->prepare("UPDATE invoices SET status = 'paid' 
                                 WHERE invoice_id = ? AND total_amount <= 
                                 (SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE invoice_id = ?)");
            $stmt->execute([$invoice_id, $invoice_id]);

            $db->commit();
            $message = "تم إضافة الدفعة بنجاح";
            logActivity($_SESSION['user_id'], 'add_payment', "تم إضافة دفعة للفاتورة رقم $invoice_id");

        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Payment Error: ' . $e->getMessage());
            $message = "حدث خطأ أثناء إضافة الدفعة: " . $e->getMessage();
        }
    }

    if (isset($_POST['delete_payment'])) {
        $payment_id = $_POST['payment_id'];

        try {
            $stmt = $db->prepare("DELETE FROM invoice_payments WHERE payment_id = ?");
            $stmt->execute([$payment_id]);

            $message = "تم حذف الدفعة بنجاح";
            logActivity($_SESSION['user_id'], 'delete_payment', "تم حذف الدفعة رقم $payment_id");

        } catch (PDOException $e) {
            error_log('Delete Payment Error: ' . $e->getMessage());
            $message = "حدث خطأ أثناء حذف الدفعة: " . $e->getMessage();
        }
    }
}

// جلب البيانات للعرض
try {
    // الفواتير مع المدفوعات
    $stmt = $db->query("
        SELECT i.invoice_id, i.invoice_number, i.total_amount, i.issue_date, i.due_date, i.status,
               c.name as customer_name,
               COALESCE(SUM(ip.amount), 0) as paid_amount,
               (i.total_amount - COALESCE(SUM(ip.amount), 0)) as due_amount
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.customer_id
        LEFT JOIN invoice_payments ip ON i.invoice_id = ip.invoice_id
        GROUP BY i.invoice_id
        ORDER BY i.issue_date DESC
    ");
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // المدفوعات الأخيرة
    $stmt = $db->query("
        SELECT ip.*, i.invoice_number, u.username as received_by_name
        FROM invoice_payments ip
        JOIN invoices i ON ip.invoice_id = i.invoice_id
        JOIN users u ON ip.received_by = u.user_id
        ORDER BY ip.created_at DESC
        LIMIT 10
    ");
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // مصاريف التحصيل
    $stmt = $db->query("
        SELECT e.*, a.name as account_name, u.username as paid_by_name
        FROM expenses e
        JOIN accounts a ON e.account_id = a.account_id
        JOIN users u ON e.paid_by = u.user_id
        WHERE e.description LIKE '%تحصيل%' OR e.description LIKE '%مدفوعات%'
        ORDER BY e.date DESC
        LIMIT 5
    ");
    $collection_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // الفواتير المستحقة
    $stmt = $db->prepare("
        SELECT i.invoice_id, i.invoice_number, i.total_amount, i.due_date, c.name as customer_name,
               (i.total_amount - COALESCE(SUM(ip.amount), 0)) as due_amount
        FROM invoices i
        JOIN customers c ON i.customer_id = c.customer_id
        LEFT JOIN invoice_payments ip ON i.invoice_id = ip.invoice_id
        WHERE i.status != 'paid' AND i.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        GROUP BY i.invoice_id
        HAVING due_amount > 0
        ORDER BY i.due_date ASC
    ");
    $stmt->execute();
    $due_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

} catch (PDOException $e) {
    error_log('Payments Page Error: ' . $e->getMessage());
    $message = "حدث خطأ في جلب البيانات من النظام";
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - مدفوعات الفواتير</title>
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
            flex: 1;
            text-align: center;
            transition: background-color 0.3s;
        }
        
        .tab.active {
            background-color: var(--primary-color);
            color: white;
            font-weight: bold;
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
        
        .card-title {
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
            color: var(--secondary-color);
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
            background-color: rgba(0, 0, 0, 0.03);
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-paid {
            background-color: var(--success-color);
            color: white;
        }
        
        .status-pending {
            background-color: var(--warning-color);
            color: white;
        }
        
        .status-overdue {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-success {
            background-color: var(--success-color);
        }
        
        .form-group {
            margin-bottom: 15px;
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
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .amount-due {
            font-weight: bold;
        }
        
        .amount-paid {
            color: var(--success-color);
            font-weight: bold;
        }
        
        .amount-total {
            font-weight: bold;
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
        
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            th, td {
                padding: 8px;
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
                    <div style="font-size: 12px; color: #777;">المحاسبة</div>
                </div>
            </div>
        </header>
        
        <h1 class="page-title">إدارة مدفوعات الفواتير</h1>
        
        <?php if (!empty($message)): ?>
            <div class="alert <?php echo strpos($message, 'خطأ') !== false ? 'alert-danger' : 'alert-success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('invoices')">الفواتير والمدفوعات</div>
            <div class="tab" onclick="switchTab('add-payment')">تسجيل دفعة جديدة</div>
            <div class="tab" onclick="switchTab('due-payments')">المستحقات</div>
            <div class="tab" onclick="switchTab('collection-expenses')">مصاريف التحصيل</div>
        </div>
        
        <div id="invoices" class="tab-content active">
            <div class="card">
                <h2 class="card-title">الفواتير والمدفوعات</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>رقم الفاتورة</th>
                                <th>العميل</th>
                                <th>تاريخ الإصدار</th>
                                <th>تاريخ الاستحقاق</th>
                                <th>المبلغ الإجمالي</th>
                                <th>المبلغ المدفوع</th>
                                <th>المبلغ المتبقي</th>
                                <th>الحالة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): 
                                $due_date = new DateTime($invoice['due_date']);
                                $today = new DateTime();
                                $is_overdue = $due_date < $today && $invoice['status'] != 'paid';
                            ?>
                            <tr>
                                <td><?php echo $invoice['invoice_number']; ?></td>
                                <td><?php echo $invoice['customer_name']; ?></td>
                                <td><?php echo $invoice['issue_date']; ?></td>
                                <td><?php echo $invoice['due_date']; ?></td>
                                <td class="amount-total"><?php echo number_format($invoice['total_amount'], 2); ?> د.ل</td>
                                <td class="amount-paid"><?php echo number_format($invoice['paid_amount'], 2); ?> د.ل</td>
                                <td class="amount-due"><?php echo number_format($invoice['due_amount'], 2); ?> د.ل</td>
                                <td>
                                    <?php if ($invoice['status'] == 'paid'): ?>
                                        <span class="status-badge status-paid">مدفوعة</span>
                                    <?php elseif ($is_overdue): ?>
                                        <span class="status-badge status-overdue">متأخرة</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">قيد الانتظار</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn" onclick="showPayments(<?php echo $invoice['invoice_id']; ?>)">عرض المدفوعات</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="card">
                <h2 class="card-title">آخر المدفوعات</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>رقم الفاتورة</th>
                                <th>المبلغ</th>
                                <th>طريقة الدفع</th>
                                <th>تاريخ الدفع</th>
                                <th>بواسطة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_payments as $payment): ?>
                            <tr>
                                <td><?php echo $payment['invoice_number']; ?></td>
                                <td class="amount-paid"><?php echo number_format($payment['amount'], 2); ?> د.ل</td>
                                <td><?php echo $payment['payment_method']; ?></td>
                                <td><?php echo $payment['created_at']; ?></td>
                                <td><?php echo $payment['received_by_name']; ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="payment_id" value="<?php echo $payment['payment_id']; ?>">
                                        <button type="submit" name="delete_payment" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذه الدفعة؟')">حذف</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div id="add-payment" class="tab-content">
            <div class="card">
                <h2 class="card-title">تسجيل دفعة جديدة</h2>
                <form method="POST">
                    <div class="grid">
                        <div class="form-group">
                            <label for="invoice_id">اختر الفاتورة</label>
                            <select class="form-control" id="invoice_id" name="invoice_id" required>
                                <option value="">-- اختر الفاتورة --</option>
                                <?php foreach ($invoices as $invoice): 
                                    if ($invoice['due_amount'] > 0): ?>
                                    <option value="<?php echo $invoice['invoice_id']; ?>">
                                        <?php echo $invoice['invoice_number'] . ' - ' . $invoice['customer_name'] . ' (متبقي: ' . number_format($invoice['due_amount'], 2) . ' د.ل)'; ?>
                                    </option>
                                <?php endif; endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">المبلغ</label>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" required>
                        </div>
                    </div>
                    
                    <div class="grid">
                        <div class="form-group">
                            <label for="payment_method">طريقة الدفع</label>
                            <select class="form-control" id="payment_method" name="payment_method" required>
                                <option value="نقدي">نقدي</option>
                                <option value="تحويل بنكي">تحويل بنكي</option>
                                <option value="شيك">شيك</option>
                                <option value="بطاقة ائتمان">بطاقة ائتمان</option>
                                <option value="أخرى">أخرى</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">ملاحظات (اختياري)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_payment" class="btn btn-success">تسجيل الدفعة</button>
                </form>
            </div>
        </div>
        
        <div id="due-payments" class="tab-content">
            <div class="card">
                <h2 class="card-title">الفواتير المستحقة القريبة</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>رقم الفاتورة</th>
                                <th>العميل</th>
                                <th>تاريخ الاستحقاق</th>
                                <th>المبلغ المستحق</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($due_invoices as $invoice): 
                                $due_date = new DateTime($invoice['due_date']);
                                $today = new DateTime();
                                $diff = $today->diff($due_date);
                                $days = $diff->days;
                                $is_past = $due_date < $today;
                            ?>
                            <tr>
                                <td><?php echo $invoice['invoice_number']; ?></td>
                                <td><?php echo $invoice['customer_name']; ?></td>
                                <td>
                                    <?php echo $invoice['due_date']; ?>
                                    <?php if ($is_past): ?>
                                        <span class="status-badge status-overdue">متأخرة <?php echo $days; ?> يوم</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">متبقي <?php echo $days; ?> يوم</span>
                                    <?php endif; ?>
                                </td>
                                <td class="amount-due"><?php echo number_format($invoice['due_amount'], 2); ?> د.ل</td>
                                <td>
                                    <button class="btn" onclick="document.getElementById('invoice_id').value='<?php echo $invoice['invoice_id']; ?>'; switchTab('add-payment');">تسديد الآن</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div id="collection-expenses" class="tab-content">
            <div class="card">
                <h2 class="card-title">مصاريف التحصيل</h2>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الحساب</th>
                                <th>المبلغ</th>
                                <th>الوصف</th>
                                <th>بواسطة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($collection_expenses as $expense): ?>
                            <tr>
                                <td><?php echo $expense['date']; ?></td>
                                <td><?php echo $expense['account_name']; ?></td>
                                <td class="amount-due"><?php echo number_format($expense['amount'], 2); ?> د.ل</td>
                                <td><?php echo $expense['description']; ?></td>
                                <td><?php echo $expense['paid_by_name']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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
                if (tab.textContent.trim() === document.querySelector(`#${tabName} .card-title`).textContent.trim() || 
                    (tabName === 'invoices' && tab.textContent.includes('الفواتير')) ||
                    (tabName === 'add-payment' && tab.textContent.includes('تسجيل')) ||
                    (tabName === 'due-payments' && tab.textContent.includes('المستحقات')) ||
                    (tabName === 'collection-expenses' && tab.textContent.includes('مصاريف'))) {
                    tab.classList.add('active');
                }
            });
        }
        
        function showPayments(invoiceId) {
            // هنا يمكنك إضافة وظيفة لعرض مدفوعات فاتورة معينة
            alert("عرض مدفوعات الفاتورة: " + invoiceId);
            // في التطبيق الحقيقي، يمكن أن تفتح نافذة منبثقة أو تظهر قسمًا معينًا
        }
        
        // تحديث قائمة الفواتير عند تغيير طريقة الدفع
        document.getElementById('payment_method').addEventListener('change', function() {
            if (this.value === 'بطاقة ائتمان') {
                alert('يرجى التأكد من توفر جهاز الصرف الآلي وتفعيله قبل إتمام عملية الدفع ببطاقة الائتمان');
            }
        });
    </script>
</body>
</html>