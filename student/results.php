<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;

// Get user's quiz attempts
$user_attempts = getUserAttempts($user_id);

// If specific attempt is requested, get details
$attempt_details = null;
$quiz_details = null;
$user_answers = null;
$questions = null;

if ($attempt_id > 0) {
    // Get attempt details
    $conn = getDbConnection();
    
    // Get attempt and quiz info
    $stmt = $conn->prepare("
        SELECT qa.*, q.title, q.description 
        FROM quiz_attempts qa 
        JOIN quizzes q ON qa.quiz_id = q.id 
        WHERE qa.id = ? AND qa.user_id = ?
    ");
    $stmt->bind_param("ii", $attempt_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $attempt_details = $result->fetch_assoc();
        $quiz_details = [
            'id' => $attempt_details['quiz_id'],
            'title' => $attempt_details['title'],
            'description' => $attempt_details['description']
        ];
        
        // Get user answers with question details
        $stmt = $conn->prepare("
            SELECT ua.*, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer, q.marks
            FROM user_answers ua
            JOIN questions q ON ua.question_id = q.id
            WHERE ua.attempt_id = ?
            ORDER BY q.id
        ");
        $stmt->bind_param("i", $attempt_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_answers = $result->fetch_all(MYSQLI_ASSOC);
    }
    
    $stmt->close();
    closeDbConnection($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .result-summary {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .result-score {
            font-size: 36px;
            font-weight: 700;
            margin: 10px 0;
        }
        
        .result-percentage {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .answer-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
        }
        
        .answer-card.correct {
            border-left: 5px solid var(--success-color);
        }
        
        .answer-card.incorrect {
            border-left: 5px solid var(--danger-color);
        }
        
        .question-text {
            font-weight: 500;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .options-list {
            list-style: none;
        }
        
        .option-item {
            margin-bottom: 10px;
            padding: 10px;
            border-radius: var(--border-radius);
        }
        
        .option-item.selected {
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        .option-item.correct {
            background-color: rgba(40, 167, 69, 0.1);
        }
        
        .option-item.selected.correct {
            background-color: rgba(40, 167, 69, 0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo SITE_NAME; ?> - Quiz Results</h1>
            <nav>
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="results.php" class="active">My Results</a></li>
                    <li><a href="../logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>
        
        <main>
            <?php if ($attempt_details): ?>
                <div class="result-summary">
                    <h2><?php echo htmlspecialchars($quiz_details['title']); ?> - Results</h2>
                    <div class="result-score">
                        <?php echo $attempt_details['score']; ?> / <?php echo $attempt_details['total_questions']; ?>
                    </div>
                    <div class="result-percentage">
                        <?php 
                            $percentage = ($attempt_details['score'] / $attempt_details['total_questions']) * 100;
                            echo number_format($percentage, 1) . '%';
                        ?>
                    </div>
                    <div class="result-date">
                        Completed on: <?php echo date('F j, Y, g:i a', strtotime($attempt_details['attempt_date'])); ?>
                    </div>
                </div>
                
                <div class="answers-container">
                    <h3>Question Review</h3>
                    
                    <?php foreach ($user_answers as $index => $answer): ?>
                        <div class="answer-card <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                            <div class="question-text">
                                <span class="question-number"><?php echo $index + 1; ?>.</span>
                                <?php echo htmlspecialchars($answer['question_text']); ?>
                                <span class="question-marks">(<?php echo $answer['marks']; ?> mark<?php echo $answer['marks'] > 1 ? 's' : ''; ?>)</span>
                            </div>
                            
                            <ul class="options-list">
                                <li class="option-item <?php echo $answer['selected_answer'] === 'A' ? 'selected' : ''; ?> <?php echo $answer['correct_answer'] === 'A' ? 'correct' : ''; ?>">
                                    A. <?php echo htmlspecialchars($answer['option_a']); ?>
                                    <?php if ($answer['correct_answer'] === 'A'): ?>
                                        <span class="correct-indicator"> ✓ Correct Answer</span>
                                    <?php endif; ?>
                                </li>
                                <li class="option-item <?php echo $answer['selected_answer'] === 'B' ? 'selected' : ''; ?> <?php echo $answer['correct_answer'] === 'B' ? 'correct' : ''; ?>">
                                    B. <?php echo htmlspecialchars($answer['option_b']); ?>
                                    <?php if ($answer['correct_answer'] === 'B'): ?>
                                        <span class="correct-indicator"> ✓ Correct Answer</span>
                                    <?php endif; ?>
                                </li>
                                <li class="option-item <?php echo $answer['selected_answer'] === 'C' ? 'selected' : ''; ?> <?php echo $answer['correct_answer'] === 'C' ? 'correct' : ''; ?>">
                                    C. <?php echo htmlspecialchars($answer['option_c']); ?>
                                    <?php if ($answer['correct_answer'] === 'C'): ?>
                                        <span class="correct-indicator"> ✓ Correct Answer</span>
                                    <?php endif; ?>
                                </li>
                                <li class="option-item <?php echo $answer['selected_answer'] === 'D' ? 'selected' : ''; ?> <?php echo $answer['correct_answer'] === 'D' ? 'correct' : ''; ?>">
                                    D. <?php echo htmlspecialchars($answer['option_d']); ?>
                                    <?php if ($answer['correct_answer'] === 'D'): ?>
                                        <span class="correct-indicator"> ✓ Correct Answer</span>
                                    <?php endif; ?>
                                </li>
                            </ul>
                            
                            <div class="answer-feedback">
                                <?php if ($answer['is_correct']): ?>
                                    <p class="correct-feedback">Your answer is correct!</p>
                                <?php else: ?>
                                    <p class="incorrect-feedback">Your answer is incorrect. The correct answer is option <?php echo $answer['correct_answer']; ?>.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="result-actions">
                    <a href="results.php" class="btn">Back to All Results</a>
                    <a href="index.php" class="btn">Back to Dashboard</a>
                </div>
                
            <?php else: ?>
                <div class="results-list">
                    <h2>My Quiz Results</h2>
                    
                    <?php if (empty($user_attempts)): ?>
                        <p>You haven't taken any quizzes yet.</p>
                        <a href="index.php" class="btn">Go to Dashboard</a>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Quiz</th>
                                    <th>Score</th>
                                    <th>Percentage</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_attempts as $attempt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attempt['title']); ?></td>
                                        <td><?php echo $attempt['score'] . '/' . $attempt['total_questions']; ?></td>
                                        <td>
                                            <?php 
                                                $percentage = ($attempt['score'] / $attempt['total_questions']) * 100;
                                                echo number_format($percentage, 1) . '%';
                                            ?>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($attempt['attempt_date'])); ?></td>
                                        <td>
                                            <a href="results.php?attempt_id=<?php echo $attempt['id']; ?>" class="btn-small">View Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved. <br> Developed by : Amit Kumar Patra</p>
        </footer>
    </div>

    <script src="../js/session-timeout.js"></script>
</body>
</html> 