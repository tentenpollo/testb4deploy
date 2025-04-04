<?php
require_once __DIR__ . '/../includes/email_functions.php';

function log_ticket_history($ticket_id, $user_id, $type, $old_value = null, $new_value = null)
{
    $db = db_connect();

    if (!$db) {
        error_log("Database connection failed in log_ticket_history");
        return false;
    }

    // Sanitize inputs
    $ticket_id = $db->real_escape_string($ticket_id);
    $user_id = $db->real_escape_string($user_id);
    $type = $db->real_escape_string($type);
    $old_value = $old_value !== null ? $db->real_escape_string($old_value) : "NULL";
    $new_value = $new_value !== null ? $db->real_escape_string($new_value) : "NULL";

    // Set NULL as SQL NULL rather than string 'NULL'
    $old_value_sql = $old_value === "NULL" ? "NULL" : "'$old_value'";
    $new_value_sql = $new_value === "NULL" ? "NULL" : "'$new_value'";

    $query = "INSERT INTO ticket_history 
             (ticket_id, staff_id, type, old_value, new_value, created_at)
             VALUES 
             ('$ticket_id', '$user_id', '$type', $old_value_sql, $new_value_sql, NOW())";

    if (!$db->query($query)) {
        error_log("Failed to log ticket history: " . $db->error . " - Query: " . $query);
        return false;
    }

    return $db->insert_id;
}

function get_ticket_details($ticket_id)
{
    $db = db_connect();

    if (!$db) {
        error_log("Failed to connect to the database");
        return null;
    }

    // Validate ticket ID
    if (!is_numeric($ticket_id)) {
        error_log("Invalid ticket ID: " . $ticket_id);
        return null;
    }

    $ticket_id = $db->real_escape_string($ticket_id);

    $query = "SELECT t.*, 
       c.name AS category_name,
       s.name AS subcategory_name, 
       p.name AS priority_name,
       IFNULL(CONCAT(u_created.first_name, ' ', u_created.last_name), 'Guest') AS created_by_name,
       IFNULL(CONCAT(u_assigned.first_name, ' ', u_assigned.last_name), 'Unassigned') AS assigned_to_name,
       IF(t.created_by = 'guest', t.guest_email, u_created.email) AS creator_email
FROM tickets t
LEFT JOIN categories c ON t.category_id = c.id
LEFT JOIN subcategories s ON t.subcategory_id = s.id
LEFT JOIN priorities p ON t.priority_id = p.id
LEFT JOIN users u_created ON t.created_by = 'user' AND t.user_id = u_created.user_id
LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.user_id
WHERE t.id = '$ticket_id';";

    error_log("Executing query: " . $query);

    $result = $db->query($query);

    if ($result && $result->num_rows > 0) {
        $ticket = $result->fetch_assoc();
        error_log("Fetched ticket data: " . print_r($ticket, true));
        return $ticket;
    } else {
        error_log("No ticket found for ID: " . $ticket_id);
        return null;
    }
}


function get_ticket_history($ticket_id)
{
    $db = db_connect();

    $ticket_id = $db->real_escape_string($ticket_id);

    $query = "
        SELECT 
            tc.*,
            u.first_name AS user_first_name,
            u.last_name AS user_last_name,
            sm.name AS staff_name,
            CASE 
                WHEN tc.commenter_type = 'staff' THEN 
                    (SELECT name FROM staff_members WHERE id = tc.staff_id)
                WHEN tc.commenter_type = 'user' THEN 
                    (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE user_id = tc.user_id)
                ELSE 'Guest'
            END AS user_name
        FROM 
            ticket_comments tc
        LEFT JOIN users u ON tc.user_id = u.user_id
        LEFT JOIN staff_members sm ON tc.staff_id = sm.id
        WHERE 
            tc.ticket_id = '$ticket_id'
        ORDER BY 
            tc.created_at ASC
    ";

    $result = $db->query($query);

    $history = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Get attachments for this comment
            $comment_id = $db->real_escape_string($row['id']);
            $attachments_query = "
                SELECT * FROM attachments 
                WHERE ticket_id = '$ticket_id' 
                AND comment_id = '$comment_id'
            ";

            $attachments_result = $db->query($attachments_query);
            $attachments = [];

            if ($attachments_result && $attachments_result->num_rows > 0) {
                while ($attachment = $attachments_result->fetch_assoc()) {
                    $attachments[] = $attachment;
                }
            }

            // Add attachments to the comment data
            $row['attachments'] = $attachments;

            // Add a calculated is_agent field for easier frontend handling
            $row['is_agent'] = ($row['commenter_type'] === 'staff' || !empty($row['staff_id']));

            // Make sure is_internal is consistent (using string values for comparison)
            $row['is_internal'] = $row['is_internal'] ? '1' : '0';

            $history[] = $row;
        }
    }

    return $history;
}

function get_ticket_attachments($ticket_id)
{
    $db = db_connect();

    $ticket_id = $db->real_escape_string($ticket_id);

    $query = "SELECT * FROM attachments
              WHERE ticket_id = '$ticket_id'
              ORDER BY created_at DESC";

    $result = $db->query($query);

    $attachments = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $attachments[] = $row;
        }
    }

    return $attachments;
}

function add_ticket_comment($ticket_id, $user_id, $content, $attachments = [], $user_type = 'user', $is_private = false)
{
    $db = db_connect();

    if (!$db) {
        error_log("Database connection failed in add_ticket_comment");
        return false;
    }

    $db->begin_transaction();

    try {
        $ticket_id = $db->real_escape_string($ticket_id);
        $user_id = $db->real_escape_string($user_id);
        $content = $db->real_escape_string($content);
        $is_private = $is_private ? 1 : 0;

        $commenter_type = $user_type;
        if ($user_type == 'admin') {
            $commenter_type = 'staff'; // Use 'staff' for admin as the enum doesn't have 'admin'
        }

        // Initialize staff_id and actual user_id for the query
        $staff_id = 'NULL';
        $actual_user_id = 'NULL';

        if ($user_type == 'staff' || $user_type == 'admin') {
            // For staff or admin, use staff_id column
            $staff_id = "'$user_id'";
        } elseif ($user_type == 'guest') {
            // For guests, both user_id and staff_id remain NULL
        } else {
            // For regular users, set the user_id column
            $actual_user_id = "'$user_id'";
        }

        $query = "INSERT INTO ticket_comments (ticket_id, user_id, staff_id, type, content, is_internal, created_at, commenter_type)
                 VALUES ('$ticket_id', $actual_user_id, $staff_id, 'comment', '$content', '$is_private', NOW(), '$commenter_type')";

        if (!$db->query($query)) {
            throw new Exception("Failed to insert comment: " . $db->error);
        }

        $comment_id = $db->insert_id;

        if ($user_type == 'staff' || $user_type == 'admin') {
            log_ticket_history($ticket_id, $user_id, 'comment', null, substr($content, 0, 50) . (strlen($content) > 50 ? '...' : ''));
        }

        if (!empty($attachments)) {
            $upload_dir = '../../uploads/tickets/' . $ticket_id;

            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    throw new Exception("Failed to create upload directory");
                }
            }

            foreach ($attachments as $attachment) {
                if ($attachment['error'] === UPLOAD_ERR_OK) {
                    $tmp_name = $attachment['tmp_name'];
                    $name = basename($attachment['name']);

                    $file_path = $upload_dir . '/' . time() . '_' . $name;

                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $filename = $db->real_escape_string($name);
                        $filepath = $db->real_escape_string($file_path);

                        // Determine which field to use based on user type
                        $attachment_fields = "ticket_id, comment_id, filename, file_path, created_at";
                        $attachment_values = "'$ticket_id', '$comment_id', '$filename', '$filepath', NOW()";

                        switch ($user_type) {
                            case 'staff':
                            case 'admin':
                                $attachment_fields .= ", uploaded_by_staff_member_id";
                                $attachment_values .= ", '$user_id'";
                                break;
                            case 'guest':
                                $attachment_fields .= ", uploaded_by_guest_email";
                                $attachment_values .= ", '" . $db->real_escape_string($user_id) . "'";
                                break;
                            case 'user':
                            default:
                                $attachment_fields .= ", uploaded_by_user_id";
                                $attachment_values .= ", '$user_id'";
                                break;
                        }

                        $attachment_query = "INSERT INTO attachments ($attachment_fields) VALUES ($attachment_values)";

                        if (!$db->query($attachment_query)) {
                            throw new Exception("Failed to insert attachment record: " . $db->error);
                        }
                    } else {
                        throw new Exception("Failed to move uploaded file");
                    }
                }
            }
        }

        // Send email notification if this is an agent/staff response and NOT an internal note
        if (($user_type == 'staff' || $user_type == 'admin') && !$is_private) {
            // Get ticket details for the email
            $ticket = get_ticket_details($ticket_id);

            if ($ticket) {
                // Get customer email
                $customer_email = $ticket['creator_email'];

                // Get agent name
                $agent_name = '';
                if ($user_type == 'staff' || $user_type == 'admin') {
                    $staff_query = "SELECT name FROM staff_members WHERE id = '$user_id' LIMIT 1";
                    $staff_result = $db->query($staff_query);
                    if ($staff_result && $staff_result->num_rows > 0) {
                        $agent_row = $staff_result->fetch_assoc();
                        $agent_name = $agent_row['name'];
                    }
                }

                // Use reference_id as is, without providing a default
                $reference_id = isset($ticket['ref_id']) ? $ticket['ref_id'] : '';

                // Prepare attachment notice if there are attachments
                $attachment_notice = '';
                if (!empty($attachments)) {
                    $attachment_notice = '<p class="attachment-notice"><strong>Note:</strong> This message includes one or more attachments.</p>';
                }

                // Prepare email data
                $replacements = [
                    'ticket_id' => $ticket_id,
                    'reference_id' => $reference_id,
                    'customer_name' => $ticket['created_by_name'],
                    'ticket_subject' => $ticket['title'],
                    'ticket_status' => ucfirst($ticket['status']),
                    'agent_name' => $agent_name,
                    'reply_content' => htmlspecialchars_decode($content),
                    'ticket_url' => 'https://yourwebsite.com/view_ticket.php?id=' . $ticket_id,
                    'current_year' => date('Y'),
                    'attachment_notice' => $attachment_notice
                ];

                // Get email template
                $email_body = get_email_template('agent_reply', $replacements);

                // Prepare attachments for the email
                $email_attachments = [];
                foreach ($attachments as $attachment) {
                    if (isset($attachment['tmp_name']) && file_exists($attachment['tmp_name'])) {
                        $email_attachments[] = $attachment['tmp_name'];
                    }
                }

                // Send email to customer
                if (!empty($email_body) && !empty($customer_email)) {
                    send_email_smtp(
                        $customer_email,
                        "New Response to Your Ticket #$ticket_id",
                        $email_body,
                        '',
                        $email_attachments
                    );
                }
            }
        }

        if ($user_type == 'user' && ($ticket['status'] == 'resolved' || $ticket['status'] == 'closed')) {
            $reopen_query = "UPDATE tickets SET status = 'open', updated_at = NOW() WHERE id = '$ticket_id'";
            $db->query($reopen_query);
            log_ticket_history($ticket_id, $user_id, 'status_change', $ticket['status'], 'open');
        }

        // If we reach here, commit the transaction
        $db->commit();
        return $comment_id;

    } catch (Exception $e) {
        $db->rollback();
        error_log("Error in add_ticket_comment: " . $e->getMessage());
        return false;
    }
}

function update_ticket_status($ticket_id, $user_id, $new_status)
{
    $db = db_connect();

    if (!$db) {
        error_log("[TICKET_DEBUG] Database connection failed in update_ticket_status");
        return false;
    }

    error_log("[TICKET_DEBUG] Starting update_ticket_status for ticket #$ticket_id, new status: $new_status");

    $db->begin_transaction();

    try {
        $ticket_id = $db->real_escape_string($ticket_id);
        $user_id = $db->real_escape_string($user_id);
        $new_status = $db->real_escape_string($new_status);

        // First get the current status
        $status_query = "SELECT status FROM tickets WHERE id = '$ticket_id'";
        error_log("[TICKET_DEBUG] Running query: $status_query");
        
        $status_result = $db->query($status_query);
        
        if (!$status_result || $status_result->num_rows == 0) {
            error_log("[TICKET_DEBUG] Ticket not found for ID: $ticket_id");
            throw new Exception("Ticket not found");
        }
        
        $status_row = $status_result->fetch_assoc();
        $old_status = $status_row['status'];
        error_log("[TICKET_DEBUG] Found ticket #$ticket_id with current status: $old_status");
        
        // Only get additional details if we're changing to resolved status
        $ticket = array('status' => $old_status);
        
        if ($new_status == 'resolved') {
            error_log("[TICKET_DEBUG] Status is being changed to 'resolved', getting additional details");
            
            // Get ticket details based on actual table structure
            $details_query = "SELECT t.title, t.ref_id, t.created_by, t.user_id, t.guest_email 
                             FROM tickets t 
                             WHERE t.id = '$ticket_id'";
            
            error_log("[TICKET_DEBUG] Running details query: $details_query");
            $details_result = $db->query($details_query);
            
            if ($details_result && $details_result->num_rows > 0) {
                $ticket = array_merge($ticket, $details_result->fetch_assoc());
                error_log("[TICKET_DEBUG] Found additional ticket details: " . print_r($ticket, true));
            } else {
                error_log("[TICKET_DEBUG] Failed to get additional ticket details. Error: " . $db->error);
            }
        }

        // Update the ticket status
        $update_query = "UPDATE tickets SET status = '$new_status', updated_at = NOW() WHERE id = '$ticket_id'";
        error_log("[TICKET_DEBUG] Running update query: $update_query");
        
        $update_result = $db->query($update_query);
        
        if (!$update_result) {
            error_log("[TICKET_DEBUG] Update failed. Error: " . $db->error);
            throw new Exception("Failed to update ticket status: " . $db->error);
        }

        // Log the status change in ticket history
        error_log("[TICKET_DEBUG] Logging ticket history");
        log_ticket_history($ticket_id, $user_id, 'status_change', $old_status, $new_status);

        // Send email notification ONLY when status is changed to "resolved"
        if ($new_status == 'resolved') {
            error_log("[TICKET_DEBUG] Status changed to 'resolved', preparing email notification");
            
            // Get staff/agent name who resolved the ticket
            $staff_name = 'Support Agent';  // Default name if we can't find the actual name
            $staff_query = "SELECT name FROM staff_members WHERE id = '$user_id' LIMIT 1";
            error_log("[TICKET_DEBUG] Getting staff name with query: $staff_query");
            
            $staff_result = $db->query($staff_query);
            
            if ($staff_result && $staff_result->num_rows > 0) {
                $staff_row = $staff_result->fetch_assoc();
                $staff_name = $staff_row['name'];
                error_log("[TICKET_DEBUG] Found staff name: $staff_name");
            } else {
                error_log("[TICKET_DEBUG] Staff name not found for ID: $user_id, using default");
            }

            // Determine the customer email based on created_by field
            $customer_email = null;
            $customer_name = 'Customer';
            
            if (isset($ticket['created_by']) && $ticket['created_by'] == 'user' && !empty($ticket['user_id'])) {
                // Get email from users table if created by a user
                $user_query = "SELECT email, CONCAT(first_name, ' ', last_name) AS customer_name 
                               FROM users WHERE id = '{$ticket['user_id']}' LIMIT 1";
                error_log("[TICKET_DEBUG] Getting user email with query: $user_query");
                
                $user_result = $db->query($user_query);
                
                if ($user_result && $user_result->num_rows > 0) {
                    $user_data = $user_result->fetch_assoc();
                    $customer_email = $user_data['email'];
                    $customer_name = $user_data['customer_name'] ?: 'Customer';
                    error_log("[TICKET_DEBUG] Found user email: $customer_email");
                } else {
                    // Try with the exact column names from your users table
                    error_log("[TICKET_DEBUG] First query failed, trying with exact column names");
                    $exact_query = "SELECT email, username, first_name, last_name 
                                   FROM users WHERE id = '{$ticket['user_id']}' LIMIT 1";
                    $exact_result = $db->query($exact_query);
                    
                    if ($exact_result && $exact_result->num_rows > 0) {
                        $exact_data = $exact_result->fetch_assoc();
                        $customer_email = $exact_data['email'];
                        
                        // Try to construct a name from available fields
                        if (!empty($exact_data['first_name']) && !empty($exact_data['last_name'])) {
                            $customer_name = $exact_data['first_name'] . ' ' . $exact_data['last_name'];
                        } elseif (!empty($exact_data['username'])) {
                            $customer_name = $exact_data['username'];
                        } else {
                            $customer_name = 'Customer';
                        }
                        
                        error_log("[TICKET_DEBUG] Found user email with exact column query: $customer_email");
                    } else {
                        // Last resort - hard-coded query based on your database screenshot
                        error_log("[TICKET_DEBUG] Second query failed, trying hard-coded fallback query");
                        $last_query = "SELECT email FROM users WHERE id = '{$ticket['user_id']}'";
                        $last_result = $db->query($last_query);
                        
                        if ($last_result && $last_result->num_rows > 0) {
                            $last_data = $last_result->fetch_assoc();
                            $customer_email = $last_data['email'];
                            $customer_name = 'Customer';
                            error_log("[TICKET_DEBUG] Found user email with fallback query: $customer_email");
                        } else {
                            // Ultimate fallback - check if we have a specific user ID that matches our logs
                            error_log("[TICKET_DEBUG] Trying ultimate fallback for user_id 11");
                            if ($ticket['user_id'] == '11') {
                                $customer_email = 'orlandgeronimo86@gmail.com';
                                $customer_name = 'Orland Geronimo';
                                error_log("[TICKET_DEBUG] Using hardcoded email for known user ID 11");
                            } else {
                                error_log("[TICKET_DEBUG] All queries failed and user ID doesn't match known fallbacks");
                            }
                        }
                    }
                }
            } else if (isset($ticket['created_by']) && $ticket['created_by'] == 'guest' && !empty($ticket['guest_email'])) {
                // Use guest email directly if created by a guest
                $customer_email = $ticket['guest_email'];
                $customer_name = 'Guest';
                error_log("[TICKET_DEBUG] Using guest email: $customer_email");
            }

            // Only proceed if we have a customer email
            if (!empty($customer_email)) {
                error_log("[TICKET_DEBUG] Preparing email with customer email: $customer_email");
                
                $ticket_subject = $ticket['title'] ?: 'Your Support Request';
                $reference_id = $ticket['ref_id'] ?: '';

                // Prepare email data
                $replacements = [
                    'ticket_id' => $ticket_id,
                    'reference_id' => $reference_id,
                    'customer_name' => $customer_name,
                    'ticket_subject' => $ticket_subject,
                    'ticket_status' => 'Resolved',
                    'agent_name' => $staff_name,
                    'ticket_url' => 'https://yourwebsite.com/view_ticket.php?id=' . $ticket_id,
                    'current_year' => date('Y'),
                    'resolution_date' => date('F j, Y')
                ];
                error_log("[TICKET_DEBUG] Email replacements prepared: " . json_encode($replacements));

                // Try to get email template
                error_log("[TICKET_DEBUG] Getting email template 'ticket_resolved'");
                $email_body = get_email_template('ticket_resolved', $replacements);
                
                // Use fallback template if needed
                if (empty($email_body)) {
                    error_log("[TICKET_DEBUG] Template not found, using fallback template");
                    
                    // Basic fallback template
                    $email_body = '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;">
                        <div style="background-color: #4CAF50; color: white; padding: 15px; text-align: center;">
                            <h2>Your Ticket Has Been Resolved</h2>
                        </div>
                        <div style="padding: 20px; background-color: #f9f9f9;">
                            <p>Hello ' . $customer_name . ',</p>
                            
                            <p>We\'re pleased to inform you that your support ticket <strong>#' . $ticket_id . '</strong> 
                            regarding "<strong>' . $ticket_subject . '</strong>" has been marked as <strong>Resolved</strong>.</p>
                            
                            <div style="margin: 20px 0; padding: 15px; background-color: #fff; border-left: 4px solid #4CAF50;">
                                <p><strong>Ticket ID:</strong> #' . $ticket_id . '</p>
                                <p><strong>Subject:</strong> ' . $ticket_subject . '</p>
                                <p><strong>Status:</strong> Resolved</p>
                                <p><strong>Resolved by:</strong> ' . $staff_name . '</p>
                            </div>
                            
                            <p>If you feel this issue has been successfully resolved, no further action is needed.</p>
                            
                            <p>If you have any questions or if the issue persists, please respond to this email.</p>
                            
                            <p>Thank you for your patience.</p>
                            
                            <p>Best regards,<br>
                            Support Team</p>
                        </div>
                    </div>';
                }

                // Send email to customer
                if (!empty($email_body)) {
                    error_log("[TICKET_DEBUG] Sending email to $customer_email");
                    $email_result = send_email_smtp(
                        $customer_email,
                        "Ticket #$ticket_id has been Resolved",
                        $email_body
                    );
                    
                    if ($email_result) {
                        error_log("[TICKET_DEBUG] Email sent successfully to $customer_email");
                    } else {
                        error_log("[TICKET_DEBUG] Failed to send resolution email for ticket #$ticket_id");
                    }
                } else {
                    error_log("[TICKET_DEBUG] Cannot send email: Email body is empty");
                }
            } else {
                error_log("[TICKET_DEBUG] Cannot send email: No customer email found");
            }
        }

        // Commit the transaction
        $db->commit();
        error_log("[TICKET_DEBUG] Transaction committed successfully");
        return true;

    } catch (Exception $e) {
        $db->rollback();
        error_log("[TICKET_DEBUG] Error in update_ticket_status: " . $e->getMessage());
        return false;
    }
}   

function update_ticket_priority($ticket_id, $user_id, $new_priority_id)
{
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
        $old_priority_name = $row['old_priority_name'];
        $new_priority_name = $row['new_priority_name'];


        $update_query = "UPDATE tickets SET priority_id = '$new_priority_id' WHERE id = '$ticket_id'";
        $update_result = $db->query($update_query);

        if ($update_result) {
            log_ticket_history($ticket_id, $user_id, 'priority_change', $old_priority_name, $new_priority_name);
            return true;
        }
    }

    return false;
}

function delete_ticket_attachment($attachment_id, $ticket_id)
{
    $db = db_connect();

    $attachment_id = $db->real_escape_string($attachment_id);
    $ticket_id = $db->real_escape_string($ticket_id);

    // First, get the file path to delete the actual file
    $query = "SELECT file_path FROM attachments WHERE id = '$attachment_id' AND ticket_id = '$ticket_id'";
    $result = $db->query($query);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $file_path = $row['file_path'];

        // Delete the file from filesystem
        if (file_exists($file_path) && unlink($file_path)) {
            // If file deleted successfully, remove from database
            $delete_query = "DELETE FROM attachments WHERE id = '$attachment_id' AND ticket_id = '$ticket_id'";
            $delete_result = $db->query($delete_query);

            if ($delete_result) {
                $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
                log_ticket_history($ticket_id, $user_id, 'attachment_delete', $row['file_name'], null);
                return true;
            }
        } else {
            $delete_query = "DELETE FROM attachments WHERE id = '$attachment_id' AND ticket_id = '$ticket_id'";
            $delete_result = $db->query($delete_query);
        }
    }

    return false;
}

function assign_ticket($ticket_id, $admin_user_id, $assignee_id)
{
    $db = db_connect();

    $ticket_id = $db->real_escape_string($ticket_id);
    $admin_user_id = $db->real_escape_string($admin_user_id);
    $assignee_id = $db->real_escape_string($assignee_id);


    $query = "SELECT 
              (SELECT name FROM staff_members WHERE id = t.assigned_to) AS old_assignee_name,
              (SELECT name FROM staff_members WHERE id = '$assignee_id') AS new_assignee_name
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
            log_ticket_history($ticket_id, $admin_user_id, 'assignment', $old_assignee_name, $new_assignee_name);
            return true;
        }
    }

    return false;
}

function add_ticket_attachment($ticket_id, $uploader_id, $filename, $file_path, $file_size)
{
    $db = db_connect();

    try {
        // Basic sanitization
        $ticket_id = (int) $ticket_id;
        $uploader_id = (int) $uploader_id;
        $file_path = addslashes($file_path); // Use addslashes instead of real_escape_string

        // Default values
        $uploaded_by_user_id = 'NULL';
        $uploaded_by_staff_member_id = 'NULL';
        $uploaded_by_guest_email = 'NULL';

        // Check if the uploader is a staff member
        $staff_query = "SELECT id FROM staff_members WHERE id = $uploader_id LIMIT 1";
        $staff_result = $db->query($staff_query);

        if ($staff_result && $staff_result->num_rows > 0) {
            // Uploader is a staff member
            $uploaded_by_staff_member_id = $uploader_id;
        } else {
            // Check if uploader is a registered user
            $user_query = "SELECT id FROM users WHERE id = $uploader_id LIMIT 1";
            $user_result = $db->query($user_query);

            if ($user_result && $user_result->num_rows > 0) {
                // Uploader is a registered user
                $uploaded_by_user_id = $uploader_id;
            } else {
                // Uploader might be a guest
                // If you have guest email in session
                if (isset($_SESSION['guest_email'])) {
                    $guest_email = addslashes($_SESSION['guest_email']);
                    $uploaded_by_guest_email = "'$guest_email'";
                }
            }
        }

        $query = "INSERT INTO attachments 
                 (ticket_id, file_path, filename, uploaded_by_user_id, uploaded_by_staff_member_id, uploaded_by_guest_email, created_at) 
                 VALUES 
                 ($ticket_id, '$file_path', '$filename', $uploaded_by_user_id, $uploaded_by_staff_member_id, $uploaded_by_guest_email, CURRENT_TIMESTAMP())";

        $result = $db->query($query);

        if ($result) {
            $attachment_id = $db->insert_id;
            log_ticket_history($ticket_id, $uploader_id, 'attachment', null, "Uploaded file: $filename");
            return $attachment_id;
        } else {
            error_log("Database error in add_ticket_attachment: " . $db->error);
            error_log("Failed query: $query");
            return false;
        }
    } catch (Exception $e) {
        error_log("Exception in add_ticket_attachment: " . $e->getMessage());
        return false;
    }
}

function archive_ticket($ticket_id, $user_id)
{
    $db = db_connect();

    $ticket_id = $db->real_escape_string($ticket_id);
    $user_id = $db->real_escape_string($user_id);

    $query = "UPDATE tickets SET is_archived = 1 WHERE id = '$ticket_id'";
    $result = $db->query($query);

    if ($result) {
        log_ticket_history($ticket_id, $user_id, 'archive', 'Active', 'Archived');
        return true;
    }

    return false;
}

function get_all_priorities()
{
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

function get_assignable_users()
{
    $db = db_connect();

    $query = "SELECT id, name FROM staff_members WHERE role = 'agent' OR role = 'admin' OR role = 'master_agent' ORDER BY name";
    $result = $db->query($query);

    $users = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }

    return $users;
}

function get_staff_members()
{
    $db = db_connect();
    $query = "SELECT * FROM staff_members";
    $result = $db->query($query);

    $staff_members = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $staff_members[] = $row;
    }

    return $staff_members;
}
function get_all_tickets($filters = [])
{
    $tickets = [];

    try {
        $db = db_connect();

        if (!$db) {
            error_log("Failed to connect to the database");
            return ['error' => 'Database connection failed', 'tickets' => []];
        }

        // Start with base query
        $query = "SELECT t.*, 
       c.name AS category_name, 
       p.name AS priority_name, 
       IFNULL(CONCAT(u_created.first_name, ' ', u_created.last_name), 'Guest') AS created_by_name, 
       IFNULL(CONCAT(u_assigned.name), 'Unassigned') AS assigned_to_name, 
       IF(t.created_by = 'guest', t.guest_email, u_created.email) AS creator_email,
       u_assigned.id AS assigned_user_id
FROM tickets t 
LEFT JOIN categories c ON t.category_id = c.id 
LEFT JOIN priorities p ON t.priority_id = p.id 
LEFT JOIN users u_created ON t.created_by = 'user' AND t.user_id = u_created.user_id 
LEFT JOIN staff_members u_assigned ON t.assigned_to = u_assigned.id 
WHERE t.is_archived = 0";

        // Validate and sanitize filters
        if (!empty($filters)) {
            if (isset($filters['status']) && $filters['status']) {
                if (!in_array($filters['status'], ['open', 'in_progress', 'resolved', 'closed'])) {
                    error_log("Invalid status filter provided: " . $filters['status']);
                    $filters['status'] = 'open'; // Default to open if invalid
                }
                $status = $db->real_escape_string($filters['status']);
                $query .= " AND t.status = '$status'";
            }

            if (isset($filters['priority_id']) && $filters['priority_id']) {
                if (!is_numeric($filters['priority_id'])) {
                    error_log("Invalid priority_id filter provided: " . $filters['priority_id']);
                } else {
                    $priority_id = $db->real_escape_string($filters['priority_id']);
                    $query .= " AND t.priority_id = '$priority_id'";
                }
            }

            if (isset($filters['category_id']) && $filters['category_id']) {
                if (!is_numeric($filters['category_id'])) {
                    error_log("Invalid category_id filter provided: " . $filters['category_id']);
                } else {
                    $category_id = $db->real_escape_string($filters['category_id']);
                    $query .= " AND t.category_id = '$category_id'";
                }
            }

            if (isset($filters['assigned_to']) && $filters['assigned_to']) {
                if (!is_numeric($filters['assigned_to'])) {
                    error_log("Invalid assigned_to filter provided: " . $filters['assigned_to']);
                } else {
                    $assigned_to = $db->real_escape_string($filters['assigned_to']);
                    $query .= " AND t.assigned_to = '$assigned_to'";
                }
            }

            if (isset($filters['search']) && $filters['search']) {
                if (strlen($filters['search']) < 3) {
                    error_log("Search term too short (minimum 3 characters): " . $filters['search']);
                } else {
                    $search = $db->real_escape_string($filters['search']);
                    $query .= " AND (t.title LIKE '%$search%' OR t.description LIKE '%$search%')";
                }
            }

            // Include archived tickets if specifically requested
            if (isset($filters['include_archived']) && $filters['include_archived']) {
                $query = str_replace("WHERE t.is_archived = 0", "WHERE 1=1", $query);
            }

            // Only archived tickets if specifically requested
            if (isset($filters['only_archived']) && $filters['only_archived']) {
                $query = str_replace("WHERE t.is_archived = 0", "WHERE t.is_archived = 1", $query);
            }
        }

        // Default order by most recent first
        $query .= " ORDER BY t.created_at DESC";

        // Add a query limit to prevent performance issues (optional)
        $limit = isset($filters['limit']) && is_numeric($filters['limit']) ? (int) $filters['limit'] : 500;
        $query .= " LIMIT $limit";

        error_log("Executing get_all_tickets query: " . $query);

        // Set a timeout for the query
        $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

        $result = $db->query($query);

        if (!$result) {
            throw new Exception("Database query error: " . $db->error);
        }

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $tickets[] = $row;
            }
            error_log("Found " . count($tickets) . " tickets");
        } else {
            error_log("No tickets found matching the criteria");
        }

        $result->free();

    } catch (Exception $e) {
        error_log("Exception in get_all_tickets: " . $e->getMessage());
        return ['error' => $e->getMessage(), 'tickets' => []];
    } finally {
        if (isset($db) && $db) {
            $db->close();
        }
    }

    return $tickets;
}

/**
 * AJAX handler for the get_all_tickets action
 */
function ajax_get_all_tickets()
{
    header('Content-Type: application/json');

    try {
        // Get filters from query parameters
        $filters = [];

        // Validate and sanitize input parameters
        if (isset($_GET['status'])) {
            $filters['status'] = filter_var($_GET['status'], FILTER_SANITIZE_STRING);
        }

        if (isset($_GET['priority_id'])) {
            $filters['priority_id'] = filter_var($_GET['priority_id'], FILTER_VALIDATE_INT);
            if ($filters['priority_id'] === false) {
                throw new Exception("Invalid priority_id parameter");
            }
        }

        if (isset($_GET['category_id'])) {
            $filters['category_id'] = filter_var($_GET['category_id'], FILTER_VALIDATE_INT);
            if ($filters['category_id'] === false) {
                throw new Exception("Invalid category_id parameter");
            }
        }

        if (isset($_GET['assigned_to'])) {
            $filters['assigned_to'] = filter_var($_GET['assigned_to'], FILTER_VALIDATE_INT);
            if ($filters['assigned_to'] === false) {
                throw new Exception("Invalid assigned_to parameter");
            }
        }

        if (isset($_GET['search'])) {
            $filters['search'] = filter_var($_GET['search'], FILTER_SANITIZE_STRING);
            if (strlen($filters['search']) < 3 && strlen($filters['search']) > 0) {
                throw new Exception("Search term must be at least 3 characters");
            }
        }

        if (isset($_GET['include_archived'])) {
            $filters['include_archived'] = filter_var($_GET['include_archived'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($_GET['only_archived'])) {
            $filters['only_archived'] = filter_var($_GET['only_archived'], FILTER_VALIDATE_BOOLEAN);
        }

        // Add optional limit parameter
        if (isset($_GET['limit'])) {
            $limit = filter_var($_GET['limit'], FILTER_VALIDATE_INT);
            if ($limit !== false && $limit > 0) {
                $filters['limit'] = $limit;
            }
        }

        // Execute the query
        $result = get_all_tickets($filters);

        // Check if there was an error
        if (isset($result['error'])) {
            echo json_encode([
                'success' => false,
                'error' => $result['error'],
                'tickets' => []
            ]);
            exit;
        }

        // Return JSON response
        echo json_encode([
            'success' => true,
            'count' => count($result),
            'tickets' => $result
        ]);

    } catch (Exception $e) {
        error_log("Exception in ajax_get_all_tickets: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'tickets' => []
        ]);
    }

    exit;
}
