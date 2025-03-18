<?php
require_once 'db.php';

// User authentication functions
function registerUser($username, $password, $role) {
    $conn = getDbConnection();
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $hashed_password, $role);
    
    $result = $stmt->execute();
    $stmt->close();
    closeDbConnection($conn);
    
    return $result;
}

function loginUser($username, $password) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $stmt->close();
            closeDbConnection($conn);
            return true;
        }
    }
    
    $stmt->close();
    closeDbConnection($conn);
    return false;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function logout() {
    session_unset();
    session_destroy();
}

// Quiz management functions
function createQuiz($title, $description, $time_limit, $created_by) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("INSERT INTO quizzes (title, description, time_limit, created_by) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssii", $title, $description, $time_limit, $created_by);
    
    $result = $stmt->execute();
    $quiz_id = $conn->insert_id;
    
    $stmt->close();
    closeDbConnection($conn);
    
    return $quiz_id;
}

/**
 * Get all quizzes with creator information
 * 
 * @return array Array of quizzes
 */
function getQuizzes() {
    $conn = getDbConnection();
    
    // Always join with users table to get creator information
    $query = "SELECT q.*, u.username as creator_name 
             FROM quizzes q 
             LEFT JOIN users u ON q.created_by = u.id 
             WHERE q.deleted_at IS NULL
             ORDER BY q.id DESC";
    
    $result = $conn->query($query);
    
    $quizzes = [];
    while ($row = $result->fetch_assoc()) {
        $quizzes[] = $row;
    }
    
    closeDbConnection($conn);
    
    return $quizzes;
}

/**
 * Get a quiz by ID
 * 
 * @param int $quiz_id The ID of the quiz
 * @return array|null The quiz data or null if not found
 */
function getQuizById($quiz_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT * FROM quizzes WHERE id = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        closeDbConnection($conn);
        return null;
    }
    
    $quiz = $result->fetch_assoc();
    $stmt->close();
    closeDbConnection($conn);
    
    return $quiz;
}

// Question management functions
function addQuestion($quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer, marks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssi", $quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks);
    
    $result = $stmt->execute();
    $stmt->close();
    closeDbConnection($conn);
    
    return $result;
}

function getQuestionsByQuizId($quiz_id) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    
    $stmt->close();
    closeDbConnection($conn);
    
    return $questions;
}

function updateQuestion($question_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("UPDATE questions SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ?, marks = ? WHERE id = ?");
    $stmt->bind_param("ssssssis", $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks, $question_id);
    
    $result = $stmt->execute();
    $stmt->close();
    closeDbConnection($conn);
    
    return $result;
}

// Quiz attempt functions
function recordQuizAttempt($user_id, $quiz_id, $score, $total_questions) {
    $conn = getDbConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Record in quiz_attempts table
        $stmt = $conn->prepare("INSERT INTO quiz_attempts (user_id, quiz_id, score, total_questions) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiii", $user_id, $quiz_id, $score, $total_questions);
        $stmt->execute();
        $attempt_id = $conn->insert_id;
        $stmt->close();
        
        // Record in quiz_results table
        $stmt = $conn->prepare("INSERT INTO quiz_results (quiz_id, user_id, score, total_questions, completion_time) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiii", $quiz_id, $user_id, $score, $total_questions);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        closeDbConnection($conn);
        return $attempt_id;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        closeDbConnection($conn);
        return false;
    }
}

function recordUserAnswer($attempt_id, $question_id, $selected_answer, $is_correct) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("INSERT INTO user_answers (attempt_id, question_id, selected_answer, is_correct) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $attempt_id, $question_id, $selected_answer, $is_correct);
    
    $result = $stmt->execute();
    $stmt->close();
    closeDbConnection($conn);
    
    return $result;
}

function getUserAttempts($user_id) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT qa.*, q.title FROM quiz_attempts qa JOIN quizzes q ON qa.quiz_id = q.id WHERE qa.user_id = ? ORDER BY qa.attempt_date DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $attempts = $result->fetch_all(MYSQLI_ASSOC);
    
    $stmt->close();
    closeDbConnection($conn);
    
    return $attempts;
}

/**
 * Get the number of questions for a specific quiz
 * 
 * @param int $quiz_id The ID of the quiz
 * @return int The number of questions
 */
function getQuestionCountByQuizId($quiz_id) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM questions WHERE quiz_id = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'];
    
    $stmt->close();
    closeDbConnection($conn);
    
    return $count;
}

/**
 * Log quiz deletion with reason
 * 
 * @param int $quiz_id The ID of the quiz being deleted
 * @param string $reason The reason for deletion
 * @return bool Whether the log was created successfully
 */
function logQuizDeletion($quiz_id, $reason) {
    // Get quiz details before deletion
    $quiz = getQuizById($quiz_id);
    if (!$quiz) {
        return false;
    }
    
    $admin_id = $_SESSION['user_id'];
    $admin_username = $_SESSION['username'];
    $quiz_title = $quiz['title'];
    $date_deleted = date('Y-m-d H:i:s');
    
    // Create deletion log
    $conn = getDbConnection();
    
    // Check if deletion_logs table exists, create if not
    $result = $conn->query("SHOW TABLES LIKE 'deletion_logs'");
    if ($result->num_rows == 0) {
        // Create the table
        $sql = "CREATE TABLE deletion_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(50) NOT NULL,
            entity_id INT NOT NULL,
            entity_name VARCHAR(255) NOT NULL,
            deleted_by INT NOT NULL,
            deleted_by_username VARCHAR(100) NOT NULL,
            reason TEXT NOT NULL,
            date_deleted DATETIME NOT NULL
        )";
        $conn->query($sql);
    }
    
    // Insert log entry
    $stmt = $conn->prepare("INSERT INTO deletion_logs (entity_type, entity_id, entity_name, deleted_by, deleted_by_username, reason, date_deleted) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $entity_type = 'quiz';
    $stmt->bind_param("sisssss", $entity_type, $quiz_id, $quiz_title, $admin_id, $admin_username, $reason, $date_deleted);
    $result = $stmt->execute();
    $stmt->close();
    
    closeDbConnection($conn);
    return $result;
}

/**
 * Get quiz results with detailed information
 * 
 * @param int $quiz_id The ID of the quiz
 * @return array Array of quiz results with user details
 */
function getQuizResultsWithDetails($quiz_id) {
    $conn = getDbConnection();
    
    // Check if quiz_results table exists
    $tableExists = false;
    $result = $conn->query("SHOW TABLES LIKE 'quiz_results'");
    if ($result->num_rows > 0) {
        $tableExists = true;
    }
    
    // If table doesn't exist, return empty array
    if (!$tableExists) {
        closeDbConnection($conn);
        return [];
    }
    
    try {
        $query = "SELECT r.*, u.username, 
                (SELECT COUNT(*) FROM questions WHERE quiz_id = r.quiz_id) as total_questions
                FROM quiz_results r
                JOIN users u ON r.user_id = u.id
                WHERE r.quiz_id = ?
                ORDER BY r.completion_time DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $results = [];
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        
        $stmt->close();
    } catch (Exception $e) {
        // If there's an error, return empty array
        $results = [];
    }
    
    closeDbConnection($conn);
    
    return $results;
}

/**
 * Format time taken in seconds to a readable format
 * 
 * @param int $seconds Time in seconds
 * @return string Formatted time
 */
function formatTimeTaken($seconds) {
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

/**
 * Get statistics for a specific question
 * 
 * @param int $question_id The ID of the question
 * @return array Array with correct and incorrect counts
 */
function getQuestionStats($question_id) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_answers WHERE question_id = ? AND is_correct = 1");
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $correct = $result->fetch_assoc()['count'];
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_answers WHERE question_id = ? AND is_correct = 0");
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $incorrect = $result->fetch_assoc()['count'];
    $stmt->close();
    
    closeDbConnection($conn);
    
    return [
        'correct' => $correct,
        'incorrect' => $incorrect
    ];
}

/**
 * Check if a user has already taken a specific quiz
 * 
 * @param int $user_id The ID of the user
 * @param int $quiz_id The ID of the quiz
 * @return bool True if the user has taken the quiz, false otherwise
 */
function hasUserTakenQuiz($user_id, $quiz_id) {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM quiz_attempts WHERE user_id = ? AND quiz_id = ?");
    $stmt->bind_param("ii", $user_id, $quiz_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $stmt->close();
    closeDbConnection($conn);
    
    return $row['count'] > 0;
}

/**
 * Generate a CSRF token
 * 
 * @return string The generated token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token
 * 
 * @param string $token The token to validate
 * @return bool True if token is valid, false otherwise
 */
function validateCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

?> 