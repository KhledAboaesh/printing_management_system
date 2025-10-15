<?php
// تفعيل عرض الأخطاء للتصحيح
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

define('INCLUDED', true);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // التحقق من الحقول المطلوبة
        $required = ['name', 'current_quantity', 'min_quantity', 'unit', 'cost_price', 'selling_price'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("حقل {$field} مطلوب");
            }
        }

        // معالجة تحميل الصورة
        $imageName = 'default.png'; // قيمة افتراضية
        if(isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/images/products/';
            
            // إنشاء المجلد إذا لم يكن موجوداً
            if(!is_dir($uploadDir)) {
                if(!mkdir($uploadDir, 0755, true)) {
                    throw new Exception("لا يمكن إنشاء مجلد الصور");
                }
            }
            
            // التحقق من أن الملف هو صورة
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['image']['tmp_name']);
            $allowed = ['image/jpeg', 'image/png', 'image/gif'];
            
            if(!in_array($mime, $allowed)) {
                throw new Exception("نوع الملف غير مسموح به");
            }

            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $imageName = uniqid() . '.' . $extension;
            
            if(!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName)) {
                throw new Exception("فشل في رفع الصورة");
            }
        }

        // إعداد بيانات المنتج
        $data = [
            'name' => trim($_POST['name']),
            'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
            'current_quantity' => (float)$_POST['current_quantity'],
            'min_quantity' => (float)$_POST['min_quantity'],
            'unit' => $_POST['unit'],
            'cost_price' => (float)$_POST['cost_price'],
            'selling_price' => (float)$_POST['selling_price'],
            'barcode' => !empty($_POST['barcode']) ? trim($_POST['barcode']) : null,
            'description' => !empty($_POST['description']) ? trim($_POST['description']) : null,
            'image' => $imageName
        ];

        // إدراج المنتج في قاعدة البيانات
        $query = "INSERT INTO inventory (name, category_id, current_quantity, min_quantity, unit, 
                  cost_price, selling_price, barcode, description, image)
                  VALUES (:name, :category_id, :current_quantity, :min_quantity, :unit, 
                  :cost_price, :selling_price, :barcode, :description, :image)";
        
        $stmt = $db->prepare($query);
        if(!$stmt->execute($data)) {
            throw new Exception("فشل في إدراج البيانات");
        }
        
        $_SESSION['success_message'] = "تمت إضافة المنتج إلى المخزون بنجاح";
        header("Location: inventory.php");
        exit();
        
    } catch(PDOException $e) {
        error_log('PDO Error in save_inventory: ' . $e->getMessage());
        $_SESSION['error_message'] = "حدث خطأ في قاعدة البيانات: " . $e->getMessage();
        header("Location: inventory.php");
        exit();
    } catch(Exception $e) {
        error_log('Error in save_inventory: ' . $e->getMessage());
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: inventory.php");
        exit();
    }
} else {
    $_SESSION['error_message'] = "طريقة الطلب غير صالحة";
    header("Location: inventory.php");
    exit();
}