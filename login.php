<?php
session_start();

// Prevent logged-in users from seeing the login/registration pages
if (isset($_SESSION['user'])) {
    // If already logged in, send to correct dashboard
    $role = $_SESSION['user']['role'] ?? 'student';
    if ($role === 'admin') {
        header('Location: admin_dashboard.php');
    } elseif ($role === 'instructor') {
        header('Location: instructor_dashboard.php');
    } else {
        header('Location: student_dashboard.php');
    }
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'project_db');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Predefined admin credential - NO DATABASE REGISTRATION NEEDED
$admin_email = 'studyhub2025web@gmail.com';
$admin_password = 'studyhub2025';

$error = '';
$success = '';

// Check for registration success message
if (isset($_SESSION['registration_success'])) {
    $success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}

if (isset($_POST['submit'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Check if it's the predefined admin
        if ($email === $admin_email && $password === $admin_password) {
            // Login successful for predefined admin
            $_SESSION['user'] = [
                'id' => 0,
                'name' => 'Administrator',
                'email' => $admin_email,
                'role' => 'admin',
                'verified' => 1
            ];
            header('Location: admin_dashboard.php');
            exit();
        }
        
        // Check if user exists in the database using prepared statement
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Check if user is verified
                if ($user['verified'] == 0) {
                    $error = "Please verify your email before logging in.";
                } else {
                    // Login successful, start session
                    $_SESSION['user'] = $user;  // Store user info in session

                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header('Location: admin_dashboard.php');
                    } elseif ($user['role'] === 'instructor') {
                        header('Location: instructor_dashboard.php');
                    } else {
                        header('Location: student_dashboard.php');
                    }
                    exit();
                }
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "No account found with this email.";
        }
        $stmt->close();
    }
}
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyHub - Interactive Learning Platform</title>
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
        
        /* Hero Section */
        .hero {
            display: flex;
            align-items: center;
            min-height: 80vh;
            padding: 40px 30px;
            max-width: 1200px;
            margin: 0 auto;
            gap: 50px;
        }
        
        .hero-content {
            flex: 1;
        }
        
        .hero-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 1.2;
            color: var(--text-dark);
            font-family: 'Outfit', sans-serif;
        }
        
        .hero-title span {
            color: var(--primary);
        }
        
        .hero-description {
            font-size: 1.2rem;
            color: var(--text-light);
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .hero-stats {
            display: flex;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-light);
            font-size: 0.9rem;
        }
        
        .hero-buttons {
            display: flex;
            gap: 15px;
        }
        
        .btn-primary {
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
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(126, 108, 202, 0.3);
        }
        
        .btn-secondary {
            background: white;
            color: var(--primary);
            padding: 15px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            border: 2px solid var(--primary-light);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-secondary:hover {
            background: var(--primary-light);
            color: white;
            transform: translateY(-3px);
        }
        
        .hero-image {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .hero-image img {
            max-width: 100%;
            border-radius: 20px;
            box-shadow: var(--shadow-hover);
        }
        
        /* Login Form Section */
        .login-section {
            background: var(--card-bg);
            padding: 60px 30px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            max-width: 1000px;
            margin: 40px auto;
            display: flex;
            gap: 50px;
        }
        
        .login-content {
            flex: 1;
        }
        
        .login-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-dark);
        }
        
        .login-description {
            color: var(--text-light);
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .login-features {
            list-style: none;
            margin-bottom: 40px;
        }
        
        .login-features li {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .login-features i {
            color: var(--primary);
            font-size: 1.2rem;
        }
        
        .login-form-container {
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
        
        .sign-in-btn {
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
        
        .sign-in-btn:hover {
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
        
        /* Features Section */
        .features-section {
            padding: 80px 30px;
            background: var(--light-bg);
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text-dark);
        }
        
        .section-subtitle {
            text-align: center;
            color: var(--text-light);
            margin-bottom: 60px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .feature-card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
            text-align: center;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin: 0 auto 20px;
        }
        
        .feature-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--text-dark);
        }
        
        .feature-description {
            color: var(--text-light);
        }
        
        /* Footer */
        .footer {
            background: var(--sidebar-bg);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .footer-content {
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 10px;
        }
        
        .footer-links a {
            color: #CBD5E0;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .hero {
                flex-direction: column;
                text-align: center;
                gap: 30px;
            }
            
            .hero-stats {
                justify-content: center;
            }
            
            .login-section {
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
            
            .hero-title {
                font-size: 2.2rem;
            }
            
            .hero-stats {
                flex-direction: column;
                gap: 15px;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-lightbulb"></i>
            </div>
            <div class="logo-text">Study<span>Hub</span></div>
        </div>
        
        <nav class="nav-menu">
            <a href="#" class="nav-item">Home</a>
            <a href="#" class="nav-item">Courses</a>
            <a href="#" class="nav-item">Features</a>
            <a href="#" class="nav-item">Pricing</a>
            <a href="#" class="nav-item">About</a>
            <a href="#login" class="login-btn">Sign In</a>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1 class="hero-title">Where Learning Becomes an <span>Adventure</span></h1>
            <p class="hero-description">Join thousands of students discovering the joy of learning with our interactive educational platform. Access SSC and HSC curriculum materials, track your progress, and achieve academic excellence.</p>
            
            <div class="hero-stats">
                <div class="stat">
                    <div class="stat-value">20+</div>
                    <div class="stat-label">Courses</div>
                </div>
                <div class="stat">
                    <div class="stat-value">100+</div>
                    <div class="stat-label">Students</div>
                </div>
                <div class="stat">
                    <div class="stat-value">70%</div>
                    <div class="stat-label">Success Rate</div>
                </div>
            </div>
            
            <div class="hero-buttons">
                <a href="#login" class="btn-primary">
                    <i class="fas fa-rocket"></i> Start Learning
                </a>
                <a href="#" class="btn-secondary">
                    <i class="fas fa-play-circle"></i> Watch Demo
                </a>
            </div>
        </div>
        
        <div class="hero-image">
            <!-- Replace with your image path -->
            <img src="images/studyhub.png" alt="StudyHub Learning Platform">
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <h2 class="section-title">Why Choose StudyHub?</h2>
        <p class="section-subtitle">Discover the features that make our learning platform stand out from the rest</p>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <h3 class="feature-title">Comprehensive Curriculum</h3>
                <p class="feature-description">Access complete SSC and HSC curriculum materials with interactive lessons and exercises.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3 class="feature-title">Progress Tracking</h3>
                <p class="feature-description">Monitor your learning journey with detailed analytics and performance reports.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <h3 class="feature-title">Certification</h3>
                <p class="feature-description">Earn certificates upon course completion to showcase your achievements.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="feature-title">Expert Instructors</h3>
                <p class="feature-description">Learn from experienced educators dedicated to your academic success.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h3 class="feature-title">Mobile Access</h3>
                <p class="feature-description">Study anytime, anywhere with our mobile-friendly platform.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <h3 class="feature-title">24/7 Support</h3>
                <p class="feature-description">Get help whenever you need it from our dedicated support team.</p>
            </div>
        </div>
    </section>

    <!-- Login Section -->
    <section class="login-section" id="login">
        <div class="login-content">
            <h2 class="login-title">Continue Your Learning Journey</h2>
            <p class="login-description">Sign in to access your personalized dashboard, track your progress, and continue from where you left off.</p>
            
            <ul class="login-features">
                <li><i class="fas fa-check-circle"></i> Access to all courses and materials</li>
                <li><i class="fas fa-check-circle"></i> Track your learning progress</li>
                <li><i class="fas fa-check-circle"></i> Earn certificates upon completion</li>
                <li><i class="fas fa-check-circle"></i> Join interactive quizzes and tests</li>
            </ul>
            
            <div class="hero-stats">
                <div class="stat">
                    <div class="stat-value">5</div>
                    <div class="stat-label">New Today</div>
                </div>
                <div class="stat">
                    <div class="stat-value">13</div>
                    <div class="stat-label">Active Now</div>
                </div>
            </div>
        </div>
        
        <div class="login-form-container">
            <h3 style="margin-bottom: 20px; text-align: center;">Sign In to StudyHub</h3>
            
            <div class="admin-notice">
                <i class="fas fa-shield-alt"></i>
                <strong>Admin Access:</strong> Use predefined credentials for administrator login.
            </div>
            
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
            
            <form method="POST" class="login-form" id="loginForm">
                <div class="form-group">
                    <label for="email" class="input-label">Email Address</label>
                    <input type="email" id="email" name="email" class="input-field" placeholder="student@example.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="input-label">Password</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" class="input-field" placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" id="passwordToggle">
                            
                        </button>
                    </div>
                </div>
                
                <button type="submit" name="submit" class="sign-in-btn">Sign In</button>
            </form>
            
            <div class="footer-links">
                <p>New to StudyHub? <a href="register.php">Create an account</a> • <a href="forget_password.php">Forgot password?</a></p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="logo" style="justify-content: center;">
                <div class="logo-icon">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <div class="logo-text">Study<span>Hub</span></div>
            </div>
            <p>© 2025 StudyHub - Interactive Learning Platform. All rights reserved.</p>
            <div class="footer-links">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Contact Us</a>
                <a href="#">Help Center</a>
            </div>
        </div>
    </footer>

    <script>
        // Password visibility toggle
        const passwordToggle = document.getElementById('passwordToggle');
        const passwordField = document.getElementById('password');
        
        passwordToggle.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
    </script>
</body>
</html>