<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');
$pageTitle = 'Admin Dashboard';

// Stats
$totalUsers = $conn->query("SELECT COUNT(*) c FROM users WHERE role != 'admin'")->fetch_assoc()['c'];
$pendingPurchases = $conn->query("SELECT COUNT(*) c FROM purchases WHERE status='pending'")->fetch_assoc()['c'];
$activeBudget = $conn->query("SELECT * FROM budgets WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch_assoc();
$totalExpenses = $conn->query("SELECT COALESCE(SUM(total_price),0) s FROM purchases WHERE status='approved'")->fetch_assoc()['s'];
$budgetAmt = $activeBudget['allocated_amount'] ?? 0;
$remaining = $budgetAmt - $totalExpenses;
$usedPct = $budgetAmt > 0 ? min(100, ($totalExpenses/$budgetAmt)*100) : 0;
$recentPurchases = $conn->query("SELECT p.*, u.full_name FROM purchases p LEFT JOIN users u ON p.submitted_by=u.id ORDER BY p.created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
$recentLogs = $conn->query("SELECT l.*, u.full_name FROM activity_logs l LEFT JOIN users u ON l.user_id=u.id ORDER BY l.created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon forest"><i class="fas fa-users"></i></div>
        <div><div class="stat-value"><?= $totalUsers ?></div><div class="stat-label">Total Users</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-clock"></i></div>
        <div><div class="stat-value"><?= $pendingPurchases ?></div><div class="stat-label">Pending Approvals</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-money-bill-wave"></i></div>
        <div><div class="stat-value"><?= formatCurrency($totalExpenses) ?></div><div class="stat-label">Total Expenses</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-wallet"></i></div>
        <div><div class="stat-value"><?= formatCurrency($remaining) ?></div><div class="stat-label">Remaining Budget</div></div>
    </div>
</div>

<!-- Budget Status -->
<?php if ($activeBudget): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <span class="card-title">Budget Status – <?= htmlspecialchars($activeBudget['period_label']) ?></span>
        <?php if ($usedPct >= 90): ?><span class="badge badge-rejected">⚠ Over-Budget Alert</span>
        <?php elseif ($usedPct >= 75): ?><span class="badge badge-pending">⚠ Low Budget</span>
        <?php else: ?><span class="badge badge-approved">On Track</span><?php endif; ?>
    </div>
    <div style="display:flex;gap:32px;margin-bottom:14px;font-size:14px;">
        <div><div style="color:var(--text-muted);font-size:12px;margin-bottom:2px;">Allocated</div><strong><?= formatCurrency($budgetAmt) ?></strong></div>
        <div><div style="color:var(--text-muted);font-size:12px;margin-bottom:2px;">Used</div><strong style="color:var(--danger)"><?= formatCurrency($totalExpenses) ?></strong></div>
        <div><div style="color:var(--text-muted);font-size:12px;margin-bottom:2px;">Remaining</div><strong style="color:var(--success)"><?= formatCurrency($remaining) ?></strong></div>
        <div><div style="color:var(--text-muted);font-size:12px;margin-bottom:2px;">Period</div><strong><?= $activeBudget['start_date'] ?> to <?= $activeBudget['end_date'] ?></strong></div>
    </div>
    <div class="progress-bar">
        <div class="progress-fill <?= $usedPct>=90?'red':($usedPct>=75?'orange':'green') ?>" style="width:<?= $usedPct ?>%"></div>
    </div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:6px;"><?= number_format($usedPct,1) ?>% used</div>
</div>
<?php endif; ?>

<div class="grid-2" style="gap:24px;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Recent Purchases</span>
            <a href="approvals.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Item</th><th>Amount</th><th>By</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recentPurchases as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['item_name']) ?></td>
                    <td><?= formatCurrency($p['total_price']) ?></td>
                    <td><?= htmlspecialchars($p['full_name'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= $p['status'] === 'approved' ? 'approved' : ($p['status']==='rejected'?'rejected':'pending') ?>"><?= ucfirst($p['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Activity Log</span>
            <a href="activity_logs.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>User</th><th>Action</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($recentLogs as $l): ?>
                <tr>
                    <td><?= htmlspecialchars($l['full_name'] ?? 'System') ?></td>
                    <td><?= htmlspecialchars($l['action']) ?></td>
                    <td style="color:var(--text-muted);font-size:12px;"><?= timeAgo($l['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
