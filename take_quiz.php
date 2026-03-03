<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

// Debug: Check if quiz_id is received
error_log("GET parameters: " . print_r($_GET, true));

if (!isset($_GET['quiz_id'])) {
    die("No quiz ID provided. Please go back and select a quiz.");
}

$quiz_id = intval($_GET['quiz_id']);

// Debug: Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Get quiz details
$quiz_stmt = $conn->prepare("SELECT q.*, c.title as course_title 
                            FROM quizzes q 
                            JOIN courses c ON q.course_id = c.id 
                            WHERE q.id = ?");
$quiz_stmt->bind_param("i", $quiz_id);
$quiz_stmt->execute();
$quiz = $quiz_stmt->get_result()->fetch_assoc();

// Debug: Check if quiz exists
error_log("Quiz found: " . ($quiz ? 'Yes' : 'No'));
if (!$quiz) {
    die("Quiz not found. Please check the quiz ID.");
}

// Check if student is enrolled in the course
$enrollment_stmt = $conn->prepare("SELECT * FROM user_courses 
                                  WHERE user_id = ? AND course_id = ?");
$enrollment_stmt->bind_param("ii", $user['id'], $quiz['course_id']);
$enrollment_stmt->execute();
$enrollment = $enrollment_stmt->get_result()->fetch_assoc();

if (!$enrollment) {
    die("You must be enrolled in this course to take the quiz.");
}

// Check if student already took this quiz
$result_stmt = $conn->prepare("SELECT * FROM quiz_results 
                              WHERE user_id = ? AND quiz_id = ?");
$result_stmt->bind_param("ii", $user['id'], $quiz_id);
$result_stmt->execute();
$existing_result = $result_stmt->get_result()->fetch_assoc();

if ($existing_result) {
    die("You have already taken this quiz. Score: " . $existing_result['score'] . "/" . $existing_result['total_questions']);
}

// Get quiz questions
$questions_stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id");
$questions_stmt->bind_param("i", $quiz_id);
$questions_stmt->execute();
$questions = $questions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Debug: Check questions count
error_log("Questions found: " . count($questions));

if (empty($questions)) {
    die("No questions available for this quiz. Please contact the instructor.");
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Quiz - StudyHub</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f7fafc;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .quiz-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        h1 {
            color: #7E6CCA;
            margin-bottom: 10px;
        }
        
        .quiz-info {
            color: #718096;
            margin: 15px 0;
        }
        
        .timer {
            background: #fef3c7;
            color: #92400e;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 18px;
            display: inline-block;
            margin: 10px 0;
        }
        
        .question-item {
            margin-bottom: 30px;
            padding: 20px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }
        
        .question-text {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 15px;
        }
        
        .options-list {
            margin-left: 20px;
        }
        
        .option-label {
            display: block;
            margin-bottom: 10px;
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .option-label:hover {
            background: #f3f4f6;
        }
        
        .option-input {
            margin-right: 10px;
        }
        
        .btn {
            background: #7E6CCA;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            display: block;
            margin: 30px auto 0;
        }
        
        .btn:hover {
            background: #6351A6;
        }
        
        .back-link {
            color: #7E6CCA;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .debug-info {
            background: #f3f4f6;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 12px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="student_quizzes.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Quizzes
        </a>
        
        <!-- Debug Information -->
        <div class="debug-info">
            <strong>Debug Info:</strong><br>
            Quiz ID: <?php echo $quiz_id; ?><br>
            Questions Found: <?php echo count($questions); ?><br>
            Course: <?php echo htmlspecialchars($quiz['course_title']); ?>
        </div>
        
        <div class="quiz-header">
            <h1><?php echo htmlspecialchars($quiz['title']); ?></h1>
            <div class="quiz-info">
                <strong>Course:</strong> <?php echo htmlspecialchars($quiz['course_title']); ?> |
                <strong>Questions:</strong> <?php echo count($questions); ?> |
                <strong>Time Limit:</strong> <?php echo $quiz['time_limit']; ?> minutes
            </div>
            <div class="timer" id="timer">
                Time Remaining: <span id="time"><?php echo $quiz['time_limit']; ?>:00</span>
            </div>
            <p><strong>Instructions:</strong> Select one answer for each question. Time will start when you begin.</p>
        </div>
        
        <form id="quizForm" method="POST" action="submit_quiz.php">
            <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
            
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-item">
                    <div class="question-text">
                        <?php echo ($index + 1) . '. ' . htmlspecialchars($question['question_text']); ?>
                        <small style="color: #6b7280; float: right;">(<?php echo $question['marks']; ?> marks)</small>
                    </div>
                    
                    <div class="options-list">
                        <label class="option-label">
                            <input type="radio" class="option-input" 
                                   name="answer[<?php echo $question['id']; ?>]" value="a" required>
                            A. <?php echo htmlspecialchars($question['option_a']); ?>
                        </label>
                        
                        <label class="option-label">
                            <input type="radio" class="option-input" 
                                   name="answer[<?php echo $question['id']; ?>]" value="b">
                            B. <?php echo htmlspecialchars($question['option_b']); ?>
                        </label>
                        
                        <?php if (!empty($question['option_c'])): ?>
                            <label class="option-label">
                                <input type="radio" class="option-input" 
                                       name="answer[<?php echo $question['id']; ?>]" value="c">
                                C. <?php echo htmlspecialchars($question['option_c']); ?>
                            </label>
                        <?php endif; ?>
                        
                        <?php if (!empty($question['option_d'])): ?>
                            <label class="option-label">
                                <input type="radio" class="option-input" 
                                       name="answer[<?php echo $question['id']; ?>]" value="d">
                                D. <?php echo htmlspecialchars($question['option_d']); ?>
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" class="btn">
                <i class="fas fa-paper-plane"></i> Submit Quiz
            </button>
        </form>
    </div>

    <script>
        // Timer functionality
        let timeLimit = <?php echo $quiz['time_limit'] * 60; ?>; // Convert to seconds
        let timer = document.getElementById('time');
        
        function updateTimer() {
            let minutes = Math.floor(timeLimit / 60);
            let seconds = timeLimit % 60;
            
            timer.textContent = minutes.toString().padStart(2, '0') + ':' + 
                               seconds.toString().padStart(2, '0');
            
            if (timeLimit <= 0) {
                clearInterval(timerInterval);
                alert('Time is up! Submitting quiz...');
                document.getElementById('quizForm').submit();
            }
            
            timeLimit--;
        }
        
        let timerInterval = setInterval(updateTimer, 1000);
        updateTimer(); // Initial call
    </script>
</body>
</html>