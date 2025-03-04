<?php
// config.php - Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ticketing_system');
define('DB_USER', 'root');
define('DB_PASS', '');

function db_connect() {
    try {
        $pdo = new PDO(
            "pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

session_start();

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
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Get user data
function get_user_data($user_id) {
    $db = db_connect();
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get guest data
function get_guest_data($guest_id) {
    $db = db_connect();
    $stmt = $db->prepare("SELECT * FROM guest_users WHERE guest_id = ?");
    $stmt->execute([$guest_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get departments
function get_departments() {
    $db = db_connect();
    $stmt = $db->prepare("SELECT * FROM departments WHERE is_active = TRUE");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get categories by department
function get_categories_by_department($department_id) {
    $db = db_connect();
    $stmt = $db->prepare("SELECT * FROM categories WHERE department_id = ? AND is_active = TRUE");
    $stmt->execute([$department_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get priorities
function get_priorities() {
    $db = db_connect();
    $stmt = $db->prepare("SELECT * FROM priorities");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Create ticket
function create_ticket($subject, $description, $department_id, $category_id, $priority_id = 1) {
    $db = db_connect();
    
    // Get initial status (usually "New" or "Open")
    $stmt = $db->prepare("SELECT status_id FROM statuses WHERE name = 'New' LIMIT 1");
    $stmt->execute();
    $status = $stmt->fetch(PDO::FETCH_ASSOC);
    $status_id = $status ? $status['status_id'] : 1;
    
    try {
        $db->beginTransaction();
        
        // Insert ticket
        $stmt = $db->prepare("
            INSERT INTO tickets 
            (subject, description, department_id, category_id, priority_id, status_id, registered_user_id, guest_user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (is_logged_in()) {
            $stmt->execute([$subject, $description, $department_id, $category_id, $priority_id, $status_id, $_SESSION['user_id'], null]);
        } else {
            $stmt->execute([$subject, $description, $department_id, $category_id, $priority_id, $status_id, null, $_SESSION['guest_id']]);
        }
        
        $ticket_id = $db->lastInsertId();
        
        // Create initial ticket history
        $stmt = $db->prepare("
            INSERT INTO ticket_history 
            (ticket_id, field_name, old_value, new_value, changed_by_user_id, changed_by_staff_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        if (is_logged_in()) {
            $stmt->execute([$ticket_id, 'status', null, 'New', $_SESSION['user_id'], null]);
        } else {
            $stmt->execute([$ticket_id, 'status', null, 'New', null, null]);
        }
        
        $db->commit();
        return $ticket_id;
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error creating ticket: " . $e->getMessage());
        return false;
    }
}

// Get user tickets
function get_user_tickets() {
    $db = db_connect();
    
    if (is_logged_in()) {
        $stmt = $db->prepare("
            SELECT t.*, s.name as status, p.name as priority, p.color as priority_color, d.name as department, c.name as category 
            FROM tickets t
            JOIN statuses s ON t.status_id = s.status_id
            JOIN priorities p ON t.priority_id = p.priority_id
            JOIN departments d ON t.department_id = d.department_id
            JOIN categories c ON t.category_id = c.category_id
            WHERE t.registered_user_id = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        $stmt = $db->prepare("
            SELECT t.*, s.name as status, p.name as priority, p.color as priority_color, d.name as department, c.name as category 
            FROM tickets t
            JOIN statuses s ON t.status_id = s.status_id
            JOIN priorities p ON t.priority_id = p.priority_id
            JOIN departments d ON t.department_id = d.department_id
            JOIN categories c ON t.category_id = c.category_id
            WHERE t.guest_user_id = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$_SESSION['guest_id']]);
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get single ticket with comments
function get_ticket($ticket_id) {
    $db = db_connect();
    
    // Get ticket details
    $stmt = $db->prepare("
        SELECT t.*, s.name as status, p.name as priority, p.color as priority_color, 
               d.name as department, c.name as category
        FROM tickets t
        JOIN statuses s ON t.status_id = s.status_id
        JOIN priorities p ON t.priority_id = p.priority_id
        JOIN departments d ON t.department_id = d.department_id
        JOIN categories c ON t.category_id = c.category_id
        WHERE t.ticket_id = ?
    ");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
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
    $stmt = $db->prepare("
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
    $stmt->execute([$ticket_id]);
    $ticket['comments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $ticket;
}

// Add comment to ticket
function add_ticket_comment($ticket_id, $content) {
    $db = db_connect();
    
    try {
        if (is_logged_in()) {
            $stmt = $db->prepare("
                INSERT INTO ticket_comments 
                (ticket_id, content, registered_user_id) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$ticket_id, $content, $_SESSION['user_id']]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO ticket_comments 
                (ticket_id, content, guest_user_id) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$ticket_id, $content, $_SESSION['guest_id']]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error adding comment: " . $e->getMessage());
        return false;
    }
}
?>