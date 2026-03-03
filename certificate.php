<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user']['id'];
$course_id = $_GET['course_id'] ?? 0;

$conn = new mysqli('localhost', 'root', '', 'project_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Verify enrollment and progress
$stmt = $conn->prepare("SELECT progress FROM user_courses WHERE user_id = ? AND course_id = ?");
$stmt->bind_param("ii", $user_id, $course_id);
$stmt->execute();
$result = $stmt->get_result();
$enrolled = $result->fetch_assoc();

if (!$enrolled || $enrolled['progress'] < 100) {
    die("You have not completed this course yet.");
}

// ---- QUIZ SCORE REQUIREMENT (average ≥ 70%) ----
$quiz_count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM quizzes WHERE course_id = ?");
$quiz_count_stmt->bind_param("i", $course_id);
$quiz_count_stmt->execute();
$total_quizzes = $quiz_count_stmt->get_result()->fetch_assoc()['total'];

if ($total_quizzes == 0) {
    die("This course has no quizzes. Certificate cannot be issued.");
}

$taken_stmt = $conn->prepare("SELECT COUNT(*) as taken FROM quiz_results WHERE user_id = ? AND quiz_id IN (SELECT id FROM quizzes WHERE course_id = ?)");
$taken_stmt->bind_param("ii", $user_id, $course_id);
$taken_stmt->execute();
$taken = $taken_stmt->get_result()->fetch_assoc()['taken'];

if ($taken < $total_quizzes) {
    die("You have not taken all quizzes for this course. Please complete all quizzes first.");
}

$avg_stmt = $conn->prepare("SELECT AVG(percentage) as avg_score FROM quiz_results WHERE user_id = ? AND quiz_id IN (SELECT id FROM quizzes WHERE course_id = ?)");
$avg_stmt->bind_param("ii", $user_id, $course_id);
$avg_stmt->execute();
$avg_score = $avg_stmt->get_result()->fetch_assoc()['avg_score'];

if ($avg_score < 70) {
    die("You need an average quiz score of at least 70% to earn a certificate. Your current average is " . round($avg_score, 1) . "%.");
}
// ---- END QUIZ CHECK ----

// Get user details
$user_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// Get course details with instructor name
$course_stmt = $conn->prepare("
    SELECT c.title, u.name as instructor_name 
    FROM courses c 
    LEFT JOIN users u ON c.instructor_id = u.id 
    WHERE c.id = ?
");
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course = $course_stmt->get_result()->fetch_assoc();

// Check if certificate already exists
$cert_stmt = $conn->prepare("SELECT certificate_code, display_name FROM certificates WHERE user_id = ? AND course_id = ?");
$cert_stmt->bind_param("ii", $user_id, $course_id);
$cert_stmt->execute();
$existing = $cert_stmt->get_result()->fetch_assoc();

$certificate_code = null;
$display_name = null;

if ($existing) {
    $certificate_code = $existing['certificate_code'];
    $display_name = $existing['display_name'] ?: $user['name'];
} else {
    // If no certificate yet, check if display name was submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['display_name'])) {
        $display_name = trim($_POST['display_name']);
        if (strlen($display_name) < 2) {
            $error = "Name must be at least 2 characters.";
        } else {
            // Generate certificate code
            $certificate_code = strtoupper(uniqid('CERT-') . '-' . bin2hex(random_bytes(4)));
            $insert = $conn->prepare("INSERT INTO certificates (user_id, course_id, certificate_code, display_name) VALUES (?, ?, ?, ?)");
            $insert->bind_param("iiss", $user_id, $course_id, $certificate_code, $display_name);
            $insert->execute();

            // Notifications (optional)
            $student_message = "Congratulations! You've earned a certificate for '{$course['title']}'.";
            $student_link = "certificate.php?course_id={$course_id}";
            $notify = $conn->prepare("INSERT INTO notifications (user_id, role, message, link) VALUES (?, 'student', ?, ?)");
            $notify->bind_param("iss", $user_id, $student_message, $student_link);
            $notify->execute();
        }
    } else {
        // Show form to enter display name
        $show_form = true;
    }
}

$conn->close();

// If certificate exists or just generated, display it
if ($certificate_code):
   $verification_url = "http://192.168.0.105/studyhub/verify_certificate.php?code=" . urlencode($certificate_code);
    $qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($verification_url);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Completion</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 20px;
            flex-direction: column;
        }
        .certificate {
            max-width: 900px;
            width: 100%;
            background: white;
            padding: 50px 40px;
            border: 20px solid #7E6CCA;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            border-radius: 20px;
            margin-bottom: 30px;
        }
        .certificate h1 {
            font-size: 3rem;
            color: #7E6CCA;
            margin-bottom: 20px;
            font-weight: 700;
            letter-spacing: 2px;
        }
        .certificate .student-name {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2d3748;
            margin: 20px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .certificate .course-title {
            font-size: 2rem;
            color: #7E6CCA;
            font-weight: 600;
            margin: 20px 0;
        }
        .certificate .instructor {
            font-size: 1.2rem;
            color: #4a5568;
            margin: 10px 0;
        }
        .certificate .date {
            font-size: 1.1rem;
            color: #718096;
            margin: 30px 0 10px;
        }
        .certificate .code {
            font-size: 0.9rem;
            color: #a0aec0;
            margin-top: 20px;
            font-family: monospace;
        }
        .certificate .seal {
            margin-top: 30px;
            font-size: 3rem;
            color: #7E6CCA;
        }
        .qr-section {
            margin: 20px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .qr-code {
            max-width: 150px;
            border: 5px solid white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .qr-fallback {
            font-size: 0.9rem;
            color: #7E6CCA;
            margin-top: 8px;
            word-break: break-all;
        }
        .btn-download {
            background: #7E6CCA;
            color: white;
            border: none;
            padding: 14px 40px;
            font-size: 1.2rem;
            border-radius: 40px;
            cursor: pointer;
            transition: 0.2s;
            box-shadow: 0 4px 10px rgba(126, 108, 202, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .btn-download:hover {
            background: #6351A6;
            transform: translateY(-2px);
        }
        @media print {
            body {
                background: white;
                margin: 0;
                padding: 0;
            }
            .btn-download, .qr-fallback {
                display: none;
            }
            .certificate {
                box-shadow: none;
                border: 20px solid #7E6CCA;
                page-break-inside: avoid;
                margin: 0 auto;
            }
        }
    </style>
</head>
<body>
    <div class="certificate" id="certificate">
        <h1>CERTIFICATE OF COMPLETION</h1>
        <div class="seal">🏅</div>
        <p>This is proudly presented to</p>
        <div class="student-name"><?php echo htmlspecialchars($display_name); ?></div>
        <p>for successfully completing the course</p>
        <div class="course-title"><?php echo htmlspecialchars($course['title']); ?></div>
        <div class="instructor">Instructor: <?php echo htmlspecialchars($course['instructor_name'] ?: 'StudyHub'); ?></div>
        <div class="date">Issued on: <?php echo date('F j, Y'); ?></div>
        <div class="code">Certificate ID: <?php echo $certificate_code; ?></div>

        <div class="qr-section">
            <img src="<?php echo $qr_code_url; ?>" alt="QR Code for verification" class="qr-code" onerror="this.style.display='none'; document.getElementById('qr-fallback').style.display='block';">
            <div id="qr-fallback" class="qr-fallback" style="display: none;">
                <p>Scan this link to verify: <br><a href="<?php echo $verification_url; ?>" target="_blank"><?php echo $verification_url; ?></a></p>
            </div>
        </div>

        <p style="margin-top:20px; font-size:0.8rem; color:#a0aec0;">StudyHub – Empowering lifelong learning</p>
    </div>
    
    <a href="download_certificate.php?course_id=<?php echo $course_id; ?>" class="btn-download" target="_blank">
    <i class="fas fa-download"></i> Download PDF
    </a>

    <script>
        // If QR image fails to load, show fallback
        document.querySelector('.qr-code').addEventListener('error', function() {
            this.style.display = 'none';
            document.getElementById('qr-fallback').style.display = 'block';
        });
    </script>
</body>
</html>

<?php elseif (isset($show_form) && $show_form): ?>
    <!-- Form to enter display name -->
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Certificate - Enter Your Name</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <style>
            body {
                font-family: 'Inter', sans-serif;
                background: #f0f2f5;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
            }
            .container {
                background: white;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 8px 25px rgba(0,0,0,0.1);
                max-width: 500px;
                width: 90%;
                text-align: center;
            }
            h1 {
                color: #7E6CCA;
                margin-bottom: 20px;
            }
            p {
                color: #718096;
                margin-bottom: 30px;
            }
            input[type="text"] {
                width: 100%;
                padding: 12px;
                border: 2px solid #e5e7eb;
                border-radius: 8px;
                font-size: 16px;
                margin-bottom: 20px;
            }
            button {
                background: #7E6CCA;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 8px;
                font-size: 16px;
                cursor: pointer;
                transition: 0.2s;
            }
            button:hover {
                background: #6351A6;
            }
            .error {
                color: #e53e3e;
                margin-bottom: 15px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Almost there! 🎉</h1>
            <p>Please enter the name you'd like to appear on your certificate:</p>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="text" name="display_name" placeholder="e.g., John Doe" required>
                <button type="submit"><i class="fas fa-certificate"></i> Generate Certificate</button>
            </form>
        </div>
    </body>
    </html>
<?php endif; ?>