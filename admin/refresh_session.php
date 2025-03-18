<?php
// Start or resume the session
session_start();

// Update the last activity time
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Session refreshed']);
} else {
    // Return error if not logged in
    header('Content-Type: application/json');
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
}
?> 