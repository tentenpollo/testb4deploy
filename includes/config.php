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

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_guest() {
    return isset($_SESSION['guest_id']);
}

function require_login() {
    if (!is_logged_in() && !is_guest()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: login.php");
        exit;
    }
}

function logout() {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
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

// Get user data
function get_user_data($user_id) {
    $mysqli = db_connect();
    
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
    
    return $user_data;
}

// Get guest data
function get_guest_data($guest_id) {
    $mysqli = db_connect();
    
    $stmt = $mysqli->prepare("SELECT * FROM guest_users WHERE guest_id = ?");
    $stmt->bind_param("i", $guest_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $guest_data = $result->fetch_assoc();
    $stmt->close();
    
    return $guest_data;
}

// Get departments
function get_departments() {
    $mysqli = db_connect();
    
    $stmt = $mysqli->prepare("SELECT * FROM departments WHERE is_active = TRUE");
    $stmt->execute();
    $result = $stmt->get_result();
    $departments = [];
    
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
    
    $stmt->close();
    return $departments;
}

// Get categories by department
function get_categories_by_department($department_id) {
    $mysqli = db_connect();
    
    $stmt = $mysqli->prepare("SELECT * FROM categories WHERE department_id = ? AND is_active = TRUE");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    $stmt->close();
    return $categories;
}

// Get priorities
function get_priorities() {
    $mysqli = db_connect();
    
    $stmt = $mysqli->prepare("SELECT * FROM priorities");
    $stmt->execute();
    $result = $stmt->get_result();
    $priorities = [];
    
    while ($row = $result->fetch_assoc()) {
        $priorities[] = $row;
    }
    
    $stmt->close();
    return $priorities;
}

// Create ticket
function create_ticket($subject, $description, $department_id, $category_id, $priority_id = 1) {
    $mysqli = db_connect();
    
    // Get initial status (usually "New" or "Open")
    $stmt = $mysqli->prepare("SELECT status_id FROM statuses WHERE name = 'New' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $status = $result->fetch_assoc();
    $status_id = $status ? $status['status_id'] : 1;
    $stmt->close();
    
    // Start transaction
    $mysqli->autocommit(FALSE);
    try {
        // Insert ticket
        $stmt = $mysqli->prepare("
            INSERT INTO tickets 
            (subject, description, department_id, category_id, priority_id, status_id, registered_user_id, guest_user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (is_logged_in()) {
            $registered_user_id = $_SESSION['user_id'];
            $guest_user_id = null;
            $stmt->bind_param("ssiiiisi", $subject, $description, $department_id, $category_id, $priority_id, $status_id, $registered_user_id, $guest_user_id);
        } else {
            $registered_user_id = null;
            $guest_user_id = $_SESSION['guest_id'];
            $stmt->bind_param("ssiiiisi", $subject, $description, $department_id, $category_id, $priority_id, $status_id, $registered_user_id, $guest_user_id);
        }
        
        $stmt->execute();
        $ticket_id = $mysqli->insert_id;
        $stmt->close();
        
        // Create initial ticket history
        $stmt = $mysqli->prepare("
            INSERT INTO ticket_history 
            (ticket_id, field_name, old_value, new_value, changed_by_user_id, changed_by_staff_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $field_name = 'status';
        $old_value = null;
        $new_value = 'New';
        $staff_id = null;
        
        if (is_logged_in()) {
            $user_id = $_SESSION['user_id'];
            $stmt->bind_param("isssii", $ticket_id, $field_name, $old_value, $new_value, $user_id, $staff_id);
        } else {
            $user_id = null;
            $stmt->bind_param("isssii", $ticket_id, $field_name, $old_value, $new_value, $user_id, $staff_id);
        }
        
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $mysqli->commit();
        return $ticket_id;
        
    } catch (Exception $e) {
        // Rollback on error
        $mysqli->rollback();
        error_log("Error creating ticket: " . $e->getMessage());
        return false;
    } finally {
        // Restore autocommit setting
        $mysqli->autocommit(TRUE);
    }
}

// Get user tickets
function get_user_tickets() {
    $mysqli = db_connect();
    
    if (is_logged_in()) {
        $stmt = $mysqli->prepare("
            SELECT t.*, s.name as status, p.name as priority, p.color as priority_color, d.name as department, c.name as category 
            FROM tickets t
            JOIN statuses s ON t.status_id = s.status_id
            JOIN priorities p ON t.priority_id = p.priority_id
            JOIN departments d ON t.department_id = d.department_id
            JOIN categories c ON t.category_id = c.category_id
            WHERE t.registered_user_id = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->bind_param("i", $_SESSION['user_id']);
    } else {
        $stmt = $mysqli->prepare("
            SELECT t.*, s.name as status, p.name as priority, p.color as priority_color, d.name as department, c.name as category 
            FROM tickets t
            JOIN statuses s ON t.status_id = s.status_id
            JOIN priorities p ON t.priority_id = p.priority_id
            JOIN departments d ON t.department_id = d.department_id
            JOIN categories c ON t.category_id = c.category_id
            WHERE t.guest_user_id = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->bind_param("i", $_SESSION['guest_id']);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $tickets = [];
    
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
    
    $stmt->close();
    return $tickets;
}

// Get single ticket with comments
function get_ticket($ticket_id) {
    $mysqli = db_connect();
    
    // Get ticket details
    $stmt = $mysqli->prepare("
        SELECT t.*, s.name as status, p.name as priority, p.color as priority_color, 
               d.name as department, c.name as category
        FROM tickets t
        JOIN statuses s ON t.status_id = s.status_id
        JOIN priorities p ON t.priority_id = p.priority_id
        JOIN departments d ON t.department_id = d.department_id
        JOIN categories c ON t.category_id = c.category_id
        WHERE t.ticket_id = ?
    ");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc();
    $stmt->close();
    
    if (!$ticket) {
        return false;
    }
    
    // Check permissions
    if (is_logged_in() && $ticket['registered_user_id'] != $_SESSION['user_id']) {
        return false;
    }
    
    if (is_guest() && $ticket['guest_user_id'] != $_SESSION['guest_id']) {
        return false;
    }
    
    // Get comments
    $stmt = $mysqli->prepare("
        SELECT tc.*, 
               u.first_name as user_first_name, u.last_name as user_last_name,
               g.first_name as guest_first_name, g.last_name as guest_last_name,
               CONCAT(s2.first_name, ' ', s2.last_name) as staff_name
        FROM ticket_comments tc
        LEFT JOIN users u ON tc.registered_user_id = u.user_id
        LEFT JOIN guest_users g ON tc.guest_user_id = g.guest_id
        LEFT JOIN staff s ON tc.staff_id = s.staff_id
        LEFT JOIN users s2 ON s.user_id = s2.user_id
        WHERE tc.ticket_id = ? AND (tc.is_internal = FALSE OR tc.is_internal IS NULL)
        ORDER BY tc.created_at ASC
    ");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = [];
    
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    
    $ticket['comments'] = $comments;
    $stmt->close();
    
    return $ticket;
}

// Add comment to ticket
function add_ticket_comment($ticket_id, $content) {
    $mysqli = db_connect();
    
    try {
        if (is_logged_in()) {
            $stmt = $mysqli->prepare("
                INSERT INTO ticket_comments 
                (ticket_id, content, registered_user_id) 
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("isi", $ticket_id, $content, $_SESSION['user_id']);
        } else {
            $stmt = $mysqli->prepare("
                INSERT INTO ticket_comments 
                (ticket_id, content, guest_user_id) 
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("isi", $ticket_id, $content, $_SESSION['guest_id']);
        }
        
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Error adding comment: " . $e->getMessage());
        return false;
    }
}
?>