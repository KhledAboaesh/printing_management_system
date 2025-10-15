<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

define('INCLUDED', true);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$item_id = $_GET['id'] ?? 0;

// جلب بيانات العنصر
$item = getInventoryItem($db, $item_id);
if(!$item) {
    header("Location: inventory.php");
    exit();
}

// جلب فئات المنتجات
$categories = getCategories($db);

// معالجة تحديث البيانات
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'item_id' => $item_id,
            'name' => $_POST['name'],
            'category_id' => !empty($_POST['category_id']) ? $_POST['category_id'] : null,
            'current_quantity' => $_POST['current_quantity'],
            'min_quantity' => $_POST['min_quantity'],
            'unit' => $_POST['unit'],
            'cost_price' => $_POST['cost_price'],
            'selling_price' => $_POST['selling_price'],
            'barcode' => $_POST['barcode'] ?? null,
            'description' => $_POST['description'] ?? null
        ];

        // معالجة تحميل الصورة إذا تم رفع صورة جديدة
        if(isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/images/products/';
            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $imageName = uniqid() . '.' . $extension;
            move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName);
            
            // حذف الصورة القديمة إذا كانت موجودة
            if(!empty($item['image'])) {
                $oldImagePath = $uploadDir . $item['image'];
                if(file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            
            $data['image'] = $imageName;
        } else {
            $data['image'] = $item['image'];
        }

        $query = "UPDATE inventory SET 
                  name = :name,
                  category_id = :category_id,
                  current_quantity = :current_quantity,
                  min_quantity = :min_quantity,
                  unit = :unit,
                  cost_price = :cost_price,
                  selling_price = :selling_price,
                  barcode = :barcode,
                  description = :description,
                  image = :image
                  WHERE item_id = :item_id";
        
        $stmt = $db->prepare($query);
        $stmt->execute($data);
        
        $_SESSION['success_message'] = "تم تحديث بيانات المنتج بنجاح";
        header("Location: inventory.php");
        exit();
    } catch(PDOException $e) {
        error_log('Error in update inventory: ' . $e->getMessage());
        $_SESSION['error_message'] = "حدث خطأ أثناء تحديث المنتج";
        header("Location: inventory.php");
        exit();
    }
}

// دالة مساعدة لجلب بيانات العنصر
function getInventoryItem($db, $item_id) {
    try {
        $query = "SELECT * FROM inventory WHERE item_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$item_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log('Error in getInventoryItem(): ' . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل عنصر المخزون - نظام المطبعة</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .image-preview {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">تعديل عنصر المخزون</h1>
            <a href="inventory.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> رجوع
            </a>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">اسم المنتج</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= htmlspecialchars($item['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="category_id" class="form-label">الفئة</label>
                            <select class="form-select" id="category_id" name="category_id">
                                <option value="">بدون فئة</option>
                                <?php foreach($categories as $category): ?>
                                <option value="<?= $category['category_id'] ?>" 
                                    <?= $item['category_id'] == $category['category_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="current_quantity" class="form-label">الكمية الحالية</label>
                            <input type="number" step="0.01" class="form-control" id="current_quantity" 
                                   name="current_quantity" value="<?= $item['current_quantity'] ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="min_quantity" class="form-label">الحد الأدنى</label>
                            <input type="number" step="0.01" class="form-control" id="min_quantity" 
                                   name="min_quantity" value="<?= $item['min_quantity'] ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="unit" class="form-label">الوحدة</label>
                            <select class="form-select" id="unit" name="unit" required>
                                <option value="piece" <?= $item['unit'] == 'piece' ? 'selected' : '' ?>>قطعة</option>
                                <option value="pack" <?= $item['unit'] == 'pack' ? 'selected' : '' ?>>علبة</option>
                                <option value="ream" <?= $item['unit'] == 'ream' ? 'selected' : '' ?>>رزمة</option>
                                <option value="box" <?= $item['unit'] == 'box' ? 'selected' : '' ?>>صندوق</option>
                                <option value="kg" <?= $item['unit'] == 'kg' ? 'selected' : '' ?>>كيلوغرام</option>
                                <option value="meter" <?= $item['unit'] == 'meter' ? 'selected' : '' ?>>متر</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="cost_price" class="form-label">سعر الشراء</label>
                            <input type="number" step="0.01" class="form-control" id="cost_price" 
                                   name="cost_price" value="<?= $item['cost_price'] ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="selling_price" class="form-label">سعر البيع</label>
                            <input type="number" step="0.01" class="form-control" id="selling_price" 
                                   name="selling_price" value="<?= $item['selling_price'] ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="barcode" class="form-label">باركود</label>
                            <input type="text" class="form-control" id="barcode" name="barcode" 
                                   value="<?= htmlspecialchars($item['barcode']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="image" class="form-label">صورة المنتج</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <?php if(!empty($item['image'])): ?>
                            <div class="mt-2">
                                <img src="images/products/<?= $item['image'] ?>" class="image-preview" id="imagePreview">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="deleteImage" name="deleteImage">
                                    <label class="form-check-label" for="deleteImage">حذف الصورة الحالية</label>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">وصف المنتج</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($item['description']) ?></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> حفظ التغييرات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // عرض معاينة الصورة عند اختيار صورة جديدة
        $('#image').change(function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#imagePreview').attr('src', e.target.result);
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>