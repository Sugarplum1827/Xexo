# CIT Food Trades – Budgeting & Inventory System
## Version 2.0 — Revised System

---

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
Ensure the upload directories are writable:
```bash
chmod 775 uploads/receipts/
chmod 775 uploads/returns/
```

### 5. Access the System
Open your browser: `http://localhost/cit_food_trades/`

---

## Default Login

| Role               | Username | Password   |
|--------------------|----------|------------|
| System Admin       | admin    | Admin@1234 |

> The admin must create other users (Budget Manager, Inventory Manager, Encoders) via **User Management**.

---

## Folder Structure

```
cit_food_trades/
├── index.php
├── database.sql
├── README.md
│
├── includes/
│   ├── db.php
│   ├── auth.php
│   ├── header.php
│   └── footer.php
│
├── auth/
│   └── logout.php
│
├── admin/
│   ├── dashboard.php            # Budget monitoring + analytics
│   ├── users.php
│   ├── approvals.php            # Purchase approvals
│   ├── budget_periods.php       # NEW: Approve/reject budget period proposals
│   ├── budget_approvals.php     # NEW: Approve/reject budget allocations
│   ├── returns_monitoring.php   # NEW: Monitor all return requests
│   ├── audit_trail.php          # NEW: Centralized audit & verification trail
│   ├── reports.php
│   ├── activity_logs.php
│   └── archive.php
│
├── budget_manager/
│   ├── dashboard.php            # Analytics + remaining budget tracker
│   ├── budget.php               # Submit budget period proposals (→ Admin approval)
│   ├── allocations.php          # NEW: Allocate budget to encoders (→ Admin approval)
│   ├── requests.php             # NEW: Approve/reject encoder budget requests
│   ├── expenses.php
│   ├── returns.php              # NEW: Verify excess budget returns
│   └── reports.php
│
├── inventory_manager/
│   ├── dashboard.php
│   ├── inventory.php            # Master inventory management
│   ├── requests.php             # NEW: Approve/reject encoder inventory requests
│   ├── usage_monitoring.php     # NEW: Monitor released inventory
│   ├── returns.php              # NEW: Verify excess inventory returns
│   ├── purchases.php
│   ├── reports.php
│   └── review.php
│
├── user/  (Encoder)
│   ├── dashboard.php            # Shows only approved allocations
│   ├── budget_requests.php      # NEW: Request budget from Budget Manager
│   ├── my_budget.php            # NEW: View allocations + record spending
│   ├── inventory_requests.php   # NEW: Request inventory from Inventory Manager
│   ├── my_inventory.php         # NEW: View assigned inventory + record consumption
│   ├── returns.php              # NEW: Upload return proof/attachment
│   ├── submit_purchase.php
│   ├── my_purchases.php
│   └── inventory_view.php
│
└── uploads/
    ├── receipts/
    └── returns/                 # NEW: Return proof attachments
```

---

## New Features in v2.0

### Encoder Module
| # | Feature | Description |
|---|---------|-------------|
| 1 | Budget Requests | Encoder submits titled budget request with amount & end date; editable while Pending only |
| 2 | Inventory Requests | Encoder requests specific items with quantity, purpose & end date |
| 3 | Table Filters | Status filters on all encoder tables |
| 4 | Budget Display | Dashboard shows only Admin-approved allocations; unapproved budgets are hidden |
| 5 | Return Process | Excess inventory/budget generates return request with attachment upload |
| 6 | Inventory Consumption | Record usage from assigned stock (does not affect master inventory) |
| 7 | Budget Consumption | Record spending from allocated budget (does not affect master budget) |

### Inventory Manager Module
| # | Feature | Description |
|---|---------|-------------|
| 1 | Request Approval | Approve/reject inventory requests with remarks; releases stock to encoder |
| 2 | Usage Monitoring | View all released items, quantities, encoder recipient, purpose & date |
| 3 | Return Verification | Verify physical returns; requires encoder attachment before marking Returned |

### Budget Manager Module
| # | Feature | Description |
|---|---------|-------------|
| 1 | Request Approval | Approve/reject encoder budget requests with remarks; creates allocation |
| 2 | Budget Proposals | New budget periods submitted to Admin for approval first |
| 3 | Shared Allocations | Allocate shared budgets accessible by all Encoders |
| 4 | Allocation History | Full history with amounts, recipients, purposes, status |
| 5 | Remaining Budget Tracker | Dashboard: Approved vs Allocated vs Used vs Remaining |
| 6 | Monthly Analytics | Bar chart of monthly allocations |
| 7 | Return Verification | Verify excess budget returns; requires attachment |

### Admin Module
| # | Feature | Description |
|---|---------|-------------|
| 1 | Budget Period Approvals | Approve or reject budget proposals from Budget Manager |
| 2 | Allocation Approvals | Approve or reject budget allocations before encoders can use them |
| 3 | Inventory Activity | See all released inventory across system |
| 4 | Budget Allocation Activity | See all allocations with status |
| 5 | Returns Monitoring | Full view of all excess budget & inventory returns |
| 6 | Audit Trail | Centralized log of all returns, verifications, approvals & actions |
| 7 | Budget Monitoring Dashboard | Total Approved / Allocated / Utilized / Remaining + monthly trends |

---

## Workflow Overview

```
Budget Flow:
  Budget Manager → Propose Budget Period → Admin Approves
  Budget Manager → Create Allocation → Admin Approves → Encoder Can Use
  Encoder → Request Budget → Budget Manager Approves → Admin Approves → Encoder Uses
  End Date Reached → Return Request Generated → Encoder Uploads Proof → Budget Manager Verifies

Inventory Flow:
  Inventory Manager → Manages Master Stock
  Encoder → Request Items → Inventory Manager Approves → Items Released to Encoder
  Encoder → Records Consumption (no effect on master stock)
  End Date Reached → Return Request Generated → Encoder Uploads Proof → Inventory Manager Verifies

Return Flow:
  System generates return_request when allocation/inventory reaches end_datetime
  Encoder uploads attachment (photo/PDF of physical return)
  Manager views attachment → marks as Returned
  Budget/Inventory restored to master records automatically
```
