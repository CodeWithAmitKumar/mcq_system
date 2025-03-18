<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$error = '';
$timeout_message = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';

// Check for session timeout message
if (isset($_COOKIE['session_timeout']) && $_COOKIE['session_timeout'] === 'true') {
    $timeout_message = "Your session has timed out due to inactivity. Please log in again.";
    // Clear the cookie
    setcookie('session_timeout', '', time() - 3600, '/');
}

// Check if user is already logged in
if (isLoggedIn()) {
    if ($redirect) {
        header("Location: " . $redirect);
    } else {
        if (isAdmin()) {
            header("Location: admin/index.php");
        } else {
            header("Location: student/index.php");
        }
    }
    exit;
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $redirect = $_POST['redirect'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        if (loginUser($username, $password)) {
            // Set a session variable to indicate a new login
            $_SESSION['just_logged_in'] = true;
            
            // Set last activity time for session timeout
            $_SESSION['last_activity'] = time();
            
            if ($redirect) {
                header("Location: " . $redirect);
            } else {
                if (isAdmin()) {
                    header("Location: admin/index.php");
                } else {
                    header("Location: student/index.php");
                }
            }
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .timeout-message {
            background-color: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.2);
            color: #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
        }
        
        .timeout-message i {
            margin-right: 10px;
            font-size: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo SITE_NAME; ?></h1>
        </header>
        
        <main>
            <div class="form-container">
                <h2>Login</h2>
                
                <?php if (!empty($timeout_message)): ?>
                    <div class="timeout-message">
                        <i class="fas fa-clock"></i>
                        <?php echo $timeout_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form action="login.php" method="post">
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <?php if ($redirect): ?>
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Login</button>
                    </div>
                </form>
                
                <p>Don't have an account? <a href="register.php<?php echo $redirect ? '?redirect=' . urlencode($redirect) : ''; ?>">Register here</a></p>
            </div>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </footer>
    </div>
</body>
</html> 