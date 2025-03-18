<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Quiz Creator Fix</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Applying Quiz Creator Field Fix</h1>";

try {
    $conn = getDbConnection();
    
    // Read and execute the SQL file
    $sql = file_get_contents('database/fix_creator_field.sql');
    
    // Split SQL file into individual statements
    $statements = explode(';', $sql);
    
    echo "<h2>Executing SQL Statements:</h2>";
    echo "<ul>";
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $conn->query($statement);
                echo "<li class='success'>Success: " . htmlspecialchars(substr($statement, 0, 100)) . "...</li>";
            } catch (Exception $e) {
                echo "<li class='error'>Error: " . htmlspecialchars($e->getMessage()) . " in statement: " . htmlspecialchars(substr($statement, 0, 100)) . "...</li>";
            }
        }
    }
    
    echo "</ul>";
    
    // Now update functions.php and manage_quizzes.php
    echo "<h2>Updating Code Files:</h2>";
    
    // Fix manage_quizzes.php
    $manage_quizzes_file = file_get_contents('admin/manage_quizzes.php');
    
    // Check for creator_id in the code and replace with created_by
    if (strpos($manage_quizzes_file, 'creator_id') !== false) {
        $manage_quizzes_file = str_replace('creator_id', 'created_by', $manage_quizzes_file);
        file_put_contents('admin/manage_quizzes.php', $manage_quizzes_file);
        echo "<p class='success'>Fixed creator_id references in manage_quizzes.php</p>";
    } else {
        echo "<p>No creator_id references found in manage_quizzes.php</p>";
    }
    
    // Make sure the display code is updated
    $display_code = 'if (isset($quiz[\'creator_name\'])) {
                                                        echo htmlspecialchars($quiz[\'creator_name\']);
                                                    } else {
                                                        echo \'Unknown\';
                                                    }';
                                                    
    $updated_display_code = 'if (isset($quiz[\'creator_name\'])) {
                                                        echo htmlspecialchars($quiz[\'creator_name\']);
                                                    } else if (isset($quiz[\'username\'])) {
                                                        echo htmlspecialchars($quiz[\'username\']);
                                                    } else {
                                                        echo \'Unknown\';
                                                    }';
    
    if (strpos($manage_quizzes_file, $display_code) !== false) {
        $manage_quizzes_file = str_replace($display_code, $updated_display_code, $manage_quizzes_file);
        file_put_contents('admin/manage_quizzes.php', $manage_quizzes_file);
        echo "<p class='success'>Updated creator display code in manage_quizzes.php</p>";
    } else {
        echo "<p>Creator display code already updated in manage_quizzes.php</p>";
    }
    
    // Check if we're already using created_by consistently in functions.php
    $functions_file = file_get_contents('includes/functions.php');
    
    if (strpos($functions_file, 'LEFT JOIN users u ON q.creator_id = u.id') !== false) {
        $functions_file = str_replace(
            'LEFT JOIN users u ON q.creator_id = u.id',
            'LEFT JOIN users u ON q.created_by = u.id',
            $functions_file
        );
        file_put_contents('includes/functions.php', $functions_file);
        echo "<p class='success'>Updated SQL join query in functions.php</p>";
    } else {
        echo "<p>SQL join query in functions.php is already correct</p>";
    }
    
    // Check sample quiz data
    echo "<h2>Checking Sample Quiz Data:</h2>";
    $result = $conn->query("SELECT q.*, u.username as creator_name FROM quizzes q LEFT JOIN users u ON q.created_by = u.id LIMIT 1");
    
    if ($result && $result->num_rows > 0) {
        $sample_quiz = $result->fetch_assoc();
        echo "<pre>";
        print_r($sample_quiz);
        echo "</pre>";
        
        if (isset($sample_quiz['creator_name'])) {
            echo "<p class='success'>creator_name is populated correctly in sample quiz!</p>";
        } else {
            echo "<p class='error'>creator_name is still missing in sample quiz.</p>";
        }
    } else {
        echo "<p>No quizzes found in database.</p>";
    }
    
    echo "<h2>Fix Complete!</h2>";
    echo "<p>The creator display issue should now be fixed. <a href='admin/manage_quizzes.php'>Click here</a> to check the manage quizzes page.</p>";
    
    closeDbConnection($conn);
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?> 