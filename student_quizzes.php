<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

// Get enrolled courses with quizzes
$enrolled_courses = [];
$stmt = $conn->prepare("
    SELECT c.id, c.title, c.description, 
           COUNT(q.id) as quiz_count
    FROM user_courses uc
    JOIN courses c ON uc.course_id = c.id
    LEFT JOIN quizzes q ON c.id = q.course_id
    WHERE uc.user_id = ? AND c.status = 'approved'
    GROUP BY c.id
    ORDER BY c.title
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$enrolled_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get available quizzes
$available_quizzes = [];
$quiz_stmt = $conn->prepare("
    SELECT q.*, c.title as course_title, 
           IF(r.id IS NULL, 0, 1) as taken
    FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    JOIN user_courses uc ON c.id = uc.course_id
    LEFT JOIN quiz_results r ON q.id = r.quiz_id AND r.user_id = ?
    WHERE uc.user_id = ? AND c.status = 'approved'
    ORDER BY q.created_at DESC
");
$quiz_stmt->bind_param("ii", $user['id'], $user['id']);
$quiz_stmt->execute();
$available_quizzes = $quiz_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Quizzes - StudyHub</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        h1 {
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .section-title {
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .courses-grid, .quizzes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .course-card, .quiz-card {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        .course-card:hover, .quiz-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-dark);
        }
        
        .card-meta {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
        }
        
        .btn:hover {
            background: var(--primary-dark);
        }
        
        .btn-disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-taken {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-available {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .back-link {
            color: var(--primary);
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #cbd5e0;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="student_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <div class="page-header">
            <h1><i class="fas fa-question-circle"></i> My Quizzes</h1>
            <p>Take quizzes for your enrolled courses and track your progress</p>
        </div>
        
        <!-- Available Quizzes Section -->
        <div class="section">
            <h2 class="section-title">Available Quizzes</h2>
            
            <?php if (!empty($available_quizzes)): ?>
                <div class="quizzes-grid">
                    <?php foreach ($available_quizzes as $quiz): ?>
                        <div class="quiz-card">
                            <div class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></div>
                            <div class="card-meta">
                                <strong>Course:</strong> <?php echo htmlspecialchars($quiz['course_title']); ?><br>
                                <strong>Questions:</strong> <?php echo $quiz['total_questions']; ?><br>
                                <strong>Time Limit:</strong> <?php echo $quiz['time_limit']; ?> minutes<br>
                                <strong>Total Marks:</strong> <?php echo $quiz['total_marks']; ?>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <span class="status-badge <?php echo $quiz['taken'] ? 'status-taken' : 'status-available'; ?>">
                                    <?php echo $quiz['taken'] ? 'Taken' : 'Available'; ?>
                                </span>
                            </div>
                            
                            <?php if ($quiz['taken']): ?>
                                <a href="quiz_results.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn">
                                    <i class="fas fa-chart-bar"></i> View Results
                                </a>
                            <?php else: ?>
                                <!-- THIS IS THE CORRECT LINK WITH quiz_id -->
                                <a href="take_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn">
                                    <i class="fas fa-play"></i> Take Quiz
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-question-circle"></i>
                    <h3>No Quizzes Available</h3>
                    <p>There are no quizzes available for your enrolled courses yet.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Enrolled Courses Section -->
        <div class="section">
            <h2 class="section-title">My Enrolled Courses</h2>
            
            <?php if (!empty($enrolled_courses)): ?>
                <div class="courses-grid">
                    <?php foreach ($enrolled_courses as $course): ?>
                        <div class="course-card">
                            <div class="card-title"><?php echo htmlspecialchars($course['title']); ?></div>
                            <p style="color: var(--text-light); margin-bottom: 15px; font-size: 14px;">
                                <?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?>
                            </p>
                            <div class="card-meta">
                                <strong>Quizzes Available:</strong> <?php echo $course['quiz_count']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>No Courses Enrolled</h3>
                    <p>You haven't enrolled in any courses yet.</p>
                    <a href="courses.php" class="btn" style="margin-top: 15px;">
                        <i class="fas fa-search"></i> Browse Courses
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>