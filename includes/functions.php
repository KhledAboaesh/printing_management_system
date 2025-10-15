<?php
// ملف includes/functions.php

require_once 'db.php';
require_once 'includes/db.php';



// دالة للحصول على عدد العملاء
function getCustomerCount() {
    global $db;
    $stmt = $db->prepare("SELECT COUNT(*) FROM customers");
    $stmt->execute();
    return $stmt->fetchColumn();
}

// دالة للحصول على عدد الطلبات
function getOrderCount() {
    global $db;
    $stmt = $db->prepare("SELECT COUNT(*) FROM orders");
    $stmt->execute();
    return $stmt->fetchColumn();
}



function get_user_name($user_id) {
    global $db;
    $stmt = $db->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?? 'غير معروف';
}



// الحصول على عناصر المخزون
function getInventory($db) {
    try {
        // استعلام معدل لضمان استرجاع البيانات حتى لو كانت الفئات غير موجودة
        $query = "SELECT 
                    i.*,
                    IFNULL(c.name, 'بدون فئة') AS category_name,
                    CASE 
                        WHEN i.current_quantity <= 0 THEN 'danger'
                        WHEN i.current_quantity < i.min_quantity THEN 'warning'
                        ELSE 'success'
                    END AS stock_status
                  FROM inventory i
                  LEFT JOIN product_categories c ON i.category_id = c.category_id
                  ORDER BY i.name";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // تسجيل النتائج للفحص
        error_log("Inventory Data: " . json_encode($results));
        
        return $results;
        
    } catch(PDOException $e) {
        error_log('Error in getInventory(): ' . $e->getMessage());
        // إرجاع مصفوفة فارغة مع رسالة خطأ للتصحيح
        return [
            'error' => $e->getMessage(),
            'query' => $query
        ];
    }
}

// الحصول على فئات المنتجات
function getCategories($db) {
    try {
        $query = "SELECT * FROM product_categories ORDER BY name";
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log('Error in getCategories(): ' . $e->getMessage());
        return [];
    }
}

// حذف عنصر من المخزون
function deleteInventoryItem($db, $item_id) {
    try {
        $query = "DELETE FROM inventory WHERE item_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$item_id]);
        return true;
    } catch(PDOException $e) {
        error_log('Error in deleteInventoryItem(): ' . $e->getMessage());
        return false;
    }
}
// دالة للحصول على عدد مواد المخزون المنخفضة
function getLowInventoryCount() {
    global $db;
    $stmt = $db->prepare("SELECT COUNT(*) FROM inventory WHERE current_quantity <= min_quantity");
    $stmt->execute();
    return $stmt->fetchColumn();
}

// دالة للحصول على إجمالي المبيعات


// دالة للحصول على قائمة العملاء

function getOrders($db) {
    try {
        $query = "SELECT o.order_id, o.order_date, o.required_date, o.status, o.priority, 
                         c.name AS customer_name, 
                         SUM(oi.quantity * oi.unit_price - oi.discount) AS total_amount
                  FROM orders o
                  JOIN customers c ON o.customer_id = c.customer_id
                  LEFT JOIN order_items oi ON o.order_id = oi.order_id
                  GROUP BY o.order_id
                  ORDER BY o.order_date DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log('Error in getOrders(): ' . $e->getMessage());
        return [];
    }
}

function getCustomers($db) {
    try {
        $query = "SELECT c.*, 
                 COUNT(o.order_id) AS orders_count,
                 MAX(o.order_date) AS last_order
                 FROM customers c
                 LEFT JOIN orders o ON c.customer_id = o.customer_id
                 GROUP BY c.customer_id
                 ORDER BY c.name";
        return $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting customers: " . $e->getMessage());
        return [];
    }
}

function getProducts($db) {
    try {
        $query = "SELECT item_id, name, selling_price FROM inventory WHERE current_quantity > 0 ORDER BY name";
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log('Error in getProducts(): ' . $e->getMessage());
        return [];
    }
}


// دالة للحصول على تفاصيل طلب معين
function getOrderDetails($db, $order_id) {
    try {
        $query = "SELECT o.*, c.name AS customer_name, c.phone, c.email, 
                         u.username AS created_by_name
                  FROM orders o
                  JOIN customers c ON o.customer_id = c.customer_id
                  JOIN users u ON o.created_by = u.user_id
                  WHERE o.order_id = :order_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getOrderDetails: " . $e->getMessage());
        return false;
    }
}

// دالة للحصول على عناصر طلب معين
function getOrderItems($db, $order_id) {
    try {
        $query = "SELECT oi.*, i.name AS product_name, i.description, i.image
                  FROM order_items oi
                  JOIN inventory i ON oi.item_id = i.item_id
                  WHERE oi.order_id = :order_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getOrderItems: " . $e->getMessage());
        return [];
    }
}

// دالة لتسجيل نشاط المستخدم
function logActivity($user_id, $action, $description = null) {
    global $db;
    
    try {
        // تحقق مما إذا كان user_id موجوداً في جدول users
        if($user_id != 0) {
            $check = $db->prepare("SELECT user_id FROM users WHERE user_id = ?");
            $check->execute([$user_id]);
            if(!$check->fetch()) {
                $user_id = 0; // استخدم 0 إذا لم يكن المستخدم موجوداً
            }
        }
        
        $stmt = $db->prepare("
            INSERT INTO activity_log 
            (user_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch(PDOException $e) {
        error_log('Activity Log Error: ' . $e->getMessage());
    }
}

// دالة للتحقق من تسجيل الدخول
function checkLogin() {
    if(!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

// دالة للحصول على معلومات المستخدم
function getUserInfo($user_id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// دالة لحفظ الطلب الجديد
function saveOrder($orderData, $itemsData) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        // حفظ بيانات الطلب الأساسية
        $stmt = $db->prepare("
            INSERT INTO orders 
            (customer_id, order_date, required_date, status, priority, notes, special_instructions, total_amount, user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $orderData['customer_id'],
            $orderData['order_date'],
            $orderData['required_date'],
            $orderData['status'] ?? 'pending',
            $orderData['priority'] ?? 'medium',
            $orderData['notes'] ?? null,
            $orderData['special_instructions'] ?? null,
            $orderData['total_amount'],
            $_SESSION['user_id']
        ]);
        
        $order_id = $db->lastInsertId();
        
        // حفظ عناصر الطلب
        foreach($itemsData as $item) {
            $stmt = $db->prepare("
                INSERT INTO order_items 
                (order_id, item_id, quantity, unit_price, discount, specifications)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $order_id,
                $item['product_id'],
                $item['quantity'],
                $item['price'],
                $item['discount'] ?? 0,
                $item['specifications'] ?? null
            ]);
        }
        
        $db->commit();
        return $order_id;
    } catch(PDOException $e) {
        $db->rollBack();
        error_log('Order Save Error: ' . $e->getMessage());
        return false;
    }
}

// دالة لتحديث حالة الطلب
function updateOrderStatus($order_id, $status) {
    global $db;
    $stmt = $db->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    return $stmt->execute([$status, $order_id]);
}

// دالة لحذف طلب
function deleteOrder($order_id) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        // حذف عناصر الطلب أولاً
        $stmt = $db->prepare("DELETE FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        
        // ثم حذف الطلب نفسه
        $stmt = $db->prepare("DELETE FROM orders WHERE order_id = ?");
        $stmt->execute([$order_id]);
        
        $db->commit();
        return true;
    } catch(PDOException $e) {
        $db->rollBack();
        error_log('Order Delete Error: ' . $e->getMessage());
        return false;
    }
}

function getInvoiceDetails($db, $invoice_id) {
    $query = "SELECT i.*, c.name AS customer_name, c.phone, c.email, 
                     c.company_name, c.tax_number, u.username AS created_by_name
              FROM invoices i
              JOIN customers c ON i.customer_id = c.customer_id
              JOIN users u ON i.created_by = u.user_id
              WHERE i.invoice_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$invoice_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getInvoiceItems($db, $invoice_id) {
    $query = "SELECT ii.*, inv.name AS product_name, inv.description
              FROM invoice_items ii
              JOIN inventory inv ON ii.item_id = inv.item_id
              WHERE ii.invoice_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$invoice_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



// في ملف includes/functions.php - بعد الدوال الموجودة

/**
 * حساب إجمالي المبيعات من الفواتير المدفوعة
 */
function getTotalSales() {
    global $db;

    if (!isset($db)) {
        error_log('Database connection not found');
        return 0;
    }

    try {
        $stmt = $db->query("
            SELECT COALESCE(SUM(total_amount), 0) AS total_sales 
            FROM invoices 
            WHERE status = 'paid'
        ");
        return (float) $stmt->fetchColumn();
    } catch(PDOException $e) {
        error_log('Sales Calculation Error: ' . $e->getMessage());
        return 0;
    }
}



/**
 * حساب إجمالي المبيعات لهذا الشهر
 */
function getMonthlySales() {
    global $db;
    try {
        $current_month = date('Y-m');
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as monthly_sales 
            FROM invoices 
            WHERE status = 'paid' 
            AND DATE_FORMAT(issue_date, '%Y-%m') = ?
        ");
        $stmt->execute([$current_month]);
        return $stmt->fetchColumn();
    } catch(PDOException $e) {
        error_log('Monthly Sales Calculation Error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * حساب نسبة النمو في المبيعات عن الشهر الماضي
 */
function getSalesGrowth() {
    global $db;
    try {
        $current_month = date('Y-m');
        $last_month = date('Y-m', strtotime('-1 month'));
        
        // مبيعات الشهر الحالي
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as sales 
            FROM invoices 
            WHERE status = 'paid' 
            AND DATE_FORMAT(issue_date, '%Y-%m') = ?
        ");
        $stmt->execute([$current_month]);
        $current_sales = $stmt->fetchColumn();
        
        // مبيعات الشهر الماضي
        $stmt->execute([$last_month]);
        $last_sales = $stmt->fetchColumn();
        
        if ($last_sales == 0) {
            return $current_sales > 0 ? 100 : 0;
        }
        
        return round((($current_sales - $last_sales) / $last_sales) * 100, 2);
    } catch(PDOException $e) {
        error_log('Sales Growth Calculation Error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * الحصول على عدد الفواتير المدفوعة
 */
function getTotalInvoicesCount() {
    global $db;
    try {
        $stmt = $db->query("
            SELECT COUNT(*) as total_invoices 
            FROM invoices 
            WHERE status = 'paid'
        ");
        return $stmt->fetchColumn();
    } catch(PDOException $e) {
        error_log('Invoices Count Error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * الحصول على إحصائيات المبيعات الشهرية للرسوم البيانية
 */
function getMonthlySalesData() {
    global $db;
    try {
        $stmt = $db->query("
            SELECT 
                DATE_FORMAT(issue_date, '%Y-%m') as month,
                SUM(total_amount) as total_sales,
                COUNT(*) as invoice_count
            FROM invoices 
            WHERE status = 'paid'
            GROUP BY DATE_FORMAT(issue_date, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log('Monthly Sales Data Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * الحصول على أفضل العملاء حسب المبيعات
 */
function getTopCustomers() {
    global $db;
    try {
        $stmt = $db->query("
            SELECT 
                c.customer_id,
                c.name,
                c.company_name,
                COUNT(i.invoice_id) as invoice_count,
                SUM(i.total_amount) as total_spent
            FROM customers c
            JOIN invoices i ON c.customer_id = i.customer_id
            WHERE i.status = 'paid'
            GROUP BY c.customer_id
            ORDER BY total_spent DESC
            LIMIT 10
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log('Top Customers Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * الحصول على أفضل المنتجات مبيعاً
 */
function getTopProducts() {
    global $db;
    try {
        $stmt = $db->query("
            SELECT 
                i.item_id,
                i.name,
                i.selling_price,
                SUM(ii.quantity) as total_sold,
                SUM(ii.quantity * ii.unit_price) as total_revenue
            FROM inventory i
            JOIN invoice_items ii ON i.item_id = ii.item_id
            JOIN invoices inv ON ii.invoice_id = inv.invoice_id
            WHERE inv.status = 'paid'
            GROUP BY i.item_id
            ORDER BY total_sold DESC
            LIMIT 10
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log('Top Products Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * الحصول على إحصائيات سريعة للمبيعات
 */
function getSalesStats() {
    return [
        'total_sales' => getTotalSales(),
        'monthly_sales' => getMonthlySales(),
        'sales_growth' => getSalesGrowth(),
        'total_invoices' => getTotalInvoicesCount(),
        'average_invoice' => getTotalInvoicesCount() > 0 ? getTotalSales() / getTotalInvoicesCount() : 0
    ];
}















// في ملف includes/functions.php - إضافة دوال جديدة للتنبيهات

/**
 * الحصول على التنبيهات الفعلية من النظام
 */
/**
 * الحصول على التنبيهات الفعلية من النظام
 */
/**
 * الحصول على التنبيهات الفعلية من النظام - نسخة محسنة
 */
function getSystemAlerts() {
    global $db;
    $alerts = [];
    
    try {
        // 1. تنبيهات نفاد المخزون - نسخة محسنة
        $stmt = $db->query("
            SELECT 
                item_id, 
                name, 
                current_quantity, 
                min_quantity,
                CASE 
                    WHEN current_quantity = 0 THEN 'نفذ تماماً'
                    WHEN current_quantity <= min_quantity THEN 'منخفض'
                    ELSE 'طبيعي'
                END as stock_status
            FROM inventory 
            WHERE current_quantity <= min_quantity 
            OR current_quantity = 0
            ORDER BY 
                CASE 
                    WHEN current_quantity = 0 THEN 1
                    WHEN current_quantity <= min_quantity THEN 2
                    ELSE 3
                END,
                current_quantity ASC
            LIMIT 10
        ");
        
        $inventory_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($inventory_alerts as $item) {
            if ($item['current_quantity'] == 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'title' => '⚠️ نفذ تماماً: ' . $item['name'],
                    'message' => 'الكمية: 0 - يجب إعادة الطلب فوراً',
                    'time' => 'الآن',
                    'icon' => 'exclamation-triangle',
                    'link' => 'inventory.php?action=edit&id=' . $item['item_id'],
                    'priority' => 1
                ];
            } elseif ($item['current_quantity'] <= $item['min_quantity']) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => '⚠️ كمية منخفضة: ' . $item['name'],
                    'message' => 'الكمية الحالية: ' . $item['current_quantity'] . ' - الحد الأدنى: ' . $item['min_quantity'],
                    'time' => 'منذ قليل',
                    'icon' => 'exclamation-circle',
                    'link' => 'inventory.php?action=edit&id=' . $item['item_id'],
                    'priority' => 2
                ];
            }
        }

        // 2. تنبيهات الطلبات المتأخرة
        $stmt = $db->query("
            SELECT 
                o.order_id, 
                o.required_date, 
                c.name as customer_name,
                DATEDIFF(CURDATE(), o.required_date) as days_late
            FROM orders o
            JOIN customers c ON o.customer_id = c.customer_id
            WHERE o.status NOT IN ('completed', 'delivered', 'cancelled')
            AND o.required_date < CURDATE()
            ORDER BY o.required_date ASC 
            LIMIT 5
        ");
        
        $late_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($late_orders as $order) {
            $alerts[] = [
                'type' => 'danger',
                'title' => '⏰ طلب متأخر #' . $order['order_id'],
                'message' => 'عميل: ' . $order['customer_name'] . ' - متأخر ' . $order['days_late'] . ' يوم',
                'time' => 'منذ ' . $order['days_late'] . ' أيام',
                'icon' => 'clock',
                'link' => 'view_order.php?id=' . $order['order_id'],
                'priority' => 1
            ];
        }

        // 3. تنبيهات الفواتير المتأخرة
        $stmt = $db->query("
            SELECT 
                i.invoice_id, 
                i.total_amount, 
                c.name as customer_name,
                DATEDIFF(CURDATE(), i.due_date) as days_overdue
            FROM invoices i
            JOIN customers c ON i.customer_id = c.customer_id
            WHERE i.status = 'sent' 
            AND i.due_date < CURDATE()
            ORDER BY i.due_date ASC 
            LIMIT 5
        ");
        
        $overdue_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($overdue_invoices as $invoice) {
            $alerts[] = [
                'type' => 'warning',
                'title' => '💳 فاتورة متأخرة #' . $invoice['invoice_id'],
                'message' => 'عميل: ' . $invoice['customer_name'] . ' - المبلغ: ' . number_format($invoice['total_amount'], 2) . ' د.ل',
                'time' => 'متأخرة ' . $invoice['days_overdue'] . ' يوم',
                'icon' => 'money-bill-wave',
                'link' => 'view_invoice.php?id=' . $invoice['invoice_id'],
                'priority' => 2
            ];
        }

        // 4. تنبيهات المدفوعات الحديثة
        $stmt = $db->query("
            SELECT 
                i.invoice_id, 
                i.total_amount, 
                c.name as customer_name,
                i.payment_date
            FROM invoices i
            JOIN customers c ON i.customer_id = c.customer_id
            WHERE i.status = 'paid'
            AND i.payment_date >= DATE_SUB(NOW(), INTERVAL 3 DAY)
            ORDER BY i.payment_date DESC 
            LIMIT 3
        ");
        
        $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($recent_payments as $payment) {
            $hours_ago = floor((time() - strtotime($payment['payment_date'])) / 3600);
            $time_text = $hours_ago < 24 ? $hours_ago . ' ساعة' : floor($hours_ago / 24) . ' يوم';
            
            $alerts[] = [
                'type' => 'success',
                'title' => '✅ تم الدفع #' . $payment['invoice_id'],
                'message' => 'عميل: ' . $payment['customer_name'] . ' - المبلغ: ' . number_format($payment['total_amount'], 2) . ' د.ل',
                'time' => 'منذ ' . $time_text,
                'icon' => 'check-circle',
                'link' => 'view_invoice.php?id=' . $payment['invoice_id'],
                'priority' => 3
            ];
        }

        // 5. تنبيهات الطلبات الجديدة
        $stmt = $db->query("
            SELECT 
                o.order_id, 
                c.name as customer_name, 
                o.order_date
            FROM orders o
            JOIN customers c ON o.customer_id = c.customer_id
            WHERE o.status = 'pending'
            AND o.order_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ORDER BY o.order_date DESC 
            LIMIT 3
        ");
        
        $new_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($new_orders as $order) {
            $hours_ago = floor((time() - strtotime($order['order_date'])) / 3600);
            
            $alerts[] = [
                'type' => 'info',
                'title' => '🛒 طلب جديد #' . $order['order_id'],
                'message' => 'عميل: ' . $order['customer_name'],
                'time' => 'منذ ' . $hours_ago . ' ساعة',
                'icon' => 'shopping-cart',
                'link' => 'view_order.php?id=' . $order['order_id'],
                'priority' => 3
            ];
        }

        // ترتيب التنبيهات حسب الأولوية (الأهم أولاً)
        usort($alerts, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        return array_slice($alerts, 0, 15); // الحد الأقصى 15 تنبيهاً
        
    } catch(PDOException $e) {
        error_log('System Alerts Error: ' . $e->getMessage());
        
        // إرجاع تنبيه خطأ مع معلومات التصحيح
        return [
            [
                'type' => 'danger',
                'title' => '❌ خطأ في النظام',
                'message' => 'تعذر تحميل التنبيهات: ' . $e->getMessage(),
                'time' => 'الآن',
                'icon' => 'bug',
                'link' => '',
                'priority' => 1
            ]
        ];
    }
}

/**
 * حساب الوقت المنقضي بشكل مقروء
 */
function getTimeAgo($timestamp) {
    $time = strtotime($timestamp);
    $time_diff = time() - $time;
    
    if ($time_diff < 60) {
        return 'الآن';
    } elseif ($time_diff < 3600) {
        $minutes = floor($time_diff / 60);
        return 'منذ ' . $minutes . ' دقيقة';
    } elseif ($time_diff < 86400) {
        $hours = floor($time_diff / 3600);
        return 'منذ ' . $hours . ' ساعة';
    } elseif ($time_diff < 2592000) {
        $days = floor($time_diff / 86400);
        return 'منذ ' . $days . ' يوم';
    } else {
        return date('Y/m/d', $time);
    }
}


function getAllInvoices($db) {
    $stmt = $db->query("SELECT * FROM invoices ORDER BY invoice_id DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



function getAllInvoicesTotal() {
    global $db;
    $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM invoices");
    return (float) $stmt->fetchColumn();
}


function getInvoicesByStatus($status) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM invoices WHERE status = ?");
    $stmt->execute([$status]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



// دالة لإرسال فاتورة إلى مصمم
function assignInvoiceToDesigner($invoice_id, $designer_id, $assigned_by, $notes = '') {
    global $db;
    
    try {
        // التحقق من أن الفاتورة ليتم تعيينها بالفعل للمصمم
        $stmt = $db->prepare("SELECT * FROM invoice_assignments WHERE invoice_id = ? AND designer_id = ?");
        $stmt->execute([$invoice_id, $designer_id]);
        
        if ($stmt->fetch()) {
            return ["success" => false, "message" => "تم إرسال الفاتورة إلى المصمم مسبقًا"];
        }
        
        // إرسال الفاتورة إلى المصمم
        $stmt = $db->prepare("INSERT INTO invoice_assignments (invoice_id, designer_id, assigned_by, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$invoice_id, $designer_id, $assigned_by, $notes]);
        
        // تسجيل النشاط
        logActivity($assigned_by, 'assign_invoice', 
            "تم إرسال الفاتورة #$invoice_id إلى المصمم $designer_id"
        );
        
        return ["success" => true, "message" => "تم إرسال الفاتورة إلى المصمم بنجاح"];
        
    } catch (Exception $e) {
        return ["success" => false, "message" => "خطأ في إرسال الفاتورة: " . $e->getMessage()];
    }
}



// إضافة هذه الدالة إلى ملف functions.php
function updateInvoicePaymentStatus($db, $invoice_id) {
    $stmt = $db->prepare("
        SELECT total_amount, amount_paid 
        FROM invoices 
        WHERE invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) return false;
    
    $total = floatval($invoice['total_amount']);
    $paid = floatval($invoice['amount_paid']);
    
    if ($paid >= $total) {
        $status = 'paid';
    } elseif ($paid > 0) {
        $status = 'partial';
    } else {
        $status = 'pending';
    }
    
    $stmt = $db->prepare("
        UPDATE invoices 
        SET payment_status = ?, 
            payment_date = CASE WHEN ? = 'paid' THEN COALESCE(payment_date, CURDATE()) ELSE payment_date END
        WHERE invoice_id = ?
    ");
    return $stmt->execute([$status, $status, $invoice_id]);
}



// دوال جديدة للإحصائيات المتقدمة
function getTotalOrdersCount() {
    global $db;
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM orders");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("Error getting orders count: " . $e->getMessage());
        return 0;
    }
}

function getPendingOrdersCount() {
    global $db;
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('pending', 'design', 'production')");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("Error getting pending orders count: " . $e->getMessage());
        return 0;
    }
}

function getCompletedOrdersCount() {
    global $db;
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('delivered', 'completed')");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("Error getting completed orders count: " . $e->getMessage());
        return 0;
    }
}

function getTotalOrdersRevenue() {
    global $db;
    try {
        $stmt = $db->query("SELECT SUM(total_amount) as total FROM orders WHERE status IN ('delivered', 'completed')");
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error getting orders revenue: " . $e->getMessage());
        return 0;
    }
}

function getMonthlyOrdersRevenue() {
    global $db;
    try {
        $stmt = $db->query("
            SELECT SUM(total_amount) as total 
            FROM orders 
            WHERE status IN ('delivered', 'completed') 
            AND MONTH(order_date) = MONTH(CURRENT_DATE()) 
            AND YEAR(order_date) = YEAR(CURRENT_DATE())
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error getting monthly orders revenue: " . $e->getMessage());
        return 0;
    }
}

function getTodayOrdersCount() {
    global $db;
    try {
        $stmt = $db->query("
            SELECT COUNT(*) as count 
            FROM orders 
            WHERE DATE(order_date) = CURDATE()
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("Error getting today orders count: " . $e->getMessage());
        return 0;
    }
}

function getUrgentOrdersCount() {
    global $db;
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE priority = 'urgent' AND status NOT IN ('delivered', 'completed', 'cancelled')");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    } catch (PDOException $e) {
        error_log("Error getting urgent orders count: " . $e->getMessage());
        return 0;
    }
}

function getRecentOrdersWithDetails($limit = 5) {
    global $db;
    try {
        $stmt = $db->prepare("
            SELECT 
                o.order_id,
                o.order_date,
                o.required_date,
                o.status,
                o.priority,
                o.total_amount,
                c.name as customer_name,
                c.phone as customer_phone,
                COUNT(oi.order_item_id) as items_count
            FROM orders o
            LEFT JOIN customers c ON o.customer_id = c.customer_id
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            GROUP BY o.order_id
            ORDER BY o.order_date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting recent orders: " . $e->getMessage());
        return [];
    }
}

function getOrdersStatusDistribution() {
    global $db;
    try {
        $stmt = $db->query("
            SELECT 
                status,
                COUNT(*) as count,
                SUM(total_amount) as total_amount
            FROM orders 
            GROUP BY status
            ORDER BY count DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting orders status distribution: " . $e->getMessage());
        return [];
    }
}

function getSalesComparison() {
    global $db;
    try {
        $stmt = $db->query("
            SELECT 
                'current_month' as period,
                SUM(total_amount) as total
            FROM orders 
            WHERE status IN ('delivered', 'completed')
            AND MONTH(order_date) = MONTH(CURRENT_DATE())
            AND YEAR(order_date) = YEAR(CURRENT_DATE())
            
            UNION ALL
            
            SELECT 
                'previous_month' as period,
                SUM(total_amount) as total
            FROM orders 
            WHERE status IN ('delivered', 'completed')
            AND MONTH(order_date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)
            AND YEAR(order_date) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting sales comparison: " . $e->getMessage());
        return [];
    }
}

// إضافة هذه الدالة إلى ملف functions.php
function getOrderStatistics($db) {
    $stats = [];
    
    // إجمالي الطلبات
    $stmt = $db->query("SELECT COUNT(*) as total FROM orders");
    $stats['total_orders'] = $stmt->fetchColumn();
    
    // الطلبات المكتملة
    $stmt = $db->query("SELECT COUNT(*) as completed FROM orders WHERE status IN ('delivered', 'completed')");
    $stats['completed_orders'] = $stmt->fetchColumn();
    
    // الطلبات المعلقة
    $stmt = $db->query("SELECT COUNT(*) as pending FROM orders WHERE status IN ('pending', 'design', 'production')");
    $stats['pending_orders'] = $stmt->fetchColumn();
    
    // إجمالي الإيرادات
    $stmt = $db->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM orders WHERE status IN ('delivered', 'completed')");
    $stats['total_revenue'] = $stmt->fetchColumn();
    
    // معدلات النمو (يمكن تخصيصها حسب احتياجاتك)
    $stats['orders_growth'] = 12; // مثال: نمو 12% عن الشهر الماضي
    $stats['completion_rate'] = round(($stats['completed_orders'] / $stats['total_orders']) * 100, 1);
    $stats['pending_rate'] = round(($stats['pending_orders'] / $stats['total_orders']) * 100, 1);
    $stats['revenue_growth'] = 8; // مثال: نمو 8% عن الشهر الماضي
    
    return $stats;
}




// دالة التحقق من الصلاحيات
function hasPermission($user_id, $permission) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT up.permission 
            FROM user_permissions up 
            WHERE up.user_id = ? AND up.permission = ?
        ");
        $stmt->execute([$user_id, $permission]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log("Permission check error: " . $e->getMessage());
        return false;
    }
}

// دالة لتصدير الأرشيف
function exportArchiveData($type, $filters) {
    global $db;
    
    try {
        if ($type === 'customers') {
            $where_conditions = ["c.is_archived = 1"];
            $params = [];

            if (!empty($filters['search'])) {
                $where_conditions[] = "(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
                $search_term = "%{$filters['search']}%";
                array_push($params, $search_term, $search_term, $search_term);
            }

            // ... بقية الشروط مثل الكود السابق ...

            $where_clause = implode(' AND ', $where_conditions);

            $stmt = $db->prepare("
                SELECT 
                    c.name, c.email, c.phone, c.archive_reason,
                    u.full_name as archived_by_name, c.archived_at,
                    COUNT(i.invoice_id) as invoice_count
                FROM customers c
                LEFT JOIN users u ON c.archived_by = u.user_id
                LEFT JOIN invoices i ON c.customer_id = i.customer_id
                WHERE $where_clause
                GROUP BY c.customer_id
                ORDER BY c.archived_at DESC
            ");

            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return [];
        
    } catch (PDOException $e) {
        error_log("Export archive error: " . $e->getMessage());
        return [];
    }
}



if (!function_exists('getUsersByRole')) {
    function getUsersByRole($db, $role) {
        $stmt = $db->prepare("SELECT user_id, full_name FROM users WHERE role = ? AND is_active = 1");
        $stmt->execute([$role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getAllActiveUsers')) {
    function getAllActiveUsers($db) {
        $stmt = $db->query("SELECT user_id, full_name FROM users WHERE is_active = 1");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}




if (!function_exists('getOrderPaymentData')) {
    function getOrderPaymentData($db, $order_id) {
        $stmt = $db->prepare("SELECT * FROM payments WHERE order_id = ?");
        $stmt->execute([$order_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC); // أو fetchAll حسب تصميم قاعدة البيانات
    }
}




function getTotalProfit() {
    global $db;
    $stmt = $db->query("SELECT SUM(total_profit) as profit FROM orders");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['profit'] ?? 0;
}


?>