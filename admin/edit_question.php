<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$message = '';
$error = '';
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

// Check if quiz exists
$quiz = getQuizById($quiz_id);
if (!$quiz) {
    header("Location: manage_quizzes.php");
    exit;
}

// Handle question addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_question') {
    $question_text = $_POST['question_text'] ?? '';
    $option_a = $_POST['option_a'] ?? '';
    $option_b = $_POST['option_b'] ?? '';
    $option_c = $_POST['option_c'] ?? '';
    $option_d = $_POST['option_d'] ?? '';
    $correct_answer = $_POST['correct_answer'] ?? '';
    $marks = (int)($_POST['marks'] ?? 1);
    
    if (empty($question_text) || empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d) || empty($correct_answer)) {
        $error = "All fields are required.";
    } else {
        $result = addQuestion($quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks);
        if ($result) {
            $message = "Question added successfully!";
        } else {
            $error = "Failed to add question.";
        }
    }
}

// Handle question update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_question') {
    $question_id = (int)($_POST['question_id'] ?? 0);
    $question_text = $_POST['question_text'] ?? '';
    $option_a = $_POST['option_a'] ?? '';
    $option_b = $_POST['option_b'] ?? '';
    $option_c = $_POST['option_c'] ?? '';
    $option_d = $_POST['option_d'] ?? '';
    $correct_answer = $_POST['correct_answer'] ?? '';
    $marks = (int)($_POST['marks'] ?? 1);
    
    if (empty($question_text) || empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d) || empty($correct_answer)) {
        $error = "All fields are required.";
    } else {
        $result = updateQuestion($question_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks);
        if ($result) {
            $message = "Question updated successfully!";
        } else {
            $error = "Failed to update question.";
        }
    }
}

// Get all questions for this quiz
$questions = getQuestionsByQuizId($quiz_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Questions - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo SITE_NAME; ?> - Manage Questions</h1>
            <nav>
                <ul>
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="manage_quizzes.php">Manage Quizzes</a></li>
                    <li><a href="view_results.php">View Results</a></li>
                    <li><a href="../logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>
        
        <main>
            <?php if (!empty($message)): ?>
                <div class="success-message"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="quiz-info">
                <h2>Quiz: <?php echo htmlspecialchars($quiz['title']); ?></h2>
                <p><?php echo htmlspecialchars($quiz['description']); ?></p>
                <p>Time Limit: <?php echo $quiz['time_limit'] > 0 ? $quiz['time_limit'] . ' minutes' : 'No limit'; ?></p>
            </div>
            
            <div class="form-container">
                <h3>Add New Question</h3>
                
                <form action="edit_question.php?quiz_id=<?php echo $quiz_id; ?>" method="post">
                    <input type="hidden" name="action" value="add_question">
                    
                    <div class="form-group">
                        <label for="question_text">Question:</label>
                        <textarea id="question_text" name="question_text" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="option_a">Option A:</label>
                        <input type="text" id="option_a" name="option_a" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="option_b">Option B:</label>
                        <input type="text" id="option_b" name="option_b" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="option_c">Option C:</label>
                        <input type="text" id="option_c" name="option_c" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="option_d">Option D:</label>
                        <input type="text" id="option_d" name="option_d" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="correct_answer">Correct Answer:</label>
                        <input type="text" id="correct_answer" name="correct_answer" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="marks">Marks:</label>
                        <input type="number" id="marks" name="marks" min="1" value="1" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Add Question</button>
                    </div>
                </form>
            </div>
            
            <div class="questions-list">
                <h3>All Questions</h3>
                
                <?php if (empty($questions)): ?>
                    <p>No questions available.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Question</th>
                                <th>Option A</th>
                                <th>Option B</th>
                                <th>Option C</th>
                                <th>Option D</th>
                                <th>Correct Answer</th>
                                <th>Marks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($questions as $question): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($question['question_text']); ?></td>
                                    <td><?php echo htmlspecialchars($question['option_a']); ?></td>
                                    <td><?php echo htmlspecialchars($question['option_b']); ?></td>
                                    <td><?php echo htmlspecialchars($question['option_c']); ?></td>
                                    <td><?php echo htmlspecialchars($question['option_d']); ?></td>
                                    <td><?php echo htmlspecialchars($question['correct_answer']); ?></td>
                                    <td><?php echo htmlspecialchars($question['marks']); ?></td>
                                    <td>
                                        <a href="edit_question.php?quiz_id=<?php echo $quiz_id; ?>&question_id=<?php echo $question['id']; ?>" class="btn-small">Edit</a>
                                        <a href="edit_question.php?quiz_id=<?php echo $quiz_id; ?>&question_id=<?php echo $question['id']; ?>&action=delete" class="btn-small btn-delete">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </footer>
    </div>

    <script src="../js/session-timeout.js"></script>
</body>
</html> 