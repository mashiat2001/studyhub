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

// Single predefined admin credential - NO REGISTRATION NEEDED
$admin_email = 'studyhub2025web@gmail.com';
$admin_password = 'studyhub2025';

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
    
    // Check if trying to register with admin email
    if ($email === $admin_email) {
        $error = "Admin email cannot be used for registration. Please use a different email.";
    } else {
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
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - StudyHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #7E6CCA;
            --primary-light: #9F90DB;
            --primary-dark: #6351A6;
            --text-dark: #2D3748;
            --text-light: #718096;
            --light-bg: #F7FAFC;
            --border-radius: 8px;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            line-height: 1.6;
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
            font-family: 'Outfit', sans-serif;
        }
        
        .logo-text span {
            color: var(--primary);
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
        }
        
        .nav-links a:hover {
            background: var(--light-bg);
        }
        
        .nav-links a.active {
            background: var(--primary);
            color: white;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: calc(100vh - 100px);
        }
        
        .register-card {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 450px;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
            text-align: center;
        }
        
        .page-subtitle {
            color: var(--text-light);
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .input-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }
        
        .input-field {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #E2E8F0;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: all 0.3s ease;
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
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
        }
        
        .message {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: var(--border-radius);
            font-weight: 500;
        }
        
        .error {
            background: #FED7D7;
            color: #742A2A;
            border-left: 4px solid #F56565;
        }
        
        .success {
            background: #C6F6D5;
            color: #22543D;
            border-left: 4px solid #48BB78;
        }
        
        .btn {
            width: 100%;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(126, 108, 202, 0.3);
        }
        
        .footer-links {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #E2E8F0;
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
            background-position: right 1rem center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 2.5rem;
        }
        
        .requirement-text {
            font-size: 0.875rem;
            color: var(--text-light);
            margin-top: 0.5rem;
        }
        
        .requirement-valid {
            color: #48BB78;
        }
        
        .requirement-invalid {
            color: #F56565;
        }
        
        .admin-notice {
            background: #E6FFFA;
            border: 1px solid #81E6D9;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: #234E52;
        }
        
        .admin-notice i {
            color: #319795;
            margin-right: 0.5rem;
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
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="login.php">Login</a>
            <a href="register.php" class="active">Register</a>
        </div>
    </header>

    <div class="container">
        <div class="register-card">
            <h1 class="page-title">Create Account</h1>
            <p class="page-subtitle">Join StudyHub and start your learning journey</p>
            
            <div class="admin-notice">
                <i class="fas fa-info-circle"></i>
                <strong>Admin Access:</strong> Pre-configured administrator account available.
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="message error">
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
                        <option value="">Select your role</option>
                        <option value="student" <?php echo (isset($_POST['role']) && $_POST['role']=="student") ? "selected" : ""; ?>>Student</option>
                        <option value="instructor" <?php echo (isset($_POST['role']) && $_POST['role']=="instructor") ? "selected" : ""; ?>>Instructor</option>
                    </select>
                </div>
                
                <button type="submit" name="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
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
            const role = document.getElementById('role').value;
            
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
            
            if (!role) {
                alert('Please select a role.');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>