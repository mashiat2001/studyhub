<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle session message
if (isset($_SESSION['message'])) {
    $success_message = $_SESSION['message'];
    unset($_SESSION['message']);
} else {
    $success_message = '';
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

// Get counts
$pending_count = $conn->query("SELECT COUNT(*) as count FROM courses WHERE status = 'pending'")->fetch_assoc()['count'];
$total_courses = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];
$approved_courses = $conn->query("SELECT COUNT(*) as count FROM courses WHERE status = 'approved'")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_instructors = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'instructor'")->fetch_assoc()['count'];
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'")->fetch_assoc()['count'];

// Get pending content update requests count
$pending_updates = $conn->query("SELECT COUNT(*) as count FROM course_update_requests WHERE status = 'pending'")->fetch_assoc()['count'];

// Get unread notifications count
$unread_count = 0;
$notif_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND role = 'admin' AND is_read = 0");
$notif_stmt->bind_param("i", $user['id']);
$notif_stmt->execute();
$unread_count = $notif_stmt->get_result()->fetch_assoc()['unread'];
$notif_stmt->close();

// ----- AUTOMATED POLICY ALERTS (without view details) -----
$alerts = [];

// 1. Courses with low average completion rate (< 50%)
$low_completion = $conn->query("
    SELECT c.id, c.title, AVG(uc.progress) as avg_progress
    FROM courses c
    JOIN user_courses uc ON c.id = uc.course_id
    WHERE c.status = 'approved'
    GROUP BY c.id
    HAVING avg_progress < 50
");
while ($row = $low_completion->fetch_assoc()) {
    $alerts[] = [
        'type' => 'completion',
        'message' => "Course '{$row['title']}' has low completion rate (" . round($row['avg_progress'], 1) . "%)"
    ];
}

// 2. Courses with low average quiz score (< 60%)
$low_quiz = $conn->query("
    SELECT c.id, c.title, AVG(r.percentage) as avg_score
    FROM courses c
    JOIN quizzes q ON c.id = q.course_id
    JOIN quiz_results r ON q.id = r.quiz_id
    WHERE c.status = 'approved'
    GROUP BY c.id
    HAVING avg_score < 60
");
while ($row = $low_quiz->fetch_assoc()) {
    $alerts[] = [
        'type' => 'quiz',
        'message' => "Course '{$row['title']}' has low quiz average (" . round($row['avg_score'], 1) . "%)"
    ];
}

// 3. Instructors inactive (no new course in last 30 days)
$inactive_instructors = $conn->query("
    SELECT u.id, u.name, MAX(c.created_at) as last_course
    FROM users u
    LEFT JOIN courses c ON u.id = c.instructor_id
    WHERE u.role = 'instructor'
    GROUP BY u.id
    HAVING last_course IS NULL OR last_course < DATE_SUB(NOW(), INTERVAL 30 DAY)
");
while ($row = $inactive_instructors->fetch_assoc()) {
    $last = $row['last_course'] ? date('M j, Y', strtotime($row['last_course'])) : 'never';
    $alerts[] = [
        'type' => 'instructor',
        'message' => "Instructor '{$row['name']}' has been inactive (last course: $last)"
    ];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - StudyHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #5a67d8;
            --primary-light: #7c8cf0;
            --primary-dark: #434190;
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
        
        /* Notification Styles */
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
            text-align: center;
        }
        
        .welcome-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .welcome-subtitle {
            opacity: 0.9;
            font-size: 1rem;
        }
        
        .alerts-section {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 5px solid var(--warning);
        }
        .alerts-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .alerts-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--warning) 0%, #cc7b2a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        .alerts-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        .alert-items {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .alert-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: var(--light-bg);
            border-radius: 8px;
            border-left: 4px solid;
        }
        .alert-item.type-completion { border-left-color: var(--success); }
        .alert-item.type-quiz { border-left-color: var(--danger); }
        .alert-item.type-instructor { border-left-color: var(--primary); }
        .alert-message {
            flex: 1;
            font-size: 0.95rem;
        }
        .alert-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-completion { background: #d1fae5; color: #065f46; }
        .badge-quiz { background: #fee2e2; color: #991b1b; }
        .badge-instructor { background: #e9d8fd; color: #44337a; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--border-light);
        }
        .stat-card:hover {
            transform: translateY(-3px);
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
        }
        
        .feature-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .feature-card {
            background: white;
            padding: 1.8rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--border-light);
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }
        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin: 0 auto 1.25rem;
        }
        .feature-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--text-dark);
        }
        .feature-description {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 1.25rem;
        }
        .feature-btn {
            background: var(--primary);
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .feature-btn:hover {
            background: var(--primary-dark);
        }
        .badge-new {
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            padding: 0.15rem 0.5rem;
            border-radius: 30px;
            margin-left: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }
            .right-section {
                justify-content: space-between;
            }
            .alert-item {
                flex-direction: column;
                align-items: flex-start;
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
                    <div class="user-role">Administrator</div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <?php if (!empty($success_message)): ?>
            <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <div class="welcome-section">
            <h1 class="welcome-title">Admin Dashboard</h1>
            <p class="welcome-subtitle">Manage your educational platform with ease</p>
        </div>

        <!-- Automated Policy Alerts (without view details) -->
        <?php if (!empty($alerts)): ?>
            <div class="alerts-section">
                <div class="alerts-header">
                    <div class="alerts-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h2 class="alerts-title">Academic Alerts</h2>
                </div>
                <div class="alert-items">
                    <?php foreach ($alerts as $alert): ?>
                        <div class="alert-item type-<?php echo $alert['type']; ?>">
                            <span class="alert-badge badge-<?php echo $alert['type']; ?>">
                                <?php
                                if ($alert['type'] == 'completion') echo 'Low Completion';
                                elseif ($alert['type'] == 'quiz') echo 'Low Quiz';
                                else echo 'Inactive';
                                ?>
                            </span>
                            <span class="alert-message"><?php echo htmlspecialchars($alert['message']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_users; ?></div>
                <div class="stat-label">Total Users</div>
                <small style="color: var(--text-muted);"><?php echo $total_students; ?> students · <?php echo $total_instructors; ?> instructors</small>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_courses; ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $approved_courses; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $pending_count; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
        </div>

        <!-- Feature Cards -->
        <div class="feature-cards">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <h3 class="feature-title">Course Approvals</h3>
                <p class="feature-description">
                    Review, approve, or reject course submissions.
                </p>
                <a href="admin_approve_courses.php" class="feature-btn">
                    <i class="fas fa-arrow-right"></i> Manage
                </a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-pen"></i>
                </div>
                <h3 class="feature-title">Content Updates</h3>
                <p class="feature-description">
                    Approve or reject instructor‑submitted content changes.
                </p>
                <a href="admin_approve_updates.php" class="feature-btn">
                    <i class="fas fa-arrow-right"></i> View 
                    <?php if ($pending_updates > 0): ?>
                        <span class="badge-new"><?php echo $pending_updates; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <h3 class="feature-title">User Management</h3>
                <p class="feature-description">
                    Manage students, instructors, and roles.
                </p>
                <a href="manage_users.php" class="feature-btn">
                    <i class="fas fa-arrow-right"></i> Manage
                </a>
            </div>
        </div>
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