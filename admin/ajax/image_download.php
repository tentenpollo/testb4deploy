<?php
// Place this file in your admin/ajax/ directory as image_download.php

// Include necessary files
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Start clean - no output buffering
while (ob_get_level()) {
    ob_end_clean();
}

// Check if ticket_id is provided
if (!isset($_GET['ticket_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Missing ticket ID';
    exit;
}

$ticket_id = $_GET['ticket_id'];
$db = db_connect();
$ticket_id = $db->real_escape_string($ticket_id);

// Get attachment info from database
$attachment = null;

if (isset($_GET['attachment_id'])) {
    $attachment_id = $_GET['attachment_id'];
    $attachment_id = $db->real_escape_string($attachment_id);
    
    $sql = "SELECT * FROM attachments WHERE id = '$attachment_id' AND ticket_id = '$ticket_id'";
    $result = $db->query($sql);
    
    if ($result && $result->num_rows == 1) {
        $attachment = $result->fetch_assoc();
    }
} elseif (isset($_GET['filename']) && isset($_GET['comment_id'])) {
    $filename = urldecode($_GET['filename']);
    $comment_id = $_GET['comment_id'];
    $comment_id = $db->real_escape_string($comment_id);
    
    $sql = "SELECT * FROM attachments WHERE ticket_id = '$ticket_id' AND comment_id = '$comment_id'";
    $result = $db->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stored_filename = basename($row['file_path']);
            
            if ($stored_filename === $filename || 
                preg_match('/^\d+_' . preg_quote($filename, '/') . '$/', $stored_filename) ||
                strcasecmp($stored_filename, $filename) === 0) {
                $attachment = $row;
                break;
            }
        }
    }
} elseif (isset($_GET['filename'])) {
    $filename = urldecode($_GET['filename']);
    
    $sql = "SELECT * FROM attachments WHERE ticket_id = '$ticket_id'";
    $result = $db->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stored_filename = basename($row['file_path']);
            
            if ($stored_filename === $filename || 
                preg_match('/^\d+_' . preg_quote($filename, '/') . '$/', $stored_filename) ||
                strcasecmp($stored_filename, $filename) === 0) {
                $attachment = $row;
                break;
            }
        }
    }
}

// If attachment not found
if (!$attachment) {
    header('HTTP/1.1 404 Not Found');
    echo 'Attachment not found';
    exit;
}

// Get file path
$file_path = $attachment['file_path'];

// Check if file exists
if (file_exists($file_path)) {
    serve_file($file_path);
} else {
    // Try alternative paths
    $alt_paths = [
        $_SERVER['DOCUMENT_ROOT'] . '/' . $file_path,
        $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . basename($file_path),
        dirname(dirname($_SERVER['SCRIPT_FILENAME'])) . '/../uploads/' . basename($file_path),
        dirname(dirname($_SERVER['SCRIPT_FILENAME'])) . '/../uploads/tickets/' . $ticket_id . '/' . basename($file_path)
    ];
    
    $found = false;
    foreach ($alt_paths as $alt_path) {
        if (file_exists($alt_path)) {
            serve_file($alt_path);
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        header('HTTP/1.1 404 Not Found');
        echo 'File not found on server';
        exit;
    }
}

/**
 * Serve a file for download
 * 
 * @param string $file_path Path to the file
 * @return void
 */
function serve_file($file_path) {
    // Get file information
    $filename = basename($file_path);
    $filesize = filesize($file_path);
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // Make sure no output buffer is active
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set very explicit headers for image download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . $filesize);
    
    // Read file in chunks and output
    if ($filesize > 0) {
        $handle = @fopen($file_path, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
        }
    }
    
    exit;
}