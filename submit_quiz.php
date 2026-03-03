<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['quiz_id'])) {
    header('Location: student_dashboard.php');
    exit();
}

$quiz_id = intval($_POST['quiz_id']);
$answers = $_POST['answer'] ?? [];

// Get quiz questions
$questions_stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ?");
$questions_stmt->bind_param("i", $quiz_id);
$questions_stmt->execute();
$questions = $questions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate score
$score = 0;
$total_questions = count($questions);

foreach ($questions as $question) {
    $question_id = $question['id'];
    $user_answer = $answers[$question_id] ?? null;
    
    if ($user_answer && $user_answer === $question['correct_answer']) {
        $score += $question['marks'];
    }
}

$percentage = ($score / array_sum(array_column($questions, 'marks'))) * 100;

// --- Weak Topics Tracking ---
if ($percentage < 50) {
    // Get quiz title and course title
    $quiz_stmt = $conn->prepare("SELECT title, course_id FROM quizzes WHERE id = ?");
    $quiz_stmt->bind_param("i", $quiz_id);
    $quiz_stmt->execute();
    $quiz = $quiz_stmt->get_result()->fetch_assoc();

    if ($quiz) {
        $course_stmt = $conn->prepare("SELECT title FROM courses WHERE id = ?");
        $course_stmt->bind_param("i", $quiz['course_id']);
        $course_stmt->execute();
        $course = $course_stmt->get_result()->fetch_assoc();

        if ($course) {
            $topic = $quiz['title'] . " in " . $course['title'];
            // Initialize weak_topics array if not exists
            if (!isset($_SESSION['weak_topics'])) {
                $_SESSION['weak_topics'] = [];
            }
            $_SESSION['weak_topics'][] = $topic;
            // Keep only last 5 unique topics
            $_SESSION['weak_topics'] = array_slice(array_unique($_SESSION['weak_topics']), -5);
        }
    }
}

// Save result
$result_stmt = $conn->prepare("INSERT INTO quiz_results 
    (user_id, quiz_id, score, total_questions, percentage) 
    VALUES (?, ?, ?, ?, ?)");
$result_stmt->bind_param("iiisd", $user['id'], $quiz_id, $score, $total_questions, $percentage);
$result_stmt->execute();

$conn->close();

// Redirect to results page
header("Location: quiz_results.php?quiz_id=$quiz_id");
exit();
?>