<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'instructor') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

// Get instructor's courses
$courses = [];
$stmt = $conn->prepare("SELECT * FROM courses WHERE instructor_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}

// Get course statistics
$course_stats = [];
foreach ($courses as $course) {
    $enroll_stmt = $conn->prepare("SELECT COUNT(*) as enrolled FROM user_courses WHERE course_id = ?");
    $enroll_stmt->bind_param("i", $course['id']);
    $enroll_stmt->execute();
    $enroll_result = $enroll_stmt->get_result();
    $enrolled = $enroll_result->fetch_assoc()['enrolled'];
    
    $course_stats[$course['id']] = [
        'enrolled' => $enrolled,
        'status' => $course['status']
    ];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard - StudyHub</title>
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
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin: 20px 0;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: white;
            color: var(--primary);
        }
        
        .btn-primary:hover {
            background: #f7f7f7;
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
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .course-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .course-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d1edff;
            color: #0c5460;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .course-body {
            padding: 15px;
        }
        
        .course-description {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-amp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .course-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 14px;
            color: var(--text-light);
        }
        
        .course-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-view {
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
            flex: 1;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--primary);
            color: white;
        }
        
        .btn-view:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
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
        <div class="welcome-section">
            <h1 class="welcome-title">Welcome, Professor <?php echo htmlspecialchars($user['name']); ?>! 🎓</h1>
            <p class="welcome-subtitle">Manage your courses and track student engagement</p>
            
            <div class="action-buttons">
                <a href="add_course.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Course
                </a>
                <a href="manage_content.php" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Manage Content
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($courses); ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $approved = array_filter($courses, function($course) {
                        return $course['status'] === 'approved';
                    });
                    echo count($approved);
                    ?>
                </div>
                <div class="stat-label">Approved Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php
                    $totalStudents = 0;
                    foreach ($course_stats as $stats) {
                        $totalStudents += $stats['enrolled'];
                    }
                    echo $totalStudents;
                    ?>
                </div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">
                    <?php 
                    $pending = array_filter($courses, function($course) {
                        return $course['status'] === 'pending';
                    });
                    echo count($pending);
                    ?>
                </div>
                <div class="stat-label">Pending Approval</div>
            </div>
        </div>

        <h2 class="section-title">My Courses</h2>
        <?php if (count($courses) > 0): ?>
            <div class="courses-grid">
                <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                            <span class="course-status status-<?php echo $course['status']; ?>">
                                <?php echo ucfirst($course['status']); ?>
                            </span>
                        </div>
                        <div class="course-body">
                            <p class="course-description"><?php echo htmlspecialchars($course['description'] ?? 'No description available'); ?></p>
                            
                            <div class="course-stats">
                                <span><i class="fas fa-users"></i> <?php echo $course_stats[$course['id']]['enrolled']; ?> students</span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($course['created_at'])); ?></span>
                            </div>
                            
                            <div class="course-actions">
                                <a href="course_content.php?id=<?php echo $course['id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View Course Content
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h3>No courses created yet</h3>
                <p>Start by creating your first course to share your knowledge!</p>
                <a href="add_course.php" class="btn btn-primary" style="margin-top: 15px;">
                    <i class="fas fa-plus"></i> Create Your First Course
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 
