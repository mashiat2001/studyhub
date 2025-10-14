<?php
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit();
}

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

// Enhanced email validation regex
$emailRegex = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
// Enhanced password validation regex (at least 6 chars, 1 letter, 1 number)
$passwordRegex = '/^(?=.*[A-Za-z])(?=.*\d).{6,}$/';

// If form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role     = $_POST['role'];
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = "All fields are required.";
    } elseif (!preg_match($emailRegex, $email)) {
        $error = "Please enter a valid email address (e.g., user@example.com).";
    } elseif (!preg_match($passwordRegex, $password)) {
        $error = "Password must be at least 6 characters long and contain at least one letter and one number.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Email is already registered.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $token = bin2hex(random_bytes(16));

            // Insert user into DB with role
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, verification_token, verified, role) VALUES (?, ?, ?, ?, 0, ?)");
            $stmt->bind_param("sssss", $name, $email, $hashed_password, $token, $role);

            if ($stmt->execute()) {
                // Send verification email
                $mail = new PHPMailer(true);

                try {
                    // SMTP settings - FIXED: Use proper authentication
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com'; 
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'masiatjahankhan@gmail.com';
                    $mail->Password   = 'ynsjpozvmdxdxkud'; // Use App Password if 2FA is enabled
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    // Recipients
                    $mail->setFrom('masiatjahankhan@gmail.com', 'StudyHub');
                    $mail->addAddress($email, $name);

                    // Content
                    $verifyLink = "http://localhost/studyhub/verify.php?token=$token";
                    $mail->isHTML(true);
                    $mail->Subject = 'Verify Your Email - StudyHub';
                    $mail->Body    = "
                        <html>
                        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                                <h2 style='color: #7E6CCA; text-align: center;'>Welcome to StudyHub!</h2>
                                <p>Hi $name,</p>
                                <p>Thank you for registering with StudyHub. To complete your registration, please verify your email address by clicking the button below:</p>
                                <div style='text-align: center; margin: 30px 0;'>
                                    <a href='$verifyLink' style='background-color: #7E6CCA; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Verify Email Address</a>
                                </div>
                                <p>If the button doesn't work, you can also copy and paste this link into your browser:</p>
                                <p style='word-break: break-all; color: #7E6CCA;'>$verifyLink</p>
                                <p>If you didn't create an account with StudyHub, please ignore this email.</p>
                                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                                <p style='font-size: 12px; color: #888; text-align: center;'>This is an automated message, please do not reply to this email.</p>
                            </div>
                        </body>
                        </html>
                    ";

                    $mail->send();
                    
                    $_SESSION['registration_success'] = "Registration successful! Please check your email to verify your account.";
                    header("Location: login.php");
                    exit();
                } catch (Exception $e) {
                    $error = "Verification email could not be sent. Please try again later. Error: " . $mail->ErrorInfo;
                }
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
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
    <title>Register - StudyHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --light-bg: #FFFFFF;
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        
        .register-header {
            background: white;
            color: var(--text-dark);
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }
        
        .logo-icon i {
            color: white;
        }
        
        .logo-text {
            font-size: 24px;
            font-weight: 700;
        }
        
        .register-header h1 {
            font-size: 24px;
            margin-bottom: 10px;
            color: var(--text-dark);
        }
        
        .register-header p {
            color: var(--text-light);
            font-size: 14px;
        }
        
        .register-form {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .input-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-dark);
            font-size: 14px;
        }
        
        .input-field {
            width: 100%;
            padding: 14px;
            border: 2px solid #e5e7eb;
            border-radius: var(--border-radius);
            font-size: 15px;
            transition: var(--transition);
            background: white;
        }
        
        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(126, 108, 202, 0.1);
        }
        
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
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
        
        .alert i {
            margin-right: 8px;
        }
        
        .sign-up-btn {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 10px;
        }
        
        .sign-up-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(126, 108, 202, 0.3);
        }
        
        .footer-links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: var(--text-light);
        }
        
        .footer-links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .footer-links a:hover {
            text-decoration: underline;
        }
        
        select.input-field {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 14px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }
        
        .requirement-text {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 5px;
        }
        
        .requirement-valid {
            color: #10b981;
        }
        
        .requirement-invalid {
            color: #ef4444;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="logo-text" style="color: var(--text-dark);">Study<span style="color: var(--primary);">Hub</span></div>
            </div>
            <h1>Create Your Account</h1>
            <p>Join StudyHub and start your learning journey</p>
        </div>
        
        <div class="register-form">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="registrationForm">
                <div class="form-group">
                    <label for="name" class="input-label">Full Name</label>
                    <input type="text" id="name" name="name" class="input-field" placeholder="Enter your full name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email" class="input-label">Email Address</label>
                    <input type="email" id="email" name="email" class="input-field" placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    <div class="requirement-text">Must be a valid email address (e.g., user@example.com)</div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="input-label">Password</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" class="input-field" placeholder="Create a password" required>
                        <button type="button" class="password-toggle" id="passwordToggle">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="requirement-text">
                        <span id="length-req">✓ At least 6 characters</span><br>
                        <span id="letter-req">✓ Contains at least one letter</span><br>
                        <span id="number-req">✓ Contains at least one number</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="input-label">Confirm Password</label>
                    <div class="password-container">
                        <input type="password" id="confirm_password" name="confirm_password" class="input-field" placeholder="Confirm your password" required>
                        <button type="button" class="password-toggle" id="confirmPasswordToggle">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="requirement-text" id="match-req">✓ Passwords match</div>
                </div>

                <div class="form-group">
                    <label for="role" class="input-label">Register As</label>
                    <select id="role" name="role" class="input-field" required>
                        <option value="student" <?php echo (isset($_POST['role']) && $_POST['role']=="student") ? "selected" : ""; ?>>Student</option>
                        <option value="instructor" <?php echo (isset($_POST['role']) && $_POST['role']=="instructor") ? "selected" : ""; ?>>Instructor</option>
                        <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role']=="admin") ? "selected" : ""; ?>>Admin</option>
                    </select>
                </div>
                
                <button type="submit" name="submit" class="sign-up-btn">Create Account</button>
            </form>
            
            <div class="footer-links">
                <p>Already have an account? <a href="login.php">Sign in here</a></p>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        const passwordToggle = document.getElementById('passwordToggle');
        const confirmPasswordToggle = document.getElementById('confirmPasswordToggle');
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');
        
        passwordToggle.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
        
        confirmPasswordToggle.addEventListener('click', function() {
            const type = confirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordField.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });

        // Real-time password validation
        passwordField.addEventListener('input', validatePassword);
        confirmPasswordField.addEventListener('input', validatePasswordMatch);
        
        function validatePassword() {
            const password = passwordField.value;
            const lengthReq = document.getElementById('length-req');
            const letterReq = document.getElementById('letter-req');
            const numberReq = document.getElementById('number-req');
            
            // Check length
            if (password.length >= 6) {
                lengthReq.className = 'requirement-valid';
                lengthReq.innerHTML = '✓ At least 6 characters';
            } else {
                lengthReq.className = 'requirement-invalid';
                lengthReq.innerHTML = '✗ At least 6 characters';
            }
            
            // Check for letter
            if (/[A-Za-z]/.test(password)) {
                letterReq.className = 'requirement-valid';
                letterReq.innerHTML = '✓ Contains at least one letter';
            } else {
                letterReq.className = 'requirement-invalid';
                letterReq.innerHTML = '✗ Contains at least one letter';
            }
            
            // Check for number
            if (/\d/.test(password)) {
                numberReq.className = 'requirement-valid';
                numberReq.innerHTML = '✓ Contains at least one number';
            } else {
                numberReq.className = 'requirement-invalid';
                numberReq.innerHTML = '✗ Contains at least one number';
            }
            
            validatePasswordMatch();
        }
        
        function validatePasswordMatch() {
            const password = passwordField.value;
            const confirmPassword = confirmPasswordField.value;
            const matchReq = document.getElementById('match-req');
            
            if (confirmPassword === '' || password === '') {
                matchReq.innerHTML = '✓ Passwords match';
                matchReq.className = '';
            } else if (password === confirmPassword) {
                matchReq.className = 'requirement-valid';
                matchReq.innerHTML = '✓ Passwords match';
            } else {
                matchReq.className = 'requirement-invalid';
                matchReq.innerHTML = '✗ Passwords do not match';
            }
        }

        // Enhanced client-side validation
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            const passwordRegex = /^(?=.*[A-Za-z])(?=.*\d).{6,}$/;
            
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address (e.g., user@example.com).');
                e.preventDefault();
                return false;
            }
            
            if (!passwordRegex.test(password)) {
                alert('Password must be at least 6 characters long and contain at least one letter and one number.');
                e.preventDefault();
                return false;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>