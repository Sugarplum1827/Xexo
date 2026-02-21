<?php
$role = $_SESSION['role'] ?? '';
$name = $_SESSION['full_name'] ?? 'User';
$roleLabel = [
    'admin' => 'System Administrator',
    'budget_manager' => 'Budget Manager',
    'inventory_manager' => 'Inventory Manager',
    'user' => 'Encoder'
][$role] ?? ucfirst($role);

$dashboardLink = [
    'admin' => '../admin/dashboard.php',
    'budget_manager' => '../budget_manager/dashboard.php',
    'inventory_manager' => '../inventory_manager/dashboard.php',
    'user' => '../user/dashboard.php'
][$role] ?? '../index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? 'CIT Food Trades' ?> – CIT Food Trades</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,300;0,600;0,800;1,300&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    /* ── RED & GREY PALETTE ── */
    --primary:       #b91c1c;   /* deep red – main brand */
    --primary-dark:  #991b1b;   /* darker red */
    --primary-mid:   #dc2626;   /* mid red */
    --primary-light: #fca5a5;   /* light red tint */
    --primary-bg:    rgba(185,28,28,0.08); /* red tint bg */

    /* Grey scale */
    --grey-900:  #1c1c1e;   /* sidebar / near-black */
    --grey-800:  #2c2c2e;   /* sidebar mid */
    --grey-700:  #3a3a3c;   /* sidebar hover */
    --grey-600:  #48484a;   /* muted text */
    --grey-400:  #98989a;   /* placeholder */
    --grey-200:  #e5e5ea;   /* borders */
    --grey-100:  #f2f2f7;   /* page background */
    --grey-50:   #f9f9fb;   /* card background */

    /* Semantic aliases (keep old names so all pages still work) */
    --forest:       var(--grey-900);
    --forest-mid:   var(--grey-800);
    --forest-light: var(--grey-700);
    --gold:         var(--primary);
    --gold-light:   var(--primary-mid);
    --cream:        var(--grey-100);
    --cream-dark:   var(--grey-200);
    --white:        #ffffff;
    --text:         #1c1c1e;
    --text-muted:   #6e6e73;
    --danger:       #b91c1c;
    --warning:      #d97706;
    --success:      #16a34a;
    --info:         #2563eb;

    --sidebar-w: 260px;
    --shadow:    0 4px 24px rgba(0,0,0,0.08);
    --radius:    14px;
}

* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'DM Sans', sans-serif;
    background: var(--grey-100);
    color: var(--text);
    min-height: 100vh;
    display: flex;
}

/* ══════════════════════════════
   SIDEBAR
══════════════════════════════ */
.sidebar {
    width: var(--sidebar-w);
    background: var(--grey-900);
    min-height: 100vh;
    position: fixed;
    top:0; left:0;
    display: flex;
    flex-direction: column;
    z-index: 100;
    transition: transform 0.3s;
    border-right: 1px solid rgba(255,255,255,0.04);
}

.sidebar-logo {
    padding: 26px 24px 18px;
    border-bottom: 1px solid rgba(255,255,255,0.07);
}

.sidebar-logo img {
    width: 176px;
    height:60px;
    background: var(--primary);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    object-fit: contain;
    box-shadow: 0 4px 16px rgba(185,28,28,0.4);
}

.sidebar-logo h1 {
    font-family: 'Fraunces', serif;
    font-size: 15px; font-weight: 600;
    color: #ffffff;
    line-height: 1.3;
}
.sidebar-logo p {
    font-size: 11px;
    color: var(--primary-light);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-top: 2px;
}

.sidebar-user {
    padding: 14px 24px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.sidebar-user .avatar {
    width: 36px; height: 36px;
    background: var(--grey-700);
    border-radius: 50%;
    display: flex; align-items:center; justify-content:center;
    font-size: 14px; font-weight: 700;
    color: var(--primary-light);
    border: 2px solid var(--primary);
    margin-right: 10px;
    flex-shrink: 0;
}
.sidebar-user .user-info { display:flex; align-items:center; }
.sidebar-user .user-name { font-size: 13px; font-weight: 600; color: #ffffff; }
.sidebar-user .user-role { font-size: 11px; color: var(--primary-light); }

.sidebar-nav { flex:1; padding: 10px 0; overflow-y:auto; }

.nav-section-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(248, 247, 247, 0.55);
    padding: 14px 24px 6px;
}

.nav-item {
    display: flex;
    align-items: center;
    padding: 10px 24px;
    color: rgba(255,255,255,0.60);
    text-decoration: none;
    font-size: 13.5px;
    font-weight: 400;
    transition: all 0.18s;
    border-left: 3px solid transparent;
    gap: 12px;
}
.nav-item i { width: 18px; font-size: 14px; opacity: 0.75; }
.nav-item:hover {
    background: rgba(185,28,28,0.10);
    color: var(--primary-light);
    border-left-color: var(--primary);
}
.nav-item.active {
    background: rgba(185,28,28,0.15);
    color: #ffffff;
    border-left-color: var(--primary);
    font-weight: 600;
}
.nav-item.active i { opacity: 1; color: var(--primary-light); }

.sidebar-footer {
    padding: 16px 24px;
    border-top: 1px solid rgba(255,255,255,0.06);
}
.sidebar-footer a {
    display:flex; align-items:center; gap:10px;
    color: rgba(255,255,255,0.40);
    text-decoration: none;
    font-size: 13px;
    transition: color 0.2s;
}
.sidebar-footer a:hover { color: var(--primary-light); }

/* ══════════════════════════════
   MAIN CONTENT
══════════════════════════════ */
.main-content {
    margin-left: var(--sidebar-w);
    flex: 1;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.topbar {
    background: var(--white);
    padding: 15px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--grey-200);
    position: sticky; top:0; z-index:50;
    box-shadow: 0 1px 0 var(--grey-200);
}
.topbar::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, var(--primary), var(--primary-mid), transparent);
}
.topbar-title {
    font-family: 'Fraunces', serif;
    font-size: 21px; font-weight: 700;
    color: var(--grey-900);
}
.topbar-right { display:flex; align-items:center; gap: 16px; }
.topbar-date { font-size: 13px; color: var(--text-muted); }
.page-body { padding: 32px; flex:1; }

/* ══════════════════════════════
   CARDS
══════════════════════════════ */
.card {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 24px;
    border: 1px solid var(--grey-200);
}
.card-header {
    display:flex; align-items:center; justify-content:space-between;
    margin-bottom: 20px;
}
.card-title {
    font-family: 'Fraunces', serif;
    font-size: 17px; font-weight: 600;
    color: var(--grey-900);
}

/* ══════════════════════════════
   STAT CARDS
══════════════════════════════ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px,1fr));
    gap: 20px; margin-bottom: 28px;
}
.stat-card {
    background: var(--white);
    border-radius: var(--radius);
    padding: 22px 24px;
    display:flex; align-items:center; gap:18px;
    box-shadow: var(--shadow);
    border: 1px solid var(--grey-200);
    transition: transform 0.2s, box-shadow 0.2s;
    border-top: 3px solid var(--grey-200);
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 32px rgba(0,0,0,0.10);
    border-top-color: var(--primary);
}
.stat-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display:flex; align-items:center; justify-content:center;
    font-size: 22px;
    flex-shrink:0;
}
.stat-icon.green  { background: rgba(22,163,74,0.10);  color: var(--success); }
.stat-icon.gold   { background: rgba(185,28,28,0.10);  color: var(--primary); }
.stat-icon.red    { background: rgba(185,28,28,0.10);  color: var(--primary); }
.stat-icon.blue   { background: rgba(37,99,235,0.10);  color: var(--info); }
.stat-icon.forest { background: rgba(28,28,30,0.08);   color: var(--grey-900); }
.stat-value {
    font-family: 'Fraunces', serif;
    font-size: 26px; font-weight: 800;
    color: var(--text); line-height:1;
}
.stat-label {
    font-size: 12px; color: var(--text-muted);
    margin-top: 4px; text-transform:uppercase; letter-spacing:0.05em;
}

/* ══════════════════════════════
   TABLES
══════════════════════════════ */
.table-wrap { overflow-x:auto; }
table { width:100%; border-collapse:collapse; }
thead th {
    background: var(--grey-100);
    color: var(--grey-600);
    font-size: 11px;
    text-transform:uppercase;
    letter-spacing:0.07em;
    font-weight: 700;
    padding: 11px 16px;
    text-align:left;
    border-bottom: 2px solid var(--grey-200);
}
tbody td {
    padding: 13px 16px;
    border-bottom: 1px solid var(--grey-200);
    font-size: 14px;
    vertical-align:middle;
}
tbody tr:hover { background: rgba(185,28,28,0.03); }
tbody tr:last-child td { border-bottom: none; }

/* ══════════════════════════════
   BADGES
══════════════════════════════ */
.badge {
    display:inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform:uppercase;
    letter-spacing:0.04em;
}
.badge-pending  { background: rgba(217,119,6,0.12);   color: var(--warning); }
.badge-approved { background: rgba(22,163,74,0.12);   color: var(--success); }
.badge-rejected { background: rgba(185,28,28,0.10);   color: var(--danger); }
.badge-low      { background: rgba(185,28,28,0.10);   color: var(--danger); }
.badge-ok       { background: rgba(22,163,74,0.12);   color: var(--success); }

/* ══════════════════════════════
   BUTTONS
══════════════════════════════ */
.btn {
    display:inline-flex; align-items:center; gap:7px;
    padding: 9px 18px;
    border-radius: 9px;
    font-size: 13.5px;
    font-weight: 500;
    cursor:pointer;
    border:none;
    transition: all 0.18s;
    text-decoration:none;
    font-family: 'DM Sans', sans-serif;
}
.btn-primary       { background: var(--grey-900);  color: #fff; }
.btn-primary:hover { background: var(--grey-700); }
.btn-gold          { background: var(--primary);   color: #fff; font-weight:700; }
.btn-gold:hover    { background: var(--primary-dark); box-shadow: 0 4px 14px rgba(185,28,28,0.35); }
.btn-danger        { background: var(--danger);    color: #fff; }
.btn-danger:hover  { background: #991b1b; }
.btn-success       { background: var(--success);   color: #fff; }
.btn-success:hover { background: #15803d; }
.btn-outline       { background: transparent; border: 1.5px solid var(--grey-300, #d1d1d6); color: var(--grey-900); }
.btn-outline:hover { background: var(--grey-900); color: #fff; border-color: var(--grey-900); }
.btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 7px; }

/* ══════════════════════════════
   FORMS
══════════════════════════════ */
.form-group { margin-bottom: 18px; }
.form-group label {
    display:block; font-size: 13px; font-weight: 600;
    color: var(--grey-800); margin-bottom:6px;
}
.form-control {
    width:100%;
    padding: 10px 14px;
    border: 1.5px solid var(--grey-200);
    border-radius: 9px;
    font-size: 14px;
    font-family: 'DM Sans', sans-serif;
    background: var(--white);
    transition: border-color 0.2s, box-shadow 0.2s;
    color: var(--text);
}
.form-control:focus {
    outline:none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(185,28,28,0.12);
}
.form-row { display:grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-row.three { grid-template-columns: 1fr 1fr 1fr; }

/* ══════════════════════════════
   ALERTS
══════════════════════════════ */
.alert {
    padding: 14px 18px; border-radius: 10px;
    margin-bottom: 16px; font-size: 14px;
    display:flex; align-items:center; gap:10px;
}
.alert-danger  { background: rgba(185,28,28,0.08);  color: #991b1b; border: 1px solid rgba(185,28,28,0.20); }
.alert-success { background: rgba(22,163,74,0.08);  color: #15803d; border: 1px solid rgba(22,163,74,0.20); }
.alert-warning { background: rgba(217,119,6,0.08);  color: #b45309; border: 1px solid rgba(217,119,6,0.20); }

/* ══════════════════════════════
   MODALS
══════════════════════════════ */
.modal-overlay {
    display:none; position:fixed; inset:0;
    background: rgba(0,0,0,0.55);
    z-index:200;
    align-items:center; justify-content:center;
}
.modal-overlay.open { display:flex; }
.modal {
    background: var(--white);
    border-radius: 16px;
    padding: 32px;
    width:90%; max-width:540px;
    max-height:90vh; overflow-y:auto;
    box-shadow: 0 24px 64px rgba(0,0,0,0.25);
    animation: modalIn 0.22s ease;
    border-top: 4px solid var(--primary);
}
@keyframes modalIn { from { transform: scale(0.95) translateY(8px); opacity:0; } to { transform:scale(1) translateY(0); opacity:1; } }
.modal-header {
    display:flex; align-items:center;
    justify-content:space-between; margin-bottom:24px;
}
.modal-title {
    font-family:'Fraunces',serif;
    font-size:20px; font-weight:700; color:var(--grey-900);
}
.modal-close {
    background:none; border:none;
    font-size:22px; cursor:pointer;
    color:var(--text-muted); line-height:1;
    transition: color 0.2s;
}
.modal-close:hover { color: var(--primary); }

/* ══════════════════════════════
   GRID HELPERS
══════════════════════════════ */
.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; }

/* ══════════════════════════════
   PROGRESS BARS
══════════════════════════════ */
.progress-bar {
    background: var(--grey-200);
    border-radius: 99px; height:8px; overflow:hidden;
}
.progress-fill { height:100%; border-radius:99px; transition: width 0.5s; }
.progress-fill.green  { background: var(--success); }
.progress-fill.orange { background: var(--warning); }
.progress-fill.red    { background: var(--primary); }

/* ══════════════════════════════
   RESPONSIVE
══════════════════════════════ */
@media(max-width:768px){
    .sidebar { transform: translateX(-100%); }
    .main-content { margin-left:0; }
    .form-row, .form-row.three { grid-template-columns:1fr; }
}
</style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo"> <img src="/image/CIT.png" alt="Logo"></div>
        <h1>CIT Food Trades</h1>
        <p>Budgeting & Inventory</p>
    </div>
    <div class="sidebar-user">
        <div class="user-info">
            <div class="avatar"><?= strtoupper(substr($name, 0, 1)) ?></div>
            <div>
                <div class="user-name"><?= htmlspecialchars($name) ?></div>
                <div class="user-role"><?= $roleLabel ?></div>
            </div>
        </div>
    </div>
    <nav class="sidebar-nav">
<?php if ($role === 'admin'): ?>
        <div class="nav-section-label">Administration</div>
        <a href="../admin/dashboard.php" class="nav-item <?= (basename($_SERVER['PHP_SELF'])=='dashboard.php'&&strpos($_SERVER['PHP_SELF'],'admin')!==false)?'active':'' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="../admin/users.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='users.php'?'active':'' ?>"><i class="fas fa-users"></i> User Management</a>
        <a href="../admin/approvals.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='approvals.php'?'active':'' ?>"><i class="fas fa-check-circle"></i> Purchase Approvals</a>
        <a href="../admin/reports.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='reports.php'?'active':'' ?>"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="../admin/activity_logs.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='activity_logs.php'?'active':'' ?>"><i class="fas fa-history"></i> Activity Logs</a>
        <a href="../admin/archive.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='archive.php'?'active':'' ?>"><i class="fas fa-archive"></i> Archive</a>
<?php elseif ($role === 'budget_manager'): ?>
        <div class="nav-section-label">Budget Management</div>
        <a href="../budget_manager/dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="../budget_manager/budget.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='budget.php'?'active':'' ?>"><i class="fas fa-wallet"></i> Budget Allocation</a>
        <a href="../budget_manager/expenses.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='expenses.php'?'active':'' ?>"><i class="fas fa-receipt"></i> Expense Tracking</a>
        <a href="../budget_manager/reports.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='reports.php'?'active':'' ?>"><i class="fas fa-chart-pie"></i> Budget Reports</a>
<?php elseif ($role === 'inventory_manager'): ?>
        <div class="nav-section-label">Inventory</div>
        <a href="../inventory_manager/dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="../inventory_manager/inventory.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='inventory.php'?'active':'' ?>"><i class="fas fa-boxes"></i> Inventory</a>
        <a href="../inventory_manager/purchases.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='purchases.php'?'active':'' ?>"><i class="fas fa-shopping-cart"></i> Purchases</a>
        <a href="../inventory_manager/reports.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='reports.php'?'active':'' ?>"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="../inventory_manager/review.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='review.php'?'active':'' ?>"><i class="fas fa-clipboard-check"></i> Inventory Review</a>
<?php elseif ($role === 'user'): ?>
        <div class="nav-section-label">Encoder</div>
        <a href="../user/dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='dashboard.php'?'active':'' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="../user/submit_purchase.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='submit_purchase.php'?'active':'' ?>"><i class="fas fa-plus-circle"></i> Submit Purchase</a>
        <a href="../user/my_purchases.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='my_purchases.php'?'active':'' ?>"><i class="fas fa-list"></i> My Purchases</a>
        <a href="../user/inventory_view.php" class="nav-item <?= basename($_SERVER['PHP_SELF'])=='inventory_view.php'?'active':'' ?>"><i class="fas fa-eye"></i> View Inventory</a>
<?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
    </div>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="topbar-title"><?= $pageTitle ?? 'Dashboard' ?></div>
        <div class="topbar-right">
            <span class="topbar-date"><i class="far fa-calendar"></i> <?= date('F j, Y') ?></span>
        </div>
    </div>
    <div class="page-body">
