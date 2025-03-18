<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get quiz ID and token from URL
$quiz_id = isset($_GET['quiz']) ? (int)$_GET['quiz'] : 0;
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Validate quiz and token
$quiz = getQuizById($quiz_id);
$valid_token = false;

if ($quiz) {
    // Generate the expected token for validation
    $expected_token = md5($quiz_id . $quiz['title'] . 'secret_salt');
    $valid_token = ($token === $expected_token);
}

// If quiz doesn't exist or token is invalid, redirect to home
if (!$quiz || !$valid_token) {
    header("Location: index.php");
    exit;
}

// If user is not logged in, show login/register options
$show_login_options = !isLoggedIn();

// If user is logged in, redirect to the quiz
if (isLoggedIn() && !$show_login_options) {
    header("Location: student/take_quiz.php?quiz_id=" . $quiz_id);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Quiz - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .quiz-preview {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .quiz-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .quiz-header h2 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .quiz-details {
            margin-bottom: 30px;
            padding: 20px;
            background-color: rgba(74, 108, 247, 0.05);
            border-radius: var(--border-radius);
        }
        
        .quiz-details p {
            margin-bottom: 10px;
        }
        
        .quiz-details .label {
            font-weight: bold;
            color: var(--primary-dark);
        }
        
        .login-options {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 30px;
        }
        
        .option-card {
            padding: 20px;
            border-radius: var(--border-radius);
            background-color: var(--light-color);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .option-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .option-icon {
            width: 60px;
            height: 60px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .option-content {
            flex: 1;
        }
        
        .option-content h3 {
            margin-bottom: 5px;
            color: var(--dark-color);
        }
        
        .option-content p {
            color: var(--secondary-color);
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo SITE_NAME; ?></h1>
        </header>
        
        <main>
            <div class="quiz-preview">
                <div class="quiz-header">
                    <h2>You've been invited to take a quiz</h2>
                    <p>Please log in or register to continue</p>
                </div>
                
                <div class="quiz-details">
                    <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                    <p><?php echo htmlspecialchars($quiz['description']); ?></p>
                    <p><span class="label">Time Limit:</span> <?php echo $quiz['time_limit'] > 0 ? $quiz['time_limit'] . ' minutes' : 'No time limit'; ?></p>
                    <p><span class="label">Questions:</span> <?php echo getQuestionCountByQuizId($quiz_id); ?> questions</p>
                </div>
                
                <?php if ($show_login_options): ?>
                    <div class="login-options">
                        <a href="login.php?redirect=take_shared_quiz.php?quiz=<?php echo $quiz_id; ?>&token=<?php echo $token; ?>" class="option-card">
                            <div class="option-icon">
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                            <div class="option-content">
                                <h3>Login</h3>
                                <p>Already have an account? Log in to take the quiz.</p>
                                <button class="btn">Login Now</button>
                            </div>
                        </a>
                        
                        <a href="register.php?redirect=take_shared_quiz.php?quiz=<?php echo $quiz_id; ?>&token=<?php echo $token; ?>" class="option-card">
                            <div class="option-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="option-content">
                                <h3>Register</h3>
                                <p>Don't have an account? Register to take the quiz.</p>
                                <button class="btn">Register Now</button>
                            </div>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </footer>
    </div>
</body>
</html> 