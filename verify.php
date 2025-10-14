<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - StudyHub</title>
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
        
        .verification-container {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
        }
        
        .logo-text {
            font-size: 32px;
            font-weight: 700;
            font-family: 'Outfit', sans-serif;
        }
        
        .logo-text span {
            color: var(--primary);
        }
        
        .verification-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 40px;
            animation: pulse 2s infinite;
        }
        
        .success .verification-icon {
            background: rgba(46, 204, 113, 0.15);
            color: #2ECC71;
            border: 3px solid #2ECC71;
        }
        
        .warning .verification-icon {
            background: rgba(241, 196, 15, 0.15);
            color: #F1C40F;
            border: 3px solid #F1C40F;
        }
        
        .error .verification-icon {
            background: rgba(231, 76, 60, 0.15);
            color: #E74C3C;
            border: 3px solid #E74C3C;
        }
        
        .verification-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--text-dark);
        }
        
        .verification-message {
            color: var(--text-light);
            margin-bottom: 30px;
            line-height: 1.6;
            font-size: 1.1rem;
        }
        
        .login-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 15px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(126, 108, 202, 0.3);
        }
        
        .status-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: var(--border-radius);
            margin: 30px 0;
            text-align: left;
        }
        
        .status-details h3 {
            margin-bottom: 15px;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-details ul {
            list-style: none;
            padding-left: 5px;
        }
        
        .status-details li {
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .status-details i {
            color: var(--primary);
            margin-top: 5px;
        }
        
        .support-note {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: var(--text-light);
        }
        
        .support-note a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.4);
            }
            70% {
                transform: scale(1.05);
                box-shadow: 0 0 0 10px rgba(46, 204, 113, 0);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(46, 204, 113, 0);
            }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .verification-container {
                padding: 30px 20px;
            }
            
            .verification-title {
                font-size: 1.8rem;
            }
            
            .logo-text {
                font-size: 28px;
            }
            
            .logo-icon {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
            
            .verification-icon {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="logo-text">Study<span>Hub</span></div>
        </div>
        
        <?php
        $conn = new mysqli("localhost", "root", "", "project_db");
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        if (isset($_GET['token'])) {
            $token = $_GET['token'];

            // Use prepared statement for safety
            $stmt = $conn->prepare("SELECT id, verified FROM users WHERE verification_token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if ($user['verified'] == 1) {
                    echo '
                    <div class="warning">
                        <div class="verification-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2 class="verification-title">Already Verified</h2>
                        <p class="verification-message">Your email address has already been verified.</p>
                        
                        <div class="status-details">
                            <h3><i class="fas fa-info-circle"></i> Account Status</h3>
                            <ul>
                                <li><i class="fas fa-check"></i> Email verification completed</li>
                                <li><i class="fas fa-user-check"></i> Account is active</li>
                                <li><i class="fas fa-sign-in-alt"></i> Ready to access your dashboard</li>
                            </ul>
                        </div>
                        
                        <a href="login.php" class="login-btn">
                            <i class="fas fa-sign-in-alt"></i> Continue to Login
                        </a>
                    </div>';
                } else {
                    // Update user to verified
                    $update = $conn->prepare("UPDATE users SET verified = 1, verification_token = NULL WHERE id = ?");
                    $update->bind_param("i", $user['id']);
                    $update->execute();

                    echo '
                    <div class="success">
                        <div class="verification-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2 class="verification-title">Email Verified Successfully!</h2>
                        <p class="verification-message">Thank you for verifying your email address. Your StudyHub account is now fully activated.</p>
                        
                        <div class="status-details">
                            <h3><i class="fas fa-rocket"></i> What\'s Next?</h3>
                            <ul>
                                <li><i class="fas fa-check"></i> Full access to all courses and materials</li>
                                <li><i class="fas fa-check"></i> Ability to track your learning progress</li>
                                <li><i class="fas fa-check"></i> Earn certificates upon course completion</li>
                            </ul>
                        </div>
                        
                        <a href="login.php" class="login-btn">
                            <i class="fas fa-sign-in-alt"></i> Start Learning Now
                        </a>
                    </div>';
                }
            } else {
                echo '
                <div class="error">
                    <div class="verification-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h2 class="verification-title">Invalid Verification Link</h2>
                    <p class="verification-message">The verification link is invalid or has expired.</p>
                    
                    <div class="status-details">
                        <h3><i class="fas fa-question-circle"></i> Possible Reasons</h3>
                        <ul>
                            <li><i class="fas fa-clock"></i> The link may have expired</li>
                            <li><i class="fas fa-link"></i> The link may have been used already</li>
                            <li><i class="fas fa-exclamation-triangle"></i> The link may be incorrect</li>
                        </ul>
                    </div>
                    
                    <a href="login.php" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i> Try Logging In
                    </a>
                </div>';
            }

            $stmt->close();
        } else {
            echo '
            <div class="error">
                <div class="verification-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2 class="verification-title">Verification Error</h2>
                <p class="verification-message">No verification token provided.</p>
                
                <div class="status-details">
                    <h3><i class="fas fa-info-circle"></i> What to do next?</h3>
                    <ul>
                        <li><i class="fas fa-envelope"></i> Check your email for the verification link</li>
                        <li><i class="fas fa-sync-alt"></i> Request a new verification email if needed</li>
                        <li><i class="fas fa-headset"></i> Contact support if problems persist</li>
                    </ul>
                </div>
                
                <a href="login.php" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Go to Login Page
                </a>
            </div>';
        }

        $conn->close();
        ?>
        
        <div class="support-note">
            <p>Need help? Contact our support team at <a href="mailto:support@studyhub.com">support@studyhub.com</a></p>
        </div>
    </div>
</body>
</html>