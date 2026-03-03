<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

// Get enrolled courses with instructor name
$enrolled_courses = [];
$enrolled_stmt = $conn->prepare("
    SELECT c.*, uc.progress, uc.status, u.name as instructor_name
    FROM user_courses uc 
    JOIN courses c ON uc.course_id = c.id 
    LEFT JOIN users u ON c.instructor_id = u.id
    WHERE uc.user_id = ? AND c.status = 'approved'
");
$enrolled_stmt->bind_param("i", $user['id']);
$enrolled_stmt->execute();
$enrolled_result = $enrolled_stmt->get_result();
while ($row = $enrolled_result->fetch_assoc()) {
    $enrolled_courses[] = $row;
}

// Get available courses with instructor name
$available_courses = [];
$available_stmt = $conn->prepare("
    SELECT c.*, u.name as instructor_name
    FROM courses c
    LEFT JOIN users u ON c.instructor_id = u.id
    WHERE c.status = 'approved' 
    AND c.id NOT IN (SELECT course_id FROM user_courses WHERE user_id = ?)
    ORDER BY c.created_at DESC
");
$available_stmt->bind_param("i", $user['id']);
$available_stmt->execute();
$available_result = $available_stmt->get_result();
while ($row = $available_result->fetch_assoc()) {
    $available_courses[] = $row;
}

// Quiz statistics and performance data
$quiz_stats_stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT q.id) as total_quizzes,
        COUNT(r.id) as taken_quizzes,
        COALESCE(AVG(r.percentage), 0) as avg_score
    FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    JOIN user_courses uc ON c.id = uc.course_id
    LEFT JOIN quiz_results r ON q.id = r.quiz_id AND r.user_id = ?
    WHERE uc.user_id = ?
");
$quiz_stats_stmt->bind_param("ii", $user['id'], $user['id']);
$quiz_stats_stmt->execute();
$quiz_stats = $quiz_stats_stmt->get_result()->fetch_assoc();

// Recent quiz results
$recent_results_stmt = $conn->prepare("
    SELECT r.*, q.title as quiz_title, c.title as course_title
    FROM quiz_results r
    JOIN quizzes q ON r.quiz_id = q.id
    JOIN courses c ON q.course_id = c.id
    WHERE r.user_id = ?
    ORDER BY r.completed_at DESC
    LIMIT 5
");
$recent_results_stmt->bind_param("i", $user['id']);
$recent_results_stmt->execute();
$recent_results = $recent_results_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Performance analysis per course
$performance_stmt = $conn->prepare("
    SELECT 
        c.title as course_title,
        COUNT(r.id) as quizzes_taken,
        AVG(r.percentage) as avg_score,
        SUM(CASE WHEN r.percentage < 50 THEN 1 ELSE 0 END) as weak_quizzes
    FROM quiz_results r
    JOIN quizzes q ON r.quiz_id = q.id
    JOIN courses c ON q.course_id = c.id
    WHERE r.user_id = ?
    GROUP BY c.id
    ORDER BY avg_score ASC
");
$performance_stmt->bind_param("i", $user['id']);
$performance_stmt->execute();
$performance_result = $performance_stmt->get_result();
$performance_data = [];
while ($row = $performance_result->fetch_assoc()) {
    $performance_data[] = $row;
}

// Overall stats
$overall_stmt = $conn->prepare("
    SELECT 
        COUNT(r.id) as total_quizzes_taken,
        AVG(r.percentage) as overall_avg
    FROM quiz_results r
    WHERE r.user_id = ?
");
$overall_stmt->bind_param("i", $user['id']);
$overall_stmt->execute();
$overall = $overall_stmt->get_result()->fetch_assoc();

$conn->close();

// --- AI Personalized Recommendations (short & actionable) ---
function getAIPersonalizedRecommendations($studentName, $enrolledCoursesWithProgress, $overallAvg, $weakTopicsDetailed, $availableCoursesTitles) {
    $api_key = 'AIzaSyCfNplW7PiCLDkGjn-ucr5AJ5N8uLuKNrQ';

    $prompt = "You are a study assistant for $studentName. Based on this data, create a personalized learning path with 3-5 very specific, actionable steps. Each step must be a concrete action like 'Review Module X', 'Take Quiz Y', or 'Enroll in Course Z'. Use the exact names of courses, quizzes, and modules if you can infer them. Be concise.

Student data:
Enrolled courses (progress):
";
    foreach ($enrolledCoursesWithProgress as $ec) {
        $prompt .= "- {$ec['title']} ({$ec['progress']}%)\n";
    }

    $prompt .= "Overall quiz average: {$overallAvg}%\n";
    if (!empty($weakTopicsDetailed)) {
        $prompt .= "Weak areas:\n";
        foreach ($weakTopicsDetailed as $wt) {
            $prompt .= "- {$wt}\n";
        }
    }
    if (!empty($availableCoursesTitles)) {
        $prompt .= "Available courses: " . implode(", ", $availableCoursesTitles) . "\n";
    }

    $prompt .= "Now, provide 3-5 specific steps. Start each with '•' and keep it to one line per step. Do not add any extra text.";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key;

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 250
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
    curl_close($ch);

    if ($http_code == 200) {
        $result = json_decode($response, true);
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? "No recommendations right now.";
    } else {
        return "AI service unavailable. Please try later.";
    }
}

// Prepare AI data
$enrolledWithProgress = [];
foreach ($enrolled_courses as $ec) {
    $enrolledWithProgress[] = [
        'title' => $ec['title'],
        'progress' => $ec['progress']
    ];
}

$weakTopicsDetailed = [];
foreach ($performance_data as $course) {
    if ($course['weak_quizzes'] > 0) {
        $weakTopicsDetailed[] = $course['course_title'] . " (" . $course['weak_quizzes'] . " weak quiz, avg " . number_format($course['avg_score'], 1) . "%)";
    }
}
$overallAvgFormatted = number_format($overall['overall_avg'] ?? 0, 1);

$availableTitles = array_column($available_courses, 'title');

$aiRecommendations = getAIPersonalizedRecommendations(
    $user['name'],
    $enrolledWithProgress,
    $overallAvgFormatted,
    $weakTopicsDetailed,
    $availableTitles
);

$motivationalQuotes = [
    "The beautiful thing about learning is that no one can take it away from you. - B.B. King",
    "Education is the most powerful weapon which you can use to change the world. - Nelson Mandela",
    "The capacity to learn is a gift; the ability to learn is a skill; the willingness to learn is a choice. - Brian Herbert",
    "Learning never exhausts the mind. - Leonardo da Vinci",
    "The more that you read, the more things you will know. The more that you learn, the more places you'll go. - Dr. Seuss"
];
$randomQuote = $motivationalQuotes[array_rand($motivationalQuotes)];

$chat_history = $_SESSION['chat_history'] ?? [];

$current_course = $_GET['course'] ?? '';
$current_lecture = $_GET['lecture'] ?? '';

$refresh_url = $_SERVER['PHP_SELF'] . '?refresh=' . time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - StudyHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #5a67d8;
            --primary-light: #7c8cf0;
            --primary-dark: #434190;
            --secondary: #ed8936;
            --success: #48bb78;
            --warning: #ecc94b;
            --danger: #f56565;
            --text-dark: #1a202c;
            --text-light: #4a5568;
            --text-muted: #718096;
            --light-bg: #f7fafc;
            --border-light: #e2e8f0;
            --card-bg: #ffffff;
            --border-radius: 10px;
            --transition: all 0.2s ease;
            --shadow-sm: 0 2px 5px rgba(0,0,0,0.05);
            --shadow-md: 0 5px 15px rgba(0,0,0,0.07);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--light-bg);
            color: var(--text-dark);
            line-height: 1.5;
        }
        
        .header {
            background: var(--card-bg);
            padding: 1rem 2rem;
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-light);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        .logo-text {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .logo-text span {
            color: var(--primary);
        }
        
        .right-section {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .notification-dropdown {
            position: relative;
        }
        .notification-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--text-light);
            cursor: pointer;
            position: relative;
            padding: 0.5rem;
        }
        .badge {
            position: absolute;
            top: 0;
            right: 0;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 0.15rem 0.4rem;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .notification-panel {
            display: none;
            position: absolute;
            right: 0;
            top: 2.5rem;
            width: 300px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            border: 1px solid var(--border-light);
        }
        .notification-panel.show {
            display: block;
        }
        .notification-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notification-header h4 {
            font-size: 0.95rem;
            font-weight: 600;
        }
        .notification-header a {
            font-size: 0.8rem;
            color: var(--primary);
            text-decoration: none;
        }
        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .notification-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-light);
            cursor: pointer;
            transition: background 0.2s;
            font-size: 0.9rem;
        }
        .notification-item:hover {
            background: var(--light-bg);
        }
        .notification-item.unread {
            background: #ebf8ff;
        }
        .notification-item .message {
            margin-bottom: 0.25rem;
        }
        .notification-item .time {
            font-size: 0.7rem;
            color: var(--text-muted);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .user-role {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        
        .logout-btn {
            background: var(--primary);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .logout-btn:hover {
            background: var(--primary-dark);
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
        }
        
        .welcome-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .welcome-subtitle {
            opacity: 0.9;
            font-size: 1rem;
        }
        
        .alert {
            padding: 0.75rem 1.25rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            font-size: 0.95rem;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: #f0fff4;
            border-color: var(--success);
            color: #22543d;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--border-light);
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.2;
        }
        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin: 2rem 0 1.25rem;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .section-title i {
            color: var(--primary);
            font-size: 1.3rem;
        }
        
        /* AI Recommendations Card */
        .ai-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: var(--border-radius);
            padding: 1.8rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
            color: white;
        }
        .ai-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 250px;
            height: 250px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        .ai-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
            position: relative;
            z-index: 1;
        }
        .ai-icon {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-size: 1.6rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .ai-title {
            font-size: 1.3rem;
            font-weight: 600;
        }
        .refresh-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: 0.2s;
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .refresh-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        .ai-content {
            position: relative;
            z-index: 1;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(8px);
            border-radius: 10px;
            padding: 1.5rem;
            line-height: 1.7;
            font-size: 0.95rem;
            white-space: pre-line;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .feature-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            transition: var(--transition);
            border: 1px solid var(--border-light);
        }
        .feature-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .feature-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        
        .feature-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
        }
        
        .feature-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        /* Quiz Center Card (compact) */
        .quiz-stats {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .quiz-progress {
            position: relative;
            width: 80px;
            height: 80px;
            flex-shrink: 0;
        }
        .quiz-progress svg {
            width: 80px;
            height: 80px;
            transform: rotate(-90deg);
        }
        .quiz-progress circle {
            fill: none;
            stroke-width: 8;
            stroke: var(--border-light);
        }
        .quiz-progress .progress-bar {
            stroke: var(--primary);
            stroke-dasharray: 200.96; /* 2 * π * 32 ≈ 200.96 */
            stroke-dashoffset: calc(200.96 * (1 - <?php echo ($quiz_stats['avg_score']/100) ?? 0; ?>));
            transition: stroke-dashoffset 0.5s;
        }
        .quiz-progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
        }
        .quiz-numbers {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .quiz-number-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
        }
        .quiz-number-item span:first-child {
            color: var(--text-muted);
        }
        .quiz-number-item span:last-child {
            font-weight: 600;
            color: var(--primary-dark);
        }
        .quiz-action-btn {
            margin-top: 0.5rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 3px 8px rgba(90, 103, 216, 0.3);
            text-decoration: none;
        }
        .quiz-action-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        /* Chat Card */
        .chat-card {
            height: 420px;
        }
        .chat-messages {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding: 0.5rem 0.5rem 0;
            margin: 0.5rem 0;
            background: var(--light-bg);
            border-radius: 8px;
            scroll-behavior: smooth;
        }
        .message {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin: 0.75rem 0;
        }
        .message-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        .user-message .message-avatar {
            background: var(--primary);
            color: white;
        }
        .assistant-message .message-avatar {
            background: var(--secondary);
            color: white;
        }
        .message-content {
            padding: 0.5rem 0.75rem;
            border-radius: 14px;
            max-width: calc(100% - 40px);
            word-break: break-word;
            font-size: 0.9rem;
            background: white;
            border: 1px solid var(--border-light);
        }
        .user-message .message-content {
            background: var(--primary);
            color: white;
            border: none;
        }
        .typing-indicator {
            display: none;
            align-items: center;
            gap: 0.3rem;
            padding: 0.5rem 0.75rem;
            background: white;
            border: 1px solid var(--border-light);
            border-radius: 30px;
            width: fit-content;
            margin: 0.5rem 0;
        }
        .typing-indicator span {
            width: 6px;
            height: 6px;
            background: var(--text-muted);
            border-radius: 50%;
            animation: typing 1s infinite ease-in-out;
        }
        @keyframes typing {
            0%,60%,100%{transform:translateY(0)}
            30%{transform:translateY(-6px)}
        }
        .chat-mode-selector {
            display: flex;
            gap: 0.4rem;
            margin: 0.5rem 0;
            flex-wrap: wrap;
        }
        .mode-btn {
            padding: 0.25rem 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: 30px;
            background: white;
            font-size: 0.8rem;
            cursor: pointer;
            transition: 0.2s;
        }
        .mode-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .smart-tools {
            display: flex;
            gap: 0.5rem;
            margin: 0.5rem 0;
            flex-wrap: wrap;
        }
        .smart-tools button {
            background: var(--light-bg);
            border: 1px solid var(--border-light);
            border-radius: 30px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        .smart-tools button:hover {
            background: #e2e8f0;
        }
        .chat-input-group {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .chat-input {
            flex: 1;
            padding: 0.6rem 1rem;
            border: 1px solid var(--border-light);
            border-radius: 30px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .chat-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(90,103,216,0.1);
        }
        .chat-send-btn {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 0.6rem 1.2rem;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.3rem;
            box-shadow: 0 3px 8px rgba(90,103,216,0.3);
        }
        .chat-send-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .quote-card {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }
        .quote-icon {
            font-size: 1.6rem;
            margin-bottom: 0.5rem;
            opacity: 0.8;
        }
        .quote-text {
            font-size: 1rem;
            font-style: italic;
            line-height: 1.5;
        }
        .quote-author {
            text-align: right;
            opacity: 0.8;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }
        .performance-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.25rem;
            box-shadow: var(--shadow-sm);
            border-left: 4px solid transparent;
            transition: var(--transition);
            border: 1px solid var(--border-light);
        }
        .performance-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .performance-card.overall-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-left: none;
        }
        .performance-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }
        .performance-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .performance-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }
        .performance-card.overall-card .performance-label {
            color: rgba(255,255,255,0.8);
        }
        .performance-detail {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
        }
        .weak-badge {
            background: #fed7d7;
            color: #9b2c2c;
            padding: 0.2rem 0.6rem;
            border-radius: 30px;
            font-size: 0.7rem;
        }
        .recommendation {
            margin-top: 0.75rem;
            padding: 0.5rem;
            background: #fefcbf;
            border-left: 3px solid #d69e2e;
            border-radius: 4px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .course-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--border-light);
        }
        .course-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .course-header {
            background: var(--primary-light);
            color: white;
            padding: 1rem;
        }
        .course-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .course-instructor {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        .course-body {
            padding: 1rem;
        }
        .course-description {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .progress-container {
            margin-bottom: 0.75rem;
        }
        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--border-light);
            border-radius: 3px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: var(--primary);
        }
        .progress-text {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }
        .course-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
        }
        .course-price {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.75rem;
            text-align: center;
        }
        .price-free {
            color: var(--success);
        }
        .course-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .btn {
            padding: 0.5rem 0.8rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: var(--transition);
            flex: 1;
            text-align: center;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.3rem;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .btn-success {
            background: var(--success);
            color: white;
        }
        .btn-success:hover {
            background: #2f855a;
        }
        .btn-quiz {
            background: #9f7aea;
            color: white;
        }
        .btn-quiz:hover {
            background: #805ad5;
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--border-light);
        }
        
        .payment-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            max-width: 400px;
            width: 90%;
        }
        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .right-section { gap: 0.75rem; }
            .user-info { display: none; }
            .features-grid { grid-template-columns: 1fr; }
            .performance-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-lightbulb"></i>
            </div>
            <div class="logo-text">Study<span>Hub</span></div>
        </div>

        <div class="right-section">
            <div class="notification-dropdown">
                <button class="notification-btn" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="badge" id="unread-count">0</span>
                </button>
                <div class="notification-panel" id="notification-panel">
                    <div class="notification-header">
                        <h4>Notifications</h4>
                        <a href="#" onclick="markAllRead(); return false;">Mark all as read</a>
                    </div>
                    <div class="notification-list" id="notification-list"></div>
                </div>
            </div>

            <div class="user-menu">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div class="user-role">Student</div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <?php if (isset($_SESSION['payment_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> 
                <?php echo $_SESSION['payment_success']; unset($_SESSION['payment_success']); ?>
            </div>
        <?php endif; ?>

        <div class="welcome-section">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h1>
            <p class="welcome-subtitle">Continue your learning journey and track your progress</p>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($enrolled_courses); ?></div>
                <div class="stat-label">Enrolled Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($enrolled_courses, fn($c) => $c['progress'] >= 100)); ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $totalProgress = count($enrolled_courses) ? round(array_sum(array_column($enrolled_courses, 'progress')) / count($enrolled_courses)) : 0; ?>%</div>
                <div class="stat-label">Avg Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $quiz_stats['taken_quizzes'] ?? 0; ?></div>
                <div class="stat-label">Quizzes Taken</div>
            </div>
        </div>

        <!-- Recent Quiz Results (if any) -->
        <?php if (!empty($recent_results)): ?>
            <h2 class="section-title"><i class="fas fa-chart-bar"></i> Recent Quiz Results</h2>
            <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem;">
                <?php foreach ($recent_results as $result): 
                    $scoreClass = $result['percentage'] >= 70 ? 'success' : ($result['percentage'] >= 40 ? 'warning' : 'danger');
                ?>
                    <div style="background: white; padding: 1rem; border-radius: 8px; border-left: 4px solid var(--<?php echo $scoreClass; ?>);">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                            <span><strong><?php echo htmlspecialchars($result['quiz_title']); ?></strong> (<?php echo htmlspecialchars($result['course_title']); ?>)</span>
                            <span style="font-weight: 600; color: var(--<?php echo $scoreClass; ?>);"><?php echo number_format($result['percentage'], 1); ?>%</span>
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo date('M j, Y', strtotime($result['completed_at'])); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- AI Learning Path -->
        <div class="ai-card">
            <div class="ai-header">
                <div class="ai-icon"><i class="fas fa-robot"></i></div>
                <div class="ai-title">Your AI Learning Path</div>
                <a href="<?php echo $refresh_url; ?>" class="refresh-btn"><i class="fas fa-sync-alt"></i> Refresh</a>
            </div>
            <div class="ai-content"><?php echo nl2br(htmlspecialchars($aiRecommendations)); ?></div>
        </div>

        <!-- Feature Cards: Quiz Center & Chat Assistant -->
        <div class="features-grid">
            <!-- Quiz Center (compact) -->
            <div class="feature-card">
                <div class="feature-header">
                    <div class="feature-icon" style="background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%);">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="feature-title">Quiz Center</div>
                </div>
                <div class="quiz-stats">
                    <div class="quiz-progress">
                        <svg viewBox="0 0 80 80">
                            <circle cx="40" cy="40" r="32"></circle>
                            <circle class="progress-bar" cx="40" cy="40" r="32" style="stroke-dashoffset: <?php echo 200.96 * (1 - ($quiz_stats['avg_score']/100)); ?>;"></circle>
                        </svg>
                        <div class="quiz-progress-text"><?php echo round($quiz_stats['avg_score']); ?>%</div>
                    </div>
                    <div class="quiz-numbers">
                        <div class="quiz-number-item">
                            <span>Average</span>
                            <span><?php echo number_format($quiz_stats['avg_score'], 1); ?>%</span>
                        </div>
                        <div class="quiz-number-item">
                            <span>Taken</span>
                            <span><?php echo $quiz_stats['taken_quizzes'] ?? 0; ?></span>
                        </div>
                        <div class="quiz-number-item">
                            <span>Available</span>
                            <span><?php echo $quiz_stats['total_quizzes'] ?? 0; ?></span>
                        </div>
                    </div>
                </div>
                <a href="student_quizzes.php" class="quiz-action-btn">
                    <i class="fas fa-play"></i> Start a Quiz
                </a>
            </div>

            <!-- Chat Assistant -->
            <div class="feature-card chat-card">
                <div class="feature-header">
                    <div class="feature-icon" style="background: linear-gradient(135deg, #5a67d8 0%, #434190 100%);">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="feature-title">Academic Assistant</div>
                </div>
                <input type="hidden" id="chat-context-course" value="<?php echo htmlspecialchars($current_course); ?>">
                <input type="hidden" id="chat-context-lecture" value="<?php echo htmlspecialchars($current_lecture); ?>">
                <div class="chat-messages" id="chat-messages">
                    <?php if (empty($chat_history)): ?>
                        <div class="message assistant-message">
                            <div class="message-avatar"><i class="fas fa-robot"></i></div>
                            <div class="message-content">Hi! Ask me about your courses, quizzes, or anything you're learning.</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($chat_history as $msg): ?>
                            <div class="message <?php echo $msg['role'] === 'user' ? 'user-message' : 'assistant-message'; ?>">
                                <div class="message-avatar"><i class="fas <?php echo $msg['role'] === 'user' ? 'fa-user' : 'fa-robot'; ?>"></i></div>
                                <div class="message-content"><?php echo htmlspecialchars($msg['content']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="typing-indicator" id="typing-indicator">
                        <span></span><span></span><span></span>
                    </div>
                </div>
                <div class="chat-mode-selector">
                    <button class="mode-btn active" data-mode="normal">Normal</button>
                    <button class="mode-btn" data-mode="simple">Simple</button>
                    <button class="mode-btn" data-mode="examples">Examples</button>
                    <button class="mode-btn" data-mode="summary">Summary</button>
                </div>
                <div class="smart-tools">
                    <button onclick="sendSmartRequest('summarize')"><i class="fas fa-file-alt"></i> Summarize</button>
                    <button onclick="sendSmartRequest('practice')"><i class="fas fa-question"></i> Practice Qs</button>
                </div>
                <div class="chat-input-group">
                    <input type="text" id="user-input" class="chat-input" placeholder="Ask me anything..." onkeypress="if(event.key==='Enter') sendMessage()">
                    <button class="chat-send-btn" onclick="sendMessage()"><i class="fas fa-paper-plane"></i> Send</button>
                </div>
            </div>
        </div>

        <!-- Performance Analysis -->
        <h2 class="section-title"><i class="fas fa-chart-line"></i> Performance Analysis</h2>
        <?php if ($overall['total_quizzes_taken'] > 0): ?>
            <div class="performance-grid">
                <div class="performance-card overall-card">
                    <div class="performance-header"><i class="fas fa-tachometer-alt"></i> Overall</div>
                    <div class="performance-value"><?php echo number_format($overall['overall_avg'], 1); ?>%</div>
                    <div class="performance-label">Average Score</div>
                    <div style="font-size:0.8rem;">Based on <?php echo $overall['total_quizzes_taken']; ?> quizzes</div>
                </div>
                <?php foreach ($performance_data as $course): ?>
                    <div class="performance-card">
                        <div class="performance-header"><i class="fas fa-book"></i> <?php echo htmlspecialchars($course['course_title']); ?></div>
                        <div class="performance-value"><?php echo number_format($course['avg_score'], 1); ?>%</div>
                        <div class="performance-label">Average</div>
                        <div class="performance-detail">
                            <span>Quizzes: <?php echo $course['quizzes_taken']; ?></span>
                            <?php if ($course['weak_quizzes'] > 0): ?>
                                <span class="weak-badge">⚠️ <?php echo $course['weak_quizzes']; ?> weak</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($course['avg_score'] < 50): ?>
                            <div class="recommendation"><i class="fas fa-lightbulb"></i> Revise this course.</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <h3>No performance data yet</h3>
                <p>Take some quizzes to see your performance analysis.</p>
            </div>
        <?php endif; ?>

        <!-- My Courses -->
        <h2 class="section-title" id="my-courses"><i class="fas fa-book-open"></i> My Courses</h2>
        <?php if (count($enrolled_courses) > 0): ?>
            <div class="courses-grid">
                <?php foreach ($enrolled_courses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                            <div class="course-instructor">By <?php echo htmlspecialchars($course['instructor_name'] ?? 'StudyHub'); ?></div>
                        </div>
                        <div class="course-body">
                            <p class="course-description"><?php echo htmlspecialchars($course['description'] ?? ''); ?></p>
                            <div class="progress-container">
                                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $course['progress']; ?>%"></div></div>
                                <div class="progress-text">Progress: <?php echo $course['progress']; ?>%</div>
                            </div>
                            <div class="course-meta">
                                <span><i class="fas fa-clock"></i> <?php echo $course['duration'] ?? 'Self-paced'; ?></span>
                                <span><i class="fas fa-signal"></i> <?php echo htmlspecialchars($course['level'] ?? 'All'); ?></span>
                            </div>
                            <div class="course-actions">
                                <a href="course_content.php?id=<?php echo $course['id']; ?>" class="btn btn-primary"><i class="fas fa-play"></i> Continue</a>
                                <a href="student_quizzes.php?course_id=<?php echo $course['id']; ?>" class="btn btn-quiz"><i class="fas fa-question-circle"></i> Quizzes</a>
                                <?php if ($course['progress'] >= 100): ?>
                                    <a href="certificate.php?course_id=<?php echo $course['id']; ?>" class="btn btn-success" target="_blank"><i class="fas fa-certificate"></i> Certificate</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h3>No courses enrolled</h3>
                <p>Browse available courses and start learning!</p>
            </div>
        <?php endif; ?>

        <!-- Available Courses -->
        <?php if (count($available_courses) > 0): ?>
            <h2 class="section-title"><i class="fas fa-compass"></i> Available Courses</h2>
            <div class="courses-grid">
                <?php foreach ($available_courses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                            <div class="course-instructor">By <?php echo htmlspecialchars($course['instructor_name'] ?? 'StudyHub'); ?></div>
                        </div>
                        <div class="course-body">
                            <p class="course-description"><?php echo htmlspecialchars($course['description'] ?? ''); ?></p>
                            <div class="course-price <?php echo ($course['price'] == 0) ? 'price-free' : ''; ?>">
                                <?php echo ($course['price'] == 0) ? 'FREE' : '$' . number_format($course['price'], 2); ?>
                            </div>
                            <div class="course-meta">
                                <span><i class="fas fa-clock"></i> <?php echo $course['duration'] ?? 'Self-paced'; ?></span>
                                <span><i class="fas fa-signal"></i> <?php echo htmlspecialchars($course['level'] ?? 'All'); ?></span>
                            </div>
                            <div class="course-actions">
                                <?php if ($course['price'] == 0): ?>
                                    <form method="POST" action="enroll_course.php" style="flex:1;">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> Enroll Free</button>
                                    </form>
                                <?php else: ?>
                                    <a href="checkout.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary"><i class="fas fa-shopping-cart"></i> Buy - $<?php echo number_format($course['price'], 2); ?></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="payment-modal">
        <div class="modal-content">
            <h3 class="modal-title">Complete Enrollment</h3>
            <p>You are about to enroll in: <strong id="modalCourseTitle"></strong></p>
            <p>Total amount: <strong id="modalCoursePrice"></strong></p>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                <form method="POST" action="process_payment.php" id="paymentForm">
                    <input type="hidden" name="course_id" id="modalCourseId">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-credit-card"></i> Proceed</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const enrollButtons = document.querySelectorAll('.enroll-paid');
        const paymentModal = document.getElementById('paymentModal');
        const modalCourseTitle = document.getElementById('modalCourseTitle');
        const modalCoursePrice = document.getElementById('modalCoursePrice');
        const modalCourseId = document.getElementById('modalCourseId');

        enrollButtons.forEach(button => {
            button.addEventListener('click', function() {
                modalCourseTitle.textContent = this.dataset.courseTitle;
                modalCoursePrice.textContent = '$' + parseFloat(this.dataset.coursePrice).toFixed(2);
                modalCourseId.value = this.dataset.courseId;
                paymentModal.style.display = 'flex';
            });
        });

        function closePaymentModal() { paymentModal.style.display = 'none'; }
        paymentModal.addEventListener('click', e => { if (e.target === paymentModal) closePaymentModal(); });

        // Notifications
        function loadNotifications() {
            fetch('get_notifications.php')
                .then(r => r.json())
                .then(data => {
                    document.getElementById('unread-count').textContent = data.unread_count;
                    const list = document.getElementById('notification-list');
                    list.innerHTML = data.notifications.length ? data.notifications.map(n => 
                        `<div class="notification-item${n.is_read ? '' : ' unread'}" onclick="window.location.href='${n.link}'">
                            <div class="message">${n.message}</div>
                            <div class="time">${n.time}</div>
                        </div>`
                    ).join('') : '<div class="notification-item">No notifications</div>';
                });
        }
        function toggleNotifications() {
            document.getElementById('notification-panel').classList.toggle('show');
            if (document.getElementById('notification-panel').classList.contains('show')) loadNotifications();
        }
        function markAllRead() {
            fetch('mark_notifications_read.php', {method:'POST'}).then(() => {
                document.getElementById('unread-count').textContent = '0';
                loadNotifications();
            });
        }
        document.addEventListener('click', e => {
            if (!e.target.closest('.notification-dropdown')) document.getElementById('notification-panel').classList.remove('show');
        });
        document.addEventListener('DOMContentLoaded', () => {
            fetch('get_notifications.php').then(r=>r.json()).then(data => document.getElementById('unread-count').textContent = data.unread_count);
        });

        // Chat
        const typingIndicator = document.getElementById('typing-indicator');
        document.querySelectorAll('.mode-btn').forEach(btn => btn.addEventListener('click', function() {
            document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
        }));

        function sendMessage() {
            const input = document.getElementById('user-input');
            const msg = input.value.trim();
            if (!msg) return;
            const mode = document.querySelector('.mode-btn.active')?.dataset.mode || 'normal';
            const course = document.getElementById('chat-context-course')?.value || '';
            const lecture = document.getElementById('chat-context-lecture')?.value || '';
            appendMessage('user', msg);
            input.value = '';
            typingIndicator.style.display = 'flex';
            document.getElementById('chat-messages').scrollTop = document.getElementById('chat-messages').scrollHeight;
            fetch('chatbot_handler.php', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'message='+encodeURIComponent(msg)+'&mode='+encodeURIComponent(mode)+'&course='+encodeURIComponent(course)+'&lecture='+encodeURIComponent(lecture)
            })
            .then(r=>r.json())
            .then(data => {
                typingIndicator.style.display = 'none';
                appendMessage('assistant', data.reply);
            })
            .catch(() => {
                typingIndicator.style.display = 'none';
                appendMessage('assistant', 'Sorry, something went wrong.');
            });
        }

        function appendMessage(role, text) {
            const div = document.getElementById('chat-messages');
            const msgDiv = document.createElement('div');
            msgDiv.className = 'message ' + (role === 'user' ? 'user-message' : 'assistant-message');
            msgDiv.innerHTML = `<div class="message-avatar"><i class="fas ${role==='user'?'fa-user':'fa-robot'}"></i></div><div class="message-content">${escapeHtml(text)}</div>`;
            div.insertBefore(msgDiv, typingIndicator);
            div.scrollTop = div.scrollHeight;
        }

        function escapeHtml(unsafe) {
            return unsafe.replace(/[&<>"]/g, function(m) {
                if(m==='&') return '&amp;';
                if(m==='<') return '&lt;';
                if(m==='>') return '&gt;';
                if(m==='"') return '&quot;';
                return m;
            });
        }

        function sendSmartRequest(type) {
            const lecture = document.getElementById('chat-context-lecture')?.value || '';
            let msg = type==='summarize' ? (lecture ? 'Please summarize: ' + lecture : 'Please summarize the topic I am studying.') :
                     (lecture ? 'Generate 3 practice questions with answers for: ' + lecture : 'Generate 3 practice questions for my current topic.');
            document.getElementById('user-input').value = msg;
            sendMessage();
        }

        document.getElementById('user-input').addEventListener('keypress', e => { if(e.key==='Enter') sendMessage(); });
    </script>
</body>
</html>