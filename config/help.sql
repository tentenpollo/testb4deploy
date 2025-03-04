-- Users table to store registered users
CREATE TABLE users (
    user_id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Guest users table to store temporary user information
CREATE TABLE guest_users (
    guest_id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL
);

-- Departments/Categories to organize tickets
CREATE TABLE departments (
    department_id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE
);

-- Categories for ticket classification
CREATE TABLE categories (
    category_id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    parent_category_id INTEGER REFERENCES categories(category_id),
    department_id INTEGER REFERENCES departments(department_id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Teams table to organize staff members
CREATE TABLE teams (
    team_id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    department_id INTEGER REFERENCES departments(department_id),
    lead_staff_id INTEGER,  -- Will be set with a deferred constraint
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Team-category assignment (which teams handle which categories)
CREATE TABLE team_categories (
    team_id INTEGER REFERENCES teams(team_id) NOT NULL,
    category_id INTEGER REFERENCES categories(category_id) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (team_id, category_id)
);

-- Staff members who can handle tickets
CREATE TABLE staff (
    staff_id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(user_id),
    department_id INTEGER REFERENCES departments(department_id),
    team_id INTEGER REFERENCES teams(team_id),
    role VARCHAR(50) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- Staff category expertise
CREATE TABLE staff_category_expertise (
    staff_id INTEGER REFERENCES staff(staff_id) NOT NULL,
    category_id INTEGER REFERENCES categories(category_id) NOT NULL,
    expertise_level INTEGER NOT NULL CHECK (expertise_level BETWEEN 1 AND 5),
    PRIMARY KEY (staff_id, category_id)
);

-- Add the deferred foreign key constraint for team lead
ALTER TABLE teams ADD CONSTRAINT fk_team_lead 
    FOREIGN KEY (lead_staff_id) REFERENCES staff(staff_id) DEFERRABLE INITIALLY DEFERRED;

-- Ticket priorities
CREATE TABLE priorities (
    priority_id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    sla_hours INTEGER,
    color VARCHAR(7)  -- Hex color code
);

-- Ticket statuses
CREATE TABLE statuses (
    status_id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    is_final BOOLEAN DEFAULT FALSE
);

-- Main tickets table
CREATE TABLE tickets (
    ticket_id SERIAL PRIMARY KEY,
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    department_id INTEGER REFERENCES departments(department_id),
    category_id INTEGER REFERENCES categories(category_id),
    team_id INTEGER REFERENCES teams(team_id),
    priority_id INTEGER REFERENCES priorities(priority_id),
    status_id INTEGER REFERENCES statuses(status_id),
    registered_user_id INTEGER REFERENCES users(user_id),
    guest_user_id INTEGER REFERENCES guest_users(guest_id),
    assigned_to INTEGER REFERENCES staff(staff_id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP,
    -- Check constraint to ensure either registered_user_id or guest_user_id is provided
    CONSTRAINT user_check CHECK (
        (registered_user_id IS NOT NULL AND guest_user_id IS NULL) OR
        (registered_user_id IS NULL AND guest_user_id IS NOT NULL)
    )
);

-- Secondary categories for tickets (a ticket can belong to multiple categories)
CREATE TABLE ticket_secondary_categories (
    ticket_id INTEGER REFERENCES tickets(ticket_id) NOT NULL,
    category_id INTEGER REFERENCES categories(category_id) NOT NULL,
    PRIMARY KEY (ticket_id, category_id)
);

-- Team permissions for ticket visibility and management
CREATE TABLE team_permissions (
    team_id INTEGER REFERENCES teams(team_id),
    department_id INTEGER REFERENCES departments(department_id),
    can_view BOOLEAN DEFAULT TRUE,
    can_update BOOLEAN DEFAULT FALSE,
    can_assign BOOLEAN DEFAULT FALSE,
    can_resolve BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (team_id, department_id)
);

-- Ticket comments/responses
CREATE TABLE ticket_comments (
    comment_id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(ticket_id) NOT NULL,
    content TEXT NOT NULL,
    registered_user_id INTEGER REFERENCES users(user_id),
    guest_user_id INTEGER REFERENCES guest_users(guest_id),
    staff_id INTEGER REFERENCES staff(staff_id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_internal BOOLEAN DEFAULT FALSE,  -- For internal notes not visible to customers
    -- Ensure at least one user type is provided
    CONSTRAINT comment_user_check CHECK (
        registered_user_id IS NOT NULL OR
        guest_user_id IS NOT NULL OR
        staff_id IS NOT NULL
    )
);

-- Attachments for tickets and comments
CREATE TABLE attachments (
    attachment_id SERIAL PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(512) NOT NULL,
    file_size INTEGER NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ticket_id INTEGER REFERENCES tickets(ticket_id),
    comment_id INTEGER REFERENCES ticket_comments(comment_id),
    -- Ensure it's attached to either a ticket or a comment
    CONSTRAINT attachment_reference_check CHECK (
        (ticket_id IS NOT NULL AND comment_id IS NULL) OR
        (ticket_id IS NULL AND comment_id IS NOT NULL)
    )
);

-- Ticket history for audit trail
CREATE TABLE ticket_history (
    history_id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(ticket_id) NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    changed_by_user_id INTEGER REFERENCES users(user_id),
    changed_by_staff_id INTEGER REFERENCES staff(staff_id),
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- SLA (Service Level Agreement) tracking
CREATE TABLE sla_violations (
    violation_id SERIAL PRIMARY KEY,
    ticket_id INTEGER REFERENCES tickets(ticket_id) NOT NULL,
    sla_type VARCHAR(50) NOT NULL,  -- 'first_response', 'resolution', etc.
    deadline TIMESTAMP NOT NULL,
    violated_at TIMESTAMP,
    is_violated BOOLEAN DEFAULT FALSE
);

-- Tags for categorizing tickets
CREATE TABLE tags (
    tag_id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

-- Junction table for ticket-tag relationship
CREATE TABLE ticket_tags (
    ticket_id INTEGER REFERENCES tickets(ticket_id) NOT NULL,
    tag_id INTEGER REFERENCES tags(tag_id) NOT NULL,
    PRIMARY KEY (ticket_id, tag_id)
);

-- Category-specific SLA definitions
CREATE TABLE category_slas (
    category_id INTEGER REFERENCES categories(category_id) NOT NULL,
    priority_id INTEGER REFERENCES priorities(priority_id) NOT NULL,
    response_time_hours INTEGER NOT NULL,
    resolution_time_hours INTEGER NOT NULL,
    PRIMARY KEY (category_id, priority_id)
);

-- Team shift schedules
CREATE TABLE team_schedules (
    schedule_id SERIAL PRIMARY KEY,
    team_id INTEGER REFERENCES teams(team_id) NOT NULL,
    day_of_week INTEGER NOT NULL CHECK (day_of_week BETWEEN 0 AND 6),
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- Common solutions/templates for categories
CREATE TABLE category_solutions (
    solution_id SERIAL PRIMARY KEY,
    category_id INTEGER REFERENCES categories(category_id) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_by INTEGER REFERENCES staff(staff_id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Create a roles table
CREATE TABLE roles (
    role_id SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE
);

-- Insert the required roles
INSERT INTO roles (name, description) VALUES
    ('admin', 'System administrator with full access'),
    ('master_agent', 'Senior agent with supervisory capabilities'),
    ('agent', 'Regular support agent'),
    ('user', 'Regular system user');

-- Add role assignments for staff members
CREATE TABLE staff_roles (
    staff_id INTEGER REFERENCES staff(staff_id) NOT NULL,
    role_id INTEGER REFERENCES roles(role_id) NOT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    assigned_by INTEGER REFERENCES staff(staff_id),
    PRIMARY KEY (staff_id, role_id)
);

-- For regular users, add a role_id field to the users table
ALTER TABLE users ADD COLUMN role_id INTEGER REFERENCES roles(role_id) DEFAULT 4; -- Default to 'user' role

-- Create indexes for better performance
CREATE INDEX idx_tickets_registered_user ON tickets(registered_user_id);
CREATE INDEX idx_tickets_guest_user ON tickets(guest_user_id);
CREATE INDEX idx_tickets_assigned_to ON tickets(assigned_to);
CREATE INDEX idx_tickets_status ON tickets(status_id);
CREATE INDEX idx_tickets_department ON tickets(department_id);
CREATE INDEX idx_tickets_category ON tickets(category_id);
CREATE INDEX idx_tickets_team ON tickets(team_id);
CREATE INDEX idx_staff_team ON staff(team_id);
CREATE INDEX idx_categories_department ON categories(department_id);
CREATE INDEX idx_categories_parent ON categories(parent_category_id);
CREATE INDEX idx_ticket_comments_ticket ON ticket_comments(ticket_id);
CREATE INDEX idx_ticket_history_ticket ON ticket_history(ticket_id);