<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

if (!isset($_GET['quiz_id'])) {
    header('Location: student_dashboard.php');
    exit();
}

$quiz_id = intval($_GET['quiz_id']);

// Get quiz result
$result_stmt = $conn->prepare("SELECT r.*, q.title as quiz_title, c.title as course_title 
                              FROM quiz_results r 
                              JOIN quizzes q ON r.quiz_id = q.id 
                              JOIN courses c ON q.course_id = c.id 
                              WHERE r.user_id = ? AND r.quiz_id = ?");
$result_stmt->bind_param("ii", $user['id'], $quiz_id);
$result_stmt->execute();
$result = $result_stmt->get_result()->fetch_assoc();

if (!$result) {
    header('Location: student_dashboard.php');
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - StudyHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #7E6CCA;
            --primary-dark: #6351A6;
            --text-dark: #2D3748;
            --text-light: #718096;
            --light-bg: #F7FAFC;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--light-bg);
            color: var(--text-dark);
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        h1 {
            color: var(--primary);
            margin-bottom: 30px;
        }
        
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: <?php echo ($result['percentage'] >= 70) ? '#d1fae5' : 
                       (($result['percentage'] >= 40) ? '#fef3c7' : '#fee2e2'); ?>;
            border: 5px solid <?php echo ($result['percentage'] >= 70) ? '#10b981' : 
                            (($result['percentage'] >= 40) ? '#f59e0b' : '#dc2626'); ?>;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin: 0 auto 30px;
        }
        
        .score-percentage {
            font-size: 36px;
            font-weight: 700;
            color: <?php echo ($result['percentage'] >= 70) ? '#065f46' : 
                   (($result['percentage'] >= 40) ? '#92400e' : '#991b1b'); ?>;
        }
        
        .score-text {
            font-size: 20px;
            font-weight: 500;
            color: <?php echo ($result['percentage'] >= 70) ? '#065f46' : 
                   (($result['percentage'] >= 40) ? '#92400e' : '#991b1b'); ?>;
        }
        
        .result-details {
            margin: 30px 0;
            text-align: left;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .btn {
            background: var(--primary);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
        }
        
        .btn:hover {
            background: var(--primary-dark);
        }
        
        .back-link {
            color: var(--primary);
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="student_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <h1>Quiz Results</h1>
        
        <div class="score-circle">
            <div class="score-percentage">
                <?php echo number_format($result['percentage'], 1); ?>%
            </div>
            <div class="score-text">
                <?php echo ($result['percentage'] >= 70) ? 'Excellent!' : 
                       (($result['percentage'] >= 40) ? 'Good Try!' : 'Needs Improvement'); ?>
            </div>
        </div>
        
        <div class="result-details">
            <div class="detail-item">
                <span>Quiz:</span>
                <strong><?php echo htmlspecialchars($result['quiz_title']); ?></strong>
            </div>
            <div class="detail-item">
                <span>Course:</span>
                <strong><?php echo htmlspecialchars($result['course_title']); ?></strong>
            </div>
            <div class="detail-item">
                <span>Score:</span>
                <strong><?php echo $result['score']; ?> / <?php echo $result['total_questions']; ?></strong>
            </div>
            <div class="detail-item">
                <span>Percentage:</span>
                <strong><?php echo number_format($result['percentage'], 1); ?>%</strong>
            </div>
            <div class="detail-item">
                <span>Completed On:</span>
                <strong><?php echo date('F j, Y g:i A', strtotime($result['completed_at'])); ?></strong>
            </div>
        </div>
        
        <a href="student_dashboard.php" class="btn">
            <i class="fas fa-home"></i> Back to Dashboard
        </a>
        
        <a href="courses.php" class="btn">
            <i class="fas fa-book"></i> Browse More Courses
        </a>
    </div>
</body>
</html>