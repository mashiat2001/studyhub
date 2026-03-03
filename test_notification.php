<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    die('Not logged in as student');
}

$user_id = $_SESSION['user']['id'];
$conn = new mysqli('localhost', 'root', '', 'project_db');

$stmt = $conn->prepare("INSERT INTO notifications (user_id, role, message, link) VALUES (?, 'student', ?, 'student_dashboard.php')");
$message = "Welcome to your dashboard! This is a test notification.";
$stmt->bind_param("is", $user_id, $message);
$stmt->execute();
$conn->close();

echo "Test notification added. <a href='student_dashboard.php'>Go to dashboard</a>";
?>