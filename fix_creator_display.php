<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Function to check if a column exists in a table
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
    return $result->num_rows > 0;
}

try {
    $conn = getDbConnection();
    
    echo "<h1>Quiz Creator Display Fix</h1>";
    
    // Step 1: Check if created_by column exists in quizzes table
    $created_by_exists = columnExists($conn, 'quizzes', 'created_by');
    
    if (!$created_by_exists) {
        echo "<p>Creating created_by column in quizzes table.</p>";
        $conn->query("ALTER TABLE quizzes ADD COLUMN created_by INT");
    } else {
        echo "<p>created_by column already exists.</p>";
    }
    
    // Step 2: Check if creator_id column exists (older installations)
    $creator_id_exists = columnExists($conn, 'quizzes', 'creator_id');
    
    if ($creator_id_exists) {
        echo "<p>Found creator_id column. Copying values to created_by column.</p>";
        $conn->query("UPDATE quizzes SET created_by = creator_id WHERE created_by IS NULL AND creator_id IS NOT NULL");
    }
    
    // Step 3: Examine all quizzes for missing creator information
    $result = $conn->query("SELECT * FROM quizzes WHERE created_by IS NULL");
    $quizzes_without_creator = $result->num_rows;
    
    if ($quizzes_without_creator > 0) {
        echo "<p>Found {$quizzes_without_creator} quiz(es) without creator information.</p>";
        // Use the current admin user id from session to set as the creator for orphaned quizzes
        if (isLoggedIn() && isAdmin()) {
            $admin_id = $_SESSION['user_id'];
            echo "<p>Setting current admin (ID: {$admin_id}) as creator for these quizzes.</p>";
            $conn->query("UPDATE quizzes SET created_by = {$admin_id} WHERE created_by IS NULL");
        } else {
            echo "<p>Please login as admin to fix creator information for these quizzes.</p>";
        }
    } else {
        echo "<p>All quizzes have creator information.</p>";
    }
    
    // Step 4: Verify the join in getQuizzes function
    echo "<p>Validating getQuizzes function...</p>";
    $query = "SELECT q.*, u.username as creator_name 
             FROM quizzes q 
             LEFT JOIN users u ON q.created_by = u.id";
             
    $result = $conn->query($query);
    
    if (!$result) {
        echo "<p>Error in JOIN query: " . $conn->error . "</p>";
    } else {
        echo "<p>JOIN query is working correctly.</p>";
        
        // Show a sample result
        $row = $result->fetch_assoc();
        if ($row) {
            echo "<p>Sample quiz data:</p>";
            echo "<pre>";
            print_r($row);
            echo "</pre>";
            
            if (isset($row['creator_name'])) {
                echo "<p>creator_name is correctly populated in query results.</p>";
            } else {
                echo "<p>creator_name is missing from query results!</p>";
            }
        } else {
            echo "<p>No quizzes found in database.</p>";
        }
    }
    
    // Step 5: Update display code in manage_quizzes.php if needed
    echo "<p>Updating display code in manage_quizzes.php...</p>";
    
    $file_path = 'admin/manage_quizzes.php';
    $file_contents = file_get_contents($file_path);
    
    // Replace the creator display code
    $search = 'if (isset($quiz[\'creator_name\'])) {
                                                        echo htmlspecialchars($quiz[\'creator_name\']);
                                                    } else {
                                                        echo \'Unknown\';
                                                    }';
                                                    
    $replace = 'if (isset($quiz[\'creator_name\'])) {
                                                        echo htmlspecialchars($quiz[\'creator_name\']);
                                                    } else if (isset($quiz[\'username\'])) {
                                                        echo htmlspecialchars($quiz[\'username\']);
                                                    } else {
                                                        echo \'Unknown\';
                                                    }';
    
    if (strpos($file_contents, $search) !== false) {
        $file_contents = str_replace($search, $replace, $file_contents);
        file_put_contents($file_path, $file_contents);
        echo "<p>Updated display code in manage_quizzes.php</p>";
    } else {
        echo "<p>Could not find the exact code to replace in manage_quizzes.php.</p>";
    }
    
    echo "<h2>Fix Completed</h2>";
    echo "<p>The creator display issue should now be fixed.</p>";
    echo "<p><a href='admin/manage_quizzes.php'>Go to Manage Quizzes</a></p>";
    
    closeDbConnection($conn);
} catch (Exception $e) {
    echo "<h2>Error</h2>";
    echo "<p>An error occurred: " . $e->getMessage() . "</p>";
}
?> 