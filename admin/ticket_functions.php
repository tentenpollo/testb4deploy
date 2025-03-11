<?php
// ticket_functions.php 
function get_ticket_details($ticket_id) {
    $db = db_connect();
    
    $ticket_id = $db->real_escape_string($ticket_id);
    
    $query = "SELECT t.*, 
              c.name AS category_name, 
              p.name AS priority_name,
              u_created.name AS created_by_name,
              u_assigned.name AS assigned_to_name
              FROM tickets t
              LEFT JOIN categories c ON t.category_id = c.id
              LEFT JOIN priorities p ON t.priority_id = p.id
              LEFT JOIN users u_created ON t.created_by = u_created.id
              LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id
              WHERE t.id = '$ticket_id'";
              
    $result = $db->query($query);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}


function get_ticket_history($ticket_id) {
    $db = db_connect();
    
    $ticket_id = $db->real_escape_string($ticket_id);
    
    $query = "SELECT h.*, u.name AS user_name 
              FROM ticket_history h
              LEFT JOIN users u ON h.user_id = u.id
              WHERE h.ticket_id = '$ticket_id'
              ORDER BY h.created_at DESC";
              
    $result = $db->query($query);
    
    $history = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
    }
    
    return $history;
}


function get_ticket_attachments($ticket_id) {
    $db = db_connect();
    
    $ticket_id = $db->real_escape_string($ticket_id);
    
    $query = "SELECT * FROM ticket_attachments
              WHERE ticket_id = '$ticket_id'
              ORDER BY upload_date DESC";
              
    $result = $db->query($query);
    
    $attachments = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $attachments[] = $row;
        }
    }
    
    return $attachments;
}

function add_ticket_comment($ticket_id, $user_id, $content, $is_private = false) {
    $db = db_connect();
    
    $ticket_id = $db->real_escape_string($ticket_id);
    $user_id = $db->real_escape_string($user_id);
    $content = $db->real_escape_string($content);
    $is_private = $is_private ? 1 : 0;
    
    $query = "INSERT INTO ticket_history (ticket_id, user_id, type, content, is_private, created_at)
              VALUES ('$ticket_id', '$user_id', 'comment', '$content', '$is_private', NOW())";
              
    return $db->query($query);
}

function update_ticket_status($ticket_id, $user_id, $new_status) {
    $db = db_connect();
    
    $ticket_id = $db->real_escape_string($ticket_id);
    $user_id = $db->real_escape_string($user_id);
    $new_status = $db->real_escape_string($new_status);
    
    
    $query = "SELECT status FROM tickets WHERE id = '$ticket_id'";
    $result = $db->query($query);
    $old_status = '';
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $old_status = $row['status'];
    }
    
    
    $update_query = "UPDATE tickets SET status = '$new_status' WHERE id = '$ticket_id'";
    $update_result = $db->query($update_query);
    
    if ($update_result) {
        
        $history_query = "INSERT INTO ticket_history 
                         (ticket_id, user_id, type, old_value, new_value, created_at)
                         VALUES 
                         ('$ticket_id', '$user_id', 'status_change', '$old_status', '$new_status', NOW())";
        $db->query($history_query);
        
        return true;
    }
    
    return false;
}

function update_ticket_priority($ticket_id, $user_id, $new_priority_id) {
    $db = db_connect();
    
    $ticket_id = $db->real_escape_string($ticket_id);
    $user_id = $db->real_escape_string($user_id);
    $new_priority_id = $db->real_escape_string($new_priority_id);
    
    
    $query = "SELECT t.priority_id, p_old.name AS old_priority_name, p_new.name AS new_priority_name
              FROM tickets t
              LEFT JOIN priorities p_old ON t.priority_id = p_old.id
              LEFT JOIN priorities p_new ON p_new.id = '$new_priority_id'
              WHERE t.id = '$ticket_id'";
    $result = $db->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $old_priority_id = $row['priority_id'];
        $old_priority_name = $row['old_priority_name'];
        $new_priority_name = $row['new_priority_name'];
        
        
        $update_query = "UPDATE tickets SET priority_id = '$new_priority_id' WHERE id = '$ticket_id'";
        $update_result = $db->query($update_query);
        
        if ($update_result) {
            
            $history_query = "INSERT INTO ticket_history 
                             (ticket_id, user_id, type, old_value, new_value, created_at)
                             VALUES 
                             ('$ticket_id', '$user_id', 'priority_change', '$old_priority_name', '$new_priority_name', NOW())";
            $db->query($history_query);
            
            return true;
        }
    }
    
    return false;
}

function assign_ticket($ticket_id, $admin_user_id, $assignee_id) {
    $db = db_connect();
    
    $ticket_id = $db->real_escape_string($ticket_id);
    $admin_user_id = $db->real_escape_string($admin_user_id);
    $assignee_id = $db->real_escape_string($assignee_id);
    
    
    $query = "SELECT 
              (SELECT name FROM users WHERE id = t.assigned_to) AS old_assignee_name,
              (SELECT name FROM users WHERE id = '$assignee_id') AS new_assignee_name
              FROM tickets t
              WHERE t.id = '$ticket_id'";
    $result = $db->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $old_assignee_name = $row['old_assignee_name'] ?: 'Unassigned';
        $new_assignee_name = $row['new_assignee_name'] ?: 'Unassigned';
        
        
        $update_query = "UPDATE tickets SET assigned_to = " . ($assignee_id ? "'$assignee_id'" : "NULL") . " WHERE id = '$ticket_id'";
        $update_result = $db->query($update_query);
        
        if ($update_result) {
            
            $history_query = "INSERT INTO ticket_history 
                             (ticket_id, user_id, type, old_value, new_value, created_at)
                             VALUES 
                             ('$ticket_id', '$admin_user_id', 'assignment', '$old_assignee_name', '$new_assignee_name', NOW())";
            $db->query($history_query);
            
            return true;
        }
    }
    
    return false;
}

function add_ticket_attachment($ticket_id, $user_id, $filename, $file_path, $file_size) {
    $db = db_connect();
    
    $ticket_id = $db->real_escape_string($ticket_id);
    $user_id = $db->real_escape_string($user_id);
    $filename = $db->real_escape_string($filename);
    $file_path = $db->real_escape_string($file_path);
    $file_size = $db->real_escape_string($file_size);
    
    
    $query = "INSERT INTO ticket_attachments 
             (ticket_id, filename, file_path, filesize, upload_date)
             VALUES 
             ('$ticket_id', '$filename', '$file_path', '$file_size', NOW())";
    $result = $db->query($query);
    
    if ($result) {
        $attachment_id = $db->insert_id;
        
        
        $history_query = "INSERT INTO ticket_history 
                         (ticket_id, user_id, type, new_value, created_at)
                         VALUES 
                         ('$ticket_id', '$user_id', 'attachment', '$filename', NOW())";
        $db->query($history_query);
        
        return $attachment_id;
    }
    
    return false;
}

function archive_ticket($ticket_id, $user_id) {
    $db = db_connect();
    
    $ticket_id = $db->real_escape_string($ticket_id);
    $user_id = $db->real_escape_string($user_id);
    
    $query = "UPDATE tickets SET is_archived = 1 WHERE id = '$ticket_id'";
    $result = $db->query($query);
    
    if ($result) {
        
        $history_query = "INSERT INTO ticket_history 
                         (ticket_id, user_id, type, new_value, created_at)
                         VALUES 
                         ('$ticket_id', '$user_id', 'archive', 'Ticket archived', NOW())";
        $db->query($history_query);
        
        return true;
    }
    
    return false;
}

function get_all_priorities() {
    $db = db_connect();
    
    $query = "SELECT * FROM priorities ORDER BY id";
    $result = $db->query($query);
    
    $priorities = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $priorities[] = $row;
        }
    }
    
    return $priorities;
}

function get_assignable_users() {
    $db = db_connect();
    
    $query = "SELECT id, name FROM users WHERE role = 'staff' OR role = 'admin' ORDER BY name";
    $result = $db->query($query);
    
    $users = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    
    return $users;
}
?>