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
$share_link = '';

// Get all quizzes for display
$quizzes = getQuizzes();

// Debug: Print the first quiz to see its structure
if (!empty($quizzes)) {
    echo "<pre style='display:none;'>";
    print_r($quizzes[0]); 
    echo "</pre>";
}

// Get quiz data for editing
$edit_quiz = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['quiz_id'])) {
    $quiz_id = (int)$_GET['quiz_id'];
    $edit_quiz = getQuizById($quiz_id);
    
    if (!$edit_quiz) {
        $error = "Quiz not found.";
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new quiz
    // In the add quiz section, modify the INSERT query
    if (isset($_POST['add_quiz'])) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $time_limit = (int)($_POST['time_limit'] ?? 0);
        $creator_id = $_SESSION['user_id']; // Get current admin's ID
        
        if (empty($title)) {
            $error = "Quiz title is required.";
        } else {
            $conn = getDbConnection();
            $stmt = $conn->prepare("INSERT INTO quizzes (title, description, time_limit, created_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssii", $title, $description, $time_limit, $creator_id);
            
            if ($stmt->execute()) {
                $quiz_id = $conn->insert_id;
                $message = "Quiz added successfully.";
                header("Location: edit_question.php?quiz_id=" . $quiz_id);
                exit;
            } else {
                $error = "Failed to add quiz: " . $conn->error;
            }
            
            $stmt->close();
            closeDbConnection($conn);
        }
    }
    
    // Update existing quiz
    if (isset($_POST['update_quiz'])) {
        $quiz_id = (int)$_POST['quiz_id'];
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $time_limit = (int)($_POST['time_limit'] ?? 0);
        
        if (empty($title)) {
            $error = "Quiz title is required.";
        } else {
            $conn = getDbConnection();
            $stmt = $conn->prepare("UPDATE quizzes SET title = ?, description = ?, time_limit = ? WHERE id = ?");
            $stmt->bind_param("ssii", $title, $description, $time_limit, $quiz_id);
            
            if ($stmt->execute()) {
                $message = "Quiz updated successfully.";
                // Redirect to prevent form resubmission
                header("Location: manage_quizzes.php?message=" . urlencode($message));
                exit;
            } else {
                $error = "Failed to update quiz: " . $conn->error;
            }
            
            $stmt->close();
            closeDbConnection($conn);
        }
    }
    
    // Delete quiz
    if (isset($_POST['delete_quiz'])) {
        $quiz_id = (int)$_POST['quiz_id'];
        $delete_reason = trim($_POST['delete_reason'] ?? '');
        
        // Log the deletion with reason
        logQuizDeletion($quiz_id, $delete_reason);
        
        $conn = getDbConnection();
        
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // First, get all questions for this quiz
            $stmt = $conn->prepare("SELECT id FROM questions WHERE quiz_id = ?");
            $stmt->bind_param("i", $quiz_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $question_ids = [];
            while ($row = $result->fetch_assoc()) {
                $question_ids[] = $row['id'];
            }
            $stmt->close();
            
            // Delete user answers for these questions
            if (!empty($question_ids)) {
                $placeholders = str_repeat('?,', count($question_ids) - 1) . '?';
                
                // Check if user_answers table exists
                $result = $conn->query("SHOW TABLES LIKE 'user_answers'");
                if ($result->num_rows > 0) {
                    $stmt = $conn->prepare("DELETE FROM user_answers WHERE question_id IN ($placeholders)");
                    $types = str_repeat('i', count($question_ids));
                    $stmt->bind_param($types, ...$question_ids);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            // Check if quiz_results table exists
            $result = $conn->query("SHOW TABLES LIKE 'quiz_results'");
            if ($result->num_rows > 0) {
                // Delete quiz results
                $stmt = $conn->prepare("DELETE FROM quiz_results WHERE quiz_id = ?");
                $stmt->bind_param("i", $quiz_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Check if quiz_attempts table exists
            $result = $conn->query("SHOW TABLES LIKE 'quiz_attempts'");
            if ($result->num_rows > 0) {
                // Delete quiz attempts
                $stmt = $conn->prepare("DELETE FROM quiz_attempts WHERE quiz_id = ?");
                $stmt->bind_param("i", $quiz_id);
                $stmt->execute();
                $stmt->close();
            }
            
            // Delete questions
            $stmt = $conn->prepare("DELETE FROM questions WHERE quiz_id = ?");
            $stmt->bind_param("i", $quiz_id);
            $stmt->execute();
            $stmt->close();
            
            // Finally delete the quiz
            $stmt = $conn->prepare("DELETE FROM quizzes WHERE id = ?");
            $stmt->bind_param("i", $quiz_id);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $message = "Quiz deleted successfully.";
            header("Location: manage_quizzes.php?message=" . urlencode($message));
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = "Failed to delete quiz: " . $e->getMessage();
        }
        
        closeDbConnection($conn);
    }
}

// Handle share link generation
if (isset($_GET['action']) && $_GET['action'] === 'share' && isset($_GET['quiz_id'])) {
    $quiz_id = (int)$_GET['quiz_id'];
    $quiz = getQuizById($quiz_id);
    
    if ($quiz) {
        // Generate a secure random token
        $token = bin2hex(random_bytes(16));
        
        $conn = getDbConnection();
        
        // Check if quiz_share_tokens table exists
        $tableExists = false;
        $result = $conn->query("SHOW TABLES LIKE 'quiz_share_tokens'");
        if ($result->num_rows > 0) {
            $tableExists = true;
        }
        
        // Store the token with the quiz_id
        if ($tableExists) {
            // Store the token in the database with an expiration time
            $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
            $stmt = $conn->prepare("INSERT INTO quiz_share_tokens (quiz_id, token, created_at, expires_at) VALUES (?, ?, NOW(), ?)");
            $stmt->bind_param("iss", $quiz_id, $token, $expiry);
            
            if ($stmt->execute()) {
                // Generate a share link
                $share_link = BASE_URL . 'take_shared_quiz.php?quiz=' . $quiz_id . '&token=' . $token;
            } else {
                // Fallback to simple token if the table doesn't exist
                $share_link = BASE_URL . 'take_shared_quiz.php?quiz=' . $quiz_id . '&token=' . md5($quiz_id . $quiz['title'] . 'secret_salt');
            }
        } else {
            // Fallback to simple token if the table doesn't exist
            $share_link = BASE_URL . 'take_shared_quiz.php?quiz=' . $quiz_id . '&token=' . md5($quiz_id . $quiz['title'] . 'secret_salt');
        }
        
        closeDbConnection($conn);
    } else {
        $error = "Quiz not found.";
    }
}

// Check for message in URL (after redirect)
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Get all quizzes for display
$quizzes = getQuizzes();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quizzes - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Enhanced Styles for Manage Quizzes */
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .content-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .content-card:hover {
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background-color: rgba(74, 108, 247, 0.05);
            border-bottom: 1px solid var(--border-color);
        }
        
        .card-header h2 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.5rem;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.1);
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            transition: background-color 0.3s, transform 0.3s;
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.875rem;
            margin-right: 5px;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            animation: fadeIn 0.5s;
        }
        
        .success-message {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        
        .error-message {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        table th, table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        table th {
            background-color: rgba(74, 108, 247, 0.05);
            font-weight: 600;
            color: var(--primary-dark);
        }
        
        table tr:hover {
            background-color: rgba(74, 108, 247, 0.02);
        }
        
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .actions .btn-small {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 90px;
        }
        
        .actions .btn-small i {
            margin-right: 5px;
        }
        
        /* Share Modal Styles */
        .share-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .share-modal.show {
            opacity: 1;
        }
        
        .share-modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            transform: translateY(20px);
            transition: transform 0.3s;
        }
        
        .share-modal.show .share-modal-content {
            transform: translateY(0);
        }
        
        .share-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .share-modal-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.5rem;
        }
        
        .share-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--secondary-color);
            transition: color 0.3s;
        }
        
        .share-modal-close:hover {
            color: var(--danger-color);
        }
        
        .share-link-container {
            display: flex;
            margin: 20px 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .share-link-input {
            flex: 1;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-right: none;
            border-radius: var(--border-radius) 0 0 var(--border-radius);
            font-size: 14px;
        }
        
        .copy-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            transition: background-color 0.3s;
        }
        
        .copy-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .copy-btn i {
            margin-right: 8px;
        }
        
        #copySuccess {
            display: none;
            padding: 10px 15px;
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.2);
            color: #28a745;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            animation: fadeIn 0.3s;
        }
        
        .share-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 25px;
        }
        
        .share-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: background-color 0.3s, transform 0.3s;
        }
        
        .share-option:hover {
            background-color: rgba(74, 108, 247, 0.05);
            transform: translateY(-3px);
        }
        
        .share-option i {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .share-option.email i {
            color: #d44638;
        }
        
        .share-option.whatsapp i {
            color: #25d366;
        }
        
        .share-option.telegram i {
            color: #0088cc;
        }
        
        .share-option span {
            font-size: 14px;
            color: var(--text-color);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .actions .btn-small {
                width: 100%;
            }
            
            .share-options {
                grid-template-columns: 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
        
        /* Delete confirmation modal */
        .delete-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .delete-modal.show {
            opacity: 1;
        }
        
        .delete-modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }
        
        .delete-modal.show .delete-modal-content {
            transform: translateY(0);
        }
        
        .delete-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .delete-modal-header h3 {
            margin: 0;
            color: #dc3545;
        }
        
        .delete-modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #6c757d;
        }
        
        .delete-modal-body {
            margin-bottom: 20px;
        }
        
        .delete-reason-field {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }
        
        .delete-reason-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .delete-reason-field textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            min-height: 80px;
            resize: vertical;
        }
        
        .delete-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-delete:hover {
            background-color: #c82333;
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-cancel:hover {
            background-color: #5a6268;
        }
        
        .btn-small.btn-danger {
            background-color: #dc3545;
        }
        
        .btn-small.btn-danger:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <!-- Share Modal -->
    <div id="shareModal" class="share-modal">
        <div class="share-modal-content">
            <div class="share-modal-header">
                <h3><i class="fas fa-share-alt"></i> Share Quiz</h3>
                <button class="share-modal-close" onclick="closeShareModal()">&times;</button>
            </div>
            
            <p>Share this link with students to let them take the quiz:</p>
            
            <div id="copySuccess">
                <i class="fas fa-check-circle"></i> Link copied to clipboard!
            </div>
            
            <div class="share-link-container">
                <input type="text" id="shareLink" class="share-link-input" readonly>
                <button class="copy-btn" onclick="copyShareLink()">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>
            
            <p>Or share directly via:</p>
            
            <div class="share-options">
                <div class="share-option email" onclick="shareViaEmail()">
                    <i class="fas fa-envelope"></i>
                    <span>Email</span>
                </div>
                
                <div class="share-option whatsapp" onclick="shareViaWhatsApp()">
                    <i class="fab fa-whatsapp"></i>
                    <span>WhatsApp</span>
                </div>
                
                <div class="share-option telegram" onclick="shareViaTelegram()">
                    <i class="fab fa-telegram-plane"></i>
                    <span>Telegram</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="delete-modal">
        <div class="delete-modal-content">
            <div class="delete-modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Delete Quiz</h3>
                <button class="delete-modal-close" onclick="closeDeleteModal()">×</button>
            </div>
            <div class="delete-modal-body">
                <p>Are you sure you want to delete the quiz "<span id="deleteQuizTitle"></span>"?</p>
                <p><strong>Warning:</strong> This action cannot be undone. All questions, student results, and attempts for this quiz will be permanently deleted.</p>
                
                <div class="delete-reason-field">
                    <label for="deleteReason">Reason for deletion:</label>
                    <textarea id="deleteReason" name="delete_reason" placeholder="Please provide a reason for deleting this quiz..." required></textarea>
                </div>
            </div>
            <div class="delete-modal-footer">
                <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <form id="deleteQuizForm" method="post" action="manage_quizzes.php" onsubmit="return validateDeleteForm()">
                    <input type="hidden" id="deleteQuizId" name="quiz_id">
                    <input type="hidden" id="deleteReasonHidden" name="delete_reason">
                    <input type="hidden" name="delete_quiz" value="1">
                    <button type="submit" class="btn-delete">Delete Quiz</button>
                </form>
            </div>
        </div>
    </div>

    <div class="admin-container">
        <header>
            <h1><i class="fas fa-graduation-cap"></i> <?php echo SITE_NAME; ?> - Admin</h1>
            <nav>
                <ul>
                    <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="manage_quizzes.php" class="active"><i class="fas fa-list-alt"></i> Manage Quizzes</a></li>
                    <li><a href="view_results.php"><i class="fas fa-chart-bar"></i> View Results</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </header>
        
        <main>
            <?php if (!empty($message)): ?>
                <div class="message success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="message error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['action']) && $_GET['action'] === 'add'): ?>
                <div class="content-card">
                    <div class="card-header">
                        <h2><i class="fas fa-plus-circle"></i> Add New Quiz</h2>
                    </div>
                    
                    <div class="card-body">
                        <form action="manage_quizzes.php" method="post">
                            <div class="form-group">
                                <label for="title">Quiz Title:</label>
                                <input type="text" id="title" name="title" required placeholder="Enter quiz title">
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description:</label>
                                <textarea id="description" name="description" rows="4" placeholder="Enter quiz description"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="time_limit">Time Limit (minutes, 0 for no limit):</label>
                                <input type="number" id="time_limit" name="time_limit" min="0" value="0" placeholder="Enter time limit">
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" name="add_quiz" class="btn">
                                    <i class="fas fa-plus"></i> Add Quiz
                                </button>
                                <a href="manage_quizzes.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif (isset($_GET['action']) && $_GET['action'] === 'edit' && $edit_quiz): ?>
                <div class="content-card">
                    <div class="card-header">
                        <h2><i class="fas fa-edit"></i> Edit Quiz</h2>
                    </div>
                    
                    <div class="card-body">
                        <form action="manage_quizzes.php" method="post">
                            <input type="hidden" name="quiz_id" value="<?php echo $edit_quiz['id']; ?>">
                            
                            <div class="form-group">
                                <label for="title">Quiz Title:</label>
                                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($edit_quiz['title']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description:</label>
                                <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($edit_quiz['description']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="time_limit">Time Limit (minutes, 0 for no limit):</label>
                                <input type="number" id="time_limit" name="time_limit" min="0" value="<?php echo $edit_quiz['time_limit']; ?>">
                            </div>
                            
                            <div class="form-group" style="display: flex; gap: 10px;">
                                <button type="submit" name="update_quiz" class="btn">
                                    <i class="fas fa-save"></i> Update Quiz
                                </button>
                                <a href="manage_quizzes.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" name="delete_quiz" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this quiz? This will also delete all questions associated with it.')">
                                    <i class="fas fa-trash-alt"></i> Delete Quiz
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="content-card">
                    <div class="card-header">
                        <h2><i class="fas fa-list-alt"></i> All Quizzes</h2>
                        <a href="manage_quizzes.php?action=add" class="btn">
                            <i class="fas fa-plus"></i> Add New Quiz
                        </a>
                    </div>
                    
                    <div class="card-body">
                        <?php if (empty($quizzes)): ?>
                            <div class="empty-state">
                                <i class="fas fa-info-circle"></i>
                                <p>No quizzes available. Click "Add New Quiz" to create your first quiz.</p>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Description</th>
                                        <th>Time Limit</th>
                                        <th>Questions</th>
                                        <th>Created By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quizzes as $quiz): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($quiz['description'], 0, 50)) . (strlen($quiz['description']) > 50 ? '...' : ''); ?></td>
                                            <td><?php echo $quiz['time_limit'] > 0 ? $quiz['time_limit'] . ' min' : 'No limit'; ?></td>
                                            <td>
                                                <?php 
                                                    $question_count = getQuestionCountByQuizId($quiz['id']);
                                                    echo $question_count;
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    if (isset($quiz['creator_name'])) {
                                                        echo htmlspecialchars($quiz['creator_name']);
                                                    } else if (isset($quiz['username'])) {
                                                        echo htmlspecialchars($quiz['username']);
                                                    } else {
                                                        echo 'Unknown';
                                                    }
                                                ?>
                                            </td>
                                            <td class="actions">
                                                <a href="edit_question.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn-small">
                                                    <i class="fas fa-question-circle"></i> Questions
                                                </a>
                                                <a href="manage_quizzes.php?action=edit&quiz_id=<?php echo $quiz['id']; ?>" class="btn-small">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="#" class="btn-small btn-share" onclick="showShareModal(event, <?php echo $quiz['id']; ?>, '<?php echo BASE_URL; ?>', '<?php echo htmlspecialchars($quiz['title'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-share-alt"></i> Share
                                                </a>
                                                <a href="view_results.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn-small">
                                                    <i class="fas fa-chart-bar"></i> Results
                                                </a>
                                                <a href="#" class="btn-small btn-danger" onclick="showDeleteModal(event, <?php echo $quiz['id']; ?>, '<?php echo htmlspecialchars($quiz['title'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved. <br> Developed by : Amit Kumar Patra</p>
        </footer>
    </div>
    
    <script src="../js/session-timeout.js"></script>

    <script>
    // Delete Modal Functions
    function showDeleteModal(event, quizId, quizTitle) {
        event.preventDefault();
        const modal = document.getElementById('deleteModal');
        const titleSpan = document.getElementById('deleteQuizTitle');
        const quizIdInput = document.getElementById('deleteQuizId');
        
        titleSpan.textContent = quizTitle;
        quizIdInput.value = quizId;
        
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
    }

    function closeDeleteModal() {
        const modal = document.getElementById('deleteModal');
        modal.classList.remove('show');
        setTimeout(() => modal.style.display = 'none', 300);
        
        // Reset form
        document.getElementById('deleteReason').value = '';
        document.getElementById('deleteReasonHidden').value = '';
    }

    function validateDeleteForm() {
        const reason = document.getElementById('deleteReason').value.trim();
        if (!reason) {
            alert('Please provide a reason for deleting this quiz.');
            return false;
        }
        
        // Copy reason to hidden input
        document.getElementById('deleteReasonHidden').value = reason;
        return true;
    }

    // Close modal if clicking outside
    window.onclick = function(event) {
        const deleteModal = document.getElementById('deleteModal');
        const shareModal = document.getElementById('shareModal');
        
        if (event.target === deleteModal) {
            closeDeleteModal();
        }
        if (event.target === shareModal) {
            closeShareModal();
        }
    }

    // Share Modal Functions
    function showShareModal(event, quizId, baseUrl, quizTitle) {
        event.preventDefault();
        
        // Show loading state
        const shareModal = document.getElementById('shareModal');
        const shareLinkInput = document.getElementById('shareLink');
        const copySuccessMsg = document.getElementById('copySuccess');
        
        // Hide any previous success message
        copySuccessMsg.style.display = 'none';
        
        // Show modal
        shareModal.style.display = 'flex';
        setTimeout(() => shareModal.classList.add('show'), 10);
        
        // Set loading state
        shareLinkInput.value = 'Generating share link...';
        
        // Make AJAX request to generate share link
        fetch(`manage_quizzes.php?action=share&quiz_id=${quizId}`)
            .then(response => response.text())
            .then(html => {
                // Extract the share link from the response using regex
                const shareLink = extractShareLinkFromHtml(html);
                
                if (shareLink) {
                    shareLinkInput.value = shareLink;
                } else {
                    shareLinkInput.value = 'Error generating link. Please try again.';
                }
            })
            .catch(error => {
                console.error('Error generating share link:', error);
                shareLinkInput.value = 'Error generating link. Please try again.';
            });
    }
    
    function extractShareLinkFromHtml(html) {
        // Create a DOM parser
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Try to find the shareLink input
        const shareLinkInput = doc.getElementById('shareLink');
        if (shareLinkInput && shareLinkInput.value) {
            return shareLinkInput.value;
        }
        
        // If not found, try regex as a fallback
        const regex = new RegExp('take_shared_quiz\\.php\\?quiz=[0-9]+&token=[a-zA-Z0-9]+');
        const match = html.match(regex);
        if (match) {
            // Add the base URL to the matched path
            // Extract base URL from current location
            const baseUrl = window.location.protocol + '//' + window.location.host + '/';
            return baseUrl + match[0];
        }
        
        return null;
    }

    function closeShareModal() {
        const modal = document.getElementById('shareModal');
        modal.classList.remove('show');
        setTimeout(() => modal.style.display = 'none', 300);
    }

    function copyShareLink() {
        const shareLinkInput = document.getElementById('shareLink');
        const copySuccessMsg = document.getElementById('copySuccess');
        
        shareLinkInput.select();
        document.execCommand('copy');
        
        // Show success message
        copySuccessMsg.style.display = 'block';
        
        // Hide after 3 seconds
        setTimeout(() => {
            copySuccessMsg.style.display = 'none';
        }, 3000);
    }

    function shareViaEmail() {
        const shareLink = document.getElementById('shareLink').value;
        const subject = 'Invitation to take a quiz';
        const body = `Hello,\n\nI'm inviting you to take this quiz: ${shareLink}\n\nRegards.`;
        
        window.location.href = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    }

    function shareViaWhatsApp() {
        const shareLink = document.getElementById('shareLink').value;
        const text = `I'm inviting you to take this quiz: ${shareLink}`;
        
        window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
    }

    function shareViaTelegram() {
        const shareLink = document.getElementById('shareLink').value;
        const text = `I'm inviting you to take this quiz: ${shareLink}`;
        
        window.open(`https://t.me/share/url?url=${encodeURIComponent(shareLink)}&text=${encodeURIComponent(text)}`, '_blank');
    }
    </script>
</body>
</html>