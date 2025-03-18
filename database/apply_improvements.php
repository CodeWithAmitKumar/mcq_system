<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$success = [];
$errors = [];

try {
    $conn = getDbConnection();
    
    // Read the SQL file
    $sql = file_get_contents('improvements.sql');
    
    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    // Execute each statement
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                if ($conn->query($statement)) {
                    $success[] = "Successfully executed: " . substr($statement, 0, 50) . "...";
                } else {
                    $errors[] = "Error executing: " . substr($statement, 0, 50) . "... Error: " . $conn->error;
                }
            } catch (Exception $e) {
                $errors[] = "Error executing: " . substr($statement, 0, 50) . "... Error: " . $e->getMessage();
            }
        }
    }
    
    closeDbConnection($conn);
    
} catch (Exception $e) {
    $errors[] = "Database connection error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Database Improvements - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .result-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .success-message {
            color: #28a745;
            margin-bottom: 10px;
        }
        
        .error-message {
            color: #dc3545;
            margin-bottom: 10px;
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
        
        .back-button:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <div class="dashboard-content">
            <header>
                <h1>Apply Database Improvements</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['username']; ?></span>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </header>
            
            <main>
                <a href="../admin/index.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                
                <div class="result-card">
                    <h2>Results</h2>
                    
                    <?php if (!empty($success)): ?>
                        <h3>Successful Operations:</h3>
                        <?php foreach ($success as $message): ?>
                            <div class="success-message">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <h3>Errors:</h3>
                        <?php foreach ($errors as $message): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if (empty($success) && empty($errors)): ?>
                        <p>No operations were performed.</p>
                    <?php endif; ?>
                </div>
            </main>
            
            <footer>
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved. <br> Developed by : Amit Kumar Patra</p>
            </footer>
        </div>
    </div>
</body>
</html> 