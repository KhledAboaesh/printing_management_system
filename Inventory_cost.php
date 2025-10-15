<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (المحاسبة أو الإدارة أو المخزون)
if ($_SESSION['role'] != 'accounting' && $_SESSION['role'] != 'admin' && $_SESSION['role'] != 'inventory') {
    header("Location: unauthorized.php");
    exit();
}

// تهيئة المتغيرات لتجنب الأخطاء
$inventory_items = [];
$transactions = [];
$cost_data = [];
$categories = [];
$total_inventory_value = 0;
$total_cogs = 0;
$low_stock_count = 0;
$message = '';
$error = '';

// معالجة عمليات الإضافة والتعديل
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add_item'])) {
            // إضافة صنف جديد
            $stmt = $db->prepare("INSERT INTO inventory (name, description, category_id, quantity, unit_price, min_stock_level) 
                                 VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['name'],
                $_POST['description'],
                $_POST['category_id'],
                $_POST['quantity'],
                $_POST['unit_price'],
                $_POST['min_stock_level']
            ]);

            // تسجيل حركة المخزون
            $item_id = $db->lastInsertId();
            $stmt = $db->prepare("INSERT INTO inventory_transactions (item_id, transaction_type, quantity, performed_by, notes) 
                                 VALUES (?, 'initial', ?, ?, 'إضافة صنف جديد')");
            $stmt->execute([$item_id, $_POST['quantity'], $_SESSION['user_id']]);

            $message = "تم إضافة الصنف بنجاح";

        } elseif (isset($_POST['update_item'])) {
            // تحديث الصنف
            $stmt = $db->prepare("UPDATE inventory SET name=?, description=?, category_id=?, unit_price=?, min_stock_level=? 
                                 WHERE item_id=?");
            $stmt->execute([
                $_POST['name'],
                $_POST['description'],
                $_POST['category_id'],
                $_POST['unit_price'],
                $_POST['min_stock_level'],
                $_POST['item_id']
            ]);

            $message = "تم تحديث الصنف بنجاح";

        } elseif (isset($_POST['adjust_stock'])) {
            // تعديل كمية المخزون
            $stmt = $db->prepare("UPDATE inventory SET quantity = quantity + ? WHERE item_id = ?");
            $stmt->execute([$_POST['adjust_quantity'], $_POST['item_id']]);

            // تسجيل حركة المخزون
            $transaction_type = $_POST['adjust_quantity'] > 0 ? 'in' : 'out';
            $stmt = $db->prepare("INSERT INTO inventory_transactions (item_id, transaction_type, quantity, performed_by, notes) 
                                 VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['item_id'],
                $transaction_type,
                abs($_POST['adjust_quantity']),
                $_SESSION['user_id'],
                $_POST['adjust_notes']
            ]);

            $message = "تم تعديل المخزون بنجاح";
        }

        // تسجيل النشاط
        logActivity($_SESSION['user_id'], 'inventory_operation', 'عملية على المخزون: ' . $message);

    } catch (PDOException $e) {
        error_log('Inventory Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء عملية المخزون: " . $e->getMessage();
    }
}

// جلب بيانات المخزون
try {
    // جلب أصناف المخزون
    $stmt = $db->query("
        SELECT i.*, c.name as category_name, 
               (i.quantity * i.unit_price) as total_value,
               CASE WHEN i.quantity <= i.min_stock_level THEN 'low' ELSE 'normal' END as stock_status
        FROM inventory i 
        LEFT JOIN product_categories c ON i.category_id = c.category_id
        ORDER BY i.name
    ");
    $inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // جلب حركة المخزون
    $stmt = $db->query("
        SELECT t.*, i.name as item_name, u.username as performed_by_name
        FROM inventory_transactions t
        JOIN inventory i ON t.item_id = i.item_id
        JOIN users u ON t.performed_by = u.user_id
        ORDER BY t.transaction_date DESC
        LIMIT 50
    ");
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // جلب التكاليف
    $stmt = $db->query("
        SELECT i.item_id, i.name, 
               SUM(CASE WHEN t.transaction_type = 'out' THEN t.quantity ELSE 0 END) as sold_quantity,
               SUM(CASE WHEN t.transaction_type = 'out' THEN t.quantity * i.unit_price ELSE 0 END) as cogs
        FROM inventory i
        LEFT JOIN inventory_transactions t ON i.item_id = t.item_id
        GROUP BY i.item_id, i.name
    ");
    $cost_data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // جلب الفئات
    $stmt = $db->query("SELECT * FROM product_categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // حساب إجماليات
    foreach ($inventory_items as $item) {
        $total_inventory_value += $item['total_value'] ?? 0;
        if (($item['stock_status'] ?? '') == 'low') {
            $low_stock_count++;
        }
    }

    foreach ($cost_data as $item) {
        $total_cogs += $item['cogs'] ?? 0;
    }

} catch (PDOException $e) {
    error_log('Inventory Data Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب بيانات المخزون";
}
?>


<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - إدارة المخزون والتكلفة</title>
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
        
        .inventory-icon { color: var(--primary-color); }
        .value-icon { color: var(--success-color); }
        .cogs-icon { color: var(--warning-color); }
        .low-stock-icon { color: var(--danger-color); }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #777;
            font-size: 16px;
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
            padding: 10px 15px;
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
        
        .btn-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .low-stock {
            background-color: #fff4f4;
        }
        
        .low-stock:hover {
            background-color: #ffe6e6;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            width: 500px;
            max-width: 90%;
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
            cursor: pointer;
        }
        
        .message {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .success {
            background-color: #eaffea;
            color: #2ecc71;
            border: 1px solid #2ecc71;
        }
        
        .error {
            background-color: #ffebee;
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }
        
        .search-box {
            display: flex;
            margin-bottom: 20px;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .tabs {
                flex-direction: column;
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
                    <div style="font-size: 12px; color: #777;">إدارة المخزون</div>
                </div>
            </div>
        </header>
        
        <h1 class="dashboard-title">إدارة المخزون والتكلفة</h1>
        
        <?php if (!empty($message)): ?>
        <div class="message <?php echo strpos($message, 'خطأ') !== false ? 'error' : 'success'; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon inventory-icon">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-value"><?php echo count($inventory_items); ?></div>
                <div class="stat-label">أصناف المخزون</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon value-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value"><?php echo number_format($total_inventory_value, 2); ?> د.ل</div>
                <div class="stat-label">القيمة الإجمالية للمخزون</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon cogs-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="stat-value"><?php echo number_format($total_cogs, 2); ?> د.ل</div>
                <div class="stat-label">تكلفة البضاعة المباعة</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon low-stock-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value"><?php echo $low_stock_count; ?></div>
                <div class="stat-label">أصناف تحت مستوى المخزون الأدنى</div>
            </div>
        </div>
        
        <div class="tabs">
            <div class="tab active" onclick="openTab('inventory')">إدارة المخزون</div>
            <div class="tab" onclick="openTab('transactions')">حركة المخزون</div>
            <div class="tab" onclick="openTab('costing')">التكاليف</div>
            <div class="tab" onclick="openTab('reports')">تقارير المخزون</div>
        </div>
        
        <!-- تبويب إدارة المخزون -->
        <div id="inventory" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">أصناف المخزون</h2>
                    <button class="btn btn-primary" onclick="openModal('addItemModal')">
                        <i class="fas fa-plus"></i> إضافة صنف جديد
                    </button>
                </div>
                
                <div class="search-box">
                    <input type="text" id="searchInventory" placeholder="بحث في المخزون..." onkeyup="filterInventory()">
                    <select id="categoryFilter" onchange="filterInventory()">
                        <option value="">جميع الفئات</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>"><?php echo $category['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <table id="inventoryTable">
                    <thead>
                        <tr>
                            <th>اسم الصنف</th>
                            <th>الفئة</th>
                            <th>الكمية</th>
                            <th>سعر الوحدة</th>
                            <th>القيمة</th>
                            <th>أدنى مستوى</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory_items as $item): ?>
                        <tr class="<?php echo $item['stock_status'] == 'low' ? 'low-stock' : ''; ?>">
                            <td><?php echo $item['name']; ?></td>
                            <td><?php echo $item['category_name']; ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo number_format($item['unit_price'], 2); ?> د.ل</td>
                            <td><?php echo number_format($item['total_value'], 2); ?> د.ل</td>
                            <td><?php echo $item['min_stock_level']; ?></td>
                            <td>
                                <?php if ($item['stock_status'] == 'low'): ?>
                                <span style="color: var(--danger-color);">
                                    <i class="fas fa-exclamation-circle"></i> منخفض
                                </span>
                                <?php else: ?>
                                <span style="color: var(--success-color);">
                                    <i class="fas fa-check-circle"></i> جيد
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-primary" onclick="openEditModal(<?php echo $item['item_id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-success" onclick="openAdjustModal(<?php echo $item['item_id']; ?>, '<?php echo $item['name']; ?>')">
                                    <i class="fas fa-adjust"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- تبويب حركة المخزون -->
        <div id="transactions" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">حركة المخزون</h2>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>الصنف</th>
                            <th>نوع الحركة</th>
                            <th>الكمية</th>
                            <th>منفذ الحركة</th>
                            <th>ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo $transaction['transaction_date']; ?></td>
                            <td><?php echo $transaction['item_name']; ?></td>
                            <td>
                                <?php 
                                $type = $transaction['transaction_type'];
                                $badge_color = $type == 'in' ? 'success' : ($type == 'out' ? 'danger' : 'primary');
                                $type_text = $type == 'in' ? 'إضافة' : ($type == 'out' ? 'سحب' : 'تعديل');
                                ?>
                                <span class="badge badge-<?php echo $badge_color; ?>"><?php echo $type_text; ?></span>
                            </td>
                            <td><?php echo $transaction['quantity']; ?></td>
                            <td><?php echo $transaction['performed_by_name']; ?></td>
                            <td><?php echo $transaction['notes']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- تبويب التكاليف -->
        <div id="costing" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">تكلفة البضاعة المباعة</h2>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>اسم الصنف</th>
                            <th>الكمية المباعة</th>
                            <th>سعر الوحدة</th>
                            <th>التكلفة الإجمالية</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cost_data as $item): ?>
                        <tr>
                            <td><?php echo $item['name']; ?></td>
                            <td><?php echo $item['sold_quantity']; ?></td>
                            <td><?php echo number_format($item['cogs'] / max($item['sold_quantity'], 1), 2); ?> د.ل</td>
                            <td><?php echo number_format($item['cogs'], 2); ?> د.ل</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight: bold;">
                            <td colspan="3">الإجمالي</td>
                            <td><?php echo number_format($total_cogs, 2); ?> د.ل</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <!-- تبويب التقارير -->
        <div id="reports" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">تقارير المخزون</h2>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div class="card">
                        <h3>تقرير المخزون المنخفض</h3>
                        <p>عدد الأصناف تحت المستوى الأدنى: <strong><?php echo $low_stock_count; ?></strong></p>
                        <button class="btn btn-primary">تحميل التقرير</button>
                    </div>
                    
                    <div class="card">
                        <h3>تقرير حركة المخزون</h3>
                        <p>آخر 50 حركة للمخزون</p>
                        <button class="btn btn-primary">تحميل التقرير</button>
                    </div>
                    
                    <div class="card">
                        <h3>تقرير التكاليف</h3>
                        <p>تحليل تكلفة البضاعة المباعة</p>
                        <button class="btn btn-primary">تحميل التقرير</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal لإضافة صنف جديد -->
    <div id="addItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>إضافة صنف جديد</h2>
                <span class="close" onclick="closeModal('addItemModal')">&times;</span>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label for="name">اسم الصنف</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="description">الوصف</label>
                    <textarea id="description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="category_id">الفئة</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">اختر الفئة</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>"><?php echo $category['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="quantity">الكمية الأولية</label>
                    <input type="number" id="quantity" name="quantity" value="0" min="0" required>
                </div>
                <div class="form-group">
                    <label for="unit_price">سعر الوحدة (د.ل)</label>
                    <input type="number" id="unit_price" name="unit_price" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="min_stock_level">أدنى مستوى للمخزون</label>
                    <input type="number" id="min_stock_level" name="min_stock_level" value="5" min="0" required>
                </div>
                <button type="submit" name="add_item" class="btn btn-primary">إضافة الصنف</button>
            </form>
        </div>
    </div>
    
    <!-- Modal لتعديل الصنف -->
    <div id="editItemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>تعديل الصنف</h2>
                <span class="close" onclick="closeModal('editItemModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" id="edit_item_id" name="item_id">
                <div class="form-group">
                    <label for="edit_name">اسم الصنف</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit_description">الوصف</label>
                    <textarea id="edit_description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_category_id">الفئة</label>
                    <select id="edit_category_id" name="category_id" required>
                        <option value="">اختر الفئة</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>"><?php echo $category['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_unit_price">سعر الوحدة (د.ل)</label>
                    <input type="number" id="edit_unit_price" name="unit_price" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit_min_stock_level">أدنى مستوى للمخزون</label>
                    <input type="number" id="edit_min_stock_level" name="min_stock_level" min="0" required>
                </div>
                <button type="submit" name="update_item" class="btn btn-primary">تحديث الصنف</button>
            </form>
        </div>
    </div>
    
    <!-- Modal لتعديل الكمية -->
    <div id="adjustStockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>تعديل كمية المخزون</h2>
                <span class="close" onclick="closeModal('adjustStockModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" id="adjust_item_id" name="item_id">
                <div class="form-group">
                    <label id="adjust_item_name">اسم الصنف: </label>
                </div>
                <div class="form-group">
                    <label for="adjust_type">نوع التعديل</label>
                    <select id="adjust_type" onchange="updateAdjustLabel()">
                        <option value="in">إضافة إلى المخزون</option>
                        <option value="out">سحب من المخزون</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="adjust_quantity" id="quantity_label">الكمية المضافة</label>
                    <input type="number" id="adjust_quantity" name="adjust_quantity" min="1" required>
                </div>
                <div class="form-group">
                    <label for="adjust_notes">ملاحظات</label>
                    <textarea id="adjust_notes" name="adjust_notes" placeholder="سبب التعديل..."></textarea>
                </div>
                <button type="submit" name="adjust_stock" class="btn btn-primary">تطبيق التعديل</button>
            </form>
        </div>
    </div>

    <script>
        // فتح وإغلاق النوافذ المنبثقة
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // التبديل بين التبويبات
        function openTab(tabName) {
            const tabs = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            document.getElementById(tabName).classList.add('active');
            
            const tabButtons = document.getElementsByClassName('tab');
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }
            event.currentTarget.classList.add('active');
        }
        
        // فتح نافذة تعديل الصنف
        function openEditModal(itemId) {
            // هنا يجب جلب بيانات الصنف من الخادم باستخدام Ajax
            // للتبسيط، سنقوم بملئها يدوياً في مثال حقيقي
            closeModal('addItemModal');
            openModal('editItemModal');
        }
        
        // فتح نافذة تعديل الكمية
        function openAdjustModal(itemId, itemName) {
            document.getElementById('adjust_item_id').value = itemId;
            document.getElementById('adjust_item_name').textContent = 'اسم الصنف: ' + itemName;
            openModal('adjustStockModal');
        }
        
        // تحديث تسمية حقل الكمية بناءً على نوع التعديل
        function updateAdjustLabel() {
            const type = document.getElementById('adjust_type').value;
            document.getElementById('quantity_label').textContent = 
                type === 'in' ? 'الكمية المضافة' : 'الكمية المسحوبة';
        }
        
        // تصفية جدول المخزون
        function filterInventory() {
            const input = document.getElementById('searchInventory').value.toLowerCase();
            const filterCategory = document.getElementById('categoryFilter').value;
            const table = document.getElementById('inventoryTable');
            const tr = table.getElementsByTagName('tr');
            
            for (let i = 1; i < tr.length; i++) {
                const tdName = tr[i].getElementsByTagName('td')[0];
                const tdCategory = tr[i].getElementsByTagName('td')[1];
                if (tdName && tdCategory) {
                    const nameText = tdName.textContent || tdName.innerText;
                    const categoryValue = tdCategory.getAttribute('data-category') || '';
                    const showByName = nameText.toLowerCase().indexOf(input) > -1;
                    const showByCategory = filterCategory === '' || categoryValue === filterCategory;
                    
                    if (showByName && showByCategory) {
                        tr[i].style.display = '';
                    } else {
                        tr[i].style.display = 'none';
                    }
                }
            }
        }
        
        // إغلاق النوافذ المنبثقة عند النقر خارجها
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>