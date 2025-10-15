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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invoice_id'])) {
    try {
        $invoice_id = $_POST['invoice_id'];
        $designer_id = $_POST['designer_id'];
        $notes = $_POST['notes'] ?? '';
        
        // التحقق من وجود الفاتورة
        $stmt = $db->prepare("SELECT * FROM invoices WHERE invoice_id = ?");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            throw new Exception("الفاتورة غير موجودة");
        }
        
        // التحقق من عدم إرسال الفاتورة للمصمم مسبقًا
        $stmt = $db->prepare("SELECT * FROM invoice_assignments WHERE invoice_id = ? AND designer_id = ?");
        $stmt->execute([$invoice_id, $designer_id]);
        
        if ($stmt->fetch()) {
            throw new Exception("تم إرسال الفاتورة إلى هذا المصمم مسبقًا");
        }
        
        // إرسال الفاتورة إلى المصمم
        $stmt = $db->prepare("INSERT INTO invoice_assignments (invoice_id, designer_id, assigned_by, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$invoice_id, $designer_id, $_SESSION['user_id'], $notes]);
        
        // تسجيل النشاط
        logActivity($_SESSION['user_id'], 'assign_invoice', 
            "تم إرسال الفاتورة #{$invoice['invoice_number']} إلى المصمم $designer_id"
        );
        
        echo json_encode(["success" => true, "message" => "تم إرسال الفاتورة إلى المصمم بنجاح"]);
        exit();
        
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
        exit();
    }
}
?>