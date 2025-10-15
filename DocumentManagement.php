<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// التحقق من صلاحيات المستخدم (الموارد البشرية أو المدير)
if ($_SESSION['role'] != 'hr' && $_SESSION['role'] != 'admin' && $_SESSION['role'] != 'manager') {
    header("Location: unauthorized.php");
    exit();
}

// معالجة رفع المستندات
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['document_file'])) {
    $employee_id = $_POST['employee_id'] ?? '';
    $document_type = $_POST['document_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $access_level = $_POST['access_level'] ?? 'private';
    
    // التحقق من صحة البيانات
    if (!empty($employee_id) && !empty($document_type) && !empty($_FILES['document_file']['name'])) {
        // معلومات الملف
        $file_name = $_FILES['document_file']['name'];
        $file_tmp = $_FILES['document_file']['tmp_name'];
        $file_size = $_FILES['document_file']['size'];
        $file_error = $_FILES['document_file']['error'];
        
        // الحصول على امتداد الملف
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // الامتدادات المسموحة
        $allowed_ext = array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt');
        
        if (in_array($file_ext, $allowed_ext)) {
            if ($file_error === 0) {
                if ($file_size <= 5242880) { // 5MB كحد أقصى
                    // إنشاء اسم فريد للملف
                    $new_file_name = uniqid('', true) . '.' . $file_ext;
                    $file_destination = 'uploads/documents/' . $new_file_name;
                    
                    // إنشاء المجلد إذا لم يكن موجوداً
                    if (!is_dir('uploads/documents')) {
                        mkdir('uploads/documents', 0777, true);
                    }
                    
                    // رفع الملف
                    if (move_uploaded_file($file_tmp, $file_destination)) {
                        try {
                            // حفظ معلومات المستند في قاعدة البيانات
                            global $db;
                            $stmt = $db->prepare("INSERT INTO customer_documents (customer_id, document_name, document_type, file_path, description, uploaded_by, access_level) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$employee_id, $file_name, $document_type, $file_destination, $description, $_SESSION['user_id'], $access_level]);
                            
                            // تسجيل النشاط
                            logActivity($_SESSION['user_id'], 'upload_document', 'تم رفع مستند جديد: ' . $file_name);
                            
                            $success = "تم رفع المستند بنجاح";
                        } catch (PDOException $e) {
                            error_log('Document Upload Error: ' . $e->getMessage());
                            $error = "حدث خطأ في حفظ بيانات المستند";
                        }
                    } else {
                        $error = "حدث خطأ أثناء رفع الملف";
                    }
                } else {
                    $error = "حجم الملف كبير جداً. الحد الأقصى المسموح به هو 5MB";
                }
            } else {
                $error = "حدث خطأ أثناء رفع الملف";
            }
        } else {
            $error = "نوع الملف غير مسموح به. الأنواع المسموحة: " . implode(', ', $allowed_ext);
        }
    } else {
        $error = "جميع الحقول الإلزامية مطلوبة";
    }
}

// معالجة حذف المستند
if (isset($_GET['delete'])) {
    $document_id = $_GET['delete'];
    
    try {
        global $db;
        
        // الحصول على معلومات المستند
        $stmt = $db->prepare("SELECT * FROM customer_documents WHERE document_id = ?");
        $stmt->execute([$document_id]);
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($document) {
            // حذف الملف من الخادم
            if (file_exists($document['file_path'])) {
                unlink($document['file_path']);
            }
            
            // حذف المستند من قاعدة البيانات
            $stmt = $db->prepare("DELETE FROM customer_documents WHERE document_id = ?");
            $stmt->execute([$document_id]);
            
            // تسجيل النشاط
            logActivity($_SESSION['user_id'], 'delete_document', 'تم حذف مستند: ' . $document['document_name']);
            
            $success = "تم حذف المستند بنجاح";
        }
    } catch (PDOException $e) {
        error_log('Document Delete Error: ' . $e->getMessage());
        $error = "حدث خطأ أثناء حذف المستند";
    }
}

// جلب قائمة الموظفين
try {
    global $db;
    $stmt = $db->query("SELECT employee_id, first_name, last_name FROM employees ORDER BY first_name, last_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Employees Fetch Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب بيانات الموظفين";
}

// جلب المستندات
try {
    global $db;
    
    // بناء الاستعلام حسب صلاحيات المستخدم
    if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'hr') {
        $stmt = $db->query("
            SELECT d.*, e.first_name, e.last_name, u.username as uploaded_by_name 
            FROM customer_documents d 
            LEFT JOIN employees e ON d.customer_id = e.employee_id 
            LEFT JOIN users u ON d.uploaded_by = u.user_id 
            ORDER BY d.uploaded_at DESC
        ");
    } else {
        // للمديرين: يمكنهم رؤية المستندات العامة أو الخاصة بموظفيهم
        $stmt = $db->prepare("
            SELECT d.*, e.first_name, e.last_name, u.username as uploaded_by_name 
            FROM customer_documents d 
            LEFT JOIN employees e ON d.customer_id = e.employee_id 
            LEFT JOIN users u ON d.uploaded_by = u.user_id 
            WHERE d.access_level = 'public' OR d.uploaded_by = ?
            ORDER BY d.uploaded_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
    }
    
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Documents Fetch Error: ' . $e->getMessage());
    $error = "حدث خطأ في جلب المستندات";
}

// أنواع المستندات
$document_types = array(
    'عقد عمل', 'هوية', 'رخصة قيادة', 'شهادة جامعية', 
    'كشف رواتب', 'صورة شخصية', 'شهادة تدريب', '其它'
);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الطباعة - إدارة المستندات</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
            --bg-color: #f8f9fa;
            --text-color: #333;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Tajawal', sans-serif;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .page-title {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--secondary-color);
            text-align: center;
        }
        
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light-color);
            color: var(--secondary-color);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
        }
        
        .form-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 16px;
            background-color: white;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .btn-success {
            background-color: var(--success-color);
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .table th, .table td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }
        
        .table th {
            background-color: var(--light-color);
            font-weight: 600;
        }
        
        .table tr:hover {
            background-color: #f5f5f5;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .badge-warning {
            background-color: var(--warning-color);
            color: white;
        }
        
        .badge-info {
            background-color: var(--primary-color);
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: var(--border-radius);
            width: 80%;
            max-width: 600px;
            box-shadow: var(--box-shadow);
        }
        
        .close {
            color: #aaa;
            float: left;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: black;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }
        
        .tab.active {
            border-bottom: 3px solid var(--primary-color);
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @media (max-width: 768px) {
            .table {
                display: block;
                overflow-x: auto;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">نظام إدارة الطباعة</div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo substr($_SESSION['username'] ?? 'U', 0, 1); ?>
                </div>
                <div>
                    <div><?php echo $_SESSION['username'] ?? 'مستخدم'; ?></div>
                    <div style="font-size: 12px; color: #777;">إدارة المستندات</div>
                </div>
            </div>
        </header>
        
        <h1 class="page-title">إدارة مستندات الموظفين</h1>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('upload')">رفع مستند جديد</div>
            <div class="tab" onclick="switchTab('manage')">إدارة المستندات</div>
        </div>
        
        <div id="upload-tab" class="tab-content active">
            <div class="card">
                <h2 class="card-title">رفع مستند جديد</h2>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">الموظف</label>
                        <select class="form-select" name="employee_id" required>
                            <option value="">اختر الموظف</option>
                            <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['employee_id']; ?>">
                                <?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">نوع المستند</label>
                        <select class="form-select" name="document_type" required>
                            <option value="">اختر نوع المستند</option>
                            <?php foreach ($document_types as $type): ?>
                            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">وصف المستند (اختياري)</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">مستوى الصلاحية</label>
                        <select class="form-select" name="access_level">
                            <option value="private">خاص (الموارد البشرية فقط)</option>
                            <option value="public">عام (جميع المديرين)</option>
                            <option value="restricted">مقيد (المدير المباشر فقط)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">رفع الملف</label>
                        <input type="file" class="form-control" name="document_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt" required>
                        <small>الحد الأقصى لحجم الملف: 5MB. الأنواع المسموحة: PDF, DOC, DOCX, JPG, JPEG, PNG, TXT</small>
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-upload"></i> رفع المستند
                    </button>
                </form>
            </div>
        </div>
        
        <div id="manage-tab" class="tab-content">
            <div class="card">
                <h2 class="card-title">المستندات المرفوعة</h2>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>اسم المستند</th>
                                <th>الموظف</th>
                                <th>النوع</th>
                                <th>مستوى الصلاحية</th>
                                <th>تاريخ الرفع</th>
                                <th>رفع بواسطة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($documents)): ?>
                                <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td><?php echo $doc['document_name']; ?></td>
                                    <td><?php echo $doc['first_name'] . ' ' . $doc['last_name']; ?></td>
                                    <td><?php echo $doc['document_type']; ?></td>
                                    <td>
                                        <?php 
                                        if ($doc['access_level'] == 'public') {
                                            echo '<span class="badge badge-success">عام</span>';
                                        } elseif ($doc['access_level'] == 'restricted') {
                                            echo '<span class="badge badge-warning">مقيد</span>';
                                        } else {
                                            echo '<span class="badge badge-info">خاص</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($doc['uploaded_at'])); ?></td>
                                    <td><?php echo $doc['uploaded_by_name']; ?></td>
                                    <td class="action-buttons">
                                        <a href="<?php echo $doc['file_path']; ?>" target="_blank" class="btn">
                                            <i class="fas fa-eye"></i> عرض
                                        </a>
                                        <a href="<?php echo $doc['file_path']; ?>" download class="btn">
                                            <i class="fas fa-download"></i> تحميل
                                        </a>
                                        <a href="?delete=<?php echo $doc['document_id']; ?>" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا المستند؟')">
                                            <i class="fas fa-trash"></i> حذف
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">لا توجد مستندات مرفوعة بعد</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // إخفاء جميع محتويات التبويبات
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // إلغاء تنشيط جميع التبويبات
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // إظهار المحتوى المحدد وتنشيط التبويب
            document.getElementById(tabName + '-tab').classList.add('active');
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>