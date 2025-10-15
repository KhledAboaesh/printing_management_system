<?php
// dashboard.php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// التأكد من تسجيل الدخول
checkLogin();

// الحصول على معرف الدور والمعرف الخاص بالمستخدم
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'guest';

if (!$user_id) {
    header("Location: login.php");
    exit();
}







?>













<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    
</body>
</html>