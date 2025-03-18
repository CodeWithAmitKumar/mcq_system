<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$message = '';
$error = '';

// Check if quiz exists
$quiz = getQuizById($quiz_id);
if (!$quiz) {
    header("Location: index.php");
    exit;
}

// Check if user has already taken this quiz
if (hasUserTakenQuiz($_SESSION['user_id'], $quiz_id)) {
    $error = "You have already taken this quiz.";
    header("Location: results.php");
    exit;
}

// Get questions for this quiz
$questions = getQuestionsByQuizId($quiz_id);

// Check if quiz has questions
if (empty($questions)) {
    $error = "This quiz doesn't have any questions yet.";
}

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid form submission. Please try again.";
    } else {
        $score = 0;
        $total_questions = count($questions);
        $user_answers = [];
        
        // Calculate score
        foreach ($questions as $question) {
            $question_id = $question['id'];
            $selected_answer = isset($_POST['answer_' . $question_id]) ? $_POST['answer_' . $question_id] : '';
            $is_correct = ($selected_answer === $question['correct_answer']);
            
            if ($is_correct) {
                $score += $question['marks'];
            }
            
            $user_answers[] = [
                'question_id' => $question_id,
                'selected_answer' => $selected_answer,
                'is_correct' => $is_correct
            ];
        }
        
        // Record quiz attempt
        $attempt_id = recordQuizAttempt($_SESSION['user_id'], $quiz_id, $score, $total_questions);
        
        // Record user answers
        if ($attempt_id) {
            foreach ($user_answers as $answer) {
                recordUserAnswer(
                    $attempt_id,
                    $answer['question_id'],
                    $answer['selected_answer'],
                    $answer['is_correct'] ? 1 : 0
                );
            }
            
            // Redirect to results page
            header("Location: results.php?attempt_id=" . $attempt_id);
            exit;
        } else {
            $error = "Failed to submit quiz. Please try again.";
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Quiz - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .question-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
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
        }
        
        .option-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 10px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .option-label:hover {
            background-color: rgba(74, 108, 247, 0.05);
        }
        
        .option-radio {
            margin-right: 10px;
        }
        
        .quiz-timer {
            position: sticky;
            top: 20px;
            background-color: var(--primary-color);
            color: white;
            padding: 15px;
            border-radius: var(--border-radius);
            text-align: center;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: 600;
            box-shadow: var(--box-shadow);
        }
        
        .quiz-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .quiz-info {
            margin-bottom: 30px;
        }
        
        .progress-bar {
            background-color: #f0f0f0;
            border-radius: 20px;
            height: 20px;
            margin: 15px 0;
            position: relative;
            overflow: hidden;
        }
        
        .progress {
            background-color: var(--primary-color);
            height: 100%;
            width: 0;
            transition: width 0.3s ease;
        }
        
        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #333;
            font-size: 12px;
            font-weight: 500;
            text-shadow: 0 0 2px white;
        }
        
        .time-up-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .time-up-content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        
        .time-up-content h3 {
            color: #dc3545;
            margin-bottom: 15px;
        }
        
        .time-up-content p {
            margin: 10px 0;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo SITE_NAME; ?> - Take Quiz</h1>
            <nav>
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="results.php">My Results</a></li>
                    <li><a href="../logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>
        
        <main>
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
                <a href="index.php" class="btn">Back to Dashboard</a>
            <?php elseif (!empty($message)): ?>
                <div class="success-message"><?php echo $message; ?></div>
            <?php else: ?>
                <div class="quiz-info">
                    <h2><?php echo htmlspecialchars($quiz['title']); ?></h2>
                    <p><?php echo htmlspecialchars($quiz['description']); ?></p>
                    <p><strong>Total Questions:</strong> <?php echo count($questions); ?></p>
                    <div class="progress-bar">
                        <div class="progress" id="quiz-progress"></div>
                        <span class="progress-text">Questions Answered: <span id="answered-count">0</span>/<?php echo count($questions); ?></span>
                    </div>
                    <?php if ($quiz['time_limit'] > 0): ?>
                        <p><strong>Time Limit:</strong> <?php echo $quiz['time_limit']; ?> minutes</p>
                        <div class="quiz-timer" id="quiz-timer">
                            Time Remaining: <span id="timer"><?php echo $quiz['time_limit']; ?>:00</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <form id="quiz-form" action="take_quiz.php?quiz_id=<?php echo $quiz_id; ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-card">
                            <div class="question-text">
                                <span class="question-number"><?php echo $index + 1; ?>.</span>
                                <?php echo htmlspecialchars($question['question_text']); ?>
                                <span class="question-marks">(<?php echo $question['marks']; ?> mark<?php echo $question['marks'] > 1 ? 's' : ''; ?>)</span>
                            </div>
                            
                            <ul class="options-list">
                                <li class="option-item">
                                    <label class="option-label">
                                        <input type="radio" name="answer_<?php echo $question['id']; ?>" value="A" class="option-radio" required>
                                        A. <?php echo htmlspecialchars($question['option_a']); ?>
                                    </label>
                                </li>
                                <li class="option-item">
                                    <label class="option-label">
                                        <input type="radio" name="answer_<?php echo $question['id']; ?>" value="B" class="option-radio">
                                        B. <?php echo htmlspecialchars($question['option_b']); ?>
                                    </label>
                                </li>
                                <li class="option-item">
                                    <label class="option-label">
                                        <input type="radio" name="answer_<?php echo $question['id']; ?>" value="C" class="option-radio">
                                        C. <?php echo htmlspecialchars($question['option_c']); ?>
                                    </label>
                                </li>
                                <li class="option-item">
                                    <label class="option-label">
                                        <input type="radio" name="answer_<?php echo $question['id']; ?>" value="D" class="option-radio">
                                        D. <?php echo htmlspecialchars($question['option_d']); ?>
                                    </label>
                                </li>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="quiz-actions">
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" name="submit_quiz" class="btn" onclick="return confirmSubmit()">Submit Quiz</button>
                    </div>
                </form>
            <?php endif; ?>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </footer>
    </div>
    
    <?php if ($quiz['time_limit'] > 0): ?>
    <script>
        // Quiz timer functionality
        document.addEventListener('DOMContentLoaded', function() {
            const timeLimit = <?php echo $quiz['time_limit']; ?>;
            let totalSeconds = timeLimit * 60;
            const timerElement = document.getElementById('timer');
            const quizForm = document.getElementById('quiz-form');
            
            const timer = setInterval(function() {
                totalSeconds--;
                
                if (totalSeconds <= 0) {
                    clearInterval(timer);
                    // Create a hidden input for the submit button
                    const submitInput = document.createElement('input');
                    submitInput.type = 'hidden';
                    submitInput.name = 'submit_quiz';
                    submitInput.value = '1';
                    quizForm.appendChild(submitInput);
                    
                    // Show a more informative popup
                    const popup = document.createElement('div');
                    popup.className = 'time-up-popup';
                    popup.innerHTML = `
                        <div class="time-up-content">
                            <h3>Time's Up!</h3>
                            <p>Your quiz will be submitted automatically with your current answers.</p>
                            <p>Please wait while we process your submission...</p>
                        </div>
                    `;
                    document.body.appendChild(popup);
                    
                    // Submit the form after a short delay
                    setTimeout(() => {
                        quizForm.submit();
                    }, 2000);
                }
                
                const minutes = Math.floor(totalSeconds / 60);
                const seconds = totalSeconds % 60;
                
                timerElement.textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
                
                // Change color when time is running out
                if (totalSeconds < 60) {
                    document.getElementById('quiz-timer').style.backgroundColor = '#dc3545';
                } else if (totalSeconds < 180) {
                    document.getElementById('quiz-timer').style.backgroundColor = '#ffc107';
                }
            }, 1000);
        });
    </script>
    <?php endif; ?>

    <script src="../js/session-timeout.js"></script>
    <script>
        function confirmSubmit() {
            const unanswered = document.querySelectorAll('input[type="radio"]:not(:checked)').length;
            if (unanswered > 0) {
                return confirm(`You have ${unanswered} unanswered questions. Are you sure you want to submit the quiz?`);
            }
            return confirm('Are you sure you want to submit the quiz?');
        }

        // Progress tracking
        document.addEventListener('DOMContentLoaded', function() {
            const radioButtons = document.querySelectorAll('input[type="radio"]');
            const progressBar = document.getElementById('quiz-progress');
            const answeredCount = document.getElementById('answered-count');
            const totalQuestions = <?php echo count($questions); ?>;
            
            function updateProgress() {
                const answered = document.querySelectorAll('input[type="radio"]:checked').length;
                const percentage = (answered / totalQuestions) * 100;
                
                progressBar.style.width = `${percentage}%`;
                answeredCount.textContent = answered;
            }
            
            radioButtons.forEach(radio => {
                radio.addEventListener('change', updateProgress);
            });
            
            // Initial progress update
            updateProgress();
        });
    </script>
</body>
</html> 