<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['unread_count' => 0, 'notifications' => []]);
    exit;
}

$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role']; // 'student', 'instructor', or 'admin'

$conn = new mysqli('localhost', 'root', '', 'project_db');
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Debug: log session info
error_log("get_notifications: user_id=$user_id, role=$role");

// Get unread count
$stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND role = ? AND is_read = 0");
if (!$stmt) {
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param("is", $user_id, $role);
$stmt->execute();
$result = $stmt->get_result();
$unread = $result->fetch_assoc()['unread'];
$stmt->close();

// Get recent 10 notifications
$stmt = $conn->prepare("SELECT message, link, is_read, created_at FROM notifications WHERE user_id = ? AND role = ? ORDER BY created_at DESC LIMIT 10");
if (!$stmt) {
    echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param("is", $user_id, $role);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'message' => htmlspecialchars($row['message']),
        'link' => htmlspecialchars($row['link']),
        'is_read' => (bool)$row['is_read'],
        'time' => timeAgo($row['created_at'])
    ];
}

$conn->close();

echo json_encode(['unread_count' => $unread, 'notifications' => $notifications]);

function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) return "Just now";
    else if ($minutes <= 60) return $minutes == 1 ? "1 minute ago" : "$minutes minutes ago";
    else if ($hours <= 24) return $hours == 1 ? "1 hour ago" : "$hours hours ago";
    else if ($days <= 7) return $days == 1 ? "yesterday" : "$days days ago";
    else if ($weeks <= 4.3) return $weeks == 1 ? "1 week ago" : "$weeks weeks ago";
    else if ($months <= 12) return $months == 1 ? "1 month ago" : "$months months ago";
    else return $years == 1 ? "1 year ago" : "$years years ago";
}
?>