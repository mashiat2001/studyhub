<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'project_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle approval/rejection
if (isset($_GET['action']) && isset($_GET['id'])) {
    $request_id = intval($_GET['id']);
    $action = $_GET['action'];

    // Get request details
    $req_stmt = $conn->prepare("SELECT * FROM course_update_requests WHERE id = ?");
    $req_stmt->bind_param("i", $request_id);
    $req_stmt->execute();
    $request = $req_stmt->get_result()->fetch_assoc();

    if ($request) {
        $course_id = $request['course_id'];
        $instructor_id = $request['instructor_id'];
        $files = json_decode($request['files'], true);
        $files_to_remove = json_decode($request['files_to_remove'], true);

        // Get course title for notification
        $course_stmt = $conn->prepare("SELECT title FROM courses WHERE id = ?");
        $course_stmt->bind_param("i", $course_id);
        $course_stmt->execute();
        $course_title = $course_stmt->get_result()->fetch_assoc()['title'];

        if ($action === 'approve') {
            // --- Process file additions ---
            if (!empty($files)) {
                $target_dir = "course_content/" . $course_id . "/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                foreach ($files as $f) {
                    $temp_path = $f['temp_path'];
                    // Ensure the file still exists
                    if (file_exists($temp_path)) {
                        $new_path = $target_dir . basename($temp_path);
                        if (rename($temp_path, $new_path)) {
                            // Insert into course_files
                            $insert = $conn->prepare("INSERT INTO course_files (course_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                            $insert->bind_param("isssi", $course_id, $f['original_name'], $new_path, $f['file_type'], $f['file_size']);
                            $insert->execute();
                            $insert->close();
                        }
                    }
                }
            }

            // --- Process file removals ---
            if (!empty($files_to_remove)) {
                foreach ($files_to_remove as $file_id) {
                    // Get file path before deleting
                    $file_stmt = $conn->prepare("SELECT file_path FROM course_files WHERE id = ?");
                    $file_stmt->bind_param("i", $file_id);
                    $file_stmt->execute();
                    $file = $file_stmt->get_result()->fetch_assoc();
                    if ($file && file_exists($file['file_path'])) {
                        unlink($file['file_path']);
                    }
                    $delete = $conn->prepare("DELETE FROM course_files WHERE id = ?");
                    $delete->bind_param("i", $file_id);
                    $delete->execute();
                    $delete->close();
                }
            }

            // Update request status
            $update = $conn->prepare("UPDATE course_update_requests SET status = 'approved', admin_notes = ? WHERE id = ?");
            $admin_notes = "Approved by admin on " . date('Y-m-d H:i:s');
            $update->bind_param("si", $admin_notes, $request_id);
            $update->execute();
            $update->close();

            // Notify instructor
            $message = "Your update request for course '{$course_title}' has been approved. The changes are now live.";
            $link = "instructor_dashboard.php";
            $notify = $conn->prepare("INSERT INTO notifications (user_id, role, message, link) VALUES (?, 'instructor', ?, ?)");
            $notify->bind_param("iss", $instructor_id, $message, $link);
            $notify->execute();
            $notify->close();

            $_SESSION['message'] = "Request approved successfully.";

        } elseif ($action === 'reject') {
            // Delete any temp files
            if (!empty($files)) {
                foreach ($files as $f) {
                    if (file_exists($f['temp_path'])) {
                        unlink($f['temp_path']);
                    }
                }
            }
            // Update request status
            $update = $conn->prepare("UPDATE course_update_requests SET status = 'rejected', admin_notes = ? WHERE id = ?");
            $admin_notes = "Rejected by admin on " . date('Y-m-d H:i:s');
            $update->bind_param("si", $admin_notes, $request_id);
            $update->execute();
            $update->close();

            // Notify instructor
            $message = "Your update request for course '{$course_title}' has been rejected. Please review your changes.";
            $link = "manage_content.php?course_id=" . $course_id;
            $notify = $conn->prepare("INSERT INTO notifications (user_id, role, message, link) VALUES (?, 'instructor', ?, ?)");
            $notify->bind_param("iss", $instructor_id, $message, $link);
            $notify->execute();
            $notify->close();

            $_SESSION['message'] = "Request rejected.";
        }
    }

    header("Location: admin_approve_updates.php");
    exit();
}

// Get all pending requests
$requests = [];
$stmt = $conn->prepare("
    SELECT r.*, c.title as course_title, u.name as instructor_name
    FROM course_update_requests r
    JOIN courses c ON r.course_id = c.id
    JOIN users u ON r.instructor_id = u.id
    WHERE r.status = 'pending'
    ORDER BY r.requested_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}
$stmt->close();

// Helper function to get file names for removal (used in display)
function getFileNames($file_ids, $conn) {
    if (empty($file_ids)) return [];
    $ids = implode(',', array_map('intval', $file_ids));
    $result = $conn->query("SELECT file_name FROM course_files WHERE id IN ($ids)");
    $names = [];
    while ($row = $result->fetch_assoc()) {
        $names[] = $row['file_name'];
    }
    return $names;
}

// Note: DO NOT close $conn here; it will be used later.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Content Updates - StudyHub</title>
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
            margin-bottom: 2rem;
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
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .back-link {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1.25rem;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .back-link:hover {
            background: var(--primary-dark);
        }
        .logout-btn {
            background: var(--primary);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .logout-btn:hover {
            background: var(--primary-dark);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success);
        }
        .requests-grid {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .request-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            border: 1px solid var(--border-light);
        }
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .course-title {
            font-size: 1.3rem;
            font-weight: 600;
        }
        .instructor-name {
            color: var(--primary);
            font-weight: 500;
        }
        .request-meta {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .files-section {
            margin: 1rem 0;
            padding: 1rem;
            background: var(--light-bg);
            border-radius: 8px;
        }
        .files-section h4 {
            margin-bottom: 0.5rem;
        }
        .file-list {
            list-style: none;
        }
        .file-list li {
            padding: 0.25rem 0;
            font-size: 0.9rem;
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        .empty-state i {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        .actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .btn {
            padding: 0.6rem 1.5rem;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-approve {
            background: var(--success);
            color: white;
        }
        .btn-approve:hover {
            background: #2f855a;
        }
        .btn-reject {
            background: var(--danger);
            color: white;
        }
        .btn-reject:hover {
            background: #c53030;
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
            <a href="admin_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">Pending Content Updates</h1>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($requests)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle"></i>
                <h3>No pending requests</h3>
                <p>All content updates have been processed.</p>
            </div>
        <?php else: ?>
            <div class="requests-grid">
                <?php foreach ($requests as $req): ?>
                    <div class="request-card">
                        <div class="request-header">
                            <div>
                                <span class="course-title"><?php echo htmlspecialchars($req['course_title']); ?></span>
                                <span class="instructor-name"> by <?php echo htmlspecialchars($req['instructor_name']); ?></span>
                            </div>
                            <div class="request-meta">Requested on <?php echo date('M j, Y g:i A', strtotime($req['requested_at'])); ?></div>
                        </div>

                        <?php
                        $files_to_add = json_decode($req['files'], true);
                        $files_to_remove = json_decode($req['files_to_remove'], true);
                        ?>

                        <?php if (!empty($files_to_add)): ?>
                            <div class="files-section">
                                <h4>Files to Add:</h4>
                                <ul class="file-list">
                                    <?php foreach ($files_to_add as $f): ?>
                                        <li><i class="fas fa-plus-circle" style="color: var(--success);"></i> <?php echo htmlspecialchars($f['original_name']); ?> (<?php echo formatFileSize($f['file_size']); ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($files_to_remove)): 
                            $file_names = getFileNames($files_to_remove, $conn);
                        ?>
                            <div class="files-section">
                                <h4>Files to Remove:</h4>
                                <ul class="file-list">
                                    <?php foreach ($file_names as $fname): ?>
                                        <li><i class="fas fa-minus-circle" style="color: var(--danger);"></i> <?php echo htmlspecialchars($fname); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="actions">
                            <a href="admin_approve_updates.php?action=approve&id=<?php echo $req['id']; ?>" class="btn btn-approve" onclick="return confirm('Approve this request?')">
                                <i class="fas fa-check"></i> Approve
                            </a>
                            <a href="admin_approve_updates.php?action=reject&id=<?php echo $req['id']; ?>" class="btn btn-reject" onclick="return confirm('Reject this request?')">
                                <i class="fas fa-times"></i> Reject
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php
    // Close connection only after all output
    $conn->close();

    function formatFileSize($bytes) {
        if ($bytes == 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
    ?>
</body>
</html>