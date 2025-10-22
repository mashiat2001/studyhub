<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "project_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = '';
$success = '';
$valid_token = false;
$token = '';

// Check if token is provided and valid
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Check if token exists and is not expired
    $check_stmt = $conn->prepare("SELECT id, token_expiry FROM users WHERE reset_token = ?");
    $check_stmt->bind_param("s", $token);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $user = $check_result->fetch_assoc();
        $expiry = strtotime($user['token_expiry']);
        $current_time = time();
        
        if ($current_time < $expiry) {
            $valid_token = true;
            $user_id = $user['id'];
            
            // Process password reset form
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (empty($password) || empty($confirm_password)) {
                    $error = "All fields are required.";
                } elseif ($password !== $confirm_password) {
                    $error = "Passwords do not match.";
                } elseif (strlen($password) < 6) {
                    $error = "Password must be at least 6 characters long.";
                } else {
                    // Hash new password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Update password and clear reset token
                    $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE id = ?");
                    $update_stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($update_stmt->execute()) {
                        $success = "Password reset successfully. You can now login with your new password.";
                        $valid_token = false; // Token is now used
                    } else {
                        $error = "Error: " . $update_stmt->error;
                    }
                    $update_stmt->close();
                }
            }
        } else {
            $error = "Password reset link has expired. Please request a new one.";
        }
    } else {
        $error = "Invalid password reset link. Please request a new one.";
    }
    $check_stmt->close();
} else {
    $error = "No password reset token provided.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - StudyHub</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .reset-container {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            max-width: 500px;
            width: 100%;
        }
        
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        .logo-text {
            font-size: 28px;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
        }
        
        .logo-text span {
            color: var(--primary);
        }
        
        .reset-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-dark);
            text-align: center;
        }
        
        .reset-description {
            color: var(--text-light);
            margin-bottom: 30px;
            line-height: 1.6;
            text-align: center;
        }
        
        .reset-form {
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
        
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
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
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .reset-container {
                padding: 30px 20px;
            }
            
            .reset-title {
                font-size: 1.8rem;
            }
            
            .reset-form {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-lightbulb"></i>
            </div>
            <div class="logo-text">Study<span>Hub</span></div>
        </div>
        
        <h2 class="reset-title">Reset Your Password</h2>
        <p class="reset-description">Enter your new password below to reset your account password.</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
        <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> 
        <?php echo $success; ?> 
        <a href="login.php" style="color: #137333; font-weight: 600; text-decoration: underline; margin-left: 5px;">Login here</a>
        </div>
        <?php endif; ?>
        
        <?php if ($valid_token): ?>
            <form method="POST" class="reset-form">
                <div class="form-group">
                    <label for="password" class="input-label">New Password</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" class="input-field" placeholder="Enter your new password" required>
                        <button type="button" class="password-toggle" id="passwordToggle">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="input-label">Confirm Password</label>
                    <div class="password-container">
                        <input type="password" id="confirm_password" name="confirm_password" class="input-field" placeholder="Confirm your new password" required>
                        <button type="button" class="password-toggle" id="confirmPasswordToggle">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="reset-btn">Reset Password</button>
            </form>
        <?php elseif (empty($success)): ?>
            <div class="footer-links">
                <p>Please <a href="forget_password.php">request a new password reset link</a> if your current link is invalid or expired.</p>
            </div>
        <?php endif; ?>
        
        <div class="footer-links">
            <p>Remember your password? <a href="login.php">Sign in here</a></p>
        </div>
    </div>

    <script>
        // Password visibility toggle
        const passwordToggle = document.getElementById('passwordToggle');
        const confirmPasswordToggle = document.getElementById('confirmPasswordToggle');
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');
        
        if (passwordToggle && passwordField) {
            passwordToggle.addEventListener('click', function() {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
        }
        
        if (confirmPasswordToggle && confirmPasswordField) {
            confirmPasswordToggle.addEventListener('click', function() {
                const type = confirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPasswordField.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
        }
    </script>
</body>
</html>