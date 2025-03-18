<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect to appropriate dashboard if logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: admin/index.php");
    } else {
        header("Location: student/index.php");
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo SITE_NAME; ?></h1>
        </header>
        
        <main>
            <div class="welcome-box">
                <h2>Welcome to the MCQ Management System</h2>
                <p>A platform for creating and taking multiple-choice quizzes.</p>
                
                <div class="action-buttons">
                    <a href="login.php" class="btn">Login</a>
                    <a href="register.php" class="btn">Register</a>
                </div>
            </div>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved. <br> Developed by : Amit Kumar Patra</p>
        </footer>
    </div>
</body>
</html> 