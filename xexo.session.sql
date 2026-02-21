-- CIT Food Trades Budgeting and Inventory System

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

-- Budget periods / allocations
CREATE TABLE IF NOT EXISTS budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period_label VARCHAR(100) NOT NULL,         -- e.g. "2nd Semester 2025-2026"
    period_type ENUM('daily','monthly','semestral','yearly') DEFAULT 'semestral',
    allocated_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Inventory items
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
    data_snapshot LONGTEXT -- JSON snapshot of archived records
);

-- NOTE: Do NOT insert admin here. Run setup.php in your browser to create the admin account.
-- This ensures password_hash() runs on YOUR server with YOUR PHP version.

-- Sample budget
INSERT IGNORE INTO budgets (period_label, period_type, allocated_amount, start_date, end_date, is_active) VALUES
('2nd Semester 2025-2026', 'semestral', 150000.00, '2026-01-01', '2026-05-31', 1);

-- Sample inventory items
INSERT IGNORE INTO inventory (item_name, category, unit, current_stock, minimum_stock, unit_cost) VALUES
('All-Purpose Flour', 'Dry Goods', 'kg', 25.5, 5, 55.00),
('White Sugar', 'Dry Goods', 'kg', 18.0, 5, 65.00),
('Cooking Oil', 'Condiments', 'L', 12.0, 3, 95.00),
('Salt', 'Condiments', 'kg', 8.5, 2, 18.00),
('Butter', 'Dairy', 'kg', 4.0, 2, 180.00),
('Eggs', 'Dairy', 'pcs', 120, 24, 9.50),
('Soy Sauce', 'Condiments', 'bottle', 6, 2, 35.00),
('Vinegar', 'Condiments', 'bottle', 5, 2, 28.00);
