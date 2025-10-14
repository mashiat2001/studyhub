<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

// Get enrolled courses
$enrolled_courses = [];
$enrolled_stmt = $conn->prepare("
    SELECT c.*, uc.progress, uc.status 
    FROM user_courses uc 
    JOIN courses c ON uc.course_id = c.id 
    WHERE uc.user_id = ? AND c.status = 'approved'
");
$enrolled_stmt->bind_param("i", $user['id']);
$enrolled_stmt->execute();
$enrolled_result = $enrolled_stmt->get_result();
while ($row = $enrolled_result->fetch_assoc()) {
    $enrolled_courses[] = $row;
}

// Get available courses (approved, not enrolled)
$available_courses = [];
$available_stmt = $conn->prepare("
    SELECT * FROM courses 
    WHERE status = 'approved' 
    AND id NOT IN (SELECT course_id FROM user_courses WHERE user_id = ?)
");
$available_stmt->bind_param("i", $user['id']);
$available_stmt->execute();
$available_result = $available_stmt->get_result();
while ($row = $available_result->fetch_assoc()) {
    $available_courses[] = $row;
}

$conn->close();
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
            --primary: #7E6CCA;
            --primary-light: #9F90DB;
            --primary-dark: #6351A6;
            --secondary: #FF9E6D;
            --accent: #6DC9FF;
            --text-dark: #2D3748;
            --text-light: #718096;
            --light-bg: #F7FAFC;
            --card-bg: rgba(255, 255, 255, 0.95);
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
        }
        
        .logout-btn:hover {
            background: var(--primary-dark);
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 30px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .welcome-title {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .welcome-subtitle {
            opacity: 0.9;
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
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 30px 0 20px 0;
            color: var(--text-dark);
        }
        
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .course-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
        }
        
        .course-header {
            background: var(--primary-light);
            color: white;
            padding: 15px;
        }
        
        .course-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .course-instructor {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .course-body {
            padding: 15px;
        }
        
        .course-description {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .progress-container {
            margin-bottom: 15px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 4px;
        }
        
        .progress-text {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 5px;
        }
        
        .course-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-light);
            margin-bottom: 15px;
        }
        
        .course-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: var(--transition);
            flex: 1;
            text-align: center;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            background: var(--light-bg);
            color: var(--text-dark);
            border: 1px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
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
    <header class="header">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="logo-text">Study<span>Hub</span></div>
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
    </header>

    <div class="dashboard-container">
        <div class="welcome-section">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($user['name']); ?>! 👋</h1>
            <p class="welcome-subtitle">Continue your learning journey and track your progress</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($enrolled_courses); ?></div>
                <div class="stat-label">Enrolled Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $completed = array_filter($enrolled_courses, function($course) {
                        return $course['status'] === 'completed';
                    });
                    echo count($completed);
                    ?>
                </div>
                <div class="stat-label">Completed Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php
                    $totalProgress = 0;
                    foreach ($enrolled_courses as $course) {
                        $totalProgress += $course['progress'];
                    }
                    echo count($enrolled_courses) > 0 ? round($totalProgress / count($enrolled_courses)) : 0;
                    ?>%
                </div>
                <div class="stat-label">Average Progress</div>
            </div>
        </div>

        <h2 class="section-title">My Courses</h2>
        <?php if (count($enrolled_courses) > 0): ?>
            <div class="courses-grid">
                <?php foreach ($enrolled_courses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                            <div class="course-instructor">By <?php echo htmlspecialchars($course['instructor']); ?></div>
                        </div>
                        <div class="course-body">
                            <p class="course-description"><?php echo htmlspecialchars($course['description'] ?? 'No description available'); ?></p>
                            
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $course['progress']; ?>%"></div>
                                </div>
                                <div class="progress-text">Progress: <?php echo $course['progress']; ?>%</div>
                            </div>
                            
                            <div class="course-meta">
                                <span><i class="fas fa-clock"></i> <?php echo $course['duration'] ?? 'N/A'; ?> hours</span>
                                <span><i class="fas fa-signal"></i> <?php echo htmlspecialchars($course['level'] ?? 'All Levels'); ?></span>
                            </div>
                            
                            <div class="course-actions">
                                <a href="course_content.php?id=<?php echo $course['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-play"></i> View Course
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h3>No courses enrolled yet</h3>
                <p>Browse available courses and start your learning journey!</p>
            </div>
        <?php endif; ?>

        <?php if (count($available_courses) > 0): ?>
            <h2 class="section-title">Available Courses</h2>
            <div class="courses-grid">
                <?php foreach ($available_courses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                            <div class="course-instructor">By <?php echo htmlspecialchars($course['instructor']); ?></div>
                        </div>
                        <div class="course-body">
                            <p class="course-description"><?php echo htmlspecialchars($course['description'] ?? 'No description available'); ?></p>
                            
                            <div class="course-meta">
                                <span><i class="fas fa-clock"></i> <?php echo $course['duration'] ?? 'N/A'; ?> hours</span>
                                <span><i class="fas fa-signal"></i> <?php echo htmlspecialchars($course['level'] ?? 'All Levels'); ?></span>
                                <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($course['category'] ?? 'General'); ?></span>
                            </div>
                            
                            <div class="course-actions">
                                <form method="POST" action="enroll_course.php" style="flex: 1;">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                                        <i class="fas fa-plus"></i> Enroll Now
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>