<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// التحقق من الصلاحيات
if (!isset($_SESSION['user_id']) ){
    header("Location: login.php");
    exit();
}

// التحقق من وجود البيانات المطلوبة
if (!isset($_POST['order_id'], $_POST['status']) || !is_numeric($_POST['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'بيانات غير صالحة']);
    exit();
}

$order_id = (int)$_POST['order_id'];
$status = $_POST['status'];
$rejection_reason = $_POST['rejection_reason'] ?? null;

try {
    $db->beginTransaction();
    
    // تحضير الاستعلام حسب الحالة
    if ($status === 'approved') {
        $stmt = $db->prepare("UPDATE orders SET 
                            status = :status,
                            approved_by = :user_id,
                            approved_at = NOW(),
                            rejection_reason = NULL
                            WHERE order_id = :order_id");
    } 
    elseif ($status === 'rejected') {
        $stmt = $db->prepare("UPDATE orders SET 
                            status = :status,
                            approved_by = :user_id,
                            approved_at = NOW(),
                            rejection_reason = :reason
                            WHERE order_id = :order_id");
        $stmt->bindParam(':reason', $rejection_reason);
    }
    else {
        $stmt = $db->prepare("UPDATE orders SET 
                            status = :status,
                            approved_by = NULL,
                            approved_at = NULL,
                            rejection_reason = NULL
                            WHERE order_id = :order_id");
    }

    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    
    $stmt->execute();
    $db->commit();

    // تسجيل النشاط
    logActivity($_SESSION['user_id'], 'order_status_change', "تم تغيير حالة الطلب #$order_id إلى $status");

    echo json_encode(['success' => true, 'message' => 'تم تحديث الحالة بنجاح']);
    
} catch (PDOException $e) {
    $db->rollBack();
    error_log("Status Update Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء تحديث الحالة']);
}
?>

<div class="order-actions">
    <h3>إدارة حالة الطلب</h3>
    
    <div class="status-buttons">
        <button class="btn-pending" onclick="updateStatus(<?= $order_id ?>, 'pending')">
            <i class="fas fa-clock"></i> انتظار
        </button>
        
        <button class="btn-approve" onclick="updateStatus(<?= $order_id ?>, 'approved')">
            <i class="fas fa-check"></i> موافقة
        </button>
        
        <button class="btn-reject" onclick="showRejectionReason(<?= $order_id ?>)">
            <i class="fas fa-times"></i> رفض
        </button>
        
        <button class="btn-complete" onclick="updateStatus(<?= $order_id ?>, 'completed')">
            <i class="fas fa-check-double"></i> إكمال
        </button>
    </div>
    
    <div id="rejection-form" style="display:none; margin-top:15px;">
        <textarea id="rejection-reason" placeholder="سبب الرفض..." class="form-control"></textarea>
        <button onclick="submitRejection()" class="btn btn-danger mt-2">
            <i class="fas fa-paper-plane"></i> إرسال
        </button>
    </div>
</div>

<script>
function updateStatus(orderId, status) {
    fetch('update_order_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `order_id=${orderId}&status=${status}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('danger', data.message);
        }
    });
}

function showRejectionReason(orderId) {
    document.getElementById('rejection-form').style.display = 'block';
    window.rejectionOrderId = orderId;
}

function submitRejection() {
    const reason = document.getElementById('rejection-reason').value;
    if (!reason.trim()) {
        showAlert('warning', 'الرجاء إدخال سبب الرفض');
        return;
    }
    
    fetch('update_order_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `order_id=${window.rejectionOrderId}&status=rejected&rejection_reason=${encodeURIComponent(reason)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert('danger', data.message);
        }
    });
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = message;
    document.body.prepend(alertDiv);
    setTimeout(() => alertDiv.remove(), 3000);
}
</script>

<style>
.status-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.status-buttons button {
    padding: 8px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
}

.btn-pending { background: #ffc107; color: #000; }
.btn-approve { background: #28a745; color: #fff; }
.btn-reject { background: #dc3545; color: #fff; }
.btn-complete { background: #17a2b8; color: #fff; }

.alert {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    padding: 10px 20px;
    border-radius: 5px;
    z-index: 1000;
}

.alert-success { background: #d4edda; color: #155724; }
.alert-danger { background: #f8d7da; color: #721c24; }
.alert-warning { background: #fff3cd; color: #856404; }
</style>