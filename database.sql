-- CIT Food Trades Budgeting and Inventory System
-- REVISED DATABASE v2.0

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(80) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','budget_manager','inventory_manager','user') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
);

-- Budget periods / allocations (submitted by Budget Manager, approved by Admin)
CREATE TABLE IF NOT EXISTS budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_label VARCHAR(100) NOT NULL,
    period_type ENUM('daily','monthly','semestral','yearly') DEFAULT 'semestral',
    allocated_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    approval_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at DATETIME NULL,
    rejection_reason TEXT NULL,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Inventory items (master records managed by Inventory Manager)
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(200) NOT NULL,
    category VARCHAR(100),
    unit VARCHAR(50) DEFAULT 'pcs',
    current_stock DECIMAL(10,3) DEFAULT 0,
    minimum_stock DECIMAL(10,3) DEFAULT 5,
    unit_cost DECIMAL(10,2) DEFAULT 0,
    expiry_date DATE NULL,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Purchases
CREATE TABLE IF NOT EXISTS purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(200) NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit VARCHAR(50) DEFAULT 'pcs',
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(12,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    supplier VARCHAR(200),
    purchase_date DATE NOT NULL,
    receipt_path VARCHAR(500),
    status ENUM('pending','approved','rejected','correction_needed') DEFAULT 'pending',
    submitted_by INT,
    reviewed_by INT NULL,
    review_notes TEXT NULL,
    reviewed_at DATETIME NULL,
    inventory_id INT NULL,
    budget_id INT NULL,
    allocation_id INT NULL,      -- which budget_allocation this purchase is charged to
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submitted_by) REFERENCES users(id),
    FOREIGN KEY (inventory_id) REFERENCES inventory(id)
);

-- Expense log (auto-populated on purchase approval)
CREATE TABLE IF NOT EXISTS expense_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT,
    amount DECIMAL(12,2) NOT NULL,
    category VARCHAR(100),
    logged_date DATE NOT NULL,
    budget_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id)
);

-- Activity logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Inventory reviews
CREATE TABLE IF NOT EXISTS inventory_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_date DATE NOT NULL,
    reviewed_by INT,
    notes TEXT,
    status ENUM('draft','completed') DEFAULT 'draft',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_review_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT,
    inventory_id INT,
    expected_stock DECIMAL(10,3),
    actual_stock DECIMAL(10,3),
    discrepancy DECIMAL(10,3) GENERATED ALWAYS AS (actual_stock - expected_stock) STORED,
    notes TEXT,
    FOREIGN KEY (review_id) REFERENCES inventory_reviews(id),
    FOREIGN KEY (inventory_id) REFERENCES inventory(id)
);

-- Archives
CREATE TABLE IF NOT EXISTS archives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(200) NOT NULL,
    semester_start DATE,
    semester_end DATE,
    total_expenses DECIMAL(14,2),
    total_budget DECIMAL(14,2),
    archived_by INT,
    archived_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_snapshot LONGTEXT
);

-- =============================================
-- NEW TABLES FOR REVISED REQUIREMENTS
-- =============================================

-- Budget requests submitted by Encoders to Budget Manager
CREATE TABLE IF NOT EXISTS budget_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_title VARCHAR(200) NOT NULL,
    description TEXT,
    requested_amount DECIMAL(12,2) NOT NULL,
    end_datetime DATETIME NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    encoder_id INT NOT NULL,
    reviewed_by INT NULL,
    review_remarks TEXT NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (encoder_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- Budget allocations from Budget Manager to Encoders
CREATE TABLE IF NOT EXISTS budget_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_id INT NOT NULL,
    budget_request_id INT NULL,
    encoder_id INT NULL,          -- NULL = shared allocation for all encoders
    is_shared TINYINT(1) DEFAULT 0,
    allocation_title VARCHAR(200),
    purpose TEXT,
    allocated_amount DECIMAL(12,2) NOT NULL,
    amount_used DECIMAL(12,2) DEFAULT 0,
    allocation_date DATE NOT NULL,
    end_datetime DATETIME NULL,   -- encoder loses access after this datetime
    admin_approval_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_approved_by INT NULL,
    admin_approved_at DATETIME NULL,
    admin_remarks TEXT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (budget_id) REFERENCES budgets(id),
    FOREIGN KEY (encoder_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Inventory requests submitted by Encoders to Inventory Manager
CREATE TABLE IF NOT EXISTS inventory_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(200) NOT NULL,
    quantity_requested DECIMAL(10,3) NOT NULL,
    unit VARCHAR(50) DEFAULT 'pcs',
    description TEXT,
    purpose TEXT,
    end_datetime DATETIME NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    encoder_id INT NOT NULL,
    reviewed_by INT NULL,
    review_remarks TEXT NULL,
    reviewed_at DATETIME NULL,
    quantity_released DECIMAL(10,3) NULL,
    release_date DATETIME NULL,
    inventory_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (encoder_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    FOREIGN KEY (inventory_id) REFERENCES inventory(id)
);

-- Assigned inventory to encoders (from approved inventory requests)
CREATE TABLE IF NOT EXISTS encoder_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_request_id INT NOT NULL,
    encoder_id INT NOT NULL,
    inventory_id INT NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    unit VARCHAR(50) DEFAULT 'pcs',
    quantity_assigned DECIMAL(10,3) NOT NULL,
    quantity_consumed DECIMAL(10,3) DEFAULT 0,
    quantity_remaining DECIMAL(10,3) GENERATED ALWAYS AS (quantity_assigned - quantity_consumed) STORED,
    assigned_date DATETIME NOT NULL,
    end_datetime DATETIME NULL,   -- encoder loses access after this datetime
    purpose TEXT,
    FOREIGN KEY (inventory_request_id) REFERENCES inventory_requests(id),
    FOREIGN KEY (encoder_id) REFERENCES users(id),
    FOREIGN KEY (inventory_id) REFERENCES inventory(id)
);

-- Encoder inventory consumption logs
CREATE TABLE IF NOT EXISTS inventory_consumption_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    encoder_inventory_id INT NOT NULL,
    encoder_id INT NOT NULL,
    quantity_consumed DECIMAL(10,3) NOT NULL,
    purpose TEXT,
    consumed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (encoder_inventory_id) REFERENCES encoder_inventory(id),
    FOREIGN KEY (encoder_id) REFERENCES users(id)
);

-- Budget consumption logs (encoder spending from their allocation)
CREATE TABLE IF NOT EXISTS budget_consumption_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    budget_allocation_id INT NOT NULL,
    encoder_id INT NOT NULL,
    amount_spent DECIMAL(12,2) NOT NULL,
    description TEXT,
    spent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (budget_allocation_id) REFERENCES budget_allocations(id),
    FOREIGN KEY (encoder_id) REFERENCES users(id)
);

-- Return requests (excess budget or inventory returned at end of period)
CREATE TABLE IF NOT EXISTS return_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_type ENUM('budget','inventory') NOT NULL,
    encoder_id INT NOT NULL,
    budget_allocation_id INT NULL,
    encoder_inventory_id INT NULL,
    original_purpose TEXT,
    return_quantity DECIMAL(10,3) NULL,        -- for inventory returns
    return_amount DECIMAL(12,2) NULL,          -- for budget returns
    return_status ENUM('not_yet_returned','returned') DEFAULT 'not_yet_returned',
    attachment_path VARCHAR(500) NULL,
    attachment_uploaded_at DATETIME NULL,
    verified_by INT NULL,
    verified_at DATETIME NULL,
    verification_remarks TEXT NULL,
    due_datetime DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (encoder_id) REFERENCES users(id),
    FOREIGN KEY (budget_allocation_id) REFERENCES budget_allocations(id),
    FOREIGN KEY (encoder_inventory_id) REFERENCES encoder_inventory(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
);
-- =============================================
-- MIGRATION: Add end_datetime to existing tables
-- Safe to run on both new and existing databases
-- =============================================
ALTER TABLE budget_allocations
    ADD COLUMN IF NOT EXISTS end_datetime DATETIME NULL
        COMMENT 'Encoder loses access after this datetime';

ALTER TABLE encoder_inventory
    ADD COLUMN IF NOT EXISTS end_datetime DATETIME NULL
        COMMENT 'Encoder loses access after this datetime';

-- Fix any rows where end_datetime was incorrectly saved as zero date
-- (caused by datetime-local "T" separator not being converted before MySQL insert)
UPDATE budget_allocations SET end_datetime = NULL WHERE end_datetime = '0000-00-00 00:00:00';
UPDATE encoder_inventory   SET end_datetime = NULL WHERE end_datetime = '0000-00-00 00:00:00';
UPDATE budget_requests     SET end_datetime = '2099-12-31 23:59:59' WHERE end_datetime = '0000-00-00 00:00:00';
UPDATE inventory_requests  SET end_datetime = '2099-12-31 23:59:59' WHERE end_datetime = '0000-00-00 00:00:00';
