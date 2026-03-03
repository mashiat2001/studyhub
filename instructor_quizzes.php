<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'instructor') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

// Get instructor's approved courses
$courses = [];
$stmt = $conn->prepare("SELECT id, title FROM courses WHERE instructor_id = ? AND status = 'approved' ORDER BY title");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$error = '';
$success = '';

// Handle quiz creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quiz'])) {
    $course_id = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $time_limit = intval($_POST['time_limit']);
    
    if (empty($title) || empty($course_id)) {
        $error = "Please fill in all required fields.";
    } else {
        // Insert quiz
        $stmt = $conn->prepare("INSERT INTO quizzes (course_id, title, description, instructor_id, time_limit) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issii", $course_id, $title, $description, $user['id'], $time_limit);
        
        if ($stmt->execute()) {
            $quiz_id = $stmt->insert_id;
            
            // --- NOTIFY ENROLLED STUDENTS ---
            // Get course title for message
            $course_title = '';
            foreach ($courses as $c) {
                if ($c['id'] == $course_id) {
                    $course_title = $c['title'];
                    break;
                }
            }
            
            // Fetch all enrolled students for this course
            $enroll_stmt = $conn->prepare("SELECT user_id FROM user_courses WHERE course_id = ?");
            $enroll_stmt->bind_param("i", $course_id);
            $enroll_stmt->execute();
            $enrolled = $enroll_stmt->get_result();
            
            // Prepare notification message
            $message = "A new quiz '{$title}' has been added to your course '{$course_title}'.";
            $link = "take_quiz.php?quiz_id={$quiz_id}"; // adjust to your quiz taking page
            
            // Insert notification for each student
            $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, role, message, link) VALUES (?, 'student', ?, ?)");
            $notify_stmt->bind_param("iss", $student_id, $message, $link);
            
            while ($row = $enrolled->fetch_assoc()) {
                $student_id = $row['user_id'];
                $notify_stmt->execute();
            }
            
            $notify_stmt->close();
            $enroll_stmt->close();
            
            $success = "Quiz created successfully! Students have been notified.";
            // Redirect to manage questions page
            header("Location: manage_quiz_questions.php?quiz_id=$quiz_id");
            exit();
        } else {
            $error = "Error creating quiz: " . $conn->error;
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
    <title>Create Quiz - StudyHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #7E6CCA;
            --primary-dark: #6351A6;
            --text-dark: #2D3748;
            --text-light: #718096;
            --light-bg: #F7FAFC;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--light-bg);
            color: var(--text-dark);
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: var(--primary);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 16px;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
        }
        
        .btn:hover {
            background: var(--primary-dark);
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .back-link {
            color: var(--primary);
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="instructor_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <h1><i class="fas fa-question-circle"></i> Create New Quiz</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="course_id">Select Course *</label>
                <select id="course_id" name="course_id" required>
                    <option value="">Choose a course</option>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?php echo $course['id']; ?>">
                            <?php echo htmlspecialchars($course['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="title">Quiz Title *</label>
                <input type="text" id="title" name="title" placeholder="e.g., Midterm Exam, Chapter 1 Quiz" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" placeholder="Optional: Describe what this quiz covers"></textarea>
            </div>
            
            <div class="form-group">
                <label for="time_limit">Time Limit (minutes) *</label>
                <input type="number" id="time_limit" name="time_limit" value="30" min="5" max="180" required>
                <small>Set time limit in minutes (5-180 minutes)</small>
            </div>
            
            <button type="submit" name="create_quiz" class="btn">
                <i class="fas fa-plus"></i> Create Quiz
            </button>
        </form>
    </div>
</body>
</html>