<?php
ob_start(); 
if (headers_sent($file, $line)) {
    die("Headers already sent in $file on line $line");
}
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'ticketing_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Add upload directory configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// Add allowed file types
define('ALLOWED_FILE_TYPES', [
    'image/jpeg',
    'image/png',
    'application/pdf',
    'text/plain'
]);

// Global connection variable
$mysqli = null;

function db_connect() {
    global $mysqli;
    
    // Create connection if it doesn't exist
    if ($mysqli === null) {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check connection
        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }
        
        // Set charset
        $mysqli->set_charset("utf8mb4");
    }
    
    return $mysqli;
}

function is_guest() {
    return isset($_SESSION['guest_id']);
}

// Generate random token
function generate_token($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Sanitize input
function sanitize($input) {
    global $mysqli;
    if (!$mysqli) {
        db_connect();
    }
    $input = trim($input);
    $input = $mysqli->real_escape_string($input);
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}