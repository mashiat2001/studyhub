<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit();
}

$course_id = intval($_GET['id']);
$file_id = isset($_GET['file_id']) ? intval($_GET['file_id']) : null;

$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    die("Course not found");
}

// Check access
$can_access = false;
if ($user['role'] === 'admin') {
    $can_access = true;
} elseif ($user['role'] === 'instructor' && $course['instructor_id'] == $user['id']) {
    $can_access = true;
} elseif ($user['role'] === 'student') {
    $enroll_stmt = $conn->prepare("SELECT id FROM user_courses WHERE user_id = ? AND course_id = ?");
    $enroll_stmt->bind_param("ii", $user['id'], $course_id);
    $enroll_stmt->execute();
    $can_access = $enroll_stmt->get_result()->num_rows > 0;
}

if (!$can_access) {
    die("You don't have access to this course");
}

// Get course files
$files_stmt = $conn->prepare("SELECT * FROM course_files WHERE course_id = ?");
$files_stmt->bind_param("i", $course_id);
$files_stmt->execute();
$files = $files_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get specific file if file_id is provided
$current_file = null;
if ($file_id) {
    $file_stmt = $conn->prepare("SELECT * FROM course_files WHERE id = ? AND course_id = ?");
    $file_stmt->bind_param("ii", $file_id, $course_id);
    $file_stmt->execute();
    $current_file = $file_stmt->get_result()->fetch_assoc();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - StudyHub</title>
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
            display: flex;
            gap: 30px;
        }
        
        .sidebar {
            flex: 0 0 300px;
        }
        
        .main-content {
            flex: 1;
        }
        
        .course-header {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .course-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--text-dark);
        }
        
        .course-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-light);
            font-size: 14px;
        }
        
        .course-description {
            line-height: 1.6;
            color: var(--text-dark);
        }
        
        .content-section {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-dark);
        }
        
        .files-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 2px solid #e5e7eb;
            border-radius: var(--border-radius);
            transition: var(--transition);
            cursor: pointer;
        }
        
        .file-item:hover, .file-item.active {
            border-color: var(--primary);
            background: #f8fafc;
        }
        
        .file-item.active {
            background: var(--primary-light);
            color: white;
        }
        
        .file-item.active .file-info h4,
        .file-item.active .file-info p {
            color: white;
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 15px;
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
        
        .file-item.active .file-icon {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .file-details h4 {
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .file-details p {
            color: var(--text-light);
            font-size: 14px;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
        }
        
        .file-action-btn {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }
        
        .btn-view {
            background: var(--primary);
            color: white;
        }
        
        .btn-download {
            background: #10b981;
            color: white;
        }
        
        .file-viewer {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            min-height: 500px;
        }
        
        .viewer-placeholder {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }
        
        .viewer-placeholder i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #cbd5e0;
        }
        
        .video-player {
            width: 100%;
            max-width: 800px;
            height: 450px;
            border-radius: var(--border-radius);
            background: #000;
        }
        
        .pdf-viewer {
            width: 100%;
            height: 600px;
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .document-viewer {
            padding: 30px;
            background: #f8fafc;
            border-radius: var(--border-radius);
            min-height: 400px;
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
        
        .course-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
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
        
        <?php 
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $is_from_approve_page = strpos($referer, 'admin_approve_courses.php') !== false;
        ?>
        
        <?php if ($user['role'] === 'admin' && $is_from_approve_page): ?>
            <a href="admin_approve_courses.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Course Approvals
            </a>
        <?php else: ?>
            <a href="<?php echo $user['role']; ?>_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        <?php endif; ?>
    </header>

    <div class="container">
        <!-- Sidebar with files list -->
        <div class="sidebar">
            <div class="content-section">
                <h2 class="section-title">Course Materials</h2>
                
                <?php if (count($files) > 0): ?>
                    <div class="files-list">
                        <?php foreach ($files as $file): 
                            $is_active = $current_file && $current_file['id'] == $file['id'];
                            $file_extension = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                            
                            $icon = 'fa-file';
                            if (in_array($file_extension, ['mp4', 'avi', 'mov', 'mkv', 'wmv', 'flv', 'webm'])) {
                                $icon = 'fa-video';
                            } elseif ($file_extension === 'pdf') {
                                $icon = 'fa-file-pdf';
                            } elseif (in_array($file_extension, ['doc', 'docx'])) {
                                $icon = 'fa-file-word';
                            } elseif (in_array($file_extension, ['ppt', 'pptx'])) {
                                $icon = 'fa-file-powerpoint';
                            } elseif (in_array($file_extension, ['xls', 'xlsx'])) {
                                $icon = 'fa-file-excel';
                            }
                        ?>
                            <div class="file-item <?php echo $is_active ? 'active' : ''; ?>" 
                                 onclick="window.location.href='course_content.php?id=<?php echo $course_id; ?>&file_id=<?php echo $file['id']; ?>'">
                                <div class="file-info">
                                    <div class="file-icon">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="file-details">
                                        <h4><?php echo htmlspecialchars($file['file_name']); ?></h4>
                                        <p><?php echo formatFileSize($file['file_size']); ?> • <?php echo strtoupper($file_extension); ?></p>
                                    </div>
                                </div>
                                <div class="file-actions">
                                    <a href="<?php echo $file['file_path']; ?>" class="file-action-btn btn-download" download>
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <h3>No materials</h3>
                        <p>No files uploaded yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main content area -->
        <div class="main-content">
            <div class="course-header">
                <h1 class="course-title">
                    <?php echo htmlspecialchars($course['title']); ?>
                    <span class="course-status status-<?php echo $course['status']; ?>">
                        <?php echo ucfirst($course['status']); ?>
                    </span>
                </h1>
                
                <div class="course-meta">
                    <div class="meta-item">
                        <i class="fas fa-user"></i>
                        <span>Instructor: <?php echo htmlspecialchars($course['instructor']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-clock"></i>
                        <span>Duration: <?php echo $course['duration'] ? $course['duration'] . ' hours' : 'Self-paced'; ?></span>
                    </div>
                    <?php if ($course['price'] && $course['price'] > 0): ?>
                    <div class="meta-item">
                        <i class="fas fa-dollar-sign"></i>
                        <span>Price: $<?php echo number_format($course['price'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="meta-item">
                        <i class="fas fa-tag"></i>
                        <span>Category: <?php echo htmlspecialchars($course['category']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Level: <?php echo htmlspecialchars($course['level']); ?></span>
                    </div>
                </div>
                
                <div class="course-description">
                    <?php echo nl2br(htmlspecialchars($course['description'])); ?>
                </div>
            </div>

            <!-- File Viewer -->
            <div class="file-viewer">
                <?php if ($current_file): 
                    $file_extension = strtolower(pathinfo($current_file['file_name'], PATHINFO_EXTENSION));
                ?>
                    <h3><?php echo htmlspecialchars($current_file['file_name']); ?></h3>
                    <p style="color: var(--text-light); margin-bottom: 20px;">
                        <?php echo formatFileSize($current_file['file_size']); ?> • 
                        Uploaded: <?php echo date('M j, Y', strtotime($current_file['uploaded_at'])); ?>
                    </p>
                    
                    <?php if (in_array($file_extension, ['mp4', 'avi', 'mov', 'mkv', 'wmv', 'flv', 'webm'])): ?>
                        <!-- Video Player -->
                        <video controls class="video-player">
                            <source src="<?php echo htmlspecialchars($current_file['file_path']); ?>" type="video/<?php echo $file_extension; ?>">
                            Your browser does not support the video tag.
                        </video>
                        
                    <?php elseif ($file_extension === 'pdf'): ?>
                        <!-- PDF Viewer -->
                        <embed src="<?php echo htmlspecialchars($current_file['file_path']); ?>" type="application/pdf" class="pdf-viewer">
                        
                    <?php else: ?>
                        <!-- Document Viewer (Download option) -->
                        <div class="document-viewer">
                            <div style="text-align: center; padding: 60px 20px;">
                                <i class="fas fa-file" style="font-size: 4rem; color: var(--primary); margin-bottom: 20px;"></i>
                                <h3><?php echo htmlspecialchars($current_file['file_name']); ?></h3>
                                <p style="margin-bottom: 20px;">This file type can be downloaded and viewed on your device.</p>
                                <a href="<?php echo $current_file['file_path']; ?>" class="file-action-btn btn-download" download style="text-decoration: none;">
                                    <i class="fas fa-download"></i> Download File
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- Default placeholder when no file is selected -->
                    <div class="viewer-placeholder">
                        <i class="fas fa-play-circle"></i>
                        <h3>Select a file to view</h3>
                        <p>Choose a file from the course materials list to start learning</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

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