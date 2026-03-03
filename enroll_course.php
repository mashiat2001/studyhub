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
        // Get course details for notification (before enrollment)
        $course_stmt = $conn->prepare("SELECT title, instructor_id FROM courses WHERE id = ?");
        $course_stmt->bind_param("i", $course_id);
        $course_stmt->execute();
        $course = $course_stmt->get_result()->fetch_assoc();
        $course_stmt->close();
        
        // Enroll student
        $enroll_stmt = $conn->prepare("INSERT INTO user_courses (user_id, course_id, progress, status) VALUES (?, ?, 0, 'enrolled')");
        $enroll_stmt->bind_param("ii", $user['id'], $course_id);
        $enroll_stmt->execute();
        $enroll_stmt->close();
        
        // --- NOTIFICATIONS ---
        
        // 1. Notify student
        $student_message = "You have successfully enrolled in '{$course['title']}'.";
        $student_link = "course_content.php?course_id={$course_id}";
        $notify_student = $conn->prepare("INSERT INTO notifications (user_id, role, message, link) VALUES (?, 'student', ?, ?)");
        $notify_student->bind_param("iss", $user['id'], $student_message, $student_link);
        $notify_student->execute();
        $notify_student->close();
        
        // 2. Notify instructor (if any)
        if (!empty($course['instructor_id'])) {
            $instructor_id = $course['instructor_id'];
            $student_name = $user['name'];
            $instructor_message = "Student {$student_name} has enrolled in your course '{$course['title']}'.";
            $instructor_link = "instructor_analytics.php?course_id={$course_id}"; // adjust if you have a different page
            $notify_instructor = $conn->prepare("INSERT INTO notifications (user_id, role, message, link) VALUES (?, 'instructor', ?, ?)");
            $notify_instructor->bind_param("iss", $instructor_id, $instructor_message, $instructor_link);
            $notify_instructor->execute();
            $notify_instructor->close();
        }
        
        $_SESSION['enrollment_success'] = "Successfully enrolled in the course!";
    }
    
    $check_stmt->close();
    header("Location: student_dashboard.php");
    exit();
}

$conn->close();
?>