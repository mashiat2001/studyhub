<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

// Handle course approval/rejection
if (isset($_GET['action']) && isset($_GET['id'])) {
    $course_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE courses SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $course_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Course approved successfully!";
        }
        $stmt->close();
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE courses SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $course_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Course rejected successfully!";
        }
        $stmt->close();
    }
    
    header("Location: admin_approve_courses.php");
    exit();
}

// Get pending courses
$pending_courses = [];
$stmt = $conn->prepare("SELECT * FROM courses WHERE status = 'pending' ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $pending_courses[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Courses - StudyHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #7E6CCA;
            --primary-light: #9F90DB;
            --primary-dark: #6351A6;
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
            margin-bottom: 30px;
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
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        
        .course-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            overflow: hidden;
            transition: var(--transition);
        }
        
        .course-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .course-header {
            background: #fff3cd;
            padding: 15px 20px;
            border-bottom: 1px solid #ffeaa7;
        }
        
        .course-body {
            padding: 20px;
        }
        
        .course-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-dark);
        }
        
        .course-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            font-size: 14px;
            color: var(--text-light);
            flex-wrap: wrap;
        }
        
        .course-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .course-description {
            color: var(--text-light);
            margin-bottom: 20px;
            line-height: 1.6;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .course-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-approve {
            background: #d1edff;
            color: #0c5460;
            border: 1px solid #b3e0ff;
        }
        
        .btn-approve:hover {
            background: #b3e0ff;
            transform: translateY(-1px);
        }
        
        .btn-reject {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn-reject:hover {
            background: #f5c6cb;
            transform: translateY(-1px);
        }
        
        .btn-view {
            background: var(--light-bg);
            color: var(--text-dark);
            border: 1px solid #e2e8f0;
        }
        
        .btn-view:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }
        
        .btn-back {
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .btn-back:hover {
            background: var(--primary-dark);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: var(--text-light);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #cbd5e0;
        }
        
        .stats {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            min-width: 150px;
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
        <a href="admin_dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </header>

    <div class="container">
        <h1 style="margin-bottom: 10px; color: var(--text-dark);">Course Approval Panel</h1>
        <p style="color: var(--text-light); margin-bottom: 30px;">Review and approve pending course submissions</p>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> 
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($pending_courses); ?></div>
                <div class="stat-label">Pending Courses</div>
            </div>
        </div>
        
        <?php if (count($pending_courses) > 0): ?>
            <?php foreach ($pending_courses as $course): ?>
                <div class="course-card">
                    <div class="course-header">
                        <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                    </div>
                    <div class="course-body">
                        <div class="course-meta">
                            <span><i class="fas fa-user"></i> <strong>Instructor:</strong> <?php echo htmlspecialchars($course['instructor']); ?></span>
                            <span><i class="fas fa-tag"></i> <strong>Category:</strong> <?php echo htmlspecialchars($course['category']); ?></span>
                            <span><i class="fas fa-signal"></i> <strong>Level:</strong> <?php echo htmlspecialchars($course['level']); ?></span>
                            <span><i class="fas fa-calendar"></i> <strong>Submitted:</strong> <?php echo date('M d, Y g:i A', strtotime($course['created_at'])); ?></span>
                        </div>
                        
                        <div class="course-description">
                            <strong>Description:</strong><br>
                            <?php echo nl2br(htmlspecialchars($course['description'])); ?>
                        </div>
                        
                        <div class="course-actions">
                            <a href="course_details.php?id=<?php echo $course['id']; ?>" class="btn btn-view">
                                <i class="fas fa-eye"></i> View Content
                            </a>
                            <a href="admin_approve_courses.php?action=approve&id=<?php echo $course['id']; ?>" class="btn btn-approve" onclick="return confirm('Are you sure you want to approve this course?')">
                                <i class="fas fa-check"></i> Approve Course
                            </a>
                            <a href="admin_approve_courses.php?action=reject&id=<?php echo $course['id']; ?>" class="btn btn-reject" onclick="return confirm('Are you sure you want to reject this course?')">
                                <i class="fas fa-times"></i> Reject Course
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No pending courses for approval</h3>
                <p>All courses are currently approved and active. Check back later for new submissions.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>