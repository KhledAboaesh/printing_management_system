<?php
// ملف includes/db.php

class Database {
    private $host = 'localhost:3360';
    private $db_name = 'printing_management_system';
    private $username = 'root'; // افتراضيًا أو غيرها حسب إعداداتك
    private $password = ''; // كلمة السر إن وجدت
    private $conn;

    // دالة الاتصال بقاعدة البيانات
    public function connect() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec('SET NAMES utf8mb4');
            $this->conn->exec('SET CHARACTER SET utf8mb4');
        } catch(PDOException $e) {
            // في بيئة الإنتاج لا تعرض الخطأ للمستخدم
            error_log('Connection Error: ' . $e->getMessage());
            die('حدث خطأ في الاتصال بقاعدة البيانات. الرجاء المحاولة لاحقًا.');
        }

        return $this->conn;
    }

    // دالة لإغلاق الاتصال
    public function close() {
        $this->conn = null;
    }
}

// إنشاء اتصال عام يمكن استخدامه في جميع الصفحات
$database = new Database();
$db = $database->connect();
?>