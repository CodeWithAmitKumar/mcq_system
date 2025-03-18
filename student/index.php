<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
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

// Get user's quiz attempts
$user_attempts = getUserAttempts($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        <p>You have successfully logged in as Student.</p>
    </div>
    <?php endif; ?>

    <div class="container">
        <header>
            <h1><?php echo SITE_NAME; ?> - Student Dashboard</h1>
            <nav>
                <ul>
                    <li><a href="index.php" class="active">Dashboard</a></li>
                    <li><a href="results.php">My Results</a></li>
                    <li><a href="../logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>
        
        <main>
            <div class="dashboard">
                <h2>Welcome, <?php echo $_SESSION['username']; ?>!</h2>
                
                <div class="dashboard-stats">
                    <div class="stat-box">
                        <h3>Available Quizzes</h3>
                        <p class="stat-number"><?php echo count($quizzes); ?></p>
                    </div>
                    <div class="stat-box">
                        <h3>Completed Quizzes</h3>
                        <p class="stat-number"><?php echo count($user_attempts); ?></p>
                    </div>
                </div>
                
                <div class="available-quizzes">
                    <h3>Available Quizzes</h3>
                    
                    <?php 
                    // Filter out quizzes that the user has already taken
                    $user_id = $_SESSION['user_id'];
                    $available_quizzes = array_filter($quizzes, function($quiz) use ($user_id) {
                        return !hasUserTakenQuiz($user_id, $quiz['id']);
                    });
                    
                    if (empty($available_quizzes)): ?>
                        <p>No new quizzes available. You have completed all available quizzes.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Time Limit</th>
                                    <th>Created By</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($available_quizzes as $quiz): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($quiz['description'], 0, 50)) . '...'; ?></td>
                                        <td><?php echo $quiz['time_limit'] > 0 ? $quiz['time_limit'] . ' minutes' : 'No limit'; ?></td>
                                        <td><?php echo htmlspecialchars($quiz['creator_name']); ?></td>
                                        <td>
                                            <a href="take_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn-small">Take Quiz</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($user_attempts)): ?>
                <div class="recent-attempts">
                    <h3>Recent Quiz Attempts</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Quiz</th>
                                <th>Score</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($user_attempts, 0, 5) as $attempt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attempt['title']); ?></td>
                                    <td><?php echo $attempt['score'] . '/' . $attempt['total_questions']; ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($attempt['attempt_date'])); ?></td>
                                    <td>
                                        <a href="results.php?attempt_id=<?php echo $attempt['id']; ?>" class="btn-small">View Details</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <a href="results.php" class="btn">View All Results</a>
                </div>
                <?php endif; ?>
            </div>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved. <br> Developed by : Amit Kumar Patra</p>
        </footer>
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