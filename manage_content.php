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

// Handle course selection
$selected_course = null;
$course_files = [];

if (isset($_GET['course_id'])) {
    $course_id = intval($_GET['course_id']);
    
    // Verify the course belongs to this instructor
    $course_stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
    $course_stmt->bind_param("ii", $course_id, $user['id']);
    $course_stmt->execute();
    $selected_course = $course_stmt->get_result()->fetch_assoc();
    
    if ($selected_course) {
        // Get course files
        $files_stmt = $conn->prepare("SELECT * FROM course_files WHERE course_id = ? ORDER BY uploaded_at DESC");
        $files_stmt->bind_param("i", $course_id);
        $files_stmt->execute();
        $course_files = $files_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Handle file deletion
if (isset($_POST['delete_file'])) {
    $file_id = intval($_POST['file_id']);
    
    // Verify file belongs to instructor's course
    $verify_stmt = $conn->prepare("SELECT cf.* FROM course_files cf 
                                  JOIN courses c ON cf.course_id = c.id 
                                  WHERE cf.id = ? AND c.instructor_id = ?");
    $verify_stmt->bind_param("ii", $file_id, $user['id']);
    $verify_stmt->execute();
    $file_to_delete = $verify_stmt->get_result()->fetch_assoc();
    
    if ($file_to_delete) {
        // Delete file from server
        if (file_exists($file_to_delete['file_path'])) {
            unlink($file_to_delete['file_path']);
        }
        
        // Delete from database
        $delete_stmt = $conn->prepare("DELETE FROM course_files WHERE id = ?");
        $delete_stmt->bind_param("i", $file_id);
        $delete_stmt->execute();
        
        // Refresh page
        header("Location: manage_content.php?course_id=" . $file_to_delete['course_id']);
        exit();
    }
}

// Handle file uploads
if (isset($_POST['upload_files']) && $selected_course) {
    $course_id = $selected_course['id'];
    
    if (!empty($_FILES['new_files']['name'][0])) {
        $upload_dir = "course_content/" . $course_id . "/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $uploaded_count = 0;
        
        foreach ($_FILES['new_files']['name'] as $key => $name) {
            if ($_FILES['new_files']['error'][$key] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['new_files']['tmp_name'][$key];
                $file_size = $_FILES['new_files']['size'][$key];
                $file_type = $_FILES['new_files']['type'][$key];
                
                if ($file_size <= 100 * 1024 * 1024) {
                    $safe_name = time() . '_' . $key . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $name);
                    $file_path = $upload_dir . $safe_name;
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $file_stmt = $conn->prepare("INSERT INTO course_files (course_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                        $file_stmt->bind_param("isssi", $course_id, $name, $file_path, $file_type, $file_size);
                        $file_stmt->execute();
                        $file_stmt->close();
                        $uploaded_count++;
                    }
                }
            }
        }
        
        if ($uploaded_count > 0) {
            $success_message = "Successfully uploaded $uploaded_count new file(s)!";
        }
        
        // Refresh page
        header("Location: manage_content.php?course_id=" . $course_id);
        exit();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Content - StudyHub</title>
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
        }
        
        .course-item:hover {
            border-color: var(--primary-light);
            background: #f8fafc;
        }
        
        .course-item.active {
            border-color: var(--primary);
            background: var(--primary-light);
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
                                <?php echo ucfirst($course['status']); ?> • 
                                <?php echo date('M d, Y', strtotime($course['created_at'])); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Main content area -->
            <div class="main-content">
                <?php if ($selected_course): ?>
                    <!-- Course Header -->
                    <div class="course-header">
                        <h2 class="selected-course-title"><?php echo htmlspecialchars($selected_course['title']); ?></h2>
                        <p class="selected-course-description"><?php echo htmlspecialchars($selected_course['description']); ?></p>
                    </div>

                    <!-- Upload Section -->
                    <div class="content-section">
                        <h3 class="section-title">Upload New Materials</h3>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="upload-area" onclick="document.getElementById('newFiles').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <div class="upload-info">
                                    <p><strong>Click to upload new files</strong></p>
                                    <p>PDFs, Videos, Documents - Max 100MB each</p>
                                    <p>You can select multiple files</p>
                                </div>
                                <input type="file" id="newFiles" name="new_files[]" multiple 
                                      style="display: none;" 
                                       accept=".pdf,.mp4,.mkv,.avi,.mov,.wmv,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt">
                            </div>
                            <button type="submit" name="upload_files" class="file-action-btn btn-view" style="padding: 10px 20px;">
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
                                            if (in_array($file_ext, ['mp4', 'avi', 'mov', 'mkv'])) {
                                                $icon = 'fa-video';
                                            } elseif ($file_ext === 'pdf') {
                                                $icon = 'fa-file-pdf';
                                            } elseif (in_array($file_ext, ['doc', 'docx'])) {
                                                $icon = 'fa-file-word';
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
        // File upload preview
        document.getElementById('newFiles')?.addEventListener('change', function(e) {
            if (this.files.length > 0) {
                const uploadArea = document.querySelector('.upload-area');
                uploadArea.innerHTML = `
                    <i class="fas fa-check-circle" style="color: #10b981;"></i>
                    <div class="upload-info">
                        <p><strong>${this.files.length} file(s) selected</strong></p>
                        <p>Click "Upload Selected Files" to add them to your course</p>
                    </div>
                `;
            }
        });
    </script>

    <?php 
    // Function to format file size
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