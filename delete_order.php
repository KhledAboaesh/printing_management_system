<?php
// delete_order.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// تضمين ملف الاتصال بقاعدة البيانات
require_once __DIR__ . '/includes/db.php';

// التحقق من وجود معرف الطلب
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: orders.php?error=invalid_id");
    exit();
}

$order_id = intval($_GET['id']);

try {
    // التحقق من وجود اتصال PDO
    if (!isset($db) || !($db instanceof PDO)) {
        throw new Exception("فشل في الاتصال بقاعدة البيانات");
    }

    // بدء المعاملة
    $db->beginTransaction();

    // 1. حذف العناصر المرتبطة بالطلب أولاً
    $stmt = $db->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);

    // 2. حذف الطلب نفسه
    $stmt = $db->prepare("DELETE FROM orders WHERE order_id = ?");
    $stmt->execute([$order_id]);

    // تأكيد المعاملة
    $db->commit();

    // تسجيل النشاط
    if (function_exists('logActivity')) {
        logActivity($_SESSION['user_id'], 'delete_order', "تم حذف الطلب رقم: $order_id");
    }

    // إعادة التوجيه مع رسالة نجاح
    header("Location: orders.php?success=order_deleted");
    exit();

} catch (PDOException $e) {
    // التراجع عن المعاملة في حالة حدوث خطأ
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    error_log("Error deleting order: " . $e->getMessage());
    header("Location: orders.php?error=delete_failed&message=" . urlencode($e->getMessage()));
    exit();

} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    header("Location: orders.php?error=db_connection");
    exit();
}
?>