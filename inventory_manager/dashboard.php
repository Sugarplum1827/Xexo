<?php
require_once '../includes/auth.php';
requireRole('inventory_manager', '../index.php');
$pageTitle = 'Inventory Dashboard';

$totalItems   = $conn->query("SELECT COUNT(*) c FROM inventory")->fetch_assoc()['c'];
$lowStock     = $conn->query("SELECT COUNT(*) c FROM inventory WHERE current_stock <= minimum_stock")->fetch_assoc()['c'];
$totalValue   = $conn->query("SELECT COALESCE(SUM(current_stock * unit_cost),0) s FROM inventory")->fetch_assoc()['s'];
$pendingCount = $conn->query("SELECT COUNT(*) c FROM purchases WHERE status='pending'")->fetch_assoc()['c'];
$lowItems     = $conn->query("SELECT * FROM inventory WHERE current_stock <= minimum_stock ORDER BY current_stock ASC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$recentPurch  = $conn->query("SELECT p.*, u.full_name FROM purchases p LEFT JOIN users u ON p.submitted_by=u.id ORDER BY p.created_at DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<?php if ($lowStock > 0): ?>
<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> <strong><?= $lowStock ?> item(s)</strong> are at or below minimum stock levels. Please review inventory.</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon forest"><i class="fas fa-boxes"></i></div><div><div class="stat-value"><?= $totalItems ?></div><div class="stat-label">Total Items</div></div></div>
    <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div><div><div class="stat-value"><?= $lowStock ?></div><div class="stat-label">Low Stock Items</div></div></div>
    <div class="stat-card"><div class="stat-icon gold"><i class="fas fa-peso-sign"></i></div><div><div class="stat-value"><?= formatCurrency($totalValue) ?></div><div class="stat-label">Inventory Value</div></div></div>
    <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-clock"></i></div><div><div class="stat-value"><?= $pendingCount ?></div><div class="stat-label">Pending Purchases</div></div></div>
</div>

<div class="grid-2" style="gap:24px;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Low Stock Alerts</span>
            <a href="inventory.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <?php if (empty($lowItems)): ?><p style="text-align:center;color:var(--success);padding:20px;font-size:14px"><i class="fas fa-check-circle"></i> All items adequately stocked!</p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Item</th><th>Unit</th><th>Current</th><th>Min</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($lowItems as $item): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                    <td><?= htmlspecialchars($item['unit']) ?></td>
                    <td style="color:var(--danger);font-weight:700"><?= $item['current_stock'] ?></td>
                    <td><?= $item['minimum_stock'] ?></td>
                    <td><span class="badge badge-<?= $item['current_stock']<=0?'rejected':'pending' ?>"><?= $item['current_stock']<=0?'Out of Stock':'Low' ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Recent Purchases</span>
            <a href="purchases.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Item</th><th>Qty</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($recentPurch as $p): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['item_name']) ?></strong></td>
                    <td><?= $p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?></td>
                    <td><span class="badge badge-<?= $p['status']==='approved'?'approved':($p['status']==='rejected'?'rejected':'pending') ?>"><?= ucfirst($p['status']) ?></span></td>
                    <td style="font-size:12px;color:var(--text-muted)"><?= $p['purchase_date'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
