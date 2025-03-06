<?php
require_once '../includes/config.php';

$mysqli = db_connect();

header('Content-Type: application/json');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Ticket ID is required']);
    exit;
}

// Get the ticket ID from the URL
$ticketId = intval($_GET['id']);

try {
    // Prepare a DELETE query
    $stmt = $mysqli->prepare("DELETE FROM tickets WHERE id = ?");
    
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }

    // Bind the ticket ID
    $stmt->bind_param('i', $ticketId);
    
    // Execute the deletion
    $result = $stmt->execute();

    if ($result) {
        // Successful deletion
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Ticket deleted successfully']);
    } else {
        // Deletion failed
        http_response_code(404);
        echo json_encode(['error' => 'Ticket not found']);
    }

    // Close the statement
    $stmt->close();
} catch (Exception $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}