<?php
// تشغيل الجلسة إذا احتجت (اختياري حسب نظامك)
// session_start();

require_once __DIR__ . '/includes/db.php'; // الاتصال بقاعدة البيانات

// نرجع JSON
header('Content-Type: application/json; charset=utf-8');

// لو ما في كلمة بحث
if (!isset($_GET['term']) || trim($_GET['term']) === '') {
    echo json_encode([]);
    exit;
}

$term = "%" . trim($_GET['term']) . "%";

try {
    $stmt = $db->prepare("
        SELECT customer_id, name, phone, email
        FROM customers
        WHERE name LIKE :term 
           OR phone LIKE :term 
           OR email LIKE :term
        ORDER BY name ASC
        LIMIT 15
    ");
    $stmt->execute([':term' => $term]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($results, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    // في حالة خطأ نرجع مصفوفة فاضية
    echo json_encode([]);
}
