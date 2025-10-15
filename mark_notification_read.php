<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    echo json_encode(['success' => false]);
    exit();
}

try {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('Error marking notification as read: ' . $e->getMessage());
    echo json_encode(['success' => false]);
}