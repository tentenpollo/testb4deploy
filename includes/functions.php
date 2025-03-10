<?php
require_once 'config.php';

function format_date($datetime, $format = 'M j, Y g:i A')
{
    if (empty($datetime))
        return '';
    $date = new DateTime($datetime);
    return $date->format($format);
}

// Calculate time elapsed in a human-readable format
function time_elapsed_string($datetime)
{
    if (empty($datetime))
        return '';

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
function get_status_class($status)
{
    switch (strtolower($status)) {
        case 'new':
            return 'status-new';
        case 'open':
            return 'status-open';
        case 'in progress':
            return 'status-progress';
        case 'awaiting response':
            return 'status-awaiting';
        case 'resolved':
            return 'status-resolved';
        case 'closed':
            return 'status-closed';
        default:
            return '';
    }
}

// Get priority class for CSS
function get_priority_class($priority)
{
    switch (strtolower($priority)) {
        case 'low':
            return 'priority-low';
        case 'medium':
            return 'priority-medium';
        case 'high':
            return 'priority-high';
        case 'urgent':
            return 'priority-urgent';
        default:
            return '';
    }
}

// Truncate text to a certain length
function truncate_text($text, $length = 50, $append = '...')
{
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
function user_can_access_ticket($ticket_id)
{
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
function send_email_notification($to, $subject, $message)
{
    // This is a placeholder function. In a real application, you would 
    // implement proper email sending (e.g., using PHPMailer)

    // For development, just log it
    error_log("Email notification: To: $to, Subject: $subject, Message: $message");

    return true;
}

// Generate a reference number for tickets
function generate_ticket_reference()
{
    // Format: HD-YYYYMMDD-XXXXX (where X is a random alphanumeric character)
    $prefix = 'HD-' . date('Ymd') . '-';
    $random = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5);

    return $prefix . $random;
}

// Format content with basic Markdown-like syntax
function format_content($content)
{
    // Convert URLs to links
    $content = preg_replace('/(https?:\/\/[^\s]+)/', '<a href="$1" target="_blank">$1</a>', $content);

    // Convert newlines to <br>
    $content = nl2br($content);

    return $content;
}

// Validate file upload
function validate_file_upload($file, $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'], $max_size = 5242880)
{
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
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_token_input()
{
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verify_csrf_token()
{
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Get All Tickets
function getAllTickets()
{
    $mysqli = db_connect();

    $sql = "SELECT 
        t.id, 
        t.title, 
        t.description, 
        t.status, 
        t.created_by, 
        t.created_at,
        t.priority_id,
        t.category_id,
        t.assigned_to,
        p.name as priority_name,
        c.name as category_name
    FROM 
        tickets t
    LEFT JOIN 
        priorities p ON t.priority_id = p.id
    LEFT JOIN 
        categories c ON t.category_id = c.id
    ORDER BY 
        t.created_at DESC";

    $result = $mysqli->query($sql);

    $tickets = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $tickets[] = $row;
        }
    }

    return $tickets;
}

function getAllPriorities()
{
    $mysqli = db_connect();

    $sql = "SELECT id, name, level, created_at, updated_at FROM priorities ORDER BY level ASC";
    $result = $mysqli->query($sql);

    $priorities = [];
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $priorities[] = $row;
        }
    }

    return $priorities;
}


function getAllCategories()
{
    $mysqli = db_connect();

    $sql = "SELECT id, name FROM categories ORDER BY id";
    $result = $mysqli->query($sql);

    $categories = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    return $categories;
}

// Get Users for Assignment
function getAssignableUsers()
{
    $mysqli = db_connect();

    $sql = "SELECT id, name FROM staff_members WHERE role IN ('admin', 'support') ORDER BY name";
    $result = $mysqli->query($sql);

    $users = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

    return $users;
}

// Create New Ticket
function createTicket($data)
{
    $mysqli = db_connect();

    $sql = "INSERT INTO tickets (
        title, 
        description, 
        status, 
        created_by, 
        priority_id, 
        category_id, 
        assigned_to, 
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param(
        "ssssiis",
        $data['title'],
        $data['description'],
        $data['status'],
        $data['created_by'],
        $data['priority_id'],
        $data['category_id'],
        $data['assigned_to']
    );

    $result = $stmt->execute();
    $ticket_id = $result ? $stmt->insert_id : false;

    $stmt->close();

    return $ticket_id;
}

// Update Ticket
function updateTicket($ticket_id, $data)
{
    $mysqli = db_connect();

    $sql = "UPDATE tickets 
            SET 
                title = ?, 
                description = ?, 
                status = ?, 
                priority_id = ?, 
                category_id = ?, 
                assigned_to = ?,
                updated_at = NOW()
            WHERE id = ?";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param(
        "sssiiis",
        $data['title'],
        $data['description'],
        $data['status'],
        $data['priority_id'],
        $data['category_id'],
        $data['assigned_to'],
        $ticket_id
    );

    $result = $stmt->execute();

    $stmt->close();

    return $result;
}

// Get Ticket by ID
function getTicketById($ticket_id)
{
    $mysqli = db_connect();

    $sql = "SELECT * FROM tickets WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc();

    $stmt->close();

    return $ticket;
}

// Delete Ticket
function deleteTicket($ticket_id)
{
    $mysqli = db_connect();

    $sql = "DELETE FROM tickets WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $ticket_id);

    $result = $stmt->execute();

    $stmt->close();

    return $result;
}

// Search Tickets
function searchTickets($search_term)
{
    $mysqli = db_connect();

    $search_term = "%{$search_term}%";
    $sql = "SELECT * FROM tickets 
            WHERE title LIKE ? 
            OR description LIKE ? 
            OR status LIKE ?";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
    $stmt->execute();

    $result = $stmt->get_result();
    $tickets = [];

    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }

    $stmt->close();

    return $tickets;
}

// Helper function to return JSON response
function jsonResponse($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Error Logging Function
function logError($message, $file = 'error.log')
{
    error_log(date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 3, $file);
}

// Get Ticket Count by Status
function getTicketCountByStatus()
{
    $mysqli = db_connect();

    $sql = "SELECT status, COUNT(*) as count FROM tickets GROUP BY status";
    $result = $mysqli->query($sql);

    $status_counts = [];
    while ($row = $result->fetch_assoc()) {
        $status_counts[$row['status']] = $row['count'];
    }

    return $status_counts;
}

// Get Recent Tickets
function getRecentTickets($limit = 5)
{
    $mysqli = db_connect();

    $sql = "SELECT id, title, status, created_at 
            FROM tickets 
            ORDER BY created_at DESC 
            LIMIT ?";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();

    $result = $stmt->get_result();
    $recent_tickets = [];

    while ($row = $result->fetch_assoc()) {
        $recent_tickets[] = $row;
    }

    $stmt->close();

    return $recent_tickets;
}

function getSubcategoryId($subcategoryName)
{
    $db = db_connect();
    $stmt = $db->prepare("SELECT id FROM subcategories WHERE name = ?");

    if ($stmt === false) {
        error_log("Database error in getSubcategoryId: " . $db->error);
        return null;
    }

    if ($stmt->execute([$subcategoryName])) {
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['id'];
        }
    }

    // If subcategory doesn't exist, create it
    $insertStmt = $db->prepare("INSERT INTO subcategories (name, created_at, updated_at) VALUES (?, NOW(), NOW())");

    if ($insertStmt === false) {
        error_log("Database error when creating subcategory: " . $db->error);
        return null;
    }

    if ($insertStmt->execute([$subcategoryName])) {
        return $db->insert_id;
    }

    return null;
}

/**
 * Get priority ID based on a predefined mapping or default priority
 * 
 * @param string $subcategoryName Name of the subcategory
 * @return int Priority ID (1=Low, 2=Medium, 3=High)
 */
function getPriorityId($subcategoryName)
{
    // Define priority mappings for specific subcategories
    $priorityMap = [
        // High priority subcategories
        'Payment Issue' => 3,
        'Login Problem' => 3,
        'Website Issue' => 2,
        'Performance' => 2,
        'Refund Request' => 2,
        // All others default to 1 (low priority)
    ];

    return $priorityMap[$subcategoryName] ?? 1; // Default to low priority (1) if not found
}