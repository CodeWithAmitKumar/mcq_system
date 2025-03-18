<?php
require_once 'includes/db.php';
require_once 'includes/config.php';

try {
    $conn = getDbConnection();
    
    // Check if created_by exists
    $created_by_exists = false;
    $creator_id_exists = false;
    
    $result = $conn->query("SHOW COLUMNS FROM quizzes LIKE 'created_by'");
    if ($result->num_rows > 0) {
        $created_by_exists = true;
    }
    
    $result = $conn->query("SHOW COLUMNS FROM quizzes LIKE 'creator_id'");
    if ($result->num_rows > 0) {
        $creator_id_exists = true;
    }
    
    // If created_by doesn't exist, add it
    if (!$created_by_exists) {
        $conn->query("ALTER TABLE quizzes ADD COLUMN created_by INT");
        echo "Added created_by column<br>";
    }
    
    // If both fields exist, copy data from creator_id to created_by
    if ($created_by_exists && $creator_id_exists) {
        $conn->query("UPDATE quizzes SET created_by = creator_id WHERE created_by IS NULL");
        echo "Copied data from creator_id to created_by<br>";
    }
    
    // Update the getQuizzes function in functions.php
    $functions_file = file_get_contents('includes/functions.php');
    
    // Check if we need to fix the query
    if (strpos($functions_file, "LEFT JOIN users u ON q.creator_id = u.id") !== false) {
        $functions_file = str_replace(
            "LEFT JOIN users u ON q.creator_id = u.id",
            "LEFT JOIN users u ON q.created_by = u.id",
            $functions_file
        );
        file_put_contents('includes/functions.php', $functions_file);
        echo "Updated query in functions.php<br>";
    }
    
    // Check all places in manage_quizzes.php
    $quizzes_file = file_get_contents('admin/manage_quizzes.php');
    
    // Look for creator_id in SQL INSERT statements
    if (strpos($quizzes_file, "INSERT INTO quizzes") !== false && 
        strpos($quizzes_file, "creator_id") !== false) {
        
        $quizzes_file = str_replace(
            "creator_id",
            "created_by",
            $quizzes_file
        );
        file_put_contents('admin/manage_quizzes.php', $quizzes_file);
        echo "Updated creator_id references in manage_quizzes.php<br>";
    }
    
    echo "Fix completed successfully!";
    
    closeDbConnection($conn);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 