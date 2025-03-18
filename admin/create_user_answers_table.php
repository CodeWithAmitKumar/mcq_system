<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

try {
    $conn = getDbConnection();
    
    // Create user_answers table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS user_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        quiz_id INT NOT NULL,
        question_id INT NOT NULL,
        given_answer TEXT NOT NULL,
        is_correct BOOLEAN NOT NULL,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (quiz_id) REFERENCES quizzes(id),
        FOREIGN KEY (question_id) REFERENCES questions(id)
    )";
    
    if ($conn->query($sql)) {
        $_SESSION['success_message'] = "User answers table created successfully!";
    } else {
        $_SESSION['error_message'] = "Error creating table: " . $conn->error;
    }
    
    closeDbConnection($conn);
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
}

header("Location: view_results.php?quiz_id=" . $_GET['quiz_id']);
exit;