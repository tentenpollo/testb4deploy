<?php
require_once 'config.php';

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
        c.name as category_name,
        sm.name as assigned_to_name
    FROM tickets t 
    LEFT JOIN priorities p ON t.priority_id = p.id 
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN staff_members sm ON t.assigned_to = sm.id
    ORDER BY t.created_at DESC";

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

    $sql = "SELECT id, name FROM staff_members WHERE role IN ('admin', 'agent', 'master_agent') ORDER BY name";
    $result = $mysqli->query($sql);

    $users = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

    return $users;
}

function getStaffMemberDetails()
{
    $mysqli = db_connect();
    
    // Prepare the SQL statement to prevent SQL injection
    $stmt = $mysqli->prepare("SELECT id, name, email FROM staff_members WHERE id = ?");
    
    // Bind the user_id parameter
    $stmt->bind_param("i", $_SESSION['user_id']);
    
    // Execute the query
    $stmt->execute();
    
    // Get the result
    $result = $stmt->get_result();
    
    // Check if a staff member was found
    if ($result->num_rows > 0) {
        // Fetch the staff member details
        $staff_member = $result->fetch_assoc();
        
        // Update session with staff member details
        $_SESSION['staff_member_id'] = $staff_member['id'];
        $_SESSION['staff_member_name'] = $staff_member['name'];
        $_SESSION['staff_member_email'] = $staff_member['email'];
        
        return $staff_member;
    }
    
    // Return null if no staff member found
    return null;
}