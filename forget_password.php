<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "project_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$error = '';
$success = '';

// If form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    // Validate inputs
    if (empty($email)) {
        $error = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if email exists in the database
        $check_stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $user = $check_result->fetch_assoc();
            $user_id = $user['id'];
            $name = $user['name'];
            
            // Generate reset token
            $reset_token = bin2hex(random_bytes(32));
            $expiry_date = date("Y-m-d H:i:s", strtotime("+1 hour")); // Token expires in 1 hour
            
            // Store token in database
            $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $reset_token, $expiry_date, $user_id);
            
            if ($update_stmt->execute()) {
                // Send password reset email
                $mail = new PHPMailer(true);

                try {
                    // SMTP settings
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com'; 
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'masiatjahankhan@gmail.com';
                    $mail->Password   = 'ynsjpozvmdxdxkud';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    // Recipients
                    $mail->setFrom('masiatjahankhan@gmail.com', 'StudyHub');
                    $mail->addAddress($email, $name);

                    // Content
                    $resetLink = "http://localhost/studyhub/reset-password.php?token=$reset_token";
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request - StudyHub';
                    $mail->Body    = "
                        <html>
                        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                                <h2 style='color: #7E6CCA; text-align: center;'>Password Reset Request</h2>
                                <p>Hi $name,</p>
                                <p>We received a request to reset your password for your StudyHub account. Click the button below to reset your password:</p>
                                <div style='text-align: center; margin: 30px 0;'>
                                    <a href='$resetLink' style='background-color: #7E6CCA; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
                                </div>
                                <p>If the button doesn't work, you can also copy and paste this link into your browser:</p>
                                <p style='word-break: break-all; color: #7E6CCA;'>$resetLink</p>
                                <p>This password reset link will expire in 1 hour for security reasons.</p>
                                <p>If you didn't request a password reset, please ignore this email. Your account remains secure.</p>
                                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                                <p style='font-size: 12px; color: #888; text-align: center;'>This is an automated message, please do not reply to this email.</p>
                            </div>
                        </body>
                        </html>
                    ";

                    $mail->send();
                    
                    $success = "Password reset instructions have been sent to your email address.";
                } catch (Exception $e) {
                    $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
            } else {
                $error = "Error: " . $update_stmt->error;
            }
            $update_stmt->close();
        } else {
            $error = "No account found with this email address.";
        }
        $check_stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - StudyHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #7E6CCA;
            --primary-light: #9F90DB;
            --primary-dark: #6351A6;
            --secondary: #FF9E6D;
            --accent: #6DC9FF;
            --text-dark: #2D3748;
            --text-light: #718096;
            --light-bg: #F7FAFC;
            --card-bg: rgba(255, 255, 255, 0.95);
            --sidebar-bg: #2D3748;
            --border-radius: 12px;
            --transition: all 0.3s ease;
            --shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 12px 35px rgba(0, 0, 0, 0.15);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
        }
        
        /* Header Styles */
        .header {
            background: var(--card-bg);
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
            font-family: 'Outfit', sans-serif;
        }
        
        .logo-text span {
            color: var(--primary);
        }
        
        .nav-menu {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .nav-item {
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .nav-item:hover {
            color: var(--primary);
        }
        
        .login-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(126, 108, 202, 0.3);
        }
        
        /* Main Content */
        .main-content {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 80px);
            padding: 40px 30px;
        }
        
        .forgot-container {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            max-width: 800px;
            width: 100%;
            display: flex;
            gap: 50px;
        }
        
        .forgot-content {
            flex: 1;
        }
        
        .forgot-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-dark);
        }
        
        .forgot-description {
            color: var(--text-light);
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .forgot-features {
            list-style: none;
            margin-bottom: 40px;
        }
        
        .forgot-features li {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .forgot-features i {
            color: var(--primary);
            font-size: 1.2rem;
        }
        
        .forgot-form-container {
            flex: 1;
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
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
            padding: 15px;
            border: 1px solid #EDF2F7;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .input-field:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(126, 108, 202, 0.15);
        }
        
        .reset-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 15px;
            width: 100%;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 10px;
        }
        
        .reset-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(126, 108, 202, 0.3);
        }
        
        .footer-links {
            margin-top: 20px;
            text-align: center;
            font-size: 0.95rem;
            color: var(--text-light);
        }
        
        .footer-links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
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
            background-color: #ffe6e6;
            color: #d93025;
            border: 1px solid #ffb3b3;
        }
        
        .alert-success {
            background-color: #e6f4ea;
            color: #137333;
            border: 1px solid #b3e0b3;
        }
        
        /* Footer */
        .footer {
            background: var(--sidebar-bg);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .footer-content {
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .footer-links-row {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 10px;
        }
        
        .footer-links-row a {
            color: #CBD5E0;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .footer-links-row a:hover {
            color: white;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .forgot-container {
                flex-direction: column;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-menu {
                gap: 15px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .forgot-container {
                padding: 30px 20px;
            }
            
            .forgot-title {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="logo-text">Study<span>Hub</span></div>
        </div>
        
        <nav class="nav-menu">
            <a href="index.php" class="nav-item">Home</a>
            <a href="#" class="nav-item">Courses</a>
            <a href="#" class="nav-item">Features</a>
            <a href="#" class="nav-item">About</a>
            <a href="login.php" class="login-btn">Sign In</a>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="forgot-container">
            <div class="forgot-content">
                <h2 class="forgot-title">Reset Your Password</h2>
                <p class="forgot-description">Enter your email address and we'll send you instructions to reset your password.</p>
                
                <ul class="forgot-features">
                    <li><i class="fas fa-shield-alt"></i> Secure password reset process</li>
                    <li><i class="fas fa-clock"></i> Reset link expires in 1 hour</li>
                    <li><i class="fas fa-envelope"></i> Check your email after submitting</li>
                    <li><i class="fas fa-lock"></i> Your account remains secure throughout</li>
                </ul>
                
                <div class="support-note">
                    <p><strong>Need help?</strong> Contact our support team at <a href="mailto:support@studyhub.com">support@studyhub.com</a></p>
                </div>
            </div>
            
            <div class="forgot-form-container">
                <h3 style="margin-bottom: 20px; text-align: center;">Password Recovery</h3>
                
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
                
                <form method="POST" class="login-form">
                    <div class="form-group">
                        <label for="email" class="input-label">Email Address</label>
                        <input type="email" id="email" name="email" class="input-field" placeholder="Enter your email address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    
                    <button type="submit" class="reset-btn">Send Reset Instructions</button>
                </form>
                
                <div class="footer-links">
                    <p>Remember your password? <a href="login.php">Sign in here</a></p>
                    <p>Don't have an account? <a href="register.php">Create one here</a></p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="logo" style="justify-content: center;">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="logo-text">Study<span>Hub</span></div>
            </div>
            <p>© 2023 StudyHub - Interactive Learning Platform. All rights reserved.</p>
            <div class="footer-links-row">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Contact Us</a>
                <a href="#">Help Center</a>
            </div>
        </div>
    </footer>
</body>
</html>