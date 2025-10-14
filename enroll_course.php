<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

if (isset($_POST['course_id'])) {
    $course_id = intval($_POST['course_id']);
    
    // Check if already enrolled
    $check_stmt = $conn->prepare("SELECT id FROM user_courses WHERE user_id = ? AND course_id = ?");
    $check_stmt->bind_param("ii", $user['id'], $course_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows === 0) {
        // Enroll student
        $enroll_stmt = $conn->prepare("INSERT INTO user_courses (user_id, course_id, progress, status) VALUES (?, ?, 0, 'enrolled')");
        $enroll_stmt->bind_param("ii", $user['id'], $course_id);
        $enroll_stmt->execute();
        $enroll_stmt->close();
        
        $_SESSION['enrollment_success'] = "Successfully enrolled in the course!";
    }
    
    $check_stmt->close();
    header("Location: student_dashboard.php");
    exit();
}

$conn->close();
?>