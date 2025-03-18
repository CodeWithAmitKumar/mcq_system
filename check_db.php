<?php
require_once 'includes/db.php';
require_once 'includes/config.php';

$conn = getDbConnection();
$result = $conn->query('DESCRIBE quizzes');
echo "<h1>Quizzes Table Structure</h1>";
echo "<pre>";
if ($result) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Error: " . $conn->error;
}
echo "</pre>";

closeDbConnection($conn);
?> 