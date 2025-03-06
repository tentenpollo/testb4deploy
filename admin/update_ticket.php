<?php
require_once '../includes/config.php';


@ini_set('display_errors', 0);
@error_reporting(0);

ob_start();
ob_clean();


header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Debug logging function
function debugLog($message) {
    $logFile = '/tmp/ticket_update_debug.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
}

// Enhanced JSON response
function sendJsonResponse($data, $status = 200) {
    // Clear all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set HTTP status
    http_response_code($status);
    
    // Output JSON
    echo json_encode($data);
    exit;
}

try {
    // Log initial debug information
    debugLog('Starting update script');
    debugLog('GET: ' . print_r($_GET, true));
    debugLog('SERVER: ' . print_r($_SERVER, true));

    
    // Database connection with extensive logging
    $mysqli = db_connect();
    if (!$mysqli) {
        debugLog('Database connection failed');
        sendJsonResponse(['error' => 'Database connection failed'], 500);
    }

    // Validate ticket ID
    $ticketId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$ticketId) {
        debugLog('Invalid ticket ID');
        sendJsonResponse(['error' => 'Invalid ticket ID'], 400);
    }

    // Raw input reading with logging
    $input = file_get_contents('php://input');
    debugLog('Raw input: ' . $input);

    // JSON decoding with error checking
    $data = json_decode($input, true);
    if ($data === null) {
        $jsonError = json_last_error_msg();
        debugLog('JSON decode error: ' . $jsonError);
        sendJsonResponse(['error' => 'Invalid JSON: ' . $jsonError], 400);
    }

    debugLog('Decoded data: ' . print_r($data, true));

    // Prepare update fields
    $updateFields = [];
    $types = '';
    $values = [];

    $allowedFields = [
        'title' => 's',
        'description' => 's',
        'status' => 's',
        'priority_id' => 'i',
        'category_id' => 'i',
        'assigned_to' => 'i'
    ];

    foreach ($allowedFields as $field => $type) {
        if (isset($data[$field]) && $data[$field] !== null) {
            $updateFields[] = "$field = ?";
            $values[] = $data[$field];
            $types .= $type;
        }
    }

    // Add timestamp
    $updateFields[] = 'updated_at = NOW()';

    if (empty($updateFields)) {
        debugLog('No fields to update');
        sendJsonResponse(['error' => 'No fields to update'], 400);
    }

    // Prepare statement with logging
    $sql = "UPDATE tickets SET " . implode(', ', $updateFields) . " WHERE id = ?";
    debugLog('SQL: ' . $sql);

    $values[] = $ticketId;
    $types .= 'i';

    // Prepare and execute with error logging
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        debugLog('Prepare statement failed: ' . $mysqli->error);
        sendJsonResponse(['error' => $mysqli->error], 500);
    }

    $bindResult = $stmt->bind_param($types, ...$values);
    if (!$bindResult) {
        debugLog('Bind parameters failed: ' . $stmt->error);
        sendJsonResponse(['error' => $stmt->error], 500);
    }

    $executeResult = $stmt->execute();
    if (!$executeResult) {
        debugLog('Execute failed: ' . $stmt->error);
        sendJsonResponse(['error' => $stmt->error], 500);
    }

    // Fetch updated ticket
    $fetchStmt = $mysqli->prepare("SELECT * FROM tickets WHERE id = ?");
    $fetchStmt->bind_param('i', $ticketId);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    $updatedTicket = $result->fetch_assoc();

    // Close statements
    $stmt->close();
    $fetchStmt->close();

    // Send response
    debugLog('Update successful');
    sendJsonResponse([
        'success' => true,
        'ticket' => $updatedTicket
    ]);

} catch (Exception $e) {
    debugLog('Exception: ' . $e->getMessage());
    sendJsonResponse(['error' => $e->getMessage()], 500);
}