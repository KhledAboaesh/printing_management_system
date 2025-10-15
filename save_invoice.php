<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// داخل try-catch
$db->beginTransaction();

// 1. التحقق من وجود العميل
$checkCustomer = $db->prepare("SELECT customer_id FROM customers WHERE customer_id = ?");
$checkCustomer->execute([$_POST['customer_id']]);
if (!$checkCustomer->fetch()) {
    throw new Exception("العميل غير موجود");
}

// 2. إدراج الفاتورة الأساسية
$invoiceQuery = "INSERT INTO invoices (...) VALUES (...)";
// ... (الكود السابق)

// 3. إضافة العناصر مع التحقق
foreach ($_POST['items'] as $item) {
    if (empty($item['product_id']) || empty($item['quantity']) || empty($item['unit_price'])) {
        throw new Exception("بيانات العنصر غير مكتملة");
    }

    // التحقق من وجود المنتج
    $checkProduct = $db->prepare("SELECT item_id, selling_price FROM inventory WHERE item_id = ?");
    $checkProduct->execute([$item['product_id']]);
    $product = $checkProduct->fetch();

    if (!$product) {
        throw new Exception("المنتج ID {$item['product_id']} غير موجود");
    }

    // استخدام سعر المنتج من قاعدة البيانات بدلاً من القيمة المرسلة
    $unitPrice = $product['selling_price'];
    $total = $unitPrice * $item['quantity'] - ($item['discount'] ?? 0);

    $stmt = $db->prepare("INSERT INTO invoice_items (...) VALUES (...)");
    $stmt->execute([
        ':invoice_id' => $invoiceId,
        ':product_id' => $item['product_id'],
        ':quantity' => $item['quantity'],
        ':unit_price' => $unitPrice,
        ':discount' => $item['discount'] ?? 0,
        ':total' => $total,
        ':description' => $item['description'] ?? ''
    ]);
}

$db->commit();
?>