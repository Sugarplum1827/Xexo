<?php
require_once '../includes/auth.php';
requireRole('user', '../index.php');
$pageTitle = 'My Budget';
$uid = $_SESSION['user_id'];

// My approved allocations (personal + shared) — read-only view
$allocations = $conn->query("
    SELECT ba.*, b.period_label, u.full_name AS created_by_name
    FROM budget_allocations ba
    JOIN budgets b ON ba.budget_id = b.id
    JOIN users u ON ba.created_by = u.id
    WHERE ba.admin_approval_status = 'approved'
      AND (
            (ba.is_shared = 0 AND ba.encoder_id = $uid)
         OR (ba.is_shared = 1)
      )
    ORDER BY ba.is_shared ASC, ba.allocation_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Purchase history that charged these allocations
$purchases = $conn->query("
    SELECT p.*, ba.allocation_title
    FROM purchases p
    LEFT JOIN budget_allocations ba ON p.allocation_id = ba.id
    WHERE p.submitted_by = $uid
    ORDER BY p.created_at DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<!-- My Allocated Budgets -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <span class="card-title">My Allocated Budgets</span>
        <a href="budget_requests.php" class="btn btn-gold btn-sm">
            <i class="fas fa-hand-holding-usd"></i> Request Budget
        </a>
    </div>

    <?php if (empty($allocations)): ?>
    <div style="text-align:center;padding:48px;color:var(--text-muted);">
        <i class="fas fa-wallet" style="font-size:36px;margin-bottom:12px;display:block;"></i>
        <strong>No budgets allocated yet.</strong>
        <p style="font-size:13px;margin-top:6px;">
            Request a budget from the Budget Manager to get started.
        </p>
        <a href="budget_requests.php" class="btn btn-gold" style="margin-top:12px;">
            <i class="fas fa-plus"></i> Request Budget
        </a>
    </div>

    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Budget Period</th>
                    <th>Type</th>
                    <th>Allocated</th>
                    <th>Used</th>
                    <th>Remaining</th>
                    <th>Utilization</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($allocations as $i => $a):
                $remaining = $a['allocated_amount'] - $a['amount_used'];
                $pct = $a['allocated_amount'] > 0
                    ? min(100, ($a['amount_used'] / $a['allocated_amount']) * 100)
                    : 0;
            ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td>
                    <strong><?= htmlspecialchars($a['allocation_title'] ?? '—') ?></strong>
                    <?php if ($a['is_shared']): ?>
                    <span class="badge badge-pending" style="font-size:10px;margin-left:4px;">Shared</span>
                    <?php endif; ?>
                    <?php if ($a['purpose']): ?>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                        <?= htmlspecialchars($a['purpose']) ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;"><?= htmlspecialchars($a['period_label']) ?></td>
                <td>
                    <span class="badge <?= $a['is_shared'] ? 'badge-pending' : 'badge-approved' ?>">
                        <?= $a['is_shared'] ? 'Shared' : 'Personal' ?>
                    </span>
                </td>
                <td><strong><?= formatCurrency($a['allocated_amount']) ?></strong></td>
                <td style="color:var(--danger);font-weight:600;"><?= formatCurrency($a['amount_used']) ?></td>
                <td style="font-weight:700;color:<?= $remaining > 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                    <?= formatCurrency($remaining) ?>
                </td>
                <td style="min-width:120px;">
                    <div class="progress-bar" style="height:7px;margin-bottom:3px;">
                        <div class="progress-fill <?= $pct >= 90 ? 'red' : ($pct >= 70 ? 'orange' : 'green') ?>"
                             style="width:<?= $pct ?>%"></div>
                    </div>
                    <div style="font-size:11px;color:var(--text-muted);"><?= number_format($pct, 1) ?>% used</div>
                </td>
                <td style="font-size:12px;color:var(--text-muted);"><?= $a['allocation_date'] ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Budget Consumption Log (from purchases) -->
<div class="card">
    <div class="card-header">
        <span class="card-title">My Budget Consumption Log</span>
        <a href="submit_purchase.php" class="btn btn-gold btn-sm">
            <i class="fas fa-plus"></i> New Purchase
        </a>
    </div>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
        Budget is consumed through purchases submitted via the
        <a href="submit_purchase.php" style="color:var(--primary);font-weight:600;">Submit Purchase</a> page.
    </p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Allocation Used</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total Spent</th>
                    <th>Supplier</th>
                    <th>Purchase Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($purchases as $i => $p): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($p['item_name']) ?></strong></td>
                <td style="font-size:12px;color:var(--text-muted);">
                    <?= htmlspecialchars($p['allocation_title'] ?? '—') ?>
                </td>
                <td><?= $p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?></td>
                <td><?= formatCurrency($p['unit_price']) ?></td>
                <td><strong><?= formatCurrency($p['total_price']) ?></strong></td>
                <td style="font-size:12px;"><?= htmlspecialchars($p['supplier'] ?? '—') ?></td>
                <td style="font-size:12px;"><?= $p['purchase_date'] ?></td>
                <td>
                    <span class="badge badge-<?= $p['status'] === 'approved' ? 'approved' : ($p['status'] === 'rejected' ? 'rejected' : 'pending') ?>">
                        <?= ucfirst(str_replace('_', ' ', $p['status'])) ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($purchases)): ?>
            <tr>
                <td colspan="9" style="text-align:center;color:var(--text-muted);padding:32px;">
                    No purchases yet.
                    <a href="submit_purchase.php" style="color:var(--primary);font-weight:600;margin-left:6px;">
                        Submit your first purchase →
                    </a>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
