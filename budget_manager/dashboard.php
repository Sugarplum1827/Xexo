<?php
require_once '../includes/auth.php';
requireRole('budget_manager', '../index.php');
$pageTitle = 'Budget Dashboard';

$activeBudget   = $conn->query("SELECT * FROM budgets WHERE is_active=1 AND approval_status='approved' AND end_date >= CURDATE() ORDER BY id DESC LIMIT 1")->fetch_assoc();
$totalApproved  = $conn->query("SELECT COALESCE(SUM(total_price),0) s FROM purchases WHERE status='approved'")->fetch_assoc()['s'];
$pendingCount   = $conn->query("SELECT COUNT(*) c FROM purchases WHERE status='pending'")->fetch_assoc()['c'];
$pendingReqs    = $conn->query("SELECT COUNT(*) c FROM budget_requests WHERE status='pending'")->fetch_assoc()['c'];
$todayExpenses  = $conn->query("SELECT COALESCE(SUM(total_price),0) s FROM purchases WHERE status='approved' AND DATE(purchase_date)=CURDATE()")->fetch_assoc()['s'];
$monthExpenses  = $conn->query("SELECT COALESCE(SUM(total_price),0) s FROM purchases WHERE status='approved' AND MONTH(purchase_date)=MONTH(NOW()) AND YEAR(purchase_date)=YEAR(NOW())")->fetch_assoc()['s'];
$activeBudgetId = $activeBudget['id'] ?? 0;
$budgetAmt      = $activeBudget['allocated_amount'] ?? 0;
$totalAllocated = $conn->query("SELECT COALESCE(SUM(allocated_amount),0) s FROM budget_allocations WHERE admin_approval_status='approved' AND budget_id=$activeBudgetId")->fetch_assoc()['s'];
$totalUsed      = $conn->query("SELECT COALESCE(SUM(amount_used),0) s FROM budget_allocations WHERE admin_approval_status='approved' AND budget_id=$activeBudgetId")->fetch_assoc()['s'];
$remaining      = $budgetAmt - $totalAllocated;
$usedPct        = $budgetAmt > 0 ? min(100, ($totalAllocated/$budgetAmt)*100) : 0;

// Monthly analytics (last 6 months)
$monthlyData = $conn->query("
    SELECT DATE_FORMAT(allocation_date,'%b %Y') AS month,
           SUM(allocated_amount) AS total_allocated,
           MONTH(allocation_date) AS m, YEAR(allocation_date) AS y
    FROM budget_allocations
    WHERE admin_approval_status='approved'
      AND allocation_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(allocation_date), MONTH(allocation_date)
    ORDER BY y ASC, m ASC
")->fetch_all(MYSQLI_ASSOC);

// Excess budget returned
$excessReturned = $conn->query("SELECT COALESCE(SUM(return_amount),0) s FROM return_requests WHERE return_type='budget' AND return_status='returned'")->fetch_assoc()['s'];

$recentExpenses = $conn->query("SELECT p.*, u.full_name FROM purchases p LEFT JOIN users u ON p.submitted_by=u.id WHERE p.status='approved' ORDER BY p.reviewed_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<?php if ($pendingReqs > 0): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
    <i class="fas fa-bell"></i> <strong><?= $pendingReqs ?> pending budget request(s)</strong> from encoders awaiting your review.
    <a href="requests.php?status=pending" style="color:var(--warning);font-weight:700;margin-left:8px;">Review Now →</a>
</div>
<?php endif; ?>

<?php if ($remaining < 0): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <strong>OVER-ALLOCATION ALERT:</strong> Total allocations exceed the approved budget by <?= formatCurrency(abs($remaining)) ?>!</div>
<?php elseif ($usedPct >= 75): ?>
<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <strong>LOW BUDGET WARNING:</strong> <?= number_format(100-$usedPct,1) ?>% of budget remaining (<?= formatCurrency($remaining) ?>).</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-wallet"></i></div>
        <div><div class="stat-value"><?= formatCurrency($budgetAmt) ?></div><div class="stat-label">Approved Budget</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-share-square"></i></div>
        <div><div class="stat-value"><?= formatCurrency($totalAllocated) ?></div><div class="stat-label">Total Allocated</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-receipt"></i></div>
        <div><div class="stat-value"><?= formatCurrency($totalUsed) ?></div><div class="stat-label">Total Used</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-piggy-bank"></i></div>
        <div><div class="stat-value"><?= formatCurrency($remaining) ?></div><div class="stat-label">Remaining Available</div></div>
    </div>
</div>

<?php if ($activeBudget): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <span class="card-title">Budget Utilization – <?= htmlspecialchars($activeBudget['period_label']) ?></span>
        <span class="badge <?= $usedPct>=90?'badge-rejected':($usedPct>=75?'badge-pending':'badge-approved') ?>"><?= number_format($usedPct,1) ?>% Allocated</span>
    </div>
    <div class="progress-bar" style="height:12px;margin-bottom:12px;">
        <div class="progress-fill <?= $usedPct>=90?'red':($usedPct>=75?'orange':'green') ?>" style="width:<?= $usedPct ?>%"></div>
    </div>
    <div style="display:flex;gap:40px;font-size:14px;flex-wrap:wrap;">
        <div><span style="color:var(--text-muted)">Today's Expenses:</span> <strong><?= formatCurrency($todayExpenses) ?></strong></div>
        <div><span style="color:var(--text-muted)">This Month:</span> <strong><?= formatCurrency($monthExpenses) ?></strong></div>
        <div><span style="color:var(--text-muted)">Returned Excess:</span> <strong style="color:var(--success)"><?= formatCurrency($excessReturned) ?></strong></div>
        <div><span style="color:var(--text-muted)">Period:</span> <strong><?= $activeBudget['start_date'] ?> – <?= $activeBudget['end_date'] ?></strong></div>
    </div>
</div>
<?php endif; ?>

<!-- Monthly Analytics -->
<?php if (!empty($monthlyData)): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><span class="card-title">Monthly Budget Allocations</span></div>
    <div style="display:flex;gap:12px;align-items:flex-end;padding:8px 0;overflow-x:auto;">
        <?php
        $maxAmt = max(array_column($monthlyData, 'total_allocated')) ?: 1;
        foreach ($monthlyData as $m):
            $barH = max(10, round(($m['total_allocated']/$maxAmt)*150));
        ?>
        <div style="text-align:center;min-width:80px;">
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;"><?= formatCurrency($m['total_allocated']) ?></div>
            <div style="height:<?= $barH ?>px;background:var(--primary);border-radius:6px 6px 0 0;opacity:0.85;"></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;"><?= $m['month'] ?></div>
        </div>
        <?php endforeach; ?>
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
