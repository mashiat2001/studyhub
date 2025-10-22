<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'instructor') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $level = trim($_POST['level']);
    $course_type = trim($_POST['course_type']); // free or premium
    $price = ($_POST['course_type'] === 'premium') ? floatval($_POST['price']) : 0.00;
    
    if (empty($title) || empty($description) || empty($category) || empty($level)) {
        $error = "Please fill in all required fields.";
    } else {
        // Insert course
        $stmt = $conn->prepare("INSERT INTO courses (title, description, instructor, instructor_id, category, level, price, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("ssisssd", $title, $description, $user['name'], $user['id'], $category, $level, $price);
        
        if ($stmt->execute()) {
            $course_id = $stmt->insert_id;
            $success = "Course created successfully! Waiting for admin approval.";
            
            // SIMPLE FILE UPLOAD HANDLING
            if ($course_id && !empty($_FILES['course_files'])) {
                $upload_dir = "course_content/" . $course_id . "/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $uploaded_count = 0;
                
                // Loop through each file
                foreach ($_FILES['course_files']['name'] as $key => $name) {
                    // Skip if file is empty
                    if (empty($name)) {
                        continue;
                    }
                    
                    $file_tmp = $_FILES['course_files']['tmp_name'][$key];
                    $file_size = $_FILES['course_files']['size'][$key];
                    $file_type = $_FILES['course_files']['type'][$key];
                    $file_error = $_FILES['course_files']['error'][$key];
                    
                    // Check for upload errors
                    if ($file_error === UPLOAD_ERR_OK) {
                        // Check file size (100MB limit)
                        if ($file_size <= 100 * 1024 * 1024) {
                            // Create safe filename
                            $safe_name = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $name);
                            $file_path = $upload_dir . $safe_name;
                            
                            // Move the file
                            if (move_uploaded_file($file_tmp, $file_path)) {
                                // Save to database
                                $file_stmt = $conn->prepare("INSERT INTO course_files (course_id, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                                $file_stmt->bind_param("isssi", $course_id, $name, $file_path, $file_type, $file_size);
                                
                                if ($file_stmt->execute()) {
                                    $uploaded_count++;
                                }
                                $file_stmt->close();
                            }
                        }
                    }
                }
                
                if ($uploaded_count > 0) {
                    $success .= " Successfully uploaded $uploaded_count file(s).";
                } else {
                    $success .= " No files were uploaded.";
                }
            }
        } else {
            $error = "Error creating course: " . $conn->error;
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Course - StudyHub</title>
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
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .form-card {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .form-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-dark);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .input-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .input-field {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: var(--border-radius);
            font-size: 15px;
            transition: var(--transition);
        }
        
        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(126, 108, 202, 0.1);
        }
        
        textarea.input-field {
            min-height: 120px;
            resize: vertical;
        }
        
        .file-upload {
            border: 2px dashed #e5e7eb;
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .file-upload:hover {
            border-color: var(--primary);
            background: #f8fafc;
        }
        
        .file-upload i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .file-info {
            font-size: 14px;
            color: var(--text-light);
            margin-top: 10px;
        }
        
        .submit-btn {
            background: var(--primary);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
        }
        
        .submit-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
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
        
        .file-icon {
            width: 24px;
            text-align: center;
        }
        
        .debug-info {
            background: #f3f4f6;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            margin-top: 10px;
            display: none;
        }

        .course-type-option {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .type-option {
            flex: 1;
            text-align: center;
            padding: 15px;
            border: 2px solid #e5e7eb;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .type-option:hover {
            border-color: var(--primary-light);
        }

        .type-option.selected {
            border-color: var(--primary);
            background: #f8fafc;
        }

        .type-option i {
            font-size: 1.5rem;
            margin-bottom: 8px;
            display: block;
        }

        .type-option.free i {
            color: #10b981;
        }

        .type-option.premium i {
            color: #f59e0b;
        }

        .price-input {
            display: none;
        }

        .price-input.show {
            display: block;
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
        <div class="form-card">
            <h1 class="form-title">Create New Course</h1>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <!-- Debug info (remove in production) -->
            <?php if ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
                <div class="debug-info">
                    <strong>Debug Info:</strong><br>
                    Files received: <?php echo isset($_FILES['course_files']) ? count($_FILES['course_files']['name']) : 0; ?><br>
                    File names: <?php echo isset($_FILES['course_files']['name']) ? implode(', ', $_FILES['course_files']['name']) : 'None'; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" id="courseForm">
                <div class="form-group">
                    <label for="title" class="input-label">Course Title *</label>
                    <input type="text" id="title" name="title" class="input-field" placeholder="Enter course title" required>
                </div>
                
                <div class="form-group">
                    <label for="description" class="input-label">Course Description *</label>
                    <textarea id="description" name="description" class="input-field" placeholder="Describe your course..." required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="category" class="input-label">Category *</label>
                    <select id="category" name="category" class="input-field" required>
                        <option value="">Select Category</option>
                        <option value="SSC">SSC</option>
                        <option value="HSC">HSC</option>
                        <option value="Programming">Programming</option>
                        <option value="Mathematics">Mathematics</option>
                        <option value="Science">Science</option>
                        <option value="Business">Business</option>
                        <option value="Arts">Arts</option>
                        <option value="Others">Others</option>
                        
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="level" class="input-label">Difficulty Level *</label>
                    <select id="level" name="level" class="input-field" required>
                        <option value="">Select Level</option>
                        <option value="Beginner">Beginner</option>
                        <option value="Intermediate">Intermediate</option>
                        <option value="Advanced">Advanced</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="input-label">Course Type *</label>
                    <div class="course-type-option">
                        <div class="type-option free" onclick="selectCourseType('free')">
                            <i class="fas fa-gift"></i>
                            <strong>Free Course</strong>
                            <p>Available to all students</p>
                        </div>
                        <div class="type-option premium" onclick="selectCourseType('premium')">
                            <i class="fas fa-crown"></i>
                            <strong>Premium Course</strong>
                            <p>Paid course with premium content</p>
                        </div>
                    </div>
                    <input type="hidden" id="course_type" name="course_type" value="free" required>
                </div>
                
                <div class="form-group price-input" id="priceInput">
                    <label for="price" class="input-label">Price (৳) *</label>
                    <input type="number" id="price" name="price" class="input-field" placeholder="e.g., 499" min="1" step="1">
                </div>
                
                <div class="form-group">
                    <label class="input-label">Course Materials</label>
                    <div class="file-upload" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <div class="file-info">
                            <p><strong>Click to upload files</strong></p>
                            <p>PDFs, Videos, Documents - Max 100MB each</p>
                            <p>You can select multiple files (Hold Ctrl/Cmd to select multiple)</p>
                        </div>
                        <input type="file" id="fileInput" name="course_files[]" multiple 
                          style="display: none;" 
                           accept=".pdf,.mp4,.mkv,.avi,.mov,.wmv,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt">
                    </div>
                    <div id="file-list"></div>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-plus"></i> Create Course
                </button>
            </form>
        </div>
    </div>

    <script>
        // Course type selection
        function selectCourseType(type) {
            // Remove selected class from all options
            document.querySelectorAll('.type-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            document.querySelector(`.type-option.${type}`).classList.add('selected');
            
            // Update hidden input
            document.getElementById('course_type').value = type;
            
            // Show/hide price input
            const priceInput = document.getElementById('priceInput');
            if (type === 'premium') {
                priceInput.classList.add('show');
                document.getElementById('price').required = true;
            } else {
                priceInput.classList.remove('show');
                document.getElementById('price').required = false;
                document.getElementById('price').value = '';
            }
        }

        // Initialize with free course selected
        document.addEventListener('DOMContentLoaded', function() {
            selectCourseType('free');
        });

        // Simple file preview
        document.getElementById('fileInput').addEventListener('change', function(e) {
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
                            <div class="file-icon">
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