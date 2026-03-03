<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'instructor') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

// Get instructor's courses with analytics (including instructor name from users)
$courses = [];
$stmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM user_courses WHERE course_id = c.id) as student_count,
           (SELECT COUNT(*) FROM quizzes WHERE course_id = c.id) as quiz_count,
           (SELECT COALESCE(AVG(r.percentage), 0) 
            FROM quiz_results r 
            JOIN quizzes q ON r.quiz_id = q.id 
            WHERE q.course_id = c.id) as avg_score,
           u.name as instructor_name
    FROM courses c
    LEFT JOIN users u ON c.instructor_id = u.id
    WHERE c.instructor_id = ?
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}

// Calculate total earnings (in Taka)
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

// Count pending approvals
$pending_stmt = $conn->prepare("SELECT COUNT(*) as pending FROM courses WHERE instructor_id = ? AND status = 'pending'");
$pending_stmt->bind_param("i", $user['id']);
$pending_stmt->execute();
$pending = $pending_stmt->get_result()->fetch_assoc()['pending'];

// Get notifications for this instructor
$notifications = [];
$unread_count = 0;
$notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND role = 'instructor' ORDER BY created_at DESC LIMIT 10");
$notif_stmt->bind_param("i", $user['id']);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
while ($row = $notif_result->fetch_assoc()) {
    $notifications[] = $row;
    if ($row['is_read'] == 0) $unread_count++;
}

$conn->close();

// Helper function for time ago (used in notifications)
function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $diff = $current_time - $time_ago;
    $seconds = $diff;
    $minutes = round($seconds / 60);
    $hours   = round($seconds / 3600);
    $days    = round($seconds / 86400);
    $weeks   = round($seconds / 604800);
    $months  = round($seconds / 2629440);
    $years   = round($seconds / 31553280);
    if ($seconds <= 60) return "Just now";
    else if ($minutes <= 60) return $minutes == 1 ? "1 minute ago" : "$minutes minutes ago";
    else if ($hours <= 24) return $hours == 1 ? "1 hour ago" : "$hours hours ago";
    else if ($days <= 7) return $days == 1 ? "yesterday" : "$days days ago";
    else if ($weeks <= 4.3) return $weeks == 1 ? "1 week ago" : "$weeks weeks ago";
    else if ($months <= 12) return $months == 1 ? "1 month ago" : "$months months ago";
    else return $years == 1 ? "1 year ago" : "$years years ago";
}
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
            --border-radius: 10px;
            --transition: all 0.2s ease;
            --shadow: 0 2px 5px rgba(0,0,0,0.05);
            --shadow-md: 0 5px 15px rgba(0,0,0,0.07);
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
            background: white;
            padding: 1rem 2rem;
            box-shadow: var(--shadow);
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
            box-shadow: var(--shadow-md);
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
        .quick-actions {
            display: flex;
            gap: 1.5rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }
        .quick-action-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            flex: 1;
            min-width: 200px;
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--border-light);
        }
        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }
        .action-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }
        .action-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .action-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .action-link:hover {
            color: var(--primary-dark);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            border: 1px solid var(--border-light);
            transition: var(--transition);
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
        }
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .course-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
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
        .course-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            color: var(--text-muted);
            flex-wrap: wrap;
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
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-light);
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--border-light);
        }
        @media (max-width: 768px) {
            .right-section { gap: 0.75rem; }
            .user-info { display: none; }
            .quick-actions { flex-direction: column; }
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
            <!-- Notification Bell -->
            <div class="notification-dropdown">
                <button class="notification-btn" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="badge" id="unread-count"><?php echo $unread_count; ?></span>
                </button>
                <div class="notification-panel" id="notification-panel">
                    <div class="notification-header">
                        <h4>Notifications</h4>
                        <a href="#" onclick="markAllRead(); return false;">Mark all as read</a>
                    </div>
                    <div class="notification-list" id="notification-list">
                        <!-- Notifications loaded dynamically -->
                    </div>
                </div>
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
        </div>
    </header>

    <div class="dashboard-container">
        <div class="welcome-section">
            <h1 class="welcome-title">Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h1>
            <p class="welcome-subtitle">Manage your courses, create quizzes, and track student engagement</p>
        </div>

        <!-- Quick Actions (Analytics card removed) -->
        <div class="quick-actions">
            <div class="quick-action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #5a67d8 0%, #434190 100%);">
                    <i class="fas fa-book"></i>
                </div>
                <div class="action-title">Course Management</div>
                <div class="action-desc">Create and manage your courses</div>
                <a href="add_course.php" class="action-link">
                    Add Course <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="quick-action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%);">
                    <i class="fas fa-question-circle"></i>
                </div>
                <div class="action-title">Quiz Management</div>
                <div class="action-desc">Create quizzes for your courses</div>
                <a href="instructor_quizzes.php" class="action-link">
                    Go to Quizzes <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <div class="quick-action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #f56565 0%, #c53030 100%);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="action-title">Drop‑Off Detection</div>
                <div class="action-desc">See where students lose interest</div>
                <a href="instructor_dropoff.php" class="action-link">
                    View Analytics <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($courses); ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $pending; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $earnings['total_students']; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">৳<?php echo number_format($earnings['total_earnings'], 2); ?></div>
                <div class="stat-label">Total Earnings</div>
            </div>
        </div>

        <!-- My Courses -->
        <h2 class="section-title"><i class="fas fa-book-open"></i> My Courses</h2>
        <?php if (count($courses) > 0): ?>
            <div class="courses-grid">
                <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                            <div class="course-instructor">By <?php echo htmlspecialchars($course['instructor_name'] ?? 'You'); ?></div>
                        </div>
                        <div class="course-body">
                            <p class="course-description"><?php echo htmlspecialchars($course['description'] ?? 'No description'); ?></p>
                            <div class="course-stats">
                                <span><i class="fas fa-users"></i> <?php echo $course['student_count']; ?> students</span>
                                <span><i class="fas fa-question-circle"></i> <?php echo $course['quiz_count']; ?> quizzes</span>
                                <span><i class="fas fa-star"></i> <?php echo number_format($course['avg_score'], 1); ?>% avg</span>
                            </div>
                            <div class="course-stats">
                                <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($course['created_at'])); ?></span>
                                <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($course['level'] ?? 'All'); ?></span>
                                <span><i class="fas fa-money-bill-wave"></i> ৳<?php echo number_format($course['price'], 2); ?></span>
                            </div>
                            <div class="course-actions">
                                <a href="manage_content.php?course_id=<?php echo $course['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-edit"></i> Manage
                                </a>
                                <!-- Quizzes button removed -->
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h3>No courses created yet</h3>
                <p>Start by creating your first course!</p>
                <div style="margin-top:1rem;">
                    <a href="add_course.php" class="btn btn-primary" style="display: inline-block; padding: 0.75rem 2rem;">
                        <i class="fas fa-plus"></i> Create Course
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Notification functions
        function loadNotifications() {
            fetch('get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('unread-count').textContent = data.unread_count;
                    let list = document.getElementById('notification-list');
                    list.innerHTML = '';
                    if (data.notifications.length === 0) {
                        list.innerHTML = '<div class="notification-item">No notifications</div>';
                    } else {
                        data.notifications.forEach(n => {
                            let item = document.createElement('div');
                            item.className = 'notification-item' + (n.is_read ? '' : ' unread');
                            item.onclick = () => { if (n.link) window.location.href = n.link; };
                            item.innerHTML = `
                                <div class="message">${n.message}</div>
                                <div class="time">${n.time}</div>
                            `;
                            list.appendChild(item);
                        });
                    }
                });
        }

        function toggleNotifications() {
            let panel = document.getElementById('notification-panel');
            panel.classList.toggle('show');
            if (panel.classList.contains('show')) {
                loadNotifications();
            }
        }

        function markAllRead() {
            fetch('mark_notifications_read.php', { method: 'POST' })
                .then(() => {
                    document.getElementById('unread-count').textContent = '0';
                    loadNotifications();
                });
        }

        // Close panel when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.notification-dropdown')) {
                document.getElementById('notification-panel').classList.remove('show');
            }
        });

        // Load unread count on page load
        document.addEventListener('DOMContentLoaded', function() {
            fetch('get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('unread-count').textContent = data.unread_count;
                });
        });
    </script>
</body>
</html>