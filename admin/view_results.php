<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Get quiz ID from URL
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

// Check if quiz exists
$quiz = getQuizById($quiz_id);
if (!$quiz) {
    header("Location: manage_quizzes.php");
    exit;
}

// Simple error handling
$error = '';
$results = [];
$questions = [];
$recent_attempts = [];

try {
    // Check if tables exist
    $conn = getDbConnection();
    
    // Get questions
    $stmt = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    $stmt->close();
    
    // Check if quiz_results table exists
    $result = $conn->query("SHOW TABLES LIKE 'quiz_results'");
    $tableExists = ($result->num_rows > 0);
    
    if ($tableExists) {
        // Get basic results
        $stmt = $conn->prepare("
            SELECT r.*, u.username 
            FROM quiz_results r
            JOIN users u ON r.user_id = u.id
            WHERE r.quiz_id = ?
            ORDER BY r.completion_time DESC
        ");
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();
        
        // Get recent attempts (last 10)
        $stmt = $conn->prepare("
            SELECT r.*, u.username
            FROM quiz_results r
            JOIN users u ON r.user_id = u.id
            WHERE r.quiz_id = ?
            ORDER BY r.completion_time DESC
            LIMIT 10
        ");
        $stmt->bind_param("i", $quiz_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $recent_attempts[] = $row;
        }
        $stmt->close();
    }
    
    closeDbConnection($conn);
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

// Function to format time
function formatTime($seconds) {
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Results - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Remove the inline styles since they're now in admin-dashboard.css -->
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/admin_sidebar.php'; ?>
        
        <div class="dashboard-content">
            <header>
                <h1>Quiz Results: <?php echo htmlspecialchars($quiz['title']); ?></h1>
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
                        <i class="fas fa-exclamation-circle"></i>
                        <p><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Export Buttons -->
                <div class="export-buttons">
                    <a href="view_results.php?quiz_id=<?php echo $quiz_id; ?>&export=excel" class="btn-export" <?php echo empty($results) ? 'style="opacity: 0.6; cursor: not-allowed;"' : ''; ?>>
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </a>
                    
                    <?php
                    // Check if user_answers table exists
                    $tableExists = false;
                    $conn = getDbConnection();
                    $result = $conn->query("SHOW TABLES LIKE 'user_answers'");
                    if ($result) {
                        $tableExists = ($result->num_rows > 0);
                    }
                    closeDbConnection($conn);
                    
                    if (!$tableExists): 
                    ?>
                    <div class="alert alert-info" style="margin-top: 15px;">
                        <i class="fas fa-info-circle"></i>
                        <p>To enable detailed answer tracking for the "View Results" feature, please run the <a href="create_user_answers_table.php" style="color: #0c5460; text-decoration: underline;">database setup script</a>.</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Basic Statistics -->
                <div class="stats-cards">
                    <div class="stat-card">
                        <i class="fas fa-users"></i>
                        <div class="stat-value"><?php echo count($results); ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-question-circle"></i>
                        <div class="stat-value"><?php echo count($questions); ?></div>
                        <div class="stat-label">Total Questions</div>
                    </div>
                    
                    <?php if (!empty($results)): 
                        $total_score = 0;
                        foreach ($results as $result) {
                            $total_score += $result['score'];
                        }
                        $avg_score = count($results) > 0 ? round($total_score / count($results), 2) : 0;
                        $avg_percentage = (count($questions) > 0 && count($results) > 0) ? 
                            round(($avg_score / count($questions)) * 100, 2) : 0;
                    ?>
                    <div class="stat-card">
                        <i class="fas fa-chart-line"></i>
                        <div class="stat-value"><?php echo $avg_score; ?></div>
                        <div class="stat-label">Average Score</div>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-percentage"></i>
                        <div class="stat-value"><?php echo $avg_percentage; ?>%</div>
                        <div class="stat-label">Average Percentage</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Attempts Section -->
                <div class="content-card">
                    <div class="card-header">
                        <h2>Recent Quiz Attempts</h2>
                    </div>
                    
                    <div class="card-body">
                        <?php if (empty($recent_attempts)): ?>
                            <div class="empty-state">
                                <i class="fas fa-clock"></i>
                                <p>No recent attempts for this quiz.</p>
                            </div>
                        <?php else: ?>
                            <div class="recent-attempts">
                                <?php foreach ($recent_attempts as $attempt): 
                                    $initials = strtoupper(substr($attempt['username'], 0, 1));
                                    $percentage = count($questions) > 0 ? 
                                        round(($attempt['score'] / count($questions)) * 100, 2) : 0;
                                ?>
                                    <div class="attempt-card">
                                        <div class="attempt-avatar">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div class="attempt-details">
                                            <div class="attempt-user"><?php echo htmlspecialchars($attempt['username']); ?></div>
                                            <div class="attempt-meta">
                                                <div><i class="fas fa-clock"></i> <?php echo $attempt['time_taken'] ? formatTime($attempt['time_taken']) : 'N/A'; ?></div>
                                                <div><i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($attempt['completion_time'])); ?></div>
                                            </div>
                                        </div>
                                        <div class="attempt-score">
                                            <?php echo $attempt['score']; ?>/<?php echo count($questions); ?>
                                            <div class="progress-bar" style="width: 100px; margin-top: 5px;">
                                                <div class="progress" style="width: <?php echo $percentage; ?>%"></div>
                                                <span><?php echo $percentage; ?>%</span>
                                            </div>
                                            <a href="view_attempt_details.php?attempt_id=<?php echo $attempt['id']; ?>" class="btn-view-results">
                                                <i class="fas fa-eye"></i> View Results
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Basic Results Table -->
                <div class="content-card">
                    <div class="card-header">
                        <h2>Student Results</h2>
                    </div>
                    
                    <div class="card-body">
                        <?php if (empty($results)): ?>
                            <div class="empty-state">
                                <i class="fas fa-info-circle"></i>
                                <p>No results available for this quiz yet.</p>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Score</th>
                                        <th>Percentage</th>
                                        <th>Time Taken</th>
                                        <th>Date Completed</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $result): 
                                        $percentage = count($questions) > 0 ? 
                                            round(($result['score'] / count($questions)) * 100, 2) : 0;
                                    ?>
                                        <tr>
                                            <td data-label="Student"><?php echo htmlspecialchars($result['username']); ?></td>
                                            <td data-label="Score"><?php echo $result['score']; ?>/<?php echo count($questions); ?></td>
                                            <td data-label="Percentage">
                                                <div class="progress-bar">
                                                    <div class="progress" style="width: <?php echo $percentage; ?>%"></div>
                                                    <span><?php echo $percentage; ?>%</span>
                                                </div>
                                            </td>
                                            <td data-label="Time Taken"><?php echo $result['time_taken'] ? formatTime($result['time_taken']) : 'N/A'; ?></td>
                                            <td data-label="Date Completed"><?php echo date('Y-m-d H:i:s', strtotime($result['completion_time'])); ?></td>
                                            <td data-label="Actions">
                                                <a href="view_attempt_details.php?attempt_id=<?php echo $result['id']; ?>" class="btn-view-results">
                                                    <i class="fas fa-eye"></i> View Results
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Questions List -->
                <div class="content-card">
                    <div class="card-header">
                        <h2>Quiz Questions</h2>
                    </div>
                    
                    <div class="card-body">
                        <?php if (empty($questions)): ?>
                            <div class="empty-state">
                                <i class="fas fa-info-circle"></i>
                                <p>No questions available for this quiz.</p>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Question</th>
                                        <th>Correct Answer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($questions as $question): ?>
                                        <tr>
                                            <td data-label="Question"><?php echo htmlspecialchars($question['question_text']); ?></td>
                                            <td data-label="Correct Answer"><?php echo htmlspecialchars($question['correct_answer']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
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