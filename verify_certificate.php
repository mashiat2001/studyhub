<?php
$code = $_GET['code'] ?? '';
if (!$code) {
    die("No certificate code provided.");
}

$conn = new mysqli('localhost', 'root', '', 'project_db');
if ($conn->connect_error) {
    die("Database connection failed.");
}

$stmt = $conn->prepare("
    SELECT 
        c.certificate_code, 
        c.issue_date, 
        c.display_name,
        u.name as student_name, 
        co.title as course_title,
        ins.name as instructor_name
    FROM certificates c 
    JOIN users u ON c.user_id = u.id 
    JOIN courses co ON c.course_id = co.id
    LEFT JOIN users ins ON co.instructor_id = ins.id
    WHERE c.certificate_code = ?
");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();
$cert = $result->fetch_assoc();

if (!$cert) {
    die("Invalid certificate code.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate Verification - StudyHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f7fafc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        h1 {
            color: #7E6CCA;
            margin-bottom: 20px;
        }
        .details {
            text-align: left;
            margin: 20px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
        }
        .details p {
            margin: 10px 0;
        }
        .valid {
            color: #48BB78;
            font-weight: 600;
        }
        .seal {
            font-size: 3rem;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="seal">✅</div>
        <h1>Certificate is Valid</h1>
        <div class="details">
            <p><strong>Certificate ID:</strong> <?php echo htmlspecialchars($cert['certificate_code']); ?></p>
            <p><strong>Issued to:</strong> <?php echo htmlspecialchars($cert['display_name'] ?: $cert['student_name']); ?></p>
            <p><strong>Course:</strong> <?php echo htmlspecialchars($cert['course_title']); ?></p>
            <p><strong>Instructor:</strong> <?php echo htmlspecialchars($cert['instructor_name'] ?: 'StudyHub'); ?></p>
            <p><strong>Issue date:</strong> <?php echo date('F j, Y', strtotime($cert['issue_date'])); ?></p>
        </div>
        <p><i class="fas fa-check-circle" style="color:#48BB78;"></i> This certificate was issued by StudyHub and is authentic.</p>
    </div>
</body>
</html>