<?php
session_start();
require_once 'includes/db.php';

$q = $_GET['q'] ?? '';
$q = "%$q%";

$stmt = $db->prepare("SELECT * FROM products 
                      WHERE name LIKE ? 
                      OR description LIKE ? 
                      OR price LIKE ? 
                      OR quantity LIKE ?
                      LIMIT 10");
$stmt->execute([$q, $q, $q, $q]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results);
?>
