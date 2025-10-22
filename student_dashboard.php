<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

// Get enrolled courses
$enrolled_courses = [];
$enrolled_stmt = $conn->prepare("
    SELECT c.*, uc.progress, uc.status 
    FROM user_courses uc 
    JOIN courses c ON uc.course_id = c.id 
    WHERE uc.user_id = ? AND c.status = 'approved'
");
$enrolled_stmt->bind_param("i", $user['id']);
$enrolled_stmt->execute();
$enrolled_result = $enrolled_stmt->get_result();
while ($row = $enrolled_result->fetch_assoc()) {
    $enrolled_courses[] = $row;
}

// Get available courses
$available_courses = [];
$available_stmt = $conn->prepare("
    SELECT * FROM courses 
    WHERE status = 'approved' 
    AND id NOT IN (SELECT course_id FROM user_courses WHERE user_id = ?)
    ORDER BY created_at DESC
");
$available_stmt->bind_param("i", $user['id']);
$available_stmt->execute();
$available_result = $available_stmt->get_result();
while ($row = $available_result->fetch_assoc()) {
    $available_courses[] = $row;
}

$conn->close();

// Features that don't need database
$motivationalQuotes = [
    "The beautiful thing about learning is that no one can take it away from you. - B.B. King",
    "Education is the most powerful weapon which you can use to change the world. - Nelson Mandela",
    "The capacity to learn is a gift; the ability to learn is a skill; the willingness to learn is a choice. - Brian Herbert",
    "Learning never exhausts the mind. - Leonardo da Vinci",
    "The more that you read, the more things you will know. The more that you learn, the more places you'll go. - Dr. Seuss"
];

$randomQuote = $motivationalQuotes[array_rand($motivationalQuotes)];

// Course recommendations based on enrolled courses
$recommendedSkills = [
    "Web Development", "Data Science", "Digital Marketing", 
    "Graphic Design", "Mobile Development", "Cloud Computing",
    "Cybersecurity", "Artificial Intelligence", "Business Analytics"
];

// Study tips
$studyTips = [
    "Set specific goals for each study session",
    "Take regular breaks to maintain focus",
    "Teach what you've learned to someone else",
    "Use the Pomodoro technique (25 min focus, 5 min break)",
    "Create a dedicated study space free from distractions"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - StudyHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #7E6CCA;
            --primary-light: #9F90DB;
            --primary-dark: #6351A6;
            --secondary: #FF9E6D;
            --accent: #6DC9FF;
            --success: #48BB78;
            --warning: #ED8936;
            --info: #4299E1;
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
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .logout-btn:hover {
            background: var(--primary-dark);
        }
        
        .dashboard-container {
            max-width: 1200px;
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
        }
        
        .welcome-title {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .welcome-subtitle {
            opacity: 0.9;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .feature-card {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .feature-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .feature-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        .feature-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .quote-card {
            background: linear-gradient(135deg, var(--info) 0%, var(--primary) 100%);
            color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .quote-icon {
            font-size: 2rem;
            margin-bottom: 15px;
            opacity: 0.8;
        }
        
        .quote-text {
            font-size: 1.1rem;
            font-style: italic;
            margin-bottom: 10px;
            line-height: 1.6;
        }
        
        .quote-author {
            text-align: right;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .skills-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .skill-tag {
            background: var(--light-bg);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            color: var(--text-dark);
            border: 1px solid #e2e8f0;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .skill-tag:hover {
            background: var(--primary-light);
            color: white;
        }
        
        .tips-list {
            list-style: none;
        }
        
        .tips-list li {
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tips-list li:last-child {
            border-bottom: none;
        }
        
        .tip-icon {
            color: var(--success);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-enrolled .stat-value { color: var(--primary); }
        .stat-completed .stat-value { color: var(--success); }
        .stat-progress .stat-value { color: var(--warning); }
        
        .stat-label {
            color: var(--text-light);
            font-size: 14px;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 30px 0 20px 0;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .course-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }
        
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
        }
        
        .course-header {
            background: var(--primary-light);
            color: white;
            padding: 15px;
        }
        
        .course-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .course-instructor {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .course-body {
            padding: 15px;
        }
        
        .course-description {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .progress-container {
            margin-bottom: 15px;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 4px;
        }
        
        .progress-text {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 5px;
        }
        
        .course-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-light);
            margin-bottom: 15px;
        }
        
        .course-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 15px;
            text-align: center;
        }
        
        .price-free {
            color: var(--success);
        }
        
        .course-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: var(--transition);
            flex: 1;
            text-align: center;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #38a169;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-light);
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #cbd5e0;
        }
        
        .payment-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            max-width: 400px;
            width: 90%;
        }
        
        .modal-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--text-dark);
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
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
                <div class="user-role">Student</div>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <div class="dashboard-container">
        <div class="welcome-section">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($user['name']); ?>! 👋</h1>
            <p class="welcome-subtitle">Continue your learning journey and track your progress</p>
        </div>

        <!-- Motivational Quote -->
        <div class="quote-card">
            <div class="quote-icon">
                <i class="fas fa-quote-left"></i>
            </div>
            <p class="quote-text"><?php echo $randomQuote; ?></p>
            <div class="quote-author">- Daily Inspiration</div>
        </div>

        <!-- Features Grid -->
        <div class="features-grid">
            <!-- Skill Recommendations -->
            <div class="feature-card">
                <div class="feature-header">
                    <div class="feature-icon">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <h3 class="feature-title">Recommended Skills</h3>
                </div>
                <p>Explore these trending skills to boost your career:</p>
                <div class="skills-grid">
                    <?php foreach(array_slice($recommendedSkills, 0, 6) as $skill): ?>
                        <span class="skill-tag"><?php echo $skill; ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Study Tips -->
            <div class="feature-card">
                <div class="feature-header">
                    <div class="feature-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3 class="feature-title">Study Tips</h3>
                </div>
                <ul class="tips-list">
                    <?php foreach($studyTips as $tip): ?>
                        <li>
                            <i class="fas fa-check-circle tip-icon"></i>
                            <?php echo $tip; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Quick Resources -->
            <div class="feature-card">
                <div class="feature-header">
                    <div class="feature-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3 class="feature-title">Learning Resources</h3>
                </div>
                <p>Quick access to helpful tools:</p>
                <div style="margin-top: 15px; display: flex; flex-direction: column; gap: 10px;">
                    <button class="btn btn-primary" onclick="showComingSoon()">
                        <i class="fas fa-download"></i> Download Study Planner
                    </button>
                    <button class="btn btn-secondary" onclick="showComingSoon()">
                        <i class="fas fa-video"></i> Video Tutorials
                    </button>
                    <button class="btn btn-secondary" onclick="showComingSoon()">
                        <i class="fas fa-file-pdf"></i> Study Guides
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card stat-enrolled">
                <div class="stat-value"><?php echo count($enrolled_courses); ?></div>
                <div class="stat-label">Enrolled Courses</div>
            </div>
            <div class="stat-card stat-completed">
                <div class="stat-value">
                    <?php 
                    $completed = array_filter($enrolled_courses, function($course) {
                        return $course['progress'] >= 100;
                    });
                    echo count($completed);
                    ?>
                </div>
                <div class="stat-label">Completed Courses</div>
            </div>
            <div class="stat-card stat-progress">
                <div class="stat-value">
                    <?php
                    $totalProgress = 0;
                    foreach ($enrolled_courses as $course) {
                        $totalProgress += $course['progress'];
                    }
                    echo count($enrolled_courses) > 0 ? round($totalProgress / count($enrolled_courses)) : 0;
                    ?>%
                </div>
                <div class="stat-label">Average Progress</div>
            </div>
        </div>

        <!-- My Courses Section -->
        <h2 class="section-title">
            <i class="fas fa-book-open"></i>
            My Courses
        </h2>
        <?php if (count($enrolled_courses) > 0): ?>
            <div class="courses-grid">
                <?php foreach ($enrolled_courses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                            <div class="course-instructor">By <?php echo htmlspecialchars($course['instructor']); ?></div>
                        </div>
                        <div class="course-body">
                            <p class="course-description"><?php echo htmlspecialchars($course['description'] ?? 'No description available'); ?></p>
                            
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $course['progress']; ?>%"></div>
                                </div>
                                <div class="progress-text">Progress: <?php echo $course['progress']; ?>%</div>
                            </div>
                            
                            <div class="course-meta">
                                <span><i class="fas fa-clock"></i> <?php echo $course['duration'] ?? 'N/A'; ?> hours</span>
                                <span><i class="fas fa-signal"></i> <?php echo htmlspecialchars($course['level'] ?? 'All Levels'); ?></span>
                            </div>
                            
                            <div class="course-actions">
                                <a href="course_content.php?id=<?php echo $course['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-play"></i> Continue
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h3>No courses enrolled yet</h3>
                <p>Browse available courses and start your learning journey!</p>
            </div>
        <?php endif; ?>

        <!-- Available Courses Section -->
        <?php if (count($available_courses) > 0): ?>
            <h2 class="section-title">
                <i class="fas fa-compass"></i>
                Available Courses
            </h2>
            <div class="courses-grid">
                <?php foreach ($available_courses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3 class="course-title"><?php echo htmlspecialchars($course['title']); ?></h3>
                            <div class="course-instructor">By <?php echo htmlspecialchars($course['instructor']); ?></div>
                        </div>
                        <div class="course-body">
                            <p class="course-description"><?php echo htmlspecialchars($course['description'] ?? 'No description available'); ?></p>
                            
                            <div class="course-price <?php echo ($course['price'] == 0) ? 'price-free' : ''; ?>">
                                <?php echo ($course['price'] == 0) ? 'FREE' : '$' . number_format($course['price'], 2); ?>
                            </div>
                            
                            <div class="course-meta">
                                <span><i class="fas fa-clock"></i> <?php echo $course['duration'] ?? 'N/A'; ?> hours</span>
                                <span><i class="fas fa-signal"></i> <?php echo htmlspecialchars($course['level'] ?? 'All Levels'); ?></span>
                            </div>
                            
                            <div class="course-actions">
                                <?php if ($course['price'] == 0): ?>
                                    <form method="POST" action="enroll_course.php" style="flex: 1;">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-plus"></i> Enroll Free
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="btn btn-primary enroll-paid" 
                                            data-course-id="<?php echo $course['id']; ?>"
                                            data-course-title="<?php echo htmlspecialchars($course['title']); ?>"
                                            data-course-price="<?php echo $course['price']; ?>">
                                        <i class="fas fa-shopping-cart"></i> Enroll - $<?php echo number_format($course['price'], 2); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="payment-modal">
        <div class="modal-content">
            <h3 class="modal-title">Complete Enrollment</h3>
            <p>You are about to enroll in: <strong id="modalCourseTitle"></strong></p>
            <p>Total amount: <strong id="modalCoursePrice"></strong></p>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                <form method="POST" action="process_payment.php" id="paymentForm">
                    <input type="hidden" name="course_id" id="modalCourseId">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-credit-card"></i> Proceed to Payment
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Payment modal functionality
        const enrollButtons = document.querySelectorAll('.enroll-paid');
        const paymentModal = document.getElementById('paymentModal');
        const modalCourseTitle = document.getElementById('modalCourseTitle');
        const modalCoursePrice = document.getElementById('modalCoursePrice');
        const modalCourseId = document.getElementById('modalCourseId');

        enrollButtons.forEach(button => {
            button.addEventListener('click', function() {
                const courseId = this.getAttribute('data-course-id');
                const courseTitle = this.getAttribute('data-course-title');
                const coursePrice = this.getAttribute('data-course-price');
                
                modalCourseTitle.textContent = courseTitle;
                modalCoursePrice.textContent = '$' + parseFloat(coursePrice).toFixed(2);
                modalCourseId.value = courseId;
                
                paymentModal.style.display = 'flex';
            });
        });

        function closePaymentModal() {
            paymentModal.style.display = 'none';
        }

        function showComingSoon() {
            alert('This feature is coming soon! 🚀');
        }

        // Close modal when clicking outside
        paymentModal.addEventListener('click', function(e) {
            if (e.target === paymentModal) {
                closePaymentModal();
            }
        });

        // Change motivational quote daily
        function refreshQuote() {
            alert('New motivational quote loaded! 💫');
            location.reload();
        }

        // Auto-refresh quote every 24 hours (86400000 ms)
        setTimeout(refreshQuote, 86400000);
    </script>
</body>
</html>