<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Check if this is a new login
$show_welcome = false;
if (isset($_SESSION['just_logged_in']) && $_SESSION['just_logged_in'] === true) {
    $show_welcome = true;
    // Reset the flag so it only shows once
    $_SESSION['just_logged_in'] = false;
}

// Get all quizzes
$quizzes = getQuizzes();

// Get total number of students
$conn = getDbConnection();
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
$student_count = $result->fetch_assoc()['total'];

// Get total number of quiz attempts
$result = $conn->query("SELECT COUNT(*) as total FROM quiz_attempts");
$attempts_count = $result->fetch_assoc()['total'];

// Get recent quiz attempts
$result = $conn->query("
    SELECT qa.*, u.username, q.title 
    FROM quiz_attempts qa 
    JOIN users u ON qa.user_id = u.id 
    JOIN quizzes q ON qa.quiz_id = q.id 
    ORDER BY qa.attempt_date DESC 
    LIMIT 5
");
$recent_attempts = $result->fetch_all(MYSQLI_ASSOC);

closeDbConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 250px;
            --header-height: 70px;
            --primary-color: #4a6cf7;
            --primary-dark: #3a56d4;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --body-bg: #f5f8ff;
            --card-bg: #ffffff;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            margin: 0;
            padding: 0;
            background-color: var(--body-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(135deg, #4a6cf7 0%, #3a56d4 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: var(--transition);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            color: white;
            margin: 0;
            font-size: 1.5rem;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }

        .menu-item:hover, .menu-item.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: white;
        }

        .menu-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            position: absolute;
            bottom: 0;
            width: 100%;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }

        .user-name {
            font-weight: 500;
        }

        .user-role {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: var(--transition);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .page-title {
            font-size: 1.8rem;
            color: var(--dark-color);
            margin: 0;
        }

        .header-actions .btn {
            margin-left: 10px;
        }

        /* Dashboard Stats */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            display: flex;
            align-items: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
        }

        .stat-icon.blue {
            background-color: rgba(74, 108, 247, 0.1);
            color: var(--primary-color);
        }

        .stat-icon.green {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }

        .stat-icon.orange {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }

        .stat-info {
            flex: 1;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }

        .stat-label {
            color: var(--secondary-color);
            margin: 0;
            font-size: 14px;
        }

        /* Content Cards */
        .content-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .card-title {
            font-size: 1.2rem;
            color: var(--dark-color);
            margin: 0;
        }

        .card-action {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: rgba(74, 108, 247, 0.05);
            color: var(--primary-color);
            font-weight: 600;
        }

        tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }

        .status-badge.warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }

        .status-badge.danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .action-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--box-shadow);
            text-align: center;
            transition: var(--transition);
            text-decoration: none;
            color: var(--dark-color);
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: rgba(74, 108, 247, 0.1);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
        }

        .action-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .action-desc {
            font-size: 14px;
            color: var(--secondary-color);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: visible;
            }

            .sidebar-header h2, .menu-text, .user-info {
                display: none;
            }

            .sidebar-header {
                padding: 15px 0;
            }

            .menu-item {
                padding: 15px 0;
                justify-content: center;
            }

            .menu-item i {
                margin-right: 0;
                font-size: 20px;
            }

            .main-content {
                margin-left: 70px;
            }

            .sidebar-footer {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
        }

        /* Welcome Popup Styles */
        .welcome-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #4a6cf7 0%, #3a56d4 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            transform: translateX(150%);
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }
        
        .welcome-popup.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .welcome-popup h3 {
            margin: 0 0 10px 0;
            color: white;
            font-size: 1.5rem;
        }
        
        .welcome-popup p {
            margin: 0;
            opacity: 0.9;
        }
        
        .welcome-popup .welcome-icon {
            font-size: 24px;
            margin-right: 10px;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <!-- Welcome Popup -->
    <?php if ($show_welcome): ?>
    <div id="welcomePopup" class="welcome-popup">
        <h3><i class="fas fa-hand-sparkles welcome-icon"></i> Welcome, <?php echo $_SESSION['username']; ?>!</h3>
        <p>You have successfully logged in as Administrator.</p>
    </div>
    <?php endif; ?>

    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>MCQ System</h2>
            </div>
            
            <div class="sidebar-menu">
                <a href="index.php" class="menu-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
                <a href="manage_quizzes.php" class="menu-item">
                    <i class="fas fa-question-circle"></i>
                    <span class="menu-text">Manage Quizzes</span>
                </a>
                <a href="view_results.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span class="menu-text">View Results</span>
                </a>
                <a href="../logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="menu-text">Logout</span>
                </a>
            </div>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <div class="user-name"><?php echo $_SESSION['username']; ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Dashboard</h1>
                <div class="header-actions">
                    <a href="manage_quizzes.php?action=add" class="btn">
                        <i class="fas fa-plus"></i> Create Quiz
                    </a>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h2 class="stat-value"><?php echo count($quizzes); ?></h2>
                        <p class="stat-label">Total Quizzes</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h2 class="stat-value"><?php echo $student_count; ?></h2>
                        <p class="stat-label">Students</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="stat-info">
                        <h2 class="stat-value"><?php echo $attempts_count; ?></h2>
                        <p class="stat-label">Quiz Attempts</p>
                    </div>
                </div>
            </div>
            
            <!-- Recent Quizzes -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">Recent Quizzes</h3>
                    <a href="manage_quizzes.php" class="card-action">View All</a>
                </div>
                
                <div class="table-responsive">
                    <?php if (empty($quizzes)): ?>
                        <p>No quizzes available.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Time Limit</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quizzes as $quiz): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($quiz['description'], 0, 50)) . '...'; ?></td>
                                        <td><?php echo $quiz['time_limit'] > 0 ? $quiz['time_limit'] . ' minutes' : 'No limit'; ?></td>
                                        <td><?php echo htmlspecialchars($quiz['creator_name']); ?></td>
                                        <td>
                                            <a href="edit_question.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn-small">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Attempts -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">Recent Quiz Attempts</h3>
                    <a href="view_results.php" class="card-action">View All</a>
                </div>
                
                <div class="table-responsive">
                    <?php if (empty($recent_attempts)): ?>
                        <p>No quiz attempts yet.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Quiz</th>
                                    <th>Score</th>
                                    <th>Percentage</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_attempts as $attempt): ?>
                                    <?php 
                                        $percentage = ($attempt['score'] / $attempt['total_questions']) * 100;
                                        $status_class = $percentage >= 70 ? 'success' : ($percentage >= 40 ? 'warning' : 'danger');
                                        $status_text = $percentage >= 70 ? 'Passed' : ($percentage >= 40 ? 'Average' : 'Failed');
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attempt['username']); ?></td>
                                        <td><?php echo htmlspecialchars($attempt['title']); ?></td>
                                        <td><?php echo $attempt['score'] . '/' . $attempt['total_questions']; ?></td>
                                        <td><?php echo number_format($percentage, 1) . '%'; ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($attempt['attempt_date'])); ?></td>
                                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                
                <div class="quick-actions">
                    <a href="manage_quizzes.php?action=add" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-plus"></i>
                        </div>
                        <h4 class="action-title">Create Quiz</h4>
                        <p class="action-desc">Add a new quiz to the system</p>
                    </a>
                    
                    <a href="view_results.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h4 class="action-title">View Results</h4>
                        <p class="action-desc">Check student performance</p>
                    </a>
                    
                    <a href="manage_quizzes.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-cog"></i>
                        </div>
                        <h4 class="action-title">Manage Quizzes</h4>
                        <p class="action-desc">Edit existing quizzes</p>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../js/session-timeout.js"></script>
    <script>
        // Welcome popup functionality
        document.addEventListener('DOMContentLoaded', function() {
            const welcomePopup = document.getElementById('welcomePopup');
            if (welcomePopup) {
                // Show the popup
                setTimeout(() => {
                    welcomePopup.classList.add('show');
                }, 300);
                
                // Hide the popup after 3 seconds
                setTimeout(() => {
                    welcomePopup.classList.remove('show');
                    // Remove from DOM after animation completes
                    setTimeout(() => {
                        welcomePopup.remove();
                    }, 500);
                }, 3000);
            }
        });
    </script>
</body>
</html> 