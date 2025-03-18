<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Get attempt ID from URL
$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;

// Check if attempt exists
$attempt = null;
$user = null;
$quiz = null;
$answers = [];
$questions = [];

try {
    $conn = getDbConnection();
    
    // Check if user_answers table exists
    $tableExists = false;
    $result = $conn->query("SHOW TABLES LIKE 'user_answers'");
    if ($result) {
        $tableExists = ($result->num_rows > 0);
    }
    
    // Get attempt details
    $stmt = $conn->prepare("
        SELECT r.*, u.username, q.title as quiz_title
        FROM quiz_results r
        JOIN users u ON r.user_id = u.id
        JOIN quizzes q ON r.quiz_id = q.id
        WHERE r.id = ?
    ");
    $stmt->bind_param("i", $attempt_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $attempt = $result->fetch_assoc();
        $quiz_id = $attempt['quiz_id'];
        
        // Get questions for this quiz
        $stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $questions[$row['id']] = $row;
        }
        
        // Only try to get user answers if the table exists
        if ($tableExists) {
            // Get user answers for this attempt
            $stmt = $conn->prepare("
                SELECT * FROM user_answers 
                WHERE attempt_id = ?
                ORDER BY question_id ASC
            ");
            $stmt->bind_param("i", $attempt_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $answers[$row['question_id']] = $row;
            }
        }
    } else {
        header("Location: manage_quizzes.php");
        exit;
    }
    
    closeDbConnection($conn);
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

// Function to format time
function formatTime($seconds) {
    if ($seconds < 60) {
        return $seconds . " seconds";
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $remaining_seconds = $seconds % 60;
        return $minutes . " min " . $remaining_seconds . " sec";
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remaining_seconds = $seconds % 60;
        return $hours . " hr " . $minutes . " min " . $remaining_seconds . " sec";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attempt Details - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .attempt-header {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .attempt-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
        }
        
        .attempt-meta-item {
            display: flex;
            align-items: center;
        }
        
        .attempt-meta-item i {
            margin-right: 8px;
            color: #4a6cf7;
        }
        
        .question-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .question-number {
            font-weight: bold;
            color: #4a6cf7;
            margin-bottom: 10px;
        }
        
        .question-text {
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .answer-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .answer {
            display: flex;
            align-items: flex-start;
            padding: 10px;
            border-radius: 4px;
        }
        
        .answer.correct {
            background-color: #d4edda;
        }
        
        .answer.incorrect {
            background-color: #f8d7da;
        }
        
        .answer-label {
            font-weight: bold;
            margin-right: 10px;
            min-width: 100px;
        }
        
        .answer-text {
            flex-grow: 1;
        }
        
        .answer-icon {
            margin-left: 10px;
        }
        
        .answer-icon.correct {
            color: #28a745;
        }
        
        .answer-icon.incorrect {
            color: #dc3545;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background-color: #6c757d;
            color: white;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.2s;
            margin-bottom: 20px;
        }
        
        .back-button i {
            margin-right: 8px;
        }
        
        .back-button:hover {
            background-color: #5a6268;
        }
        
        .score-summary {
            display: flex;
            align-items: center;
            margin-top: 15px;
        }
        
        .score-pill {
            background-color: #4a6cf7;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .progress-container {
            flex-grow: 1;
            max-width: 300px;
        }
        
        @media screen and (max-width: 768px) {
            .attempt-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .score-summary {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .progress-container {
                width: 100%;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <div class="dashboard-content">
            <header>
                <h1>Attempt Details</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['username']; ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>
            
            <main>
                <a href="view_results.php?quiz_id=<?php echo $attempt['quiz_id']; ?>" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back to Results
                </a>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <p><?php echo $error; ?></p>
                    </div>
                <?php elseif (!$tableExists): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <p>The user_answers table is missing. Please run the <a href="create_user_answers_table.php" style="color: #0c5460; text-decoration: underline;">database setup script</a> to enable detailed answer tracking.</p>
                    </div>
                <?php else: ?>
                    <div class="attempt-header">
                        <h2><?php echo htmlspecialchars($attempt['quiz_title']); ?></h2>
                        <div class="attempt-meta">
                            <div class="attempt-meta-item">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($attempt['username']); ?></span>
                            </div>
                            <div class="attempt-meta-item">
                                <i class="fas fa-clock"></i>
                                <span><?php echo $attempt['time_taken'] ? formatTime($attempt['time_taken']) : 'N/A'; ?></span>
                            </div>
                            <div class="attempt-meta-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('F d, Y H:i', strtotime($attempt['completion_time'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="score-summary">
                            <div class="score-pill">
                                <?php echo $attempt['score']; ?>/<?php echo count($questions); ?>
                            </div>
                            
                            <?php 
                                $percentage = count($questions) > 0 ? 
                                    round(($attempt['score'] / count($questions)) * 100, 2) : 0;
                            ?>
                            
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress" style="width: <?php echo $percentage; ?>%"></div>
                                    <span><?php echo $percentage; ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php 
                    $question_number = 1;
                    foreach ($questions as $question_id => $question): 
                        $user_answer = isset($answers[$question_id]) ? $answers[$question_id]['selected_answer'] : 'No answer data available';
                        $is_correct = isset($answers[$question_id]) && isset($answers[$question_id]['is_correct']) && $answers[$question_id]['is_correct'] == 1;
                    ?>
                        <div class="question-card">
                            <div class="question-number">Question <?php echo $question_number; ?></div>
                            <div class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></div>
                            
                            <div class="answer-container">
                                <?php if (!$tableExists): ?>
                                <div class="alert alert-info" style="margin-bottom: 0;">
                                    <i class="fas fa-info-circle"></i>
                                    <p>Detailed answer data is not available. The user_answers table is missing.</p>
                                </div>
                                <?php else: ?>
                                <div class="answer <?php echo $is_correct ? 'correct' : 'incorrect'; ?>">
                                    <div class="answer-label">User Answer:</div>
                                    <div class="answer-text"><?php echo htmlspecialchars($user_answer); ?></div>
                                    <div class="answer-icon <?php echo $is_correct ? 'correct' : 'incorrect'; ?>">
                                        <i class="fas <?php echo $is_correct ? 'fa-check' : 'fa-times'; ?>"></i>
                                    </div>
                                </div>
                                
                                <?php if (!$is_correct): ?>
                                <div class="answer correct">
                                    <div class="answer-label">Correct Answer:</div>
                                    <div class="answer-text"><?php echo htmlspecialchars($question['correct_answer']); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php 
                        $question_number++;
                    endforeach; 
                    ?>
                <?php endif; ?>
            </main>
            
            <footer>
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            </footer>
        </div>
    </div>
    
    <script src="../js/session-timeout.js"></script>
</body>
</html> 