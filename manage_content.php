<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'instructor') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get instructor's courses
$courses = [];
$stmt = $conn->prepare("SELECT * FROM courses WHERE instructor_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}

$selected_course = null;
$course_files = [];
$success_message = '';
$error_message = '';

if (isset($_GET['course_id'])) {
    $course_id = intval($_GET['course_id']);

    // Get the selected course (only if owned by this instructor)
    $course_stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
    $course_stmt->bind_param("ii", $course_id, $user['id']);
    $course_stmt->execute();
    $selected_course = $course_stmt->get_result()->fetch_assoc();

    if ($selected_course) {
        // Get existing files for this course
        $files_stmt = $conn->prepare("SELECT * FROM course_files WHERE course_id = ? ORDER BY uploaded_at DESC");
        $files_stmt->bind_param("i", $course_id);
        $files_stmt->execute();
        $course_files = $files_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// --- Helper function to notify all admins ---
function notifyAdmins($conn, $course_title, $instructor_name) {
    $admins = $conn->query("SELECT id FROM users WHERE role = 'admin'");
    if ($admins && $admins->num_rows > 0) {
        $message = "New content update request for course '{$course_title}' by instructor {$instructor_name}.";
        $link = "admin_approve_updates.php";
        $notify = $conn->prepare("INSERT INTO notifications (user_id, role, message, link) VALUES (?, 'admin', ?, ?)");
        if ($notify) {
            while ($admin = $admins->fetch_assoc()) {
                $notify->bind_param("iss", $admin['id'], $message, $link);
                $notify->execute();
            }
            $notify->close();
        } else {
            error_log("Failed to prepare admin notification: " . $conn->error);
        }
    } else {
        error_log("No admin users found to notify about content update.");
    }
}

// --- Handle file deletion request ---
if (isset($_POST['delete_file'])) {
    $file_id = intval($_POST['file_id']);

    // Verify the file belongs to the instructor's course
    $verify_stmt = $conn->prepare("SELECT cf.*, c.status, c.id as course_id, c.title as course_title FROM course_files cf 
                                  JOIN courses c ON cf.course_id = c.id 
                                  WHERE cf.id = ? AND c.instructor_id = ?");
    $verify_stmt->bind_param("ii", $file_id, $user['id']);
    $verify_stmt->execute();
    $file_to_delete = $verify_stmt->get_result()->fetch_assoc();

    if ($file_to_delete) {
        $course_status = $file_to_delete['status'];
        $course_id = $file_to_delete['course_id'];
        $course_title = $file_to_delete['course_title'];

        if ($course_status === 'approved') {
            // Approved course: create a deletion request
            $files_to_remove = json_encode([$file_id]);

            // Check if the course_update_requests table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'course_update_requests'");
            if ($table_check->num_rows == 0) {
                $error_message = "Database table 'course_update_requests' is missing. Please run the SQL to create it.";
            } else {
                $request_stmt = $conn->prepare("INSERT INTO course_update_requests (course_id, instructor_id, files_to_remove, status) VALUES (?, ?, ?, 'pending')");
                if (!$request_stmt) {
                    $error_message = "SQL prepare error: " . $conn->error;
                } else {
                    $request_stmt->bind_param("iis", $course_id, $user['id'], $files_to_remove);
                    if ($request_stmt->execute()) {
                        $success_message = "Deletion request submitted for admin approval.";
                        // --- Notify all admins about the new request ---
                        notifyAdmins($conn, $course_title, $user['name']);
                    } else {
                        $error_message = "Failed to create deletion request: " . $request_stmt->error;
                    }
                    $request_stmt->close();
                }
            }
        } else {
            // Pending/rejected course: delete immediately
            if (file_exists($file_to_delete['file_path'])) {
                unlink($file_to_delete['file_path']);
            }
            $delete_stmt = $conn->prepare("DELETE FROM course_files WHERE id = ?");
            $delete_stmt->bind_param("i", $file_id);
            if ($delete_stmt->execute()) {
                $success_message = "File deleted successfully!";
            } else {
                $error_message = "Failed to delete file from database: " . $delete_stmt->error;
            }
            $delete_stmt->close();
        }
        // Redirect to avoid resubmission
        if (!empty($error_message)) {
            $_SESSION['error'] = $error_message;
        }
        if (!empty($success_message)) {
            $_SESSION['success'] = $success_message;
        }
        header("Location: manage_content.php?course_id=" . $course_id);
        exit();
    } else {
        $error_message = "File not found or you don't have permission.";
    }
}

// --- Handle file upload request ---
if (isset($_POST['upload_files']) && $selected_course) {
    $course_id = $selected_course['id'];
    $course_status = $selected_course['status'];
    $course_title = $selected_course['title'];

    if (isset($_FILES['new_files']) && !empty($_FILES['new_files']['name'][0])) {
        $uploaded_files_info = []; // will store info about each file

        if ($course_status === 'approved') {
            // For approved courses, store files in temp folder and create request
            $temp_dir = "temp_uploads/" . $course_id . "/";
            if (!is_dir($temp_dir)) {
                if (!mkdir($temp_dir, 0777, true)) {
                    $error_message = "Failed to create temp upload directory. Check permissions.";
                }
            }
        } else {
            // For pending/rejected, upload directly to course folder
            $upload_dir = "course_content/" . $course_id . "/";
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    $error_message = "Failed to create course content directory.";
                }
            }
        }

        $uploaded_count = 0;
        $upload_errors = '';

        foreach ($_FILES['new_files']['name'] as $key => $name) {
            if (empty($name)) continue;

            $file_tmp = $_FILES['new_files']['tmp_name'][$key];
            $file_size = $_FILES['new_files']['size'][$key];
            $file_type = $_FILES['new_files']['type'][$key];
            $file_error = $_FILES['new_files']['error'][$key];

            if ($file_error === UPLOAD_ERR_OK && $file_size <= 100 * 1024 * 1024) {
                $safe_name = time() . '_' . $key . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $name);

                if ($course_status === 'approved') {
                    if (isset($temp_dir)) {
                        $file_path = $temp_dir . $safe_name;
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            $uploaded_files_info[] = [
                                'temp_path' => $file_path,
                                'original_name' => $name,
                                'file_type' => $file_type,
                                'file_size' => $file_size
                            ];
                            $uploaded_count++;
                        } else {
                            $upload_errors .= "Failed to move uploaded file: $name. ";
                        }
                    }
                } else {
                    if (isset($upload_dir)) {
                        $file_path = $upload_dir . $safe_name;
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            // Insert directly into course_files
                            $file_stmt = $conn->prepare("INSERT INTO course_files (course_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                            if ($file_stmt) {
                                $file_stmt->bind_param("isssi", $course_id, $name, $file_path, $file_type, $file_size);
                                if ($file_stmt->execute()) {
                                    $uploaded_count++;
                                } else {
                                    $upload_errors .= "Database insert failed for $name: " . $file_stmt->error . " ";
                                }
                                $file_stmt->close();
                            } else {
                                $upload_errors .= "Prepare failed for $name: " . $conn->error . " ";
                            }
                        } else {
                            $upload_errors .= "Failed to move uploaded file: $name. ";
                        }
                    }
                }
            } else {
                $upload_errors .= "File $name failed upload or exceeds 100MB. ";
            }
        }

        if ($uploaded_count > 0 && $course_status === 'approved') {
            // Check table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'course_update_requests'");
            if ($table_check->num_rows == 0) {
                $error_message = "Database table 'course_update_requests' is missing. Files are in temp folder but cannot be approved.";
            } else {
                // Create a request record
                $files_json = json_encode($uploaded_files_info);
                $request_stmt = $conn->prepare("INSERT INTO course_update_requests (course_id, instructor_id, files, status) VALUES (?, ?, ?, 'pending')");
                if ($request_stmt) {
                    $request_stmt->bind_param("iis", $course_id, $user['id'], $files_json);
                    if ($request_stmt->execute()) {
                        $success_message = "$uploaded_count file(s) submitted for admin approval.";
                        // --- Notify all admins about the new request ---
                        notifyAdmins($conn, $course_title, $user['name']);
                    } else {
                        $error_message = "Files uploaded but failed to create approval request: " . $request_stmt->error;
                    }
                    $request_stmt->close();
                } else {
                    $error_message = "Prepare failed for request: " . $conn->error;
                }
            }
        } elseif ($uploaded_count > 0) {
            $success_message = "$uploaded_count file(s) uploaded successfully!";
        } else {
            $error_message = "No files were uploaded. " . $upload_errors;
        }

        if (!empty($error_message)) {
            $_SESSION['error'] = $error_message;
        }
        if (!empty($success_message)) {
            $_SESSION['success'] = $success_message;
        }
        header("Location: manage_content.php?course_id=" . $course_id);
        exit();

    } else {
        $error_message = "Please select at least one file to upload.";
    }
}

// Check for session messages (for redirects)
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

$conn->close();

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return number_format($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Content - StudyHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            font-family: 'Outfit', sans-serif;
        }
        
        .logo-text span {
            color: var(--primary);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
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
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-dark);
            font-family: 'Outfit', sans-serif;
        }
        
        .page-subtitle {
            color: var(--text-light);
            margin-bottom: 30px;
        }
        
        .content-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 30px;
        }
        
        .sidebar {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            height: fit-content;
        }
        
        .sidebar-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--text-dark);
            font-family: 'Outfit', sans-serif;
        }
        
        .course-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .course-item {
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
        }
        
        .course-item:hover {
            border-color: var(--primary-light);
            background: #f8fafc;
        }
        
        .course-item.active {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }
        
        .course-item.active .course-name {
            color: white;
        }
        
        .course-item.active .course-meta {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .course-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-dark);
        }
        
        .course-meta {
            font-size: 12px;
            color: var(--text-light);
        }
        
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .course-header {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .selected-course-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--text-dark);
            font-family: 'Outfit', sans-serif;
        }
        
        .selected-course-description {
            color: var(--text-light);
            line-height: 1.5;
        }
        
        .content-section {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-dark);
            font-family: 'Outfit', sans-serif;
        }
        
        .upload-area {
            border: 2px dashed #e5e7eb;
            border-radius: var(--border-radius);
            padding: 30px;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .upload-area:hover {
            border-color: var(--primary);
            background: #f8fafc;
        }
        
        .upload-area i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .upload-info {
            font-size: 14px;
            color: var(--text-light);
        }
        
        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
        }
        
        .file-card {
            border: 2px solid #e5e7eb;
            border-radius: var(--border-radius);
            padding: 15px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .file-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .file-icon {
            width: 40px;
            height: 40px;
            background: var(--light-bg);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--primary);
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--text-dark);
        }
        
        .file-meta {
            font-size: 12px;
            color: var(--text-light);
        }
        
        .file-actions {
            display: flex;
            gap: 8px;
        }
        
        .file-action-btn {
            padding: 6px 10px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }
        
        .btn-view {
            background: var(--primary);
            color: white;
        }
        
        .btn-view:hover {
            background: var(--primary-dark);
        }
        
        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .btn-delete:hover {
            background: #fecaca;
        }
        
        .btn-upload {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            font-size: 16px;
            width: 100%;
        }
        
        .btn-upload:hover {
            background: var(--primary-dark);
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
        
        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .no-course-selected {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }
        
        .no-course-selected i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #cbd5e0;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #FEF3C7;
            color: #92400E;
        }
        
        .status-approved {
            background: #D1FAE5;
            color: #065F46;
        }
        
        .file-selected {
            border-color: #10b981 !important;
            background: #f0fdf4 !important;
        }
        
        #file-list {
            margin-top: 10px;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
            display: none;
        }

        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            border-bottom: 1px solid #f3f4f6;
        }

        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-preview {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .file-icon-small {
            width: 24px;
            text-align: center;
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
            <a href="instructor_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">Manage Course Content</h1>
        <p class="page-subtitle">Upload, organize, and manage your course materials</p>
        
        <!-- Alert Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="content-layout">
            <!-- Sidebar with course list -->
            <div class="sidebar">
                <h3 class="sidebar-title">Your Courses</h3>
                <div class="course-list">
                    <?php foreach ($courses as $course): ?>
                        <a href="manage_content.php?course_id=<?php echo $course['id']; ?>" 
                           class="course-item <?php echo ($selected_course && $selected_course['id'] == $course['id']) ? 'active' : ''; ?>">
                            <div class="course-name"><?php echo htmlspecialchars($course['title']); ?></div>
                            <div class="course-meta">
                                <span class="status-badge status-<?php echo $course['status']; ?>">
                                    <?php echo ucfirst($course['status']); ?>
                                </span> • 
                                <?php echo date('M d, Y', strtotime($course['created_at'])); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    
                    <?php if (empty($courses)): ?>
                        <div class="empty-state" style="padding: 20px;">
                            <i class="fas fa-book-open"></i>
                            <p>No courses found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main content area -->
            <div class="main-content">
                <?php if ($selected_course): ?>
                    <!-- Course Header -->
                    <div class="course-header">
                        <h2 class="selected-course-title"><?php echo htmlspecialchars($selected_course['title']); ?></h2>
                        <p class="selected-course-description"><?php echo htmlspecialchars($selected_course['description']); ?></p>
                        <?php if ($selected_course['status'] === 'approved'): ?>
                            <div style="margin-top:10px; background:#FEF3C7; border-left:4px solid #D97706; padding:8px 12px; border-radius:6px;">
                                <i class="fas fa-info-circle"></i> This course is approved. Any changes will require admin approval.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Upload Section -->
                    <div class="content-section">
                        <h3 class="section-title">Upload New Materials</h3>
                        
                        <form method="POST" enctype="multipart/form-data" action="">
                            <div class="upload-area" onclick="document.getElementById('newFiles').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <div class="upload-info">
                                    <p><strong>Click to upload new files</strong></p>
                                    <p>PDFs, Videos, Documents - Max 100MB each</p>
                                    <p>You can select multiple files</p>
                                </div>
                                <input type="file" id="newFiles" name="new_files[]" multiple 
                                      style="display: none;" 
                                       accept=".pdf,.mp4,.mkv,.avi,.mov,.wmv,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.rar">
                            </div>
                            
                            <div id="file-list"></div>
                            
                            <button type="submit" name="upload_files" class="btn-upload">
                                <i class="fas fa-upload"></i> Upload Selected Files
                            </button>
                        </form>
                    </div>

                    <!-- Existing Files Section -->
                    <div class="content-section">
                        <h3 class="section-title">
                            Course Materials 
                            <?php if (count($course_files) > 0): ?>
                                <span style="font-size: 1rem; color: var(--text-light);">(<?php echo count($course_files); ?> files)</span>
                            <?php endif; ?>
                        </h3>
                        
                        <?php if (count($course_files) > 0): ?>
                            <div class="files-grid">
                                <?php foreach ($course_files as $file): ?>
                                    <div class="file-card">
                                        <div class="file-icon">
                                            <?php
                                            $file_ext = pathinfo($file['file_name'], PATHINFO_EXTENSION);
                                            $icon = 'fa-file';
                                            if (in_array($file_ext, ['mp4', 'avi', 'mov', 'mkv', 'wmv'])) {
                                                $icon = 'fa-video';
                                            } elseif ($file_ext === 'pdf') {
                                                $icon = 'fa-file-pdf';
                                            } elseif (in_array($file_ext, ['doc', 'docx'])) {
                                                $icon = 'fa-file-word';
                                            } elseif (in_array($file_ext, ['ppt', 'pptx'])) {
                                                $icon = 'fa-file-powerpoint';
                                            } elseif (in_array($file_ext, ['xls', 'xlsx'])) {
                                                $icon = 'fa-file-excel';
                                            } elseif (in_array($file_ext, ['zip', 'rar'])) {
                                                $icon = 'fa-file-archive';
                                            }
                                            ?>
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name"><?php echo htmlspecialchars($file['file_name']); ?></div>
                                            <div class="file-meta">
                                                <?php echo formatFileSize($file['file_size']); ?> • 
                                                <?php echo date('M d, Y', strtotime($file['uploaded_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="file-actions">
                                            <a href="<?php echo $file['file_path']; ?>" target="_blank" class="file-action-btn btn-view">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                                <button type="submit" name="delete_file" class="file-action-btn btn-delete" 
                                                        onclick="return confirm('Are you sure you want to delete this file?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-folder-open"></i>
                                <h3>No course materials yet</h3>
                                <p>Upload your first file to get started!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- No course selected -->
                    <div class="no-course-selected">
                        <i class="fas fa-folder"></i>
                        <h3>Select a Course</h3>
                        <p>Choose a course from the sidebar to manage its content</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // File upload preview - same as add course
        document.getElementById('newFiles')?.addEventListener('change', function(e) {
            const fileList = document.getElementById('file-list');
            fileList.innerHTML = '';
            
            if (this.files.length > 0) {
                fileList.style.display = 'block';
                
                for (let file of this.files) {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';
                    
                    // Choose icon based on file type
                    let icon = 'fa-file';
                    let iconColor = '#6b7280';
                    
                    if (file.type.includes('video')) {
                        icon = 'fa-video';
                        iconColor = '#dc2626';
                    } else if (file.type.includes('pdf')) {
                        icon = 'fa-file-pdf';
                        iconColor = '#dc2626';
                    } else if (file.type.includes('word') || file.type.includes('document')) {
                        icon = 'fa-file-word';
                        iconColor = '#2563eb';
                    }
                    
                    fileItem.innerHTML = `
                        <div class="file-preview">
                            <div class="file-icon-small">
                                <i class="fas ${icon}" style="color: ${iconColor};"></i>
                            </div>
                            <span style="font-size: 14px;">${file.name}</span>
                        </div>
                        <small style="color: #6b7280;">${formatFileSize(file.size)}</small>
                    `;
                    fileList.appendChild(fileItem);
                }
            } else {
                fileList.style.display = 'none';
            }
        });

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>