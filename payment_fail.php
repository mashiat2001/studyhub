<?php
session_start();
$order_id = $_GET['order_id'] ?? 0;

$conn = new mysqli('localhost', 'root', '', 'project_db');
$update = $conn->prepare("UPDATE orders SET status = 'failed' WHERE id = ?");
$update->bind_param("i", $order_id);
$update->execute();
$conn->close();

echo "<h1>Payment Failed</h1>";
echo "<a href='student_dashboard.php'>Try Again</a>";
?>