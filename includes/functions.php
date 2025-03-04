<?php
/**
 * Additional helper functions for the ticketing system
 */

// Format date in a user-friendly way
function format_date($datetime, $format = 'M j, Y g:i A') {
    if (empty($datetime)) return '';
    $date = new DateTime($datetime);
    return $date->format($format);
}

// Calculate time elapsed in a human-readable format
function time_elapsed_string($datetime) {
    if (empty($datetime)) return '';
    
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    
    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    
    if (!$string) {
        return 'just now';
    }
    
    $string = array_slice($string, 0, 1);
    return implode(', ', $string) . ' ago';
}

// Get ticket status class for CSS
function get_status_class($status) {
    switch (strtolower($status)) {
        case 'new': return 'status-new';
        case 'open': return 'status-open';
        case 'in progress': return 'status-progress';
        case 'awaiting response': return 'status-awaiting';
        case 'resolved': return 'status-resolved';
        case 'closed': return 'status-closed';
        default: return '';
    }
}

// Get priority class for CSS
function get_priority_class($priority) {
    switch (strtolower($priority)) {
        case 'low': return 'priority-low';
        case 'medium': return 'priority-medium';
        case 'high': return 'priority-high';
        case 'urgent': return 'priority-urgent';
        default: return '';
    }
}

// Truncate text to a certain length
function truncate_text($text, $length = 50, $append = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    $text = substr($text, 0, $length);
    $last_space = strrpos($text, ' ');
    
    if ($last_space !== false) {
        $text = substr($text, 0, $last_space);
    }
    
    return $text . $append;
}

// Check if user has access to a ticket
function user_can_access_ticket($ticket_id) {
    if (!is_logged_in() && !is_guest()) {
        return false;
    }
    
    $db = db_connect();
    
    if (is_logged_in()) {
        $ticket_id_escaped = $db->real_escape_string($ticket_id);
        $user_id_escaped = $db->real_escape_string($_SESSION['user_id']);
        
        $query = "
            SELECT 1 FROM tickets 
            WHERE ticket_id = '$ticket_id_escaped' AND registered_user_id = '$user_id_escaped'
        ";
        $result = $db->query($query);
    } else {
        $ticket_id_escaped = $db->real_escape_string($ticket_id);
        $guest_id_escaped = $db->real_escape_string($_SESSION['guest_id']);
        
        $query = "
            SELECT 1 FROM tickets 
            WHERE ticket_id = '$ticket_id_escaped' AND guest_user_id = '$guest_id_escaped'
        ";
        $result = $db->query($query);
    }
    
    return ($result && $result->num_rows > 0);
}

// Send email notification
function send_email_notification($to, $subject, $message) {
    // This is a placeholder function. In a real application, you would 
    // implement proper email sending (e.g., using PHPMailer)
    
    // For development, just log it
    error_log("Email notification: To: $to, Subject: $subject, Message: $message");
    
    return true;
}

// Generate a reference number for tickets
function generate_ticket_reference() {
    // Format: HD-YYYYMMDD-XXXXX (where X is a random alphanumeric character)
    $prefix = 'HD-' . date('Ymd') . '-';
    $random = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5);
    
    return $prefix . $random;
}

// Format content with basic Markdown-like syntax
function format_content($content) {
    // Convert URLs to links
    $content = preg_replace('/(https?:\/\/[^\s]+)/', '<a href="$1" target="_blank">$1</a>', $content);
    
    // Convert newlines to <br>
    $content = nl2br($content);
    
    return $content;
}

// Validate file upload
function validate_file_upload($file, $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'], $max_size = 5242880) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    if ($file['size'] > $max_size) {
        return false;
    }
    
    return true;
}

// CSRF token generation and validation
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_token_input() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verify_csrf_token() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}