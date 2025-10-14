<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$conn = new mysqli("localhost", "root", "", "project_db");

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    
    // Check if the user is an admin
    $check_stmt = $conn->prepare("SELECT role, name FROM users WHERE id = ?");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $user = $result->fetch_assoc();
    
    if ($user) {
        // Prevent deletion of admin users
        if ($user['role'] === 'admin') {
            $_SESSION['error'] = "Cannot delete administrator users";
        } else {
            if ($user['role'] === 'instructor') {
                // Set courses instructor_id to NULL before deleting instructor
                $update_courses = $conn->prepare("UPDATE courses SET instructor_id = NULL WHERE instructor_id = ?");
                $update_courses->bind_param("i", $user_id);
                $update_courses->execute();
            }
            
            // Also delete from user_courses table
            $delete_user_courses = $conn->prepare("DELETE FROM user_courses WHERE user_id = ?");
            $delete_user_courses->bind_param("i", $user_id);
            $delete_user_courses->execute();
            
            // Now delete the user
            $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $delete_stmt->bind_param("i", $user_id);
            
            if ($delete_stmt->execute()) {
                $_SESSION['message'] = "User '{$user['name']}' deleted successfully";
            } else {
                $_SESSION['error'] = "Error deleting user: " . $conn->error;
            }
        }
    }
    
    header('Location: manage_users.php');
    exit();
}

// Handle role update
if (isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['role'];
    
    // Prevent changing role of current admin user to non-admin
    $current_user_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $current_user_stmt->bind_param("i", $user_id);
    $current_user_stmt->execute();
    $current_user_result = $current_user_stmt->get_result();
    $current_user = $current_user_result->fetch_assoc();
    
    if ($current_user && $current_user['role'] === 'admin' && $new_role !== 'admin') {
        $_SESSION['error'] = "Cannot change administrator role";
    } else {
        $update_stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $update_stmt->bind_param("si", $new_role, $user_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['message'] = "User role updated successfully";
        } else {
            $_SESSION['error'] = "Error updating role: " . $conn->error;
        }
    }
    
    header('Location: manage_users.php');
    exit();
}

// Get all users with course counts
$users_query = "
    SELECT u.*, 
           COUNT(DISTINCT c.id) as courses_created,
           COUNT(DISTINCT uc.course_id) as courses_enrolled
    FROM users u 
    LEFT JOIN courses c ON u.id = c.instructor_id 
    LEFT JOIN user_courses uc ON u.id = uc.user_id 
    GROUP BY u.id 
    ORDER BY u.created_at DESC
";
$users_result = $conn->query($users_query);

// Get user statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_users,
        SUM(role = 'student') as total_students,
        SUM(role = 'instructor') as total_instructors,
        SUM(role = 'admin') as total_admins
    FROM users
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - StudyHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #7E6CCA;
            --primary-light: #9F90DB;
            --primary-dark: #6351A6;
            --success: #48BB78;
            --warning: #ED8936;
            --danger: #F56565;
            --text-dark: #2D3748;
            --text-light: #718096;
            --light-bg: #F7FAFC;
            --border-radius: 8px;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            line-height: 1.6;
        }
        
        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .logo span {
            color: var(--primary);
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
        }
        
        .nav-links a:hover {
            background: var(--light-bg);
        }
        
        .nav-links a.active {
            background: var(--primary);
            color: white;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: var(--text-dark);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-users .stat-value { color: var(--primary); }
        .stat-students .stat-value { color: var(--success); }
        .stat-instructors .stat-value { color: var(--warning); }
        .stat-admins .stat-value { color: var(--danger); }
        
        .stat-label {
            color: var(--text-light);
            font-weight: 500;
        }
        
        .message {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: var(--border-radius);
            font-weight: 500;
        }
        
        .success {
            background: #C6F6D5;
            color: #22543D;
            border-left: 4px solid var(--success);
        }
        
        .error {
            background: #FED7D7;
            color: #742A2A;
            border-left: 4px solid var(--danger);
        }
        
        .users-table {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #E2E8F0;
        }
        
        th {
            background: #F7FAFC;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        tr:hover {
            background: #F7FAFC;
        }
        
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .role-student { background: #C6F6D5; color: #22543D; }
        .role-instructor { background: #FEEBC8; color: #744210; }
        .role-admin { background: #FED7D7; color: #742A2A; }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #E53E3E;
        }
        
        .btn-danger:disabled {
            background: #CBD5E0;
            cursor: not-allowed;
        }
        
        .select-role {
            padding: 0.5rem;
            border: 1px solid #E2E8F0;
            border-radius: var(--border-radius);
            background: white;
            font-size: 0.875rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .user-details .name {
            font-weight: 600;
        }
        
        .user-details .email {
            font-size: 0.875rem;
            color: var(--text-light);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            Study<span>Hub</span>
        </div>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_users.php" class="active">Users</a>
            <a href="manage_courses.php">Courses</a>
            <a href="logout.php">Logout</a>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">User Management</h1>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card stat-users">
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            
            <div class="stat-card stat-students">
                <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                <div class="stat-label">Students</div>
            </div>
            
            <div class="stat-card stat-instructors">
                <div class="stat-value"><?php echo $stats['total_instructors']; ?></div>
                <div class="stat-label">Instructors</div>
            </div>
            
            <div class="stat-card stat-admins">
                <div class="stat-value"><?php echo $stats['total_admins']; ?></div>
                <div class="stat-label">Administrators</div>
            </div>
        </div>

        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Courses Created</th>
                        <th>Courses Enrolled</th>
                        <th>Join Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users_result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                                <div class="user-details">
                                    <div class="name"><?php echo htmlspecialchars($user['name']); ?></div>
                                    <div class="email"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td><?php echo $user['courses_created']; ?></td>
                        <td><?php echo $user['courses_enrolled']; ?></td>
                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <div class="actions">
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <select name="role" class="select-role" onchange="this.form.submit()" <?php echo $user['role'] === 'admin' ? 'disabled' : ''; ?>>
                                        <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                        <option value="instructor" <?php echo $user['role'] === 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                    <input type="hidden" name="update_role" value="1">
                                </form>
                                
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($user['name']); ?>?');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user" class="btn btn-danger" <?php echo $user['role'] === 'admin' ? 'disabled' : ''; ?>>
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                message.style.opacity = '0';
                message.style.transition = 'opacity 0.5s ease';
                setTimeout(() => message.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>