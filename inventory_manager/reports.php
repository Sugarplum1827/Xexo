<?php
require_once '../includes/auth.php';
requireRole('inventory_manager', '../index.php');
$pageTitle = 'Inventory Reports';

$items     = $conn->query("SELECT * FROM inventory ORDER BY item_name")->fetch_all(MYSQLI_ASSOC);
$totalVal  = array_sum(array_map(fn($i) => $i['current_stock']*$i['unit_cost'], $items));
$lowCount  = count(array_filter($items, fn($i) => $i['current_stock'] <= $i['minimum_stock']));
$topItems  = $conn->query("SELECT item_name, SUM(quantity) total_qty, SUM(total_price) total_cost, COUNT(*) cnt FROM purchases WHERE status='approved' GROUP BY item_name ORDER BY total_cost DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<div style="display:flex;justify-content:flex-end;margin-bottom:16px;">
    <a href="../includes/export_pdf.php?type=inventory&period=monthly&year=<?= date('Y') ?>" target="_blank" class="btn btn-gold"><i class="fas fa-file-pdf"></i> Export PDF</a>
</div>

<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card"><div class="stat-icon forest"><i class="fas fa-boxes"></i></div><div><div class="stat-value"><?= count($items) ?></div><div class="stat-label">Total Items</div></div></div>
    <div class="stat-card"><div class="stat-icon gold"><i class="fas fa-peso-sign"></i></div><div><div class="stat-value"><?= formatCurrency($totalVal) ?></div><div class="stat-label">Inventory Value</div></div></div>
    <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div><div><div class="stat-value"><?= $lowCount ?></div><div class="stat-label">Low Stock</div></div></div>
</div>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">
    <div class="card">
        <div class="card-header"><span class="card-title">Most Purchased Items</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Item</th><th>Orders</th><th>Total Qty</th><th>Total Cost</th></tr></thead>
                <tbody>
                <?php foreach ($topItems as $r): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['item_name']) ?></strong></td>
                    <td><?= $r['cnt'] ?></td>
                    <td><?= $r['total_qty'] ?></td>
                    <td><?= formatCurrency($r['total_cost']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Stock Status Summary</span></div>
        <?php
        $out = count(array_filter($items, fn($i) => $i['current_stock'] <= 0));
        $low = count(array_filter($items, fn($i) => $i['current_stock'] > 0 && $i['current_stock'] <= $i['minimum_stock']));
        $ok  = count($items) - $out - $low;
        ?>
        <div style="padding:8px 0;">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid var(--cream-dark);">
                <span style="display:flex;align-items:center;gap:8px;"><span style="width:10px;height:10px;border-radius:50%;background:var(--danger);display:inline-block;"></span>Out of Stock</span>
                <strong style="font-size:22px;color:var(--danger)"><?= $out ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid var(--cream-dark);">
                <span style="display:flex;align-items:center;gap:8px;"><span style="width:10px;height:10px;border-radius:50%;background:var(--warning);display:inline-block;"></span>Low Stock</span>
                <strong style="font-size:22px;color:var(--warning)"><?= $low ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;">
                <span style="display:flex;align-items:center;gap:8px;"><span style="width:10px;height:10px;border-radius:50%;background:var(--success);display:inline-block;"></span>Adequate Stock</span>
                <strong style="font-size:22px;color:var(--success)"><?= $ok ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Full Inventory Report</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Item</th><th>Category</th><th>Unit</th><th>Stock</th><th>Min</th><th>Unit Cost</th><th>Total Value</th><th>Expiry</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i => $item): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                <td><?= htmlspecialchars($item['category']??'—') ?></td>
                <td><?= htmlspecialchars($item['unit']) ?></td>
                <td style="font-weight:700;color:<?= $item['current_stock']<=$item['minimum_stock']?'var(--danger)':'var(--success)' ?>"><?= $item['current_stock'] ?></td>
                <td><?= $item['minimum_stock'] ?></td>
                <td><?= formatCurrency($item['unit_cost']) ?></td>
                <td><?= formatCurrency($item['current_stock']*$item['unit_cost']) ?></td>
                <td style="font-size:12px"><?= $item['expiry_date']??'—' ?></td>
                <td><span class="badge badge-<?= $item['current_stock']<=0?'rejected':($item['current_stock']<=$item['minimum_stock']?'pending':'ok') ?>"><?= $item['current_stock']<=0?'Out':($item['current_stock']<=$item['minimum_stock']?'Low':'OK') ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
