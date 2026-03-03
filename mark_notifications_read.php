<?php
session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit;
}

$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

$conn = new mysqli('localhost', 'root', '', 'project_db');
if ($conn->connect_error) {
    http_response_code(500);
    exit;
}

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND role = ? AND is_read = 0");
$stmt->bind_param("is", $user_id, $role);
$stmt->execute();
$conn->close();

echo json_encode(['success' => true]);
?>