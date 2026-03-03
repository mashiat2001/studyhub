<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in as student
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    echo json_encode(['reply' => 'Please log in as a student first.']);
    exit;
}

$user_id = $_SESSION['user']['id'];

// Database connection
$conn = new mysqli('localhost', 'root', '', 'project_db');
if ($conn->connect_error) {
    echo json_encode(['reply' => 'Database connection failed.']);
    exit;
}

$user_message = $_POST['message'] ?? '';
$mode = $_POST['mode'] ?? 'normal';
$course = $_POST['course'] ?? '';
$lecture = $_POST['lecture'] ?? '';

if (empty($user_message)) {
    echo json_encode(['reply' => 'Please ask something.']);
    exit;
}

// Initialize chat history in session if not exists
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// Append user message to history
$_SESSION['chat_history'][] = ['role' => 'user', 'content' => $user_message];

// Keep only last 10 messages
if (count($_SESSION['chat_history']) > 10) {
    array_shift($_SESSION['chat_history']);
}

// -------------------- HELPER FUNCTIONS --------------------

// Get titles of courses the student is enrolled in
function getEnrolledCoursesTitles($user_id, $conn) {
    $stmt = $conn->prepare("SELECT c.title FROM user_courses uc JOIN courses c ON uc.course_id = c.id WHERE uc.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $titles = [];
    while ($row = $result->fetch_assoc()) {
        $titles[] = $row['title'];
    }
    return $titles;
}

// Fetch material excerpts for a given course ID
function getCourseMaterialExcerpts($course_id, $conn) {
    $stmt = $conn->prepare("SELECT file_name, content FROM course_material_contents WHERE course_id = ? LIMIT 3");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $excerpts = [];
    while ($row = $result->fetch_assoc()) {
        $excerpt = substr($row['content'], 0, 1000); // first 1000 chars
        $excerpts[] = "From file '{$row['file_name']}':\n$excerpt";
    }
    return $excerpts;
}

// -------------------- INTENT DETECTION --------------------
function detect_intent($message) {
    $msg = strtolower($message);
    
    if (strpos($msg, 'my courses') !== false || (strpos($msg, 'course') !== false && strpos($msg, 'enrolled') !== false)) {
        return 'courses';
    }
    if ((strpos($msg, 'available') !== false || strpos($msg, 'browse') !== false) && strpos($msg, 'course') !== false) {
        return 'available_courses';
    }
    if ((strpos($msg, 'quiz') !== false || strpos($msg, 'quizzes') !== false) && 
        (strpos($msg, 'result') !== false || strpos($msg, 'score') !== false || strpos($msg, 'grade') !== false || strpos($msg, 'performance') !== false)) {
        return 'quiz_results';
    }
    if (strpos($msg, 'quiz') !== false || strpos($msg, 'quizzes') !== false) {
        return 'quizzes';
    }
    return 'ai';
}

$intent = detect_intent($user_message);

// -------------------- DATABASE FUNCTIONS (unchanged) --------------------
function getCourses($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT c.title 
        FROM user_courses uc 
        JOIN courses c ON uc.course_id = c.id 
        WHERE uc.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row['title'];
    }
    if (empty($courses)) {
        return "You are not enrolled in any courses.";
    }
    return "You are enrolled in: " . implode(", ", $courses);
}

function getAvailableCourses($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT title, description, instructor, price, level
        FROM courses 
        WHERE status = 'approved' 
        AND id NOT IN (SELECT course_id FROM user_courses WHERE user_id = ?)
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        return "No new courses available at the moment.";
    }
    
    $output = "Available courses:\n";
    while ($row = $result->fetch_assoc()) {
        $price = $row['price'] == 0 ? 'FREE' : '$' . $row['price'];
        $output .= "- {$row['title']} ({$row['level']}) - {$price}\n  {$row['description']}\n";
    }
    return $output;
}

function listQuizzes($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT q.title, q.description, c.title as course_title
        FROM quizzes q
        JOIN courses c ON q.course_id = c.id
        JOIN user_courses uc ON c.id = uc.course_id
        WHERE uc.user_id = ? AND q.status = 'active'
        ORDER BY c.title, q.title
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        return "No quizzes available for your enrolled courses.";
    }
    
    $output = "Quizzes you can take:\n";
    while ($row = $result->fetch_assoc()) {
        $output .= "- {$row['title']} (Course: {$row['course_title']})\n";
    }
    return $output;
}

function getQuizResults($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT q.title as quiz_title, c.title as course_title, r.score, r.total_questions, r.percentage, r.completed_at
        FROM quiz_results r
        JOIN quizzes q ON r.quiz_id = q.id
        JOIN courses c ON q.course_id = c.id
        WHERE r.user_id = ?
        ORDER BY r.completed_at DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        return "You haven't taken any quizzes yet.";
    }
    
    $output = "Your recent quiz results:\n";
    while ($row = $result->fetch_assoc()) {
        $output .= "- {$row['quiz_title']} ({$row['course_title']}): {$row['percentage']}% ({$row['score']}/{$row['total_questions']}) on " . date('M j, Y', strtotime($row['completed_at'])) . "\n";
    }
    return $output;
}

// -------------------- ENHANCED AI FUNCTION WITH COURSE MATERIALS --------------------
function askGemini($prompt, $history, $user_id, $conn, $mode = 'normal', $course = '', $lecture = '') {
    $api_key = 'AIzaSyCfNplW7PiCLDkGjn-ucr5AJ5N8uLuKNrQ';

    // Get enrolled courses for this student
    $enrolledCourses = getEnrolledCoursesTitles($user_id, $conn);

    // Determine which course to fetch materials for
    $targetCourse = null;
    // First, use explicit course from context if provided
    if ($course && in_array($course, $enrolledCourses)) {
        $targetCourse = $course;
    } else {
        // Otherwise, search prompt for any enrolled course title
        foreach ($enrolledCourses as $c) {
            if (stripos($prompt, $c) !== false) {
                $targetCourse = $c;
                break;
            }
        }
    }

    // Fetch materials if a course is identified
    $materialsText = "";
    if ($targetCourse) {
        // Get course ID
        $stmt = $conn->prepare("SELECT id FROM courses WHERE title = ?");
        $stmt->bind_param("s", $targetCourse);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $course_id = $row['id'];
            $excerpts = getCourseMaterialExcerpts($course_id, $conn);
            if (!empty($excerpts)) {
                $materialsText = "The following are excerpts from the course materials for '{$targetCourse}':\n\n" . implode("\n\n", $excerpts) . "\n\n";
            } else {
                $materialsText = "No specific course materials were found for '{$targetCourse}'. Use your general knowledge.\n\n";
            }
        }
    }

    // Mode instructions
    $modeInstructions = [
        'normal' => 'Explain the topic in a clear, standard academic tone.',
        'simple' => 'Explain the topic in very simple, beginner-friendly language. Use analogies and avoid jargon.',
        'examples' => 'Explain the topic and provide practical code examples or real-world examples.',
        'summary' => 'Provide a concise summary of the topic, highlighting key points for quick revision.'
    ];
    $instruction = $modeInstructions[$mode] ?? $modeInstructions['normal'];

    // Build context string
    $context = "";
    if ($targetCourse) {
        $context .= "The student is currently studying the course: \"$targetCourse\". ";
    }
    if ($lecture) {
        $context .= "They are on the lecture: \"$lecture\". ";
    }

    // Include weak topics if available
    $weakTopics = $_SESSION['weak_topics'] ?? [];
    if (!empty($weakTopics)) {
        $context .= "The student has shown weakness in the following topics: " . implode(", ", $weakTopics) . ". ";
    }

    // Build conversation history
    $historyText = "";
    foreach ($history as $msg) {
        $role = ($msg['role'] == 'user') ? 'Student' : 'Assistant';
        $historyText .= "$role: " . $msg['content'] . "\n";
    }

    // Final prompt
    $full_prompt = "You are a helpful academic assistant for an online learning platform. 
$materialsText
$context $instruction

Conversation history:
$historyText

Now answer the student's latest question: $prompt

Answer in a friendly, encouraging tone.";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key;

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $full_prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($http_code == 200) {
        $result = json_decode($response, true);
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? "I couldn't understand that.";
    } else {
        error_log("Gemini API error: HTTP $http_code, cURL error: $curl_error, Response: $response");
        return "I'm having trouble connecting to my knowledge base. Please try again later.";
    }
}

// -------------------- MAIN LOGIC --------------------
switch ($intent) {
    case 'courses':
        $reply = getCourses($user_id, $conn);
        break;
    case 'available_courses':
        $reply = getAvailableCourses($user_id, $conn);
        break;
    case 'quiz_results':
        $reply = getQuizResults($user_id, $conn);
        break;
    case 'quizzes':
        $reply = listQuizzes($user_id, $conn);
        break;
    default:
        $reply = askGemini($user_message, $_SESSION['chat_history'], $user_id, $conn, $mode, $course, $lecture);
        break;
}

// Append bot reply to history
$_SESSION['chat_history'][] = ['role' => 'assistant', 'content' => $reply];
// Keep last 10 messages
if (count($_SESSION['chat_history']) > 10) {
    array_shift($_SESSION['chat_history']);
}

echo json_encode(['reply' => $reply]);
$conn->close();
?>