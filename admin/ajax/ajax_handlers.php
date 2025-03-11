<?php
require_once '../../includes/config.php';
require_once '../ticket_functions.php';


session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];


if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    switch ($action) {
        case 'get_ticket_details':
            if (isset($_GET['ticket_id'])) {
                $ticket_id = $_GET['ticket_id'];
                $ticket = get_ticket_details($ticket_id);
                
                if ($ticket) {
                    echo json_encode(['success' => true, 'ticket' => $ticket]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Ticket not found']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Ticket ID required']);
            }
            break;
            
        case 'get_ticket_history':
            if (isset($_GET['ticket_id'])) {
                $ticket_id = $_GET['ticket_id'];
                $history = get_ticket_history($ticket_id);
                
                echo json_encode(['success' => true, 'history' => $history]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Ticket ID required']);
            }
            break;
            
        case 'get_ticket_attachments':
            if (isset($_GET['ticket_id'])) {
                $ticket_id = $_GET['ticket_id'];
                $attachments = get_ticket_attachments($ticket_id);
                
                echo json_encode(['success' => true, 'attachments' => $attachments]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Ticket ID required']);
            }
            break;
            
        case 'update_ticket_status':
            if (isset($_POST['ticket_id']) && isset($_POST['status'])) {
                $ticket_id = $_POST['ticket_id'];
                $new_status = $_POST['status'];
                
                $result = update_ticket_status($ticket_id, $user_id, $new_status);
                
                if ($result) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to update status']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
            }
            break;
            
        case 'update_ticket_priority':
            if (isset($_POST['ticket_id']) && isset($_POST['priority_id'])) {
                $ticket_id = $_POST['ticket_id'];
                $priority_id = $_POST['priority_id'];
                
                $result = update_ticket_priority($ticket_id, $user_id, $priority_id);
                
                if ($result) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to update priority']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
            }
            break;
            
        case 'assign_ticket':
            if (isset($_POST['ticket_id']) && isset($_POST['assignee_id'])) {
                $ticket_id = $_POST['ticket_id'];
                $assignee_id = $_POST['assignee_id'];
                
                $result = assign_ticket($ticket_id, $user_id, $assignee_id);
                
                if ($result) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to assign ticket']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
            }
            break;
            
        case 'add_comment':
            if (isset($_POST['ticket_id']) && isset($_POST['content'])) {
                $ticket_id = $_POST['ticket_id'];
                $content = $_POST['content'];
                $is_private = isset($_POST['is_private']) ? (bool)$_POST['is_private'] : false;
                
                $result = add_ticket_comment($ticket_id, $user_id, $content, $is_private);
                
                if ($result) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to add comment']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
            }
            break;
            
        case 'archive_ticket':
            if (isset($_POST['ticket_id'])) {
                $ticket_id = $_POST['ticket_id'];
                
                $result = archive_ticket($ticket_id, $user_id);
                
                if ($result) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to archive ticket']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing ticket ID']);
            }
            break;
            
        case 'get_priorities':
            $priorities = get_all_priorities();
            echo json_encode(['success' => true, 'priorities' => $priorities]);
            break;
            
        case 'get_assignable_users':
            $users = get_assignable_users();
            echo json_encode(['success' => true, 'users' => $users]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
    exit;
}


if (isset($_FILES['attachment']) && isset($_POST['ticket_id'])) {
    $ticket_id = $_POST['ticket_id'];
    $file = $_FILES['attachment'];
    
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $file['tmp_name'];
        $name = basename($file['name']);
        $size = $file['size'];
        
        
        $upload_dir = 'uploads/tickets/' . $ticket_id;
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        
        $file_path = $upload_dir . '/' . time() . '_' . $name;
        
        
        if (move_uploaded_file($tmp_name, $file_path)) {
            
            $attachment_id = add_ticket_attachment($ticket_id, $user_id, $name, $file_path, $size);
            
            if ($attachment_id) {
                echo json_encode(['success' => true, 'attachment_id' => $attachment_id]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save attachment to database']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'File upload error: ' . $file['error']]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'No action specified']);
?>