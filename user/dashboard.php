<?php
require_once '../includes/auth.php';
requireRole('user', '../index.php');
$pageTitle = 'My Dashboard';
$uid = $_SESSION['user_id'];

// Stats
$myPurchases  = $conn->query("SELECT COUNT(*) c FROM purchases WHERE submitted_by=$uid")->fetch_assoc()['c'];
$pendingMine  = $conn->query("SELECT COUNT(*) c FROM purchases WHERE submitted_by=$uid AND status='pending'")->fetch_assoc()['c'];
$approvedMine = $conn->query("SELECT COUNT(*) c FROM purchases WHERE submitted_by=$uid AND status='approved'")->fetch_assoc()['c'];
// amount_used on allocations includes both approved AND pending (pending is reserved)
// so totalSpent should also include pending to match what the allocation shows as "used"
$totalSpent   = $conn->query("SELECT COALESCE(SUM(total_price),0) s FROM purchases WHERE submitted_by=$uid AND status IN ('approved','pending')")->fetch_assoc()['s'];

// Only show budgets allocated and APPROVED for this encoder (personal or shared)
$myAllocations = $conn->query("
    SELECT ba.*, b.period_label
    FROM budget_allocations ba
    JOIN budgets b ON ba.budget_id = b.id
    WHERE ba.admin_approval_status='approved'
      AND (ba.encoder_id=$uid OR ba.is_shared=1)
    ORDER BY ba.allocation_date DESC
")->fetch_all(MYSQLI_ASSOC);

$totalMyBudget  = array_sum(array_column($myAllocations, 'allocated_amount'));
$totalMyUsed    = array_sum(array_column($myAllocations, 'amount_used'));
$totalMyRemain  = $totalMyBudget - $totalMyUsed;

// Pending requests
$pendingBudgetReqs = $conn->query("SELECT COUNT(*) c FROM budget_requests WHERE encoder_id=$uid AND status='pending'")->fetch_assoc()['c'];
$pendingInvReqs    = $conn->query("SELECT COUNT(*) c FROM inventory_requests WHERE encoder_id=$uid AND status='pending'")->fetch_assoc()['c'];
$pendingReturns    = $conn->query("SELECT COUNT(*) c FROM return_requests WHERE encoder_id=$uid AND return_status='not_yet_returned'")->fetch_assoc()['c'];

// Recent purchases
$recentMine = $conn->query("SELECT * FROM purchases WHERE submitted_by=$uid ORDER BY created_at DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon forest"><i class="fas fa-shopping-cart"></i></div><div><div class="stat-value"><?= $myPurchases ?></div><div class="stat-label">My Submissions</div></div></div>
    <div class="stat-card"><div class="stat-icon gold"><i class="fas fa-clock"></i></div><div><div class="stat-value"><?= $pendingMine ?></div><div class="stat-label">Pending Review</div></div></div>
    <div class="stat-card"><div class="stat-icon green"><i class="fas fa-wallet"></i></div><div><div class="stat-value"><?= formatCurrency($totalMyRemain) ?></div><div class="stat-label">My Budget Remaining</div></div></div>
    <div class="stat-card"><div class="stat-icon red"><i class="fas fa-peso-sign"></i></div><div><div class="stat-value"><?= formatCurrency($totalSpent) ?></div><div class="stat-label">My Total Spend</div></div></div>
</div>

<?php if ($pendingReturns > 0): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>Pending Returns:</strong> You have <?= $pendingReturns ?> return request(s) awaiting action.
    <a href="returns.php" style="color:var(--warning);font-weight:700;margin-left:8px;">View Returns →</a>
</div>
<?php endif; ?>

<!-- My Allocated Budgets (only approved) -->
<?php if (!empty($myAllocations)): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <span class="card-title">My Allocated Budgets</span>
        <a href="my_budget.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <?php foreach ($myAllocations as $a):
        $rem = $a['allocated_amount'] - $a['amount_used'];
        $pct = $a['allocated_amount'] > 0 ? min(100, ($a['amount_used']/$a['allocated_amount'])*100) : 0;
    ?>
    <div style="margin-bottom:14px;padding:14px;border:1px solid var(--grey-200);border-radius:10px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
            <span style="font-weight:600;"><?= htmlspecialchars($a['allocation_title']??'Allocation') ?></span>
            <?php if ($a['is_shared']): ?><span class="badge badge-pending" style="font-size:10px;">Shared</span><?php endif; ?>
        </div>
        <div class="progress-bar" style="margin-bottom:6px;">
            <div class="progress-fill <?= $pct>=90?'red':($pct>=75?'orange':'green') ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <div style="display:flex;gap:24px;font-size:13px;">
            <div><span style="color:var(--text-muted)">Allocated:</span> <strong><?= formatCurrency($a['allocated_amount']) ?></strong></div>
            <div><span style="color:var(--text-muted)">Used:</span> <strong><?= formatCurrency($a['amount_used']) ?></strong></div>
            <div><span style="color:var(--text-muted)">Remaining:</span> <strong style="color:<?= $rem>=0?'var(--success)':'var(--danger)' ?>"><?= formatCurrency($rem) ?></strong></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><span class="card-title">My Allocated Budgets</span></div>
    <div style="text-align:center;padding:32px;color:var(--text-muted);">
        <i class="fas fa-wallet" style="font-size:32px;margin-bottom:8px;display:block;"></i>
        No approved budget allocations yet.
        <a href="budget_requests.php" style="display:block;margin-top:8px;color:var(--primary);font-weight:600;">Request a Budget →</a>
    </div>
</div>
<?php endif; ?>

<!-- Quick links for pending items -->
<?php if ($pendingBudgetReqs > 0 || $pendingInvReqs > 0): ?>
<div class="grid-2" style="gap:16px;margin-bottom:24px;">
    <?php if ($pendingBudgetReqs > 0): ?>
    <div class="stat-card" style="cursor:pointer;" onclick="location.href='budget_requests.php?status=pending'">
        <div class="stat-icon gold"><i class="fas fa-hand-holding-usd"></i></div>
        <div><div class="stat-value"><?= $pendingBudgetReqs ?></div><div class="stat-label">Budget Requests Pending</div></div>
    </div>
    <?php endif; ?>
    <?php if ($pendingInvReqs > 0): ?>
    <div class="stat-card" style="cursor:pointer;" onclick="location.href='inventory_requests.php?status=pending'">
        <div class="stat-icon blue"><i class="fas fa-boxes"></i></div>
        <div><div class="stat-value"><?= $pendingInvReqs ?></div><div class="stat-label">Inventory Requests Pending</div></div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">My Recent Purchases</span>
        <a href="submit_purchase.php" class="btn btn-gold"><i class="fas fa-plus"></i> New Purchase</a>
    </div>
    <?php if (empty($recentMine)): ?>
    <div style="text-align:center;padding:40px;">
        <div style="font-size:48px;margin-bottom:12px;">🧾</div>
        <p style="color:var(--text-muted);font-size:14px;">No purchases yet. <a href="submit_purchase.php" style="color:var(--primary);font-weight:600;">Submit your first one!</a></p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Item</th><th>Qty</th><th>Total</th><th>Supplier</th><th>Date</th><th>Status</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ($recentMine as $p): ?>
            <tr>
                <td><strong><?= htmlspecialchars($p['item_name']) ?></strong></td>
                <td><?= $p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?></td>
                <td><?= formatCurrency($p['total_price']) ?></td>
                <td><?= htmlspecialchars($p['supplier']??'—') ?></td>
                <td><?= $p['purchase_date'] ?></td>
                <td><span class="badge badge-<?= $p['status']==='approved'?'approved':($p['status']==='rejected'?'rejected':'pending') ?>"><?= ucfirst(str_replace('_',' ',$p['status'])) ?></span></td>
                <td style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($p['review_notes']??'') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
