<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Display success message if exists
if (isset($_SESSION['message'])) {
    $success_message = $_SESSION['message'];
    unset($_SESSION['message']);
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

// Get pending courses count
$pending_count = $conn->query("SELECT COUNT(*) as count FROM courses WHERE status = 'pending'")->fetch_assoc()['count'];

// Get all courses count
$total_courses = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];

// Get approved courses count
$approved_courses = $conn->query("SELECT COUNT(*) as count FROM courses WHERE status = 'approved'")->fetch_assoc()['count'];

// Get all users count
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];

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
            max-width: 1000px;
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
            text-align: center;
        }
        
        .welcome-title {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .welcome-subtitle {
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
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
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 10px;
            text-align: center;
            justify-content: center;
        }
        
        .pending-alert {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: var(--border-radius);
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        .pending-alert-title {
            color: #856404;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .pending-alert-text {
            color: #856404;
            margin-bottom: 15px;
        }
        
        .action-btn {
            background: #856404;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .action-btn:hover {
            background: #6b4f04;
            transform: translateY(-2px);
            color: white;
        }
        
        .feature-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .feature-card {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            margin: 0 auto 20px;
        }
        
        .feature-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-dark);
        }
        
        .feature-description {
            color: var(--text-light);
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .feature-btn {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .feature-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            color: white;
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
                <div class="user-role">Administrator</div>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <div class="dashboard-container">
        <?php if (!empty($success_message)): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> 
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="welcome-section">
            <h1 class="welcome-title">Admin Dashboard</h1>
            <p class="welcome-subtitle">Manage your educational platform</p>
            
            <!-- Pending Courses Alert -->
            <?php if ($pending_count > 0): ?>
                <div class="pending-alert">
                    <div class="pending-alert-title">
                        <i class="fas fa-exclamation-circle"></i> Action Required
                    </div>
                    <p class="pending-alert-text">
                        You have <strong><?php echo $pending_count; ?> courses</strong> waiting for approval.
                    </p>
                    <a href="admin_approve_courses.php" class="action-btn">
                        <i class="fas fa-clipboard-check"></i> Review Course Approvals
                    </a>
                </div>
            <?php else: ?>
                <div style="background: rgba(255, 255, 255, 0.2); padding: 15px; border-radius: 8px; margin-top: 15px;">
                    <i class="fas fa-check-circle" style="margin-right: 10px;"></i>
                    All courses are approved. No pending actions.
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_users; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_courses; ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $approved_courses; ?></div>
                <div class="stat-label">Approved Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $pending_count; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
        </div>

        <!-- Main Features -->
        <div class="feature-cards">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <h3 class="feature-title">Course Approvals</h3>
                <p class="feature-description">
                    Review, approve, or reject course submissions from instructors.
                    Manage the quality of content on your platform.
                </p>
                <a href="admin_approve_courses.php" class="feature-btn">
                    <i class="fas fa-arrow-right"></i> Manage Approvals
                </a>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <h3 class="feature-title">View Course Content</h3>
                <p class="feature-description">
                    Browse and view all course materials exactly as students see them.
                    Preview videos, PDFs, and other learning materials.
                </p>
                <a href="admin_courses.php" class="feature-btn">
                    <i class="fas fa-arrow-right"></i> View Courses
                </a>
            </div>
           <div class="feature-card">
    <div class="feature-icon">
        <i class="fas fa-users-cog"></i>
    </div>
    <h3 class="feature-title">User Management</h3>
    <p class="feature-description">
        Manage all platform users, roles, and permissions.
        View students, instructors, and admin accounts.
    </p>
    <a href="manage_users.php" class="feature-btn">
        <i class="fas fa-arrow-right"></i> Manage Users
    </a>
    </div>
        </div>
    </div>

    <script>
        // Auto-hide success message after 5 seconds
        setTimeout(function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                successAlert.style.transition = 'opacity 0.5s ease';
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.remove(), 500);
            }
        }, 5000);
    </script>
</body>
</html>