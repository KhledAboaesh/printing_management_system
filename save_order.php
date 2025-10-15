<?php
// بدء الجلسة إذا لم تكن بدأت
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// تضمين ملفات الضرورية
define('INCLUDED', true);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "طريقة الطلب غير صالحة";
    header("Location: add_order.php");
    exit();
}

// التحقق من البيانات المطلوبة
if (empty($_POST['customer_id']) || empty($_POST['items'])) {
    $_SESSION['error_message'] = "بيانات العميل والمنتجات مطلوبة";
    header("Location: add_order.php");
    exit();
}

try {
    // بدء المعاملة
    $db->beginTransaction();

    // متغيرات لحساب الإجماليات
    $subtotal = 0;
    $total_discount = 0;
    $total_profit = 0;

    // 1. حفظ بيانات الطلب الأساسية (مبدئياً بدون totals)
    $orderQuery = "INSERT INTO orders 
        (customer_id, order_date, required_date, status, priority, notes, created_by,
         subtotal, total_discount, tax_amount, total_amount, total_profit,
         deposit_amount, amount_paid, payment_status)
        VALUES 
        (:customer_id, NOW(), :required_date, 'pending', :priority, :notes, :user_id,
         0, 0, 0, 0, 0, 0, 0, 'unpaid')";

    $stmt = $db->prepare($orderQuery);
    $stmt->execute([
        ':customer_id' => $_POST['customer_id'],
        ':required_date' => $_POST['required_date'] ?? null,
        ':priority' => $_POST['priority'] ?? 'medium',
        ':notes' => $_POST['notes'] ?? '',
        ':user_id' => $_SESSION['user_id']
    ]);

    $orderId = $db->lastInsertId();

    // 2. حفظ عناصر الطلب
    foreach ($_POST['items'] as $item) {
        if (empty($item['product_id']) || empty($item['quantity']) || $item['quantity'] <= 0) {
            throw new Exception("بيانات المنتج غير صالحة");
        }

        $product = $db->query("
            SELECT selling_price, cost_price 
            FROM inventory 
            WHERE item_id = " . (int)$item['product_id']
        )->fetch();

        if (!$product) {
            throw new Exception("المنتج غير موجود في المخزون");
        }

        $quantity = (int)$item['quantity'];
        $unit_price = (float)$product['selling_price'];
        $cost_price = (float)$product['cost_price'];
        $discount = (float)($item['discount'] ?? 0);

        // حساب الإجماليات
        $line_total = ($unit_price * $quantity) - $discount;
        $line_profit = (($unit_price - $cost_price) * $quantity) - $discount;

        $subtotal += ($unit_price * $quantity);
        $total_discount += $discount;
        $total_profit += $line_profit;

        // حفظ العنصر
        $itemQuery = "INSERT INTO order_items 
            (order_id, item_id, quantity, unit_price, discount, specifications) 
            VALUES (:order_id, :item_id, :quantity, :unit_price, :discount, :specifications)";

        $stmt = $db->prepare($itemQuery);
        $stmt->execute([
            ':order_id' => $orderId,
            ':item_id' => $item['product_id'],
            ':quantity' => $quantity,
            ':unit_price' => $unit_price,
            ':discount' => $discount,
            ':specifications' => $item['specifications'] ?? ''
        ]);

        // تحديث المخزون
        $updateInventory = "UPDATE inventory 
                            SET current_quantity = current_quantity - :quantity 
                            WHERE item_id = :item_id AND current_quantity >= :quantity";
        $stmt = $db->prepare($updateInventory);
        $stmt->execute([
            ':quantity' => $quantity,
            ':item_id' => $item['product_id']
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("الكمية المطلوبة غير متوفرة للمنتج ID: " . $item['product_id']);
        }
    }

    // 3. حساب الضريبة والإجمالي
    $tax_amount = $subtotal * 0.15; // ضريبة 15%
    $total_amount = $subtotal + $tax_amount - $total_discount;

    // 4. تحديث الطلب بالقيم النهائية
    $updateOrder = "UPDATE orders SET 
                        subtotal = :subtotal,
                        total_discount = :total_discount,
                        tax_amount = :tax_amount,
                        total_amount = :total_amount,
                        total_profit = :total_profit,
                        deposit_amount = 0,
                        amount_paid = 0,
                        payment_status = 'unpaid'
                    WHERE order_id = :order_id";

    $stmt = $db->prepare($updateOrder);
    $stmt->execute([
        ':subtotal' => $subtotal,
        ':total_discount' => $total_discount,
        ':tax_amount' => $tax_amount,
        ':total_amount' => $total_amount,
        ':total_profit' => $total_profit,
        ':order_id' => $orderId
    ]);

    $db->commit();

    // إعادة التوجيه مع رسالة نجاح
    $_SESSION['success_message'] = "تم حفظ الطلب بنجاح! رقم الطلب: ORD-" . str_pad($orderId, 4, '0', STR_PAD_LEFT);
    logActivity($_SESSION['user_id'], 'add_order', "تم إنشاء طلب جديد رقم $orderId");
    header("Location: view_order.php?id=$orderId");
    exit();

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Order Error: " . $e->getMessage());
    $_SESSION['error_message'] = "حدث خطأ أثناء حفظ الطلب: " . $e->getMessage();
    header("Location: add_order.php");
    exit();
}
?>
