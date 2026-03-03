<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'instructor') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

if (!isset($_GET['quiz_id'])) {
    header('Location: instructor_quizzes.php');
    exit();
}

$quiz_id = intval($_GET['quiz_id']);

// Verify quiz belongs to instructor
$quiz_stmt = $conn->prepare("SELECT q.*, c.title as course_title 
                            FROM quizzes q 
                            JOIN courses c ON q.course_id = c.id 
                            WHERE q.id = ? AND q.instructor_id = ?");
$quiz_stmt->bind_param("ii", $quiz_id, $user['id']);
$quiz_stmt->execute();
$quiz = $quiz_stmt->get_result()->fetch_assoc();

if (!$quiz) {
    header('Location: instructor_quizzes.php');
    exit();
}

// Get existing questions
$questions = [];
$stmt = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$error = '';
$success = '';

// Handle question addition
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_question'])) {
        $question_text = trim($_POST['question_text']);
        $option_a = trim($_POST['option_a']);
        $option_b = trim($_POST['option_b']);
        $option_c = trim($_POST['option_c']);
        $option_d = trim($_POST['option_d']);
        $correct_answer = $_POST['correct_answer'];
        $marks = intval($_POST['marks']);
        
        if (empty($question_text) || empty($option_a) || empty($option_b) || empty($correct_answer)) {
            $error = "Please fill in all required fields.";
        } else {
            $stmt = $conn->prepare("INSERT INTO quiz_questions 
                (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer, marks) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssi", $quiz_id, $question_text, $option_a, $option_b, 
                            $option_c, $option_d, $correct_answer, $marks);
            
            if ($stmt->execute()) {
                $success = "Question added successfully!";
                // Update total questions and marks in quizzes table
                $update_stmt = $conn->prepare("UPDATE quizzes SET 
                    total_questions = total_questions + 1,
                    total_marks = total_marks + ? 
                    WHERE id = ?");
                $update_stmt->bind_param("ii", $marks, $quiz_id);
                $update_stmt->execute();
                
                // Refresh page
                header("Location: manage_quiz_questions.php?quiz_id=$quiz_id");
                exit();
            } else {
                $error = "Error adding question: " . $conn->error;
            }
        }
    }
    
    // Handle question deletion
    if (isset($_POST['delete_question'])) {
        $question_id = intval($_POST['question_id']);
        
        // Get question marks before deleting
        $marks_stmt = $conn->prepare("SELECT marks FROM quiz_questions WHERE id = ?");
        $marks_stmt->bind_param("i", $question_id);
        $marks_stmt->execute();
        $marks_result = $marks_stmt->get_result();
        $question_marks = $marks_result->fetch_assoc()['marks'] ?? 0;
        
        // Delete question
        $delete_stmt = $conn->prepare("DELETE FROM quiz_questions WHERE id = ?");
        $delete_stmt->bind_param("i", $question_id);
        
        if ($delete_stmt->execute()) {
            // Update quiz totals
            $update_stmt = $conn->prepare("UPDATE quizzes SET 
                total_questions = total_questions - 1,
                total_marks = total_marks - ? 
                WHERE id = ?");
            $update_stmt->bind_param("ii", $question_marks, $quiz_id);
            $update_stmt->execute();
            
            $success = "Question deleted successfully!";
            header("Location: manage_quiz_questions.php?quiz_id=$quiz_id");
            exit();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quiz Questions - StudyHub</title>
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
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .quiz-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        h1, h2 {
            color: var(--primary);
        }
        
        .quiz-meta {
            color: var(--text-light);
            margin: 10px 0;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e5e7eb;
            border-radius: 6px;
            font-size: 16px;
        }
        
        textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 15px 0;
        }
        
        .btn {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn:hover {
            background: var(--primary-dark);
        }
        
        .btn-delete {
            background: #dc2626;
        }
        
        .btn-delete:hover {
            background: #b91c1c;
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
        
        .questions-list {
            margin-top: 30px;
        }
        
        .question-item {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            position: relative;
        }
        
        .question-text {
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .options-list {
            margin-left: 20px;
        }
        
        .option {
            margin-bottom: 5px;
        }
        
        .correct-option {
            color: #10b981;
            font-weight: 600;
        }
        
        .question-meta {
            color: var(--text-light);
            font-size: 14px;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
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
        <a href="instructor_quizzes.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Quizzes
        </a>
        
        <div class="quiz-header">
            <h1><?php echo htmlspecialchars($quiz['title']); ?></h1>
            <div class="quiz-meta">
                <strong>Course:</strong> <?php echo htmlspecialchars($quiz['course_title']); ?> |
                <strong>Time Limit:</strong> <?php echo $quiz['time_limit']; ?> minutes |
                <strong>Questions:</strong> <?php echo $quiz['total_questions']; ?> |
                <strong>Total Marks:</strong> <?php echo $quiz['total_marks']; ?>
            </div>
        </div>
        
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
        
        <!-- Add Question Form -->
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Add New Question</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="question_text">Question Text *</label>
                    <textarea id="question_text" name="question_text" required></textarea>
                </div>
                
                <div class="options-grid">
                    <div class="form-group">
                        <label for="option_a">Option A *</label>
                        <input type="text" id="option_a" name="option_a" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="option_b">Option B *</label>
                        <input type="text" id="option_b" name="option_b" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="option_c">Option C (Optional)</label>
                        <input type="text" id="option_c" name="option_c">
                    </div>
                    
                    <div class="form-group">
                        <label for="option_d">Option D (Optional)</label>
                        <input type="text" id="option_d" name="option_d">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="correct_answer">Correct Answer *</label>
                    <select id="correct_answer" name="correct_answer" required>
                        <option value="">Select correct option</option>
                        <option value="a">Option A</option>
                        <option value="b">Option B</option>
                        <option value="c">Option C</option>
                        <option value="d">Option D</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="marks">Marks *</label>
                    <input type="number" id="marks" name="marks" value="1" min="1" max="10" required>
                </div>
                
                <button type="submit" name="add_question" class="btn">
                    <i class="fas fa-save"></i> Add Question
                </button>
            </form>
        </div>
        
        <!-- Existing Questions -->
        <div class="questions-list">
            <h2><i class="fas fa-list"></i> Existing Questions (<?php echo count($questions); ?>)</h2>
            
            <?php if (empty($questions)): ?>
                <p>No questions added yet. Add your first question above.</p>
            <?php else: ?>
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-item">
                        <div class="question-text">
                            <?php echo ($index + 1) . '. ' . htmlspecialchars($question['question_text']); ?>
                        </div>
                        
                        <div class="options-list">
                            <div class="option <?php echo $question['correct_answer'] === 'a' ? 'correct-option' : ''; ?>">
                                A. <?php echo htmlspecialchars($question['option_a']); ?>
                            </div>
                            <div class="option <?php echo $question['correct_answer'] === 'b' ? 'correct-option' : ''; ?>">
                                B. <?php echo htmlspecialchars($question['option_b']); ?>
                            </div>
                            <?php if ($question['option_c']): ?>
                                <div class="option <?php echo $question['correct_answer'] === 'c' ? 'correct-option' : ''; ?>">
                                    C. <?php echo htmlspecialchars($question['option_c']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($question['option_d']): ?>
                                <div class="option <?php echo $question['correct_answer'] === 'd' ? 'correct-option' : ''; ?>">
                                    D. <?php echo htmlspecialchars($question['option_d']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="question-meta">
                            <span>Marks: <?php echo $question['marks']; ?></span>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                <button type="submit" name="delete_question" class="btn btn-delete" 
                                        onclick="return confirm('Delete this question?')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>