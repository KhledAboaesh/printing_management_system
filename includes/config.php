<?php
// config.php
// إعدادات الاتصال بقاعدة البيانات
// إعدادات قاعدة البيانات
$host = 'localhost';
$dbname = 'printing_management_system';
$user = 'root';
$pass = '';
              // كلمة المرور


// إنشاء اتصال PDO
try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}
?>
