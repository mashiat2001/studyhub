<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'instructor') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

// Get instructor's courses with analytics
$courses = [];
$stmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM user_courses WHERE course_id = c.id) as student_count,
           (SELECT COUNT(*) FROM quizzes WHERE course_id = c.id) as quiz_count,
           (SELECT COALESCE(AVG(r.percentage), 0) 
            FROM quiz_results r 
            JOIN quizzes q ON r.quiz_id = q.id 
            WHERE q.course_id = c.id) as avg_score,
           (SELECT COUNT(*) FROM user_courses WHERE course_id = c.id AND progress >= 100) as completed_count
    FROM courses c
    WHERE c.instructor_id = ?
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}

// Overall earnings (from paid orders)
$earnings_stmt = $conn->prepare("
    SELECT COALESCE(SUM(o.amount), 0) as total_earnings,
           COUNT(DISTINCT o.user_id) as total_students
    FROM orders o
    JOIN courses c ON o.course_id = c.id
    WHERE c.instructor_id = ? AND o.status = 'paid'
");
$earnings_stmt->bind_param("i", $user['id']);
$earnings_stmt->execute();
$earnings = $earnings_stmt->get_result()->fetch_assoc();

// Monthly enrollment data (for chart)
$monthly_data = [];
$monthly_stmt = $conn->prepare("
    SELECT DATE_FORMAT(uc.enrolled_date, '%Y-%m') as month, COUNT(*) as enrollments
    FROM user_courses uc
    JOIN courses c ON uc.course_id = c.id
    WHERE c.instructor_id = ?
    GROUP BY month
    ORDER BY month DESC
    LIMIT 12
");
$monthly_stmt->bind_param("i", $user['id']);
$monthly_stmt->execute();
$monthly_result = $monthly_stmt->get_result();
while ($row = $monthly_result->fetch_assoc()) {
    $monthly_data[] = $row;
}

// Weak topics: quiz questions where students often fail (optional)
$weak_topics = [];
$weak_stmt = $conn->prepare("
    SELECT qq.question_text, qq.correct_answer, q.title as quiz_title, c.title as course_title,
           COUNT(r.id) as total_attempts,
           SUM(CASE WHEN r.score = 0 THEN 1 ELSE 0 END) as failed_attempts
    FROM quiz_questions qq
    JOIN quizzes q ON qq.quiz_id = q.id
    JOIN courses c ON q.course_id = c.id
    LEFT JOIN quiz_results r ON r.quiz_id = q.id
    WHERE c.instructor_id = ?
    GROUP BY qq.id
    HAVING failed_attempts > 0
    ORDER BY failed_attempts DESC
    LIMIT 10
");
$weak_stmt->bind_param("i", $user['id']);
$weak_stmt->execute();
$weak_result = $weak_stmt->get_result();
while ($row = $weak_result->fetch_assoc()) {
    $weak_topics[] = $row;
}

$conn->close();

// Prepare data for charts
$months = [];
$enrollments = [];
foreach (array_reverse($monthly_data) as $data) {
    $months[] = $data['month'];
    $enrollments[] = $data['enrollments'];
}

$course_names = [];
$course_scores = [];
$course_students = [];
foreach ($courses as $c) {
    $course_names[] = $c['title'];
    $course_scores[] = round($c['avg_score'], 1);
    $course_students[] = $c['student_count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Analytics - StudyHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Reuse the same CSS from instructor dashboard */
        :root {
            --primary: #7E6CCA;
            --primary-light: #9F90DB;
            --primary-dark: #6351A6;
            --secondary: #FF9E6D;
            --success: #48BB78;
            --warning: #ED8936;
            --danger: #F56565;
            --info: #4299E1;
            --text-dark: #2D3748;
            --text-light: #718096;
            --light-bg: #F7FAFC;
            --border-radius: 12px;
            --transition: all 0.3s ease;
            --shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
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
            min-height: 100vh;
        }
        
        .header {
            background: white;
            padding: 15px 30px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .logo-text {
            font-size: 24px;
            font-weight: 700;
        }
        
        .logo-text span {
            color: var(--primary);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .user-role {
            font-size: 14px;
            color: var(--text-light);
        }
        
        .logout-btn {
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logout-btn:hover {
            background: var(--primary-dark);
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .back-link {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }
        
        .back-link:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 14px;
        }
        
        .chart-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-dark);
        }
        
        .chart-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .course-table {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 20px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 12px;
            background: #f7fafc;
            color: var(--text-dark);
            font-weight: 600;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .weak-topic-item {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .weak-topic-item:last-child {
            border-bottom: none;
        }
        
        .weak-topic-question {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .weak-topic-meta {
            font-size: 13px;
            color: var(--text-light);
            display: flex;
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .chart-row {
                grid-template-columns: 1fr;
            }
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
        
        <div class="user-menu">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                <div class="user-role">Instructor</div>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <div class="dashboard-container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i> Analytics Dashboard
            </h1>
            <a href="instructor_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <!-- Key Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($courses); ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $earnings['total_students']; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?php echo number_format($earnings['total_earnings'], 2); ?></div>
                <div class="stat-label">Total Earnings</div>
            </div>
            <div class="stat-card">
                <?php
                $total_completed = 0;
                $total_enrolled = 0;
                foreach ($courses as $c) {
                    $total_completed += $c['completed_count'];
                    $total_enrolled += $c['student_count'];
                }
                $completion_rate = $total_enrolled > 0 ? round(($total_completed / $total_enrolled) * 100, 1) : 0;
                ?>
                <div class="stat-value"><?php echo $completion_rate; ?>%</div>
                <div class="stat-label">Avg Completion Rate</div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="chart-row">
            <!-- Monthly Enrollments -->
            <div class="chart-container">
                <div class="chart-title">
                    <i class="fas fa-calendar-alt"></i> Monthly Enrollments
                </div>
                <canvas id="enrollmentChart" style="width:100%; max-height:300px;"></canvas>
            </div>

            <!-- Course Performance (Avg Score vs Students) -->
            <div class="chart-container">
                <div class="chart-title">
                    <i class="fas fa-tachometer-alt"></i> Course Performance
                </div>
                <canvas id="courseChart" style="width:100%; max-height:300px;"></canvas>
            </div>
        </div>

        <!-- Course Details Table -->
        <div class="course-table">
            <div class="chart-title">
                <i class="fas fa-table"></i> Course Performance Breakdown
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Status</th>
                        <th>Students</th>
                        <th>Quizzes</th>
                        <th>Avg Score</th>
                        <th>Completed</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): 
                        $revenue = $course['price'] * $course['student_count'];
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($course['title']); ?></strong></td>
                        <td>
                            <span class="badge badge-<?php echo $course['status']; ?>">
                                <?php echo ucfirst($course['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $course['student_count']; ?></td>
                        <td><?php echo $course['quiz_count']; ?></td>
                        <td><?php echo number_format($course['avg_score'], 1); ?>%</td>
                        <td><?php echo $course['completed_count']; ?></td>
                        <td>$<?php echo number_format($revenue, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Weak Topics / At-Risk Students -->
        <div class="chart-container" style="margin-top: 30px;">
            <div class="chart-title">
                <i class="fas fa-exclamation-triangle" style="color: #ed8936;"></i> Topics Students Struggle With
            </div>
            <?php if (count($weak_topics) > 0): ?>
                <?php foreach ($weak_topics as $topic): 
                    $fail_rate = round(($topic['failed_attempts'] / max($topic['total_attempts'], 1)) * 100, 1);
                ?>
                <div class="weak-topic-item">
                    <div class="weak-topic-question">
                        <?php echo htmlspecialchars($topic['question_text']); ?>
                    </div>
                    <div class="weak-topic-meta">
                        <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($topic['course_title']); ?></span>
                        <span><i class="fas fa-question-circle"></i> <?php echo htmlspecialchars($topic['quiz_title']); ?></span>
                        <span><i class="fas fa-check-circle" style="color:#48BB78;"></i> Correct: <?php echo $topic['correct_answer']; ?></span>
                        <span><i class="fas fa-exclamation-circle" style="color:#F56565;"></i> Fail rate: <?php echo $fail_rate; ?>% (<?php echo $topic['failed_attempts']; ?>/<?php echo $topic['total_attempts']; ?>)</span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="color: var(--text-light); text-align: center; padding: 20px;">No weak topics identified yet. Students are doing well!</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Enrollment Chart
        const ctx1 = document.getElementById('enrollmentChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [{
                    label: 'Enrollments',
                    data: <?php echo json_encode($enrollments); ?>,
                    borderColor: '#7E6CCA',
                    backgroundColor: 'rgba(126, 108, 202, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // Course Performance Chart (bar: avg score vs students)
        const ctx2 = document.getElementById('courseChart').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($course_names); ?>,
                datasets: [
                    {
                        label: 'Average Score (%)',
                        data: <?php echo json_encode($course_scores); ?>,
                        backgroundColor: '#8B5CF6',
                        yAxisID: 'y'
                    },
                    {
                        label: 'Students',
                        data: <?php echo json_encode($course_students); ?>,
                        backgroundColor: '#48BB78',
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Score (%)' }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Students' }
                    }
                }
            }
        });
    </script>
</body>
</html>