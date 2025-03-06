-- Create database
CREATE DATABASE IF NOT EXISTS ticketing_system;
USE ticketing_system;

-- Staff Members Table (admins, master agents, agents)
CREATE TABLE IF NOT EXISTS staff_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    role ENUM('admin', 'master_agent', 'agent') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Users Table (regular users)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categories Table (for dynamic ticket categories)
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Priorities Table (for dynamic ticket priorities)
CREATE TABLE IF NOT EXISTS priorities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    level INT NOT NULL UNIQUE COMMENT 'Lower number means higher priority',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tickets Table (supports both registered users and guests)
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open', 'pending', 'closed') NOT NULL DEFAULT 'open',
    created_by ENUM('user', 'guest') NOT NULL COMMENT 'Type of creator (user or guest)',
    user_id INT COMMENT 'ID of the user who created the ticket (if created_by = "user")',
    guest_email VARCHAR(255) COMMENT 'Email of the guest who created the ticket (if created_by = "guest")',
    category_id INT NOT NULL,
    priority_id INT NOT NULL,
    assigned_to INT COMMENT 'Assigned to a staff member (admin/master agent/agent)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (priority_id) REFERENCES priorities(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES staff_members(id) ON DELETE SET NULL,
    CHECK (
        (created_by = 'user' AND user_id IS NOT NULL AND guest_email IS NULL) OR
        (created_by = 'guest' AND guest_email IS NOT NULL AND user_id IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ticket Messages Table
CREATE TABLE IF NOT EXISTS ticket_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT COMMENT 'Regular user who sent the message',
    staff_member_id INT COMMENT 'Staff member who sent the message',
    guest_email VARCHAR(255) COMMENT 'Guest who sent the message',
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (staff_member_id) REFERENCES staff_members(id) ON DELETE SET NULL,
    CHECK (user_id IS NOT NULL OR staff_member_id IS NOT NULL OR guest_email IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attachments Table
CREATE TABLE IF NOT EXISTS attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    message_id INT,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by_user_id INT COMMENT 'Regular user who uploaded the file',
    uploaded_by_staff_member_id INT COMMENT 'Staff member who uploaded the file',
    uploaded_by_guest_email VARCHAR(255) COMMENT 'Guest who uploaded the file',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (message_id) REFERENCES ticket_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by_staff_member_id) REFERENCES staff_members(id) ON DELETE SET NULL,
    CHECK (
        uploaded_by_user_id IS NOT NULL OR
        uploaded_by_staff_member_id IS NOT NULL OR
        uploaded_by_guest_email IS NOT NULL
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample Data
-- Staff Members
INSERT INTO staff_members (username, password, email, role) VALUES
('admin1', 'hashed_password', 'admin1@example.com', 'admin'),
('master_agent1', 'hashed_password', 'master1@example.com', 'master_agent'),
('agent1', 'hashed_password', 'agent1@example.com', 'agent');

-- Users
INSERT INTO users (username, password, email) VALUES
('user1', 'hashed_password', 'user1@example.com'),
('user2', 'hashed_password', 'user2@example.com');

-- Categories
INSERT INTO categories (name, description) VALUES
('Technical Support', 'Hardware and software issues'),
('Billing', 'Payment and subscription issues'),
('General Inquiry', 'General questions and information');

-- Priorities
INSERT INTO priorities (name, level) VALUES
('Low', 3),
('Medium', 2),
('High', 1);

-- Tickets
INSERT INTO tickets (title, description, status, created_by, user_id, category_id, priority_id, assigned_to) VALUES
('Login Problem', 'Cannot access my account', 'open', 'user', 1, 1, 3, 3),
('Payment Issue', 'Double charge on my credit card', 'pending', 'user', 2, 2, 2, 2);

INSERT INTO tickets (title, description, status, created_by, guest_email, category_id, priority_id, assigned_to) VALUES
('Website Bug', 'Form submission not working', 'open', 'guest', 'guest@example.com', 1, 1, 3);

-- Ticket Messages
INSERT INTO ticket_messages (ticket_id, user_id, message) VALUES
(1, 1, 'I tried resetting my password but still can''t login'),
(1, NULL, 3, 'We''re looking into your login issue');

INSERT INTO ticket_messages (ticket_id, guest_email, message) VALUES
(3, 'guest@example.com', 'This happens on Chrome browser latest version');

-- Attachments
INSERT INTO attachments (ticket_id, message_id, file_path, uploaded_by_user_id) VALUES
(1, 1, 'uploads/screenshot1.png', 1);

INSERT INTO attachments (ticket_id, file_path, uploaded_by_guest_email) VALUES
(3, 'uploads/error_log.txt', 'guest@example.com');