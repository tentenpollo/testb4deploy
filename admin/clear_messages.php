<?php
// Start session if not already started
if (!isset($_SESSION)) {
    session_start();
}

// Check if request is POST and has a type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type'])) {
    $type = $_POST['type'];
    
    // Clear specific type of messages
    if ($type === 'priorities') {
        if (isset($_SESSION['success_msg'])) {
            unset($_SESSION['success_msg']);
        }
        
        if (isset($_SESSION['error_msg'])) {
            unset($_SESSION['error_msg']);
        }
    }
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    // Return error response
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>