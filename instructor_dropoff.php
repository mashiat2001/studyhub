<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'instructor') {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$conn = new mysqli("localhost", "root", "", "project_db");

// Get instructor's courses
$courses = [];
$stmt = $conn->prepare("SELECT id, title FROM courses WHERE instructor_id = ? ORDER BY title");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get selected course
$selected_course_id = $_GET['course_id'] ?? ($courses[0]['id'] ?? 0);

// Engagement data for selected course
$lectures = [];
$drop_messages = [];
if ($selected_course_id) {
    // Get all files (lectures) for this course, ordered by uploaded_at (or you could have an order_index)
    $file_stmt = $conn->prepare("
        SELECT id, file_name, uploaded_at 
        FROM course_files 
        WHERE course_id = ? 
        ORDER BY uploaded_at
    ");
    $file_stmt->bind_param("i", $selected_course_id);
    $file_stmt->execute();
    $files = $file_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // For each file, count unique students who viewed it
    $lectures = [];
    $previous_views = null;
    foreach ($files as $file) {
        $view_stmt = $conn->prepare("
            SELECT COUNT(DISTINCT user_id) as views 
            FROM course_content_views 
            WHERE course_id = ? AND file_id = ?
        ");
        $view_stmt->bind_param("ii", $selected_course_id, $file['id']);
        $view_stmt->execute();
        $views = $view_stmt->get_result()->fetch_assoc()['views'];

        $lecture = [
            'id' => $file['id'],
            'name' => $file['file_name'],
            'views' => $views,
            'drop' => false,
            'drop_percent' => 0
        ];

        // Calculate drop if previous exists
        if ($previous_views !== null && $previous_views > 0) {
            $drop = $previous_views - $views;
            $percent = round(($drop / $previous_views) * 100, 1);
            if ($percent >= 20) { // significant drop threshold (adjustable)
                $lecture['drop'] = true;
                $lecture['drop_percent'] = $percent;
                $drop_messages[] = "Lecture " . (count($lectures)+1) . " – drop of {$percent}%";
            }
        }

        $lectures[] = $lecture;
        $previous_views = $views;
    }
}

$conn->close();

// Chart data
$lecture_labels = [];
$view_counts = [];
$drop_colors = [];
foreach ($lectures as $i => $l) {
    $lecture_labels[] = "L" . ($i+1);
    $view_counts[] = $l['views'];
    $drop_colors[] = $l['drop'] ? '#f56565' : '#5a67d8'; // red for drop, blue normal
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Drop-Off Detection - StudyHub</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #5a67d8;
            --primary-light: #7c8cf0;
            --primary-dark: #434190;
            --secondary: #ed8936;
            --success: #48bb78;
            --warning: #ecc94b;
            --danger: #f56565;
            --text-dark: #1a202c;
            --text-light: #4a5568;
            --text-muted: #718096;
            --light-bg: #f7fafc;
            --border-light: #e2e8f0;
            --border-radius: 10px;
            --shadow: 0 2px 5px rgba(0,0,0,0.05);
            --shadow-md: 0 5px 15px rgba(0,0,0,0.07);
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
            line-height: 1.5;
        }
        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-light);
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        .logo-text {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        .logo-text span {
            color: var(--primary);
        }
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .logout-btn {
            background: var(--primary);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .logout-btn:hover {
            background: var(--primary-dark);
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .back-link {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1.25rem;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .back-link:hover {
            background: var(--primary-dark);
        }
        .controls {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        select {
            padding: 0.6rem 1rem;
            border: 1px solid var(--border-light);
            border-radius: 30px;
            background: white;
            font-size: 1rem;
            min-width: 250px;
        }
        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 30px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        .btn:hover {
            background: var(--primary-dark);
        }
        .chart-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }
        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .drop-alert {
            background: #fed7d7;
            border-left: 4px solid var(--danger);
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        .drop-alert h4 {
            color: #9b2c2c;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .drop-alert ul {
            list-style: none;
        }
        .drop-alert li {
            padding: 0.25rem 0;
            color: #742a2a;
        }
        .table-responsive {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }
        th {
            background: var(--light-bg);
            font-weight: 600;
        }
        .badge-drop {
            background: var(--danger);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
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
        <div class="user-menu">
            <a href="instructor_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-chart-line"></i> Student Drop‑Off Detection</h1>
        </div>

        <div class="controls">
            <form method="get" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <select name="course_id" onchange="this.form.submit()">
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $selected_course_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="btn">Load</button></noscript>
            </form>
            <?php if ($selected_course_id): ?>
                <a href="instructor_dropoff.php?course_id=<?php echo $selected_course_id; ?>" class="btn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($lectures)): ?>
            <div class="chart-card" style="text-align: center; padding: 3rem;">
                <i class="fas fa-chart-bar" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                <h3>No lecture data available</h3>
                <p>Upload course materials to start tracking engagement.</p>
            </div>
        <?php else: ?>
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-users" style="color: var(--primary);"></i>
                    Student Views per Lecture (unique students)
                </div>
                <canvas id="engagementChart" style="max-height: 300px; width:100%;"></canvas>
            </div>

            <?php if (!empty($drop_messages)): ?>
                <div class="drop-alert">
                    <h4><i class="fas fa-exclamation-triangle"></i> Significant Drop‑Off Points Detected</h4>
                    <ul>
                        <?php foreach ($drop_messages as $msg): ?>
                            <li>⚠️ <?php echo $msg; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="margin-top:0.5rem; font-size:0.9rem;">
                        Consider reviewing these lectures to improve engagement.
                    </p>
                </div>
            <?php else: ?>
                <div style="background: #e6fffa; border-left: 4px solid #38b2ac; padding: 1rem; border-radius: 8px;">
                    <p><i class="fas fa-check-circle" style="color:#319795;"></i> No significant drop‑offs detected. Great job!</p>
                </div>
            <?php endif; ?>

            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-list"></i> Lecture Details
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Lecture</th>
                                <th>File Name</th>
                                <th>Unique Views</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lectures as $i => $l): ?>
                                <tr>
                                    <td>Lecture <?php echo $i+1; ?></td>
                                    <td><?php echo htmlspecialchars($l['name']); ?></td>
                                    <td><?php echo $l['views']; ?></td>
                                    <td>
                                        <?php if ($l['drop']): ?>
                                            <span class="badge-drop">Drop <?php echo $l['drop_percent']; ?>%</span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">Stable</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        <?php if (!empty($lectures)): ?>
        const ctx = document.getElementById('engagementChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($lecture_labels); ?>,
                datasets: [{
                    label: 'Unique Students',
                    data: <?php echo json_encode($view_counts); ?>,
                    backgroundColor: <?php echo json_encode($drop_colors); ?>,
                    borderWidth: 0,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (ctx) => `${ctx.raw} students` } }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Views' },
                        grid: { color: '#e2e8f0' }
                    },
                    x: {
                        title: { display: true, text: 'Lecture' }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>