<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $time_limit = (int)($_POST['time_limit'] ?? 0);
    $passing_score = (int)($_POST['passing_score'] ?? 0);
    
    // Validate input
    if (empty($title)) {
        $error = "Quiz title is required";
    } else {
        try {
            $conn = getDbConnection();
            
            // Insert quiz
            $stmt = $conn->prepare("INSERT INTO quizzes (title, description, time_limit, passing_score, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssiii", $title, $description, $time_limit, $passing_score, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $quiz_id = $conn->insert_id;
                $success = "Quiz created successfully!";
                
                // Redirect to add questions
                header("Location: edit_quiz.php?id=$quiz_id&new=1");
                exit;
            } else {
                $error = "Error creating quiz: " . $stmt->error;
            }
            
            closeDbConnection($conn);
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Quiz - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-primary {
            background-color: #4a6cf7;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .form-hint {
            font-size: 14px;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <div class="dashboard-content">
            <header>
                <h1>Add New Quiz</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['username']; ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>
            
            <main>
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <div class="content-card">
                    <div class="card-header">
                        <h2>Quiz Details</h2>
                    </div>
                    
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="title">Quiz Title *</label>
                                <input type="text" id="title" name="title" required value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                <div class="form-hint">Provide a brief description of what this quiz is about.</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="time_limit">Time Limit (minutes)</label>
                                <input type="number" id="time_limit" name="time_limit" min="0" value="<?php echo isset($_POST['time_limit']) ? (int)$_POST['time_limit'] : '30'; ?>">
                                <div class="form-hint">Set to 0 for no time limit.</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="passing_score">Passing Score (%)</label>
                                <input type="number" id="passing_score" name="passing_score" min="0" max="100" value="<?php echo isset($_POST['passing_score']) ? (int)$_POST['passing_score'] : '70'; ?>">
                            </div>
                            
                            <div class="btn-container">
                                <a href="manage_quizzes.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Create Quiz
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
            
            <footer>
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            </footer>
        </div>
    </div>
    
    <script src="../js/session-timeout.js"></script>
</body>
</html> 