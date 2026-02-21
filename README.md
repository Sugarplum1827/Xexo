# CIT Food Trades – Budgeting & Inventory System

## Setup Instructions

### Requirements
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.4+
- Apache or Nginx with mod_rewrite
- XAMPP / WAMP / LAMP recommended for local development

---

## Installation Steps

### 1. Copy Files
Place the entire `cit_food_trades/` folder inside your web server's document root:
- XAMPP: `C:/xampp/htdocs/cit_food_trades/`
- Linux: `/var/www/html/cit_food_trades/`

### 2. Create the Database
Open phpMyAdmin (or MySQL CLI) and run the database script:
```
mysql -u root -p < database.sql
```
Or paste the contents of `database.sql` into phpMyAdmin's SQL tab.

### 3. Configure Database Connection
Edit `includes/db.php` if needed:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // your MySQL password
define('DB_NAME', 'cit_food_trades');
```

### 4. Set Upload Directory Permissions
Ensure the `uploads/receipts/` directory is writable:
```bash
chmod 775 uploads/receipts/
```

### 5. Access the System
Open your browser: `http://localhost/cit_food_trades/`

---

## Default Login Credentials

| Role               | Username | Password   |
|--------------------|----------|------------|
| System Admin       | admin    | Admin@1234 |

> The admin must create other users (Budget Manager, Inventory Manager, Encoders) via **User Management**.

---

## Folder Structure

```
cit_food_trades/
├── index.php                    # Login page
├── database.sql                 # Database setup
├── README.md
│
├── includes/
│   ├── db.php                   # Database connection
│   ├── auth.php                 # Auth helpers, session, logging
│   ├── header.php               # Shared sidebar + topbar
│   └── footer.php               # Shared footer
│
├── auth/
│   └── logout.php
│
├── admin/
│   ├── dashboard.php            # Admin overview
│   ├── users.php                # Create/manage users (RBAC)
│   ├── approvals.php            # Review & approve purchases
│   ├── reports.php              # Generate reports by period
│   ├── activity_logs.php        # Full system audit log
│   └── archive.php              # End-of-semester archiving
│
├── budget_manager/
│   ├── dashboard.php            # Budget overview + alerts
│   ├── budget.php               # Allocate/adjust budgets
│   ├── expenses.php             # Track expenses by period
│   └── reports.php              # Budget utilization reports
│
├── inventory_manager/
│   ├── dashboard.php            # Inventory overview + alerts
│   ├── inventory.php            # Add/edit/delete inventory items
│   ├── purchases.php            # View all purchase records
│   ├── reports.php              # Inventory reports
│   └── review.php               # Periodic inventory review/count
│
├── user/
│   ├── dashboard.php            # Encoder overview
│   ├── submit_purchase.php      # Submit purchase + receipt upload
│   ├── my_purchases.php         # View/repeat own purchases
│   └── inventory_view.php       # Read-only inventory view
│
└── uploads/
    └── receipts/                # Uploaded receipt files
```

---

## Key Features

| # | Feature                        | Description |
|---|-------------------------------|-------------|
| 8 | Purchase Submission           | Encoders submit purchases with receipt upload |
| 9 | Inventory Update              | Auto-updates stock on approval; shows current levels |
| 10| Expense & Budget Tracking     | Daily/monthly/semestral/yearly tracking with alerts |
| 11| Review & Approval             | Admin approves/rejects/requests correction |
| 12| Report Generation             | Printable reports by time period |
| 13| Activity Logging              | Full audit trail with timestamps and IP |
| 14| RBAC Access Control           | Admin, Budget Mgr, Inventory Mgr, Encoder roles |
| 15| Repeat Purchases              | Reorder from purchase history |
| 16| Inventory Review              | Periodic stock count & discrepancy tracking |
| 17| End-of-Term Archiving         | Semester-end data archiving |
