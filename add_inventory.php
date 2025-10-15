<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// // التحقق من صلاحيات المستخدم (مدير أو مسؤول مخزون)
// if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'inventory'])) {
//     header("Location: login.php");
//     exit();
// }

define('INCLUDED', true);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// جلب فئات المنتجات
$categories = $db->query("SELECT * FROM product_categories")->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name']);
        $description = trim($_POST['description'] ?? '');
        $category_id = $_POST['category_id'] ?? null;
        $unit = $_POST['unit'];
        $current_quantity = (float)$_POST['current_quantity'];
        $min_quantity = (float)$_POST['min_quantity'];
        $cost_price = (float)$_POST['cost_price'];
        $selling_price = (float)$_POST['selling_price'];
        $barcode = trim($_POST['barcode'] ?? '');

        // التحقق من البيانات المطلوبة
        if (empty($name) || empty($unit)) {
            throw new Exception("الاسم والوحدة حقول مطلوبة");
        }

        // إضافة الصنف إلى المخزون
        $stmt = $db->prepare("INSERT INTO inventory 
                            (name, description, category_id, unit, current_quantity, min_quantity, 
                             cost_price, selling_price, barcode, last_updated) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $name, $description, $category_id, $unit, $current_quantity, $min_quantity,
            $cost_price, $selling_price, $barcode
        ]);

        $item_id = $db->lastInsertId();
        $success = "تم إضافة الصنف بنجاح (رقم: $item_id)";
        logActivity($_SESSION['user_id'], 'add_inventory', "تم إضافة صنف جديد: $name");

    } catch (PDOException $e) {
        $error = "حدث خطأ في قاعدة البيانات: " . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة صنف جديد - نظام المطبعة</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .form-title {
            color: #4361ee;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
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
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-col {
            flex: 1;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 15px;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #4361ee;
            color: white;
        }
        
        .btn-primary:hover {
            background: #3a56d4;
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: rgba(76, 201, 240, 0.1);
            color: #4cc9f0;
            border: 1px solid rgba(76, 201, 240, 0.3);
        }
        
        .alert-danger {
            background-color: rgba(247, 37, 133, 0.1);
            color: #f72585;
            border: 1px solid rgba(247, 37, 133, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="header">
                <h1>إضافة صنف جديد للمخزون</h1>
                <?php include 'includes/user-menu.php'; ?>
            </div>
            
            <div class="form-container">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                </div>
                <?php endif; ?>
                
                <h2 class="form-title"><i class="fas fa-box-open"></i> بيانات الصنف</h2>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="name" class="form-label">اسم الصنف <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">وصف الصنف</label>
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="category_id" class="form-label">الفئة</label>
                                <select id="category_id" name="category_id" class="form-control">
                                    <option value="">اختر فئة</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['category_id'] ?>"><?= $category['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="unit" class="form-label">الوحدة <span class="text-danger">*</span></label>
                                <select id="unit" name="unit" class="form-control" required>
                                    <option value="">اختر وحدة</option>
                                    <option value="piece">قطعة</option>
                                    <option value="pack">علبة</option>
                                    <option value="ream">رزمة</option>
                                    <option value="box">كرتون</option>
                                    <option value="kg">كيلوغرام</option>
                                    <option value="meter">متر</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="current_quantity" class="form-label">الكمية الحالية</label>
                                <input type="number" id="current_quantity" name="current_quantity" 
                                       class="form-control" min="0" step="0.01" value="0">
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="min_quantity" class="form-label">الحد الأدنى للتنبيه</label>
                                <input type="number" id="min_quantity" name="min_quantity" 
                                       class="form-control" min="0" step="0.01" value="5">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="cost_price" class="form-label">سعر التكلفة (د.ل)</label>
                                <input type="number" id="cost_price" name="cost_price" 
                                       class="form-control" min="0" step="0.01">
                            </div>
                        </div>
                        
                        <div class="form-col">
                            <div class="form-group">
                                <label for="selling_price" class="form-label">سعر البيع (د.ل)</label>
                                <input type="number" id="selling_price" name="selling_price" 
                                       class="form-control" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="barcode" class="form-label">باركود (إن وجد)</label>
                        <input type="text" id="barcode" name="barcode" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> حفظ الصنف
                        </button>
                        <a href="inventory.php" class="btn" style="background: #f0f0f0;">
                            <i class="fas fa-times"></i> إلغاء
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // حساب سعر البيع تلقائياً عند إدخال سعر التكلفة
        document.getElementById('cost_price').addEventListener('input', function() {
            const cost = parseFloat(this.value) || 0;
            const sellingPrice = cost * 1.3; // هامش ربح 30%
            document.getElementById('selling_price').value = sellingPrice.toFixed(2);
        });
    </script>
</body>
</html>