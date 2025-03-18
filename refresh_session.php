<?php
require_once 'includes/config.php';

// Just updating the last_activity timestamp
$_SESSION['last_activity'] = time();

// Return success response
header('Content-Type: application/json');
echo json_encode(['status' => 'success']);
?> 