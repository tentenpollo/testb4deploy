-- Create the database
CREATE DATABASE IF NOT EXISTS ticketing_system;
USE ticketing_system;

-- Users table to store all types of users (staff and customers)
CREATE TABLE `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  `avatar_path` VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Guest users table
CREATE TABLE `guest_users` (
  `guest_id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(100) NOT NULL,
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `first_name` VARCHAR(50) NOT NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL,
  `is_active` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Staff roles table
CREATE TABLE `staff_roles` (
  `role_id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_name` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_active` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default staff roles
INSERT INTO `staff_roles` (`role_name`, `description`) VALUES
('Admin', 'Full system access with all privileges'),
('Master Agent', 'Can manage tickets, agents, and some settings'),
('Agent', 'Can handle tickets assigned to them'),
('User', 'Regular user who can create and track tickets');

-- Staff table linking users to roles
CREATE TABLE `staff` (
  `staff_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `role_id` INT NOT NULL,
  `department_id` INT DEFAULT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `staff_roles`(`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Departments table
CREATE TABLE `departments` (
  `department_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_active` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories table
CREATE TABLE `categories` (
  `category_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `department_id` INT DEFAULT NULL,
  `parent_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_active` BOOLEAN DEFAULT TRUE,
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`department_id`),
  FOREIGN KEY (`parent_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Priorities table
CREATE TABLE `priorities` (
  `priority_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `description` TEXT,
  `color` VARCHAR(7) DEFAULT '#000000',
  `sla_hours` INT DEFAULT 24,
  `is_default` BOOLEAN DEFAULT FALSE,
  `is_active` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default priorities
INSERT INTO `priorities` (`name`, `description`, `color`, `sla_hours`, `is_default`) VALUES
('Low', 'Non-urgent issues that can wait', '#28a745', 72, FALSE),
('Medium', 'Standard issues requiring attention', '#ffc107', 48, TRUE),
('High', 'Urgent issues needing prompt resolution', '#fd7e14', 24, FALSE),
('Critical', 'Emergency issues requiring immediate attention', '#dc3545', 4, FALSE);

-- Statuses table
CREATE TABLE `statuses` (
  `status_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `description` TEXT,
  `color` VARCHAR(7) DEFAULT '#000000',
  `is_default` BOOLEAN DEFAULT FALSE,
  `is_closed` BOOLEAN DEFAULT FALSE,
  `is_active` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default statuses
INSERT INTO `statuses` (`name`, `description`, `color`, `is_default`, `is_closed`) VALUES
('New', 'Ticket has been created but not assigned', '#17a2b8', TRUE, FALSE),
('Open', 'Ticket assigned but work not yet started', '#007bff', FALSE, FALSE),
('In Progress', 'Work has begun on the ticket', '#6f42c1', FALSE, FALSE),
('On Hold', 'Ticket is waiting for customer response', '#fd7e14', FALSE, FALSE),
('Resolved', 'Issue has been resolved, awaiting confirmation', '#28a745', FALSE, FALSE),
('Closed', 'Ticket has been completed and closed', '#6c757d', FALSE, TRUE),
('Cancelled', 'Ticket has been cancelled', '#dc3545', FALSE, TRUE);

-- Tickets table
CREATE TABLE `tickets` (
  `ticket_id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_number` VARCHAR(20) NOT NULL UNIQUE,
  `user_id` INT DEFAULT NULL,
  `guest_id` INT DEFAULT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `category_id` INT DEFAULT NULL,
  `subcategory_id` INT DEFAULT NULL,
  `priority_id` INT DEFAULT NULL,
  `status_id` INT NOT NULL,
  `department_id` INT DEFAULT NULL,
  `assigned_to` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `due_date` TIMESTAMP NULL DEFAULT NULL,
  `resolution` TEXT DEFAULT NULL,
  `is_deleted` BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
  FOREIGN KEY (`guest_id`) REFERENCES `guest_users`(`guest_id`) ON DELETE SET NULL,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL,
  FOREIGN KEY (`subcategory_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL,
  FOREIGN KEY (`priority_id`) REFERENCES `priorities`(`priority_id`) ON DELETE SET NULL,
  FOREIGN KEY (`status_id`) REFERENCES `statuses`(`status_id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments`(`department_id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `staff`(`staff_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trigger to generate ticket number
DELIMITER $$
CREATE TRIGGER before_insert_ticket
BEFORE INSERT ON `tickets`
FOR EACH ROW
BEGIN
  SET NEW.ticket_number = CONCAT('TKT-', LPAD(YEAR(CURRENT_TIMESTAMP), 2, '0'), LPAD(MONTH(CURRENT_TIMESTAMP), 2, '0'), LPAD((SELECT IFNULL(MAX(SUBSTR(ticket_number, 8)), 0) + 1 FROM tickets), 6, '0'));
END$$
DELIMITER ;

-- Ticket replies/comments
CREATE TABLE `ticket_replies` (
  `reply_id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `user_id` INT DEFAULT NULL,
  `guest_id` INT DEFAULT NULL,
  `staff_id` INT DEFAULT NULL,
  `message` TEXT NOT NULL,
  `is_internal` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
  FOREIGN KEY (`guest_id`) REFERENCES `guest_users`(`guest_id`) ON DELETE SET NULL,
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`staff_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attachments table for tickets and replies
CREATE TABLE `attachments` (
  `attachment_id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT DEFAULT NULL,
  `reply_id` INT DEFAULT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `filepath` VARCHAR(255) NOT NULL,
  `filesize` INT NOT NULL,
  `filetype` VARCHAR(100) NOT NULL,
  `uploaded_by_user_id` INT DEFAULT NULL,
  `uploaded_by_guest_id` INT DEFAULT NULL,
  `uploaded_by_staff_id` INT DEFAULT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE,
  FOREIGN KEY (`reply_id`) REFERENCES `ticket_replies`(`reply_id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
  FOREIGN KEY (`uploaded_by_guest_id`) REFERENCES `guest_users`(`guest_id`) ON DELETE SET NULL,
  FOREIGN KEY (`uploaded_by_staff_id`) REFERENCES `staff`(`staff_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ticket history for auditing
CREATE TABLE `ticket_history` (
  `history_id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `user_id` INT DEFAULT NULL,
  `guest_id` INT DEFAULT NULL,
  `staff_id` INT DEFAULT NULL,
  `action` VARCHAR(50) NOT NULL,
  `field_name` VARCHAR(50) DEFAULT NULL,
  `old_value` TEXT DEFAULT NULL,
  `new_value` TEXT DEFAULT NULL,
  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
  FOREIGN KEY (`guest_id`) REFERENCES `guest_users`(`guest_id`) ON DELETE SET NULL,
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`staff_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Knowledge Base Categories
CREATE TABLE `kb_categories` (
  `kb_category_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `parent_id` INT DEFAULT NULL,
  `sort_order` INT DEFAULT 0,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`parent_id`) REFERENCES `kb_categories`(`kb_category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Knowledge Base Articles
CREATE TABLE `kb_articles` (
  `article_id` INT AUTO_INCREMENT PRIMARY KEY,
  `kb_category_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `keywords` VARCHAR(255) DEFAULT NULL,
  `author_id` INT DEFAULT NULL,
  `views` INT DEFAULT 0,
  `helpful_yes` INT DEFAULT 0,
  `helpful_no` INT DEFAULT 0,
  `is_published` BOOLEAN DEFAULT FALSE,
  `is_featured` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`kb_category_id`) REFERENCES `kb_categories`(`kb_category_id`),
  FOREIGN KEY (`author_id`) REFERENCES `staff`(`staff_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- KB Article attachments
CREATE TABLE `kb_attachments` (
  `kb_attachment_id` INT AUTO_INCREMENT PRIMARY KEY,
  `article_id` INT NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `filepath` VARCHAR(255) NOT NULL,
  `filesize` INT NOT NULL,
  `filetype` VARCHAR(100) NOT NULL,
  `uploaded_by` INT DEFAULT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`article_id`) REFERENCES `kb_articles`(`article_id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `staff`(`staff_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Canned responses for quick replies
CREATE TABLE `canned_responses` (
  `response_id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(100) NOT NULL,
  `content` TEXT NOT NULL,
  `created_by` INT NOT NULL,
  `is_public` BOOLEAN DEFAULT FALSE,
  `category_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `staff`(`staff_id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SLA policies
CREATE TABLE `sla_policies` (
  `sla_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `response_time_hours` INT NOT NULL,
  `resolution_time_hours` INT NOT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Link SLA to priorities
CREATE TABLE `sla_priority_mapping` (
  `mapping_id` INT AUTO_INCREMENT PRIMARY KEY,
  `sla_id` INT NOT NULL,
  `priority_id` INT NOT NULL,
  FOREIGN KEY (`sla_id`) REFERENCES `sla_policies`(`sla_id`) ON DELETE CASCADE,
  FOREIGN KEY (`priority_id`) REFERENCES `priorities`(`priority_id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_sla_priority` (`sla_id`, `priority_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tags for tickets
CREATE TABLE `tags` (
  `tag_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `color` VARCHAR(7) DEFAULT '#cccccc',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ticket-Tag mapping
CREATE TABLE `ticket_tags` (
  `ticket_id` INT NOT NULL,
  `tag_id` INT NOT NULL,
  PRIMARY KEY (`ticket_id`, `tag_id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE,
  FOREIGN KEY (`tag_id`) REFERENCES `tags`(`tag_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notifications
CREATE TABLE `notifications` (
  `notification_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `staff_id` INT DEFAULT NULL,
  `ticket_id` INT DEFAULT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(255) DEFAULT NULL,
  `is_read` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`staff_id`) ON DELETE CASCADE,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email templates
CREATE TABLE `email_templates` (
  `template_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `variables` TEXT COMMENT 'JSON array of available variables',
  `type` VARCHAR(50) NOT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- System settings
CREATE TABLE `settings` (
  `setting_id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `setting_description` TEXT,
  `is_public` BOOLEAN DEFAULT FALSE,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_description`, `is_public`) VALUES
('site_name', 'Help Desk System', 'Name of the help desk system', TRUE),
('company_name', 'Your Company', 'Your company name', TRUE),
('admin_email', 'admin@example.com', 'Admin email address', FALSE),
('support_email', 'support@example.com', 'Support email address', TRUE),
('ticket_prefix', 'TKT-', 'Prefix for ticket numbers', FALSE),
('allow_attachments', 'true', 'Allow file attachments', TRUE),
('max_attachment_size', '5242880', 'Maximum attachment size in bytes (5MB)', TRUE),
('allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,zip', 'Allowed file types for attachments', TRUE),
('enable_kb', 'true', 'Enable knowledge base', TRUE),
('enable_guest_tickets', 'true', 'Allow guests to create tickets', TRUE),
('default_ticket_status', '1', 'Default status ID for new tickets', FALSE),
('default_ticket_priority', '2', 'Default priority ID for new tickets', FALSE),
('guest_token_expiry_days', '7', 'Number of days before guest tokens expire', FALSE);

-- Staff permissions
CREATE TABLE `permissions` (
  `permission_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT,
  `category` VARCHAR(50) DEFAULT 'general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default permissions
INSERT INTO `permissions` (`name`, `description`, `category`) VALUES
('manage_tickets', 'Create, view, edit and delete tickets', 'tickets'),
('view_all_tickets', 'View all tickets in the system', 'tickets'),
('assign_tickets', 'Assign tickets to staff members', 'tickets'),
('close_tickets', 'Close tickets', 'tickets'),
('delete_tickets', 'Delete tickets', 'tickets'),
('manage_users', 'Create, view, edit and delete users', 'users'),
('manage_staff', 'Create, view, edit and delete staff members', 'users'),
('manage_roles', 'Create, view, edit and delete staff roles', 'admin'),
('manage_departments', 'Create, view, edit and delete departments', 'admin'),
('manage_categories', 'Create, view, edit and delete ticket categories', 'admin'),
('manage_kb', 'Create, view, edit and delete knowledge base articles', 'kb'),
('manage_settings', 'Edit system settings', 'admin'),
('view_reports', 'View reports', 'reports'),
('manage_canned_responses', 'Create, view, edit and delete canned responses', 'tools');

-- Role-Permission mapping
CREATE TABLE `role_permissions` (
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `staff_roles`(`role_id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`permission_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default role permissions
-- Admin role (all permissions)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, permission_id FROM `permissions`;

-- Master Agent permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, permission_id FROM `permissions` 
WHERE `name` IN (
  'manage_tickets', 'view_all_tickets', 'assign_tickets', 'close_tickets',
  'manage_users', 'manage_canned_responses', 'view_reports', 'manage_kb'
);

-- Agent permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, permission_id FROM `permissions` 
WHERE `name` IN (
  'manage_tickets', 'close_tickets', 'manage_canned_responses'
);

-- Shift/work hours
CREATE TABLE `work_schedules` (
  `schedule_id` INT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `day_of_week` TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday, etc.',
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `is_working_day` BOOLEAN DEFAULT TRUE,
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`staff_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Time off/vacation tracking
CREATE TABLE `time_off` (
  `time_off_id` INT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `approved_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`staff_id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `staff`(`staff_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ticket feedback/satisfaction
CREATE TABLE `ticket_feedback` (
  `feedback_id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `rating` TINYINT NOT NULL COMMENT 'Scale 1-5',
  `comments` TEXT,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Login attempts/security
CREATE TABLE `login_attempts` (
  `attempt_id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(100) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_successful` BOOLEAN DEFAULT FALSE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API keys for external integrations
CREATE TABLE `api_keys` (
  `key_id` INT AUTO_INCREMENT PRIMARY KEY,
  `api_key` VARCHAR(64) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `created_by` INT NOT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `last_used` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`created_by`) REFERENCES `staff`(`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Save custom fields definitions
CREATE TABLE `custom_fields` (
  `field_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `type` ENUM('text', 'textarea', 'select', 'multiselect', 'checkbox', 'radio', 'date') NOT NULL,
  `options` TEXT COMMENT 'JSON array for select/multiselect/radio/checkbox options',
  `required` BOOLEAN DEFAULT FALSE,
  `visible_to_customers` BOOLEAN DEFAULT TRUE,
  `applies_to` ENUM('ticket', 'user', 'organization', 'kb_article') NOT NULL,
  `sort_order` INT DEFAULT 0,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Store custom field values
CREATE TABLE `custom_field_values` (
  `value_id` INT AUTO_INCREMENT PRIMARY KEY,
  `field_id` INT NOT NULL,
  `entity_id` INT NOT NULL COMMENT 'ID of the ticket, user, etc.',
  `value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`field_id`) REFERENCES `custom_fields`(`field_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Organizations/companies
CREATE TABLE `organizations` (
  `org_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `address` TEXT,
  `phone` VARCHAR(20) DEFAULT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `industry` VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Link users to organizations
CREATE TABLE `user_organizations` (
  `user_id` INT NOT NULL,
  `org_id` INT NOT NULL,
  `is_admin` BOOLEAN DEFAULT FALSE COMMENT 'Can manage org users',
  `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `org_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`org_id`) REFERENCES `organizations`(`org_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Advanced search saved filters
CREATE TABLE `saved_searches` (
  `search_id` INT AUTO_INCREMENT PRIMARY KEY,
  `staff_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `criteria` TEXT NOT NULL COMMENT 'JSON search criteria',
  `is_public` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`staff_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Automated rules
CREATE TABLE `automation_rules` (
  `rule_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `conditions` TEXT NOT NULL COMMENT 'JSON conditions',
  `actions` TEXT NOT NULL COMMENT 'JSON actions',
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `staff`(`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ticket watchers/followers
CREATE TABLE `ticket_watchers` (
  `ticket_id` INT NOT NULL,
  `staff_id` INT NOT NULL,
  `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ticket_id`, `staff_id`),
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE,
  FOREIGN KEY (`staff_id`) REFERENCES `staff`(`staff_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ticket relations (parent/child, related)
CREATE TABLE `ticket_relations` (
  `relation_id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `related_ticket_id` INT NOT NULL,
  `relation_type` ENUM('parent', 'child', 'related', 'duplicate') NOT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE,
  FOREIGN KEY (`related_ticket_id`) REFERENCES `tickets`(`ticket_id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `staff`(`staff_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create indexes for performance
CREATE INDEX idx_tickets_status ON tickets(status_id);
CREATE INDEX idx_tickets_priority ON tickets(priority_id);
CREATE INDEX idx_tickets_assigned ON tickets(assigned_to);
CREATE INDEX idx_tickets_created ON tickets(created_at);
CREATE INDEX idx_tickets_updated ON tickets(updated_at);
CREATE INDEX idx_replies_ticket ON ticket_replies(ticket_id);
CREATE INDEX idx_kb_articles_category ON kb_articles(kb_category_id);
CREATE INDEX idx_kb_articles_published ON kb_articles(is_published);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);
CREATE INDEX idx_staff_role ON staff(role_id);