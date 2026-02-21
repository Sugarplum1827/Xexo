<?php
require_once '../includes/auth.php';
requireRole('user', '../index.php');
$pageTitle = 'My Dashboard';
$uid = $_SESSION['user_id'];

$myPurchases  = $conn->query("SELECT COUNT(*) c FROM purchases WHERE submitted_by=$uid")->fetch_assoc()['c'];
$pendingMine  = $conn->query("SELECT COUNT(*) c FROM purchases WHERE submitted_by=$uid AND status='pending'")->fetch_assoc()['c'];
$approvedMine = $conn->query("SELECT COUNT(*) c FROM purchases WHERE submitted_by=$uid AND status='approved'")->fetch_assoc()['c'];
$totalSpent   = $conn->query("SELECT COALESCE(SUM(total_price),0) s FROM purchases WHERE submitted_by=$uid AND status='approved'")->fetch_assoc()['s'];
$recentMine   = $conn->query("SELECT * FROM purchases WHERE submitted_by=$uid ORDER BY created_at DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);
$activeBudget = $conn->query("SELECT * FROM budgets WHERE is_active=1 LIMIT 1")->fetch_assoc();
$totalAllExp  = $conn->query("SELECT COALESCE(SUM(total_price),0) s FROM purchases WHERE status='approved'")->fetch_assoc()['s'];

include '../includes/header.php';
?>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon forest"><i class="fas fa-shopping-cart"></i></div><div><div class="stat-value"><?= $myPurchases ?></div><div class="stat-label">My Submissions</div></div></div>
    <div class="stat-card"><div class="stat-icon gold"><i class="fas fa-clock"></i></div><div><div class="stat-value"><?= $pendingMine ?></div><div class="stat-label">Pending Review</div></div></div>
    <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div><div class="stat-value"><?= $approvedMine ?></div><div class="stat-label">Approved</div></div></div>
    <div class="stat-card"><div class="stat-icon red"><i class="fas fa-peso-sign"></i></div><div><div class="stat-value"><?= formatCurrency($totalSpent) ?></div><div class="stat-label">My Total Spend</div></div></div>
</div>

<?php if ($activeBudget): 
    $remaining = $activeBudget['allocated_amount'] - $totalAllExp;
    $pct = $activeBudget['allocated_amount'] > 0 ? min(100, ($totalAllExp/$activeBudget['allocated_amount'])*100) : 0;
?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <span class="card-title">Budget Overview – <?= htmlspecialchars($activeBudget['period_label']) ?></span>
        <?php if ($remaining < 0): ?><span class="badge badge-rejected">Over Budget</span>
        <?php elseif ($pct >= 75): ?><span class="badge badge-pending">Low Budget</span>
        <?php else: ?><span class="badge badge-approved">On Track</span><?php endif; ?>
    </div>
    <div class="progress-bar" style="margin-bottom:10px;">
        <div class="progress-fill <?= $pct>=90?'red':($pct>=75?'orange':'green') ?>" style="width:<?= $pct ?>%"></div>
    </div>
    <div style="display:flex;gap:24px;font-size:14px;">
        <div><span style="color:var(--text-muted)">Total Budget:</span> <strong><?= formatCurrency($activeBudget['allocated_amount']) ?></strong></div>
        <div><span style="color:var(--text-muted)">Remaining:</span> <strong style="color:<?= $remaining>=0?'var(--success)':'var(--danger)' ?>"><?= formatCurrency($remaining) ?></strong></div>
    </div>
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
        <p style="color:var(--text-muted);font-size:14px;">No purchases yet. <a href="submit_purchase.php" style="color:var(--forest-light);font-weight:600;">Submit your first one!</a></p>
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
