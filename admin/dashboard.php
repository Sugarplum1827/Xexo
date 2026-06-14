<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');
$pageTitle = 'Admin Dashboard';

// Stats
$totalUsers        = $conn->query("SELECT COUNT(*) c FROM users WHERE role != 'admin'")->fetch_assoc()['c'];
$pendingPurchases  = $conn->query("SELECT COUNT(*) c FROM purchases WHERE status='pending'")->fetch_assoc()['c'];
$pendingBudgets    = $conn->query("SELECT COUNT(*) c FROM budgets WHERE approval_status='pending'")->fetch_assoc()['c'];
$pendingAllocations= $conn->query("SELECT COUNT(*) c FROM budget_allocations WHERE admin_approval_status='pending'")->fetch_assoc()['c'];
$activeBudget      = $conn->query("SELECT * FROM budgets WHERE is_active=1 AND approval_status='approved' AND end_date >= CURDATE() ORDER BY id DESC LIMIT 1")->fetch_assoc();
$activeBudgetId    = $activeBudget['id'] ?? 0;
$totalAllocated    = $conn->query("
    SELECT COALESCE(SUM(ba.allocated_amount),0) s
    FROM budget_allocations ba
    LEFT JOIN return_requests rr ON rr.budget_allocation_id = ba.id AND rr.return_type='budget' AND rr.return_status='returned'
    WHERE ba.admin_approval_status='approved'
      AND ba.budget_id=$activeBudgetId
      AND rr.id IS NULL
")->fetch_assoc()['s'];
$totalUsed         = $conn->query("
    SELECT COALESCE(SUM(ba.amount_used),0) s
    FROM budget_allocations ba
    LEFT JOIN return_requests rr ON rr.budget_allocation_id = ba.id AND rr.return_type='budget' AND rr.return_status='returned'
    WHERE ba.admin_approval_status='approved'
      AND ba.budget_id=$activeBudgetId
      AND rr.id IS NULL
")->fetch_assoc()['s'];
$budgetAmt         = $activeBudget['allocated_amount'] ?? 0;
$remaining         = $budgetAmt - $totalAllocated;
$usedPct           = $budgetAmt > 0 ? min(100, ($totalAllocated/$budgetAmt)*100) : 0;
// Excess returned and pending returns — scoped to active budget period only
$excessReturned    = $conn->query("
    SELECT COALESCE(SUM(rr.return_amount),0) s
    FROM return_requests rr
    JOIN budget_allocations ba ON rr.budget_allocation_id = ba.id
    WHERE rr.return_type='budget'
      AND rr.return_status='returned'
      AND ba.budget_id=$activeBudgetId
")->fetch_assoc()['s'];
$pendingReturns    = $conn->query("
    SELECT COUNT(*) c
    FROM return_requests rr
    JOIN budget_allocations ba ON rr.budget_allocation_id = ba.id
    WHERE rr.return_status='not_yet_returned'
      AND ba.budget_id=$activeBudgetId
")->fetch_assoc()['c'];

// Monthly analytics (last 6 months)
$monthlyAlloc = $conn->query("
    SELECT DATE_FORMAT(allocation_date,'%b %Y') AS month,
           SUM(allocated_amount) AS total_allocated,
           MONTH(allocation_date) AS m, YEAR(allocation_date) AS y
    FROM budget_allocations
    WHERE admin_approval_status='approved'
      AND allocation_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(allocation_date), MONTH(allocation_date)
    ORDER BY y ASC, m ASC
")->fetch_all(MYSQLI_ASSOC);

// Inventory activity monitoring
$invActivity = $conn->query("
    SELECT ei.*, u.full_name AS encoder_name, ir.description AS purpose
    FROM encoder_inventory ei
    JOIN users u ON ei.encoder_id = u.id
    JOIN inventory_requests ir ON ei.inventory_request_id = ir.id
    ORDER BY ei.assigned_date DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Budget allocation activity
$allocActivity = $conn->query("
    SELECT ba.*, b.period_label, u.full_name AS encoder_name, cr.full_name AS manager_name
    FROM budget_allocations ba
    JOIN budgets b ON ba.budget_id = b.id
    LEFT JOIN users u ON ba.encoder_id = u.id
    JOIN users cr ON ba.created_by = cr.id
    ORDER BY ba.created_at DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$recentPurchases = $conn->query("SELECT p.*, u.full_name FROM purchases p LEFT JOIN users u ON p.submitted_by=u.id ORDER BY p.created_at DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<!-- Alerts -->
<?php if ($pendingPurchases > 0): ?>
<div class="alert alert-warning" style="margin-bottom:12px;">
    <i class="fas fa-clock"></i> <strong><?= $pendingPurchases ?> purchase(s)</strong> awaiting approval.
    <a href="approvals.php" style="color:var(--warning);font-weight:700;margin-left:8px;">Review →</a>
</div>
<?php endif; ?>
<?php if ($pendingBudgets > 0): ?>
<div class="alert alert-warning" style="margin-bottom:12px;">
    <i class="fas fa-calendar-alt"></i> <strong><?= $pendingBudgets ?> budget proposal(s)</strong> pending your approval.
    <a href="budget_periods.php?status=pending" style="color:var(--warning);font-weight:700;margin-left:8px;">Review →</a>
</div>
<?php endif; ?>
<?php if ($pendingAllocations > 0): ?>
<div class="alert alert-warning" style="margin-bottom:12px;">
    <i class="fas fa-file-invoice-dollar"></i> <strong><?= $pendingAllocations ?> allocation(s)</strong> pending your approval.
    <a href="budget_approvals.php?status=pending" style="color:var(--warning);font-weight:700;margin-left:8px;">Review →</a>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon forest"><i class="fas fa-users"></i></div>
        <div><div class="stat-value"><?= $totalUsers ?></div><div class="stat-label">Total Users</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-wallet"></i></div>
        <div><div class="stat-value"><?= formatCurrency($budgetAmt) ?></div><div class="stat-label">Approved Budget</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-share-square"></i></div>
        <div><div class="stat-value"><?= formatCurrency($totalAllocated) ?></div><div class="stat-label">Total Allocated</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-piggy-bank"></i></div>
        <div><div class="stat-value"><?= formatCurrency($remaining) ?></div><div class="stat-label">Remaining Budget</div></div>
    </div>
</div>

<!-- Budget Monitoring Dashboard -->
<?php if ($activeBudget): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <span class="card-title">Budget Monitoring – <?= htmlspecialchars($activeBudget['period_label']) ?></span>
        <?php if ($usedPct >= 90): ?><span class="badge badge-rejected">⚠ Over-Allocation</span>
        <?php elseif ($usedPct >= 75): ?><span class="badge badge-pending">⚠ Low Budget</span>
        <?php else: ?><span class="badge badge-approved">On Track</span><?php endif; ?>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:20px;margin-bottom:16px;">
        <div style="text-align:center;padding:12px;background:var(--grey-100);border-radius:10px;">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Total Approved</div>
            <div style="font-size:20px;font-weight:800;font-family:'Fraunces',serif;"><?= formatCurrency($budgetAmt) ?></div>
        </div>
        <div style="text-align:center;padding:12px;background:var(--grey-100);border-radius:10px;">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Total Allocated</div>
            <div style="font-size:20px;font-weight:800;font-family:'Fraunces',serif;color:var(--primary);"><?= formatCurrency($totalAllocated) ?></div>
        </div>
        <div style="text-align:center;padding:12px;background:var(--grey-100);border-radius:10px;">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Total Utilized</div>
            <div style="font-size:20px;font-weight:800;font-family:'Fraunces',serif;color:var(--warning);"><?= formatCurrency($totalUsed) ?></div>
        </div>
        <div style="text-align:center;padding:12px;background:var(--grey-100);border-radius:10px;">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Remaining Balance</div>
            <div style="font-size:20px;font-weight:800;font-family:'Fraunces',serif;color:var(--success);"><?= formatCurrency($remaining) ?></div>
        </div>
        <div style="text-align:center;padding:12px;background:var(--grey-100);border-radius:10px;">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Excess Returned</div>
            <div style="font-size:20px;font-weight:800;font-family:'Fraunces',serif;color:var(--success);"><?= formatCurrency($excessReturned) ?></div>
        </div>
        <div style="text-align:center;padding:12px;background:var(--grey-100);border-radius:10px;">
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;">Pending Returns</div>
            <div style="font-size:20px;font-weight:800;font-family:'Fraunces',serif;color:<?= $pendingReturns>0?'var(--warning)':'var(--success)' ?>;"><?= $pendingReturns ?></div>
        </div>
    </div>
    <div class="progress-bar" style="height:10px;">
        <div class="progress-fill <?= $usedPct>=90?'red':($usedPct>=75?'orange':'green') ?>" style="width:<?= $usedPct ?>%"></div>
    </div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:6px;"><?= number_format($usedPct,1) ?>% of budget allocated</div>
</div>
<?php endif; ?>

<!-- Monthly Analytics Chart -->
<?php if (!empty($monthlyAlloc)): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><span class="card-title">Monthly Budget Allocation Trends</span></div>
    <div style="display:flex;gap:12px;align-items:flex-end;padding:8px 0 4px;overflow-x:auto;">
        <?php
        $maxAmt = max(array_column($monthlyAlloc, 'total_allocated')) ?: 1;
        foreach ($monthlyAlloc as $m):
            $barH = max(10, round(($m['total_allocated']/$maxAmt)*160));
        ?>
        <div style="text-align:center;min-width:90px;">
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;"><?= formatCurrency($m['total_allocated']) ?></div>
            <div style="height:<?= $barH ?>px;background:var(--primary);border-radius:6px 6px 0 0;opacity:0.85;transition:opacity .2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=.85"></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;"><?= $m['month'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">
    <!-- Inventory Activity -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Inventory Releases</span>
            <a href="../inventory_manager/usage_monitoring.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Encoder</th><th>Item</th><th>Qty</th><th>Purpose</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($invActivity as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['encoder_name']) ?></td>
                    <td><strong><?= htmlspecialchars($r['item_name']) ?></strong></td>
                    <td><?= $r['quantity_assigned'] ?> <?= htmlspecialchars($r['unit']) ?></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($r['purpose']??'—') ?></td>
                    <td style="font-size:12px;"><?= date('M d', strtotime($r['assigned_date'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($invActivity)): ?><tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:20px">No releases yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Allocation Activity -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Budget Allocations</span>
            <a href="budget_approvals.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Title</th><th>Recipient</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($allocActivity as $a): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($a['allocation_title']??'—') ?></strong></td>
                    <td style="font-size:12px;"><?= $a['is_shared']?'Shared':htmlspecialchars($a['encoder_name']??'—') ?></td>
                    <td><?= formatCurrency($a['allocated_amount']) ?></td>
                    <td><span class="badge badge-<?= $a['admin_approval_status']==='approved'?'approved':($a['admin_approval_status']==='rejected'?'rejected':'pending') ?>"><?= ucfirst($a['admin_approval_status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($allocActivity)): ?><tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:20px">No allocations yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Purchases -->
<div class="card">
    <div class="card-header">
        <span class="card-title">Recent Purchases</span>
        <a href="approvals.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Item</th><th>Amount</th><th>By</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recentPurchases as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['item_name']) ?></td>
                <td><?= formatCurrency($p['total_price']) ?></td>
                <td><?= htmlspecialchars($p['full_name'] ?? '—') ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= $p['purchase_date'] ?></td>
                <td><span class="badge badge-<?= $p['status'] === 'approved' ? 'approved' : ($p['status']==='rejected'?'rejected':'pending') ?>"><?= ucfirst($p['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentPurchases)): ?><tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px">No purchases yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
