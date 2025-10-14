<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

// Get all courses
$courses = [];
$stmt = $conn->prepare("SELECT * FROM courses ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Courses - StudyHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Add your existing CSS styles here */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .course-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
        }
        
        .btn-view {
            background: #7E6CCA;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <header class="header">
        <!-- Your existing header code -->
    </header>

    <div class="container">
        <h1>All Courses</h1>
        <div class="courses-grid">
            <?php foreach ($courses as $course): ?>
                <div class="course-card">
                    <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                    <p>Instructor: <?php echo htmlspecialchars($course['instructor']); ?></p>
                    <p>Status: <?php echo ucfirst($course['status']); ?></p>
                    <a href="course_content.php?id=<?php echo $course['id']; ?>" class="btn-view">
                        View Course Content
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>