<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

require_once 'vendor/autoload.php'; // Load Dompdf

use Dompdf\Dompdf;
use Dompdf\Options;

$user_id = $_SESSION['user']['id'];
$course_id = $_GET['course_id'] ?? 0;

$conn = new mysqli('localhost', 'root', '', 'project_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Validation (same as in certificate.php) ---
// Verify enrollment and progress
$stmt = $conn->prepare("SELECT progress FROM user_courses WHERE user_id = ? AND course_id = ?");
$stmt->bind_param("ii", $user_id, $course_id);
$stmt->execute();
$result = $stmt->get_result();
$enrolled = $result->fetch_assoc();

if (!$enrolled || $enrolled['progress'] < 100) {
    die("You have not completed this course yet.");
}

// Quiz score requirement
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
// --- End validation ---

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

// Get certificate code and display name
$cert_stmt = $conn->prepare("SELECT certificate_code, display_name FROM certificates WHERE user_id = ? AND course_id = ?");
$cert_stmt->bind_param("ii", $user_id, $course_id);
$cert_stmt->execute();
$cert = $cert_stmt->get_result()->fetch_assoc();

if (!$cert) {
    die("Certificate not found. Please generate it first.");
}

$certificate_code = $cert['certificate_code'];
$display_name = $cert['display_name'] ?: $user['name'];

$conn->close();

// --- Generate PDF ---
// Build verification URL and QR code
$verification_url = "http://192.168.0.105/studyhub/verify_certificate.php?code=" . urlencode($certificate_code);
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($verification_url);

// HTML content for the PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: "Inter", sans-serif;
            background: white;
            margin: 0;
            padding: 0;
        }
        .certificate {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 50px 40px;
            border: 20px solid #7E6CCA;
            text-align: center;
            border-radius: 20px;
        }
        h1 {
            font-size: 3rem;
            color: #7E6CCA;
            margin-bottom: 20px;
        }
        .seal {
            font-size: 3rem;
            margin: 30px 0;
        }
        .student-name {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2d3748;
            margin: 20px 0;
        }
        .course-title {
            font-size: 2rem;
            color: #7E6CCA;
            margin: 20px 0;
        }
        .instructor {
            font-size: 1.2rem;
            color: #4a5568;
        }
        .date {
            font-size: 1.1rem;
            color: #718096;
            margin: 30px 0 10px;
        }
        .code {
            font-size: 0.9rem;
            color: #a0aec0;
            margin-top: 20px;
        }
        .qr-section {
            margin: 20px 0;
        }
        .qr-code {
            max-width: 150px;
            border: 5px solid white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .footer {
            margin-top: 20px;
            font-size: 0.8rem;
            color: #a0aec0;
        }
    </style>
</head>
<body>
    <div class="certificate">
        <h1>CERTIFICATE OF COMPLETION</h1>
        <div class="seal">🏅</div>
        <p>This is proudly presented to</p>
        <div class="student-name">' . htmlspecialchars($display_name) . '</div>
        <p>for successfully completing the course</p>
        <div class="course-title">' . htmlspecialchars($course['title']) . '</div>
        <div class="instructor">Instructor: ' . htmlspecialchars($course['instructor_name'] ?: 'StudyHub') . '</div>
        <div class="date">Issued on: ' . date('F j, Y') . '</div>
        <div class="code">Certificate ID: ' . $certificate_code . '</div>
        <div class="qr-section">
            <img src="' . $qr_code_url . '" alt="QR Code" class="qr-code">
        </div>
        <div class="footer">StudyHub – Empowering lifelong learning</div>
    </div>
</body>
</html>
';

// Create PDF
$options = new Options();
$options->set('isRemoteEnabled', true); // Allow loading QR code from external URL
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// Output PDF for download
$dompdf->stream("certificate_" . $certificate_code . ".pdf", array("Attachment" => true));
exit;