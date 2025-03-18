<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$error = '';
$success = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';

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

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $redirect = $_POST['redirect'] ?? '';
    
    // Validate input
    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Check if username already exists
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Username already exists. Please choose a different one.";
        } else {
            // Register the user
            if (registerUser($username, $password, $role)) {
                // Auto-login after registration
                if (loginUser($username, $password)) {
                    $_SESSION['just_logged_in'] = true;
                    $_SESSION['last_activity'] = time();
                    
                    if ($redirect) {
                        header("Location: " . $redirect);
                        exit;
                    } else {
                        $success = "Registration successful! You can now <a href='login.php'>login</a>.";
                    }
                } else {
                    $success = "Registration successful! You can now <a href='login.php'>login</a>.";
                }
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        
        $stmt->close();
        closeDbConnection($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo SITE_NAME; ?></h1>
        </header>
        
        <main>
            <div class="form-container">
                <h2>Register</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="success-message"><?php echo $success; ?></div>
                <?php else: ?>
                    <form action="register.php" method="post">
                        <div class="form-group">
                            <label for="username">Username:</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password" required>
                            <small>Password must be at least 6 characters long.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password:</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Register as:</label>
                            <select id="role" name="role">
                                <option value="student">Student</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        
                        <?php if ($redirect): ?>
                            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <button type="submit" class="btn">Register</button>
                        </div>
                    </form>
                    
                    <p>Already have an account? <a href="login.php<?php echo $redirect ? '?redirect=' . urlencode($redirect) : ''; ?>">Login here</a></p>
                <?php endif; ?>
            </div>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved. <br> Developed by : Amit Kumar Patra</p>
        </footer>
    </div>
</body>
</html> 