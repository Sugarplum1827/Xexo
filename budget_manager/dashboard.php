<?php
require_once '../includes/auth.php';
requireRole('budget_manager', '../index.php');
$pageTitle = 'Budget Dashboard';

$activeBudget   = $conn->query("SELECT * FROM budgets WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch_assoc();
$totalApproved  = $conn->query("SELECT COALESCE(SUM(total_price),0) s FROM purchases WHERE status='approved'")->fetch_assoc()['s'];
$pendingCount   = $conn->query("SELECT COUNT(*) c FROM purchases WHERE status='pending'")->fetch_assoc()['c'];
$todayExpenses  = $conn->query("SELECT COALESCE(SUM(total_price),0) s FROM purchases WHERE status='approved' AND DATE(purchase_date)=CURDATE()")->fetch_assoc()['s'];
$monthExpenses  = $conn->query("SELECT COALESCE(SUM(total_price),0) s FROM purchases WHERE status='approved' AND MONTH(purchase_date)=MONTH(NOW()) AND YEAR(purchase_date)=YEAR(NOW())")->fetch_assoc()['s'];
$budgetAmt      = $activeBudget['allocated_amount'] ?? 0;
$remaining      = $budgetAmt - $totalApproved;
$usedPct        = $budgetAmt > 0 ? min(100, ($totalApproved/$budgetAmt)*100) : 0;
$recentExpenses = $conn->query("SELECT p.*, u.full_name FROM purchases p LEFT JOIN users u ON p.submitted_by=u.id WHERE p.status='approved' ORDER BY p.reviewed_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<?php if ($remaining < 0): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <strong>OVERSPENDING ALERT:</strong> Total expenses exceed the allocated budget by <?= formatCurrency(abs($remaining)) ?>!</div>
<?php elseif ($usedPct >= 75): ?>
<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <strong>LOW BUDGET WARNING:</strong> <?= number_format(100-$usedPct,1) ?>% of budget remaining (<?= formatCurrency($remaining) ?>). </div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-wallet"></i></div>
        <div><div class="stat-value"><?= formatCurrency($budgetAmt) ?></div><div class="stat-label">Allocated Budget</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-money-bill-wave"></i></div>
        <div><div class="stat-value"><?= formatCurrency($totalApproved) ?></div><div class="stat-label">Total Expenses</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $remaining >= 0 ? 'green' : 'red' ?>"><i class="fas fa-<?= $remaining >= 0 ? 'piggy-bank' : 'exclamation-triangle' ?>"></i></div>
        <div><div class="stat-value"><?= formatCurrency(abs($remaining)) ?></div><div class="stat-label"><?= $remaining >= 0 ? 'Remaining' : 'Over Budget' ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-clock"></i></div>
        <div><div class="stat-value"><?= $pendingCount ?></div><div class="stat-label">Pending Purchases</div></div>
    </div>
</div>

<?php if ($activeBudget): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <span class="card-title">Budget Utilization – <?= htmlspecialchars($activeBudget['period_label']) ?></span>
        <span class="badge <?= $usedPct>=90?'badge-rejected':($usedPct>=75?'badge-pending':'badge-approved') ?>"><?= number_format($usedPct,1) ?>% Used</span>
    </div>
    <div class="progress-bar" style="height:12px;margin-bottom:12px;">
        <div class="progress-fill <?= $usedPct>=90?'red':($usedPct>=75?'orange':'green') ?>" style="width:<?= $usedPct ?>%"></div>
    </div>
    <div style="display:flex;gap:40px;font-size:14px;flex-wrap:wrap;">
        <div><span style="color:var(--text-muted)">Today's Expenses:</span> <strong><?= formatCurrency($todayExpenses) ?></strong></div>
        <div><span style="color:var(--text-muted)">This Month:</span> <strong><?= formatCurrency($monthExpenses) ?></strong></div>
        <div><span style="color:var(--text-muted)">Period:</span> <strong><?= $activeBudget['start_date'] ?> – <?= $activeBudget['end_date'] ?></strong></div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Recent Approved Expenses</span>
        <a href="expenses.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Item</th><th>Qty</th><th>Total</th><th>Supplier</th><th>Date</th><th>By</th></tr></thead>
            <tbody>
            <?php foreach ($recentExpenses as $p): ?>
            <tr>
                <td><strong><?= htmlspecialchars($p['item_name']) ?></strong></td>
                <td><?= $p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?></td>
                <td><strong><?= formatCurrency($p['total_price']) ?></strong></td>
                <td><?= htmlspecialchars($p['supplier'] ?? '—') ?></td>
                <td><?= $p['purchase_date'] ?></td>
                <td><?= htmlspecialchars($p['full_name'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentExpenses)): ?><tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px">No expenses yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
