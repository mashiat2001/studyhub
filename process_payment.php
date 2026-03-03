<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment - StudyHub</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
        h1 { color: #333; margin-bottom: 15px; }
        p { color: #666; margin-bottom: 25px; }
        .btn { background: #7E6CCA; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Payment Coming Soon</h1>
        <p>This feature will be available in future updates.</p>
        <a href="student_dashboard.php" class="btn">Go Back</a>
    </div>
</body>
</html>