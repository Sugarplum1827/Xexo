<?php
require_once '../includes/auth.php';
requireRole('user', '../index.php');
$pageTitle = 'Inventory View';

$search = $conn->real_escape_string(trim($_GET['q'] ?? ''));
$where  = $search ? "WHERE item_name LIKE '%$search%' OR category LIKE '%$search%'" : '';
$items  = $conn->query("SELECT * FROM inventory $where ORDER BY item_name")->fetch_all(MYSQLI_ASSOC);
include '../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <span class="card-title">Current Inventory Stock</span>
        <form method="GET" style="display:flex;gap:8px;">
            <input type="text" name="q" class="form-control" style="width:220px;padding:8px 12px;" placeholder="Search items…" value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">View-only. Please check stock levels before submitting a purchase request to avoid duplicates.</p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Item Name</th><th>Category</th><th>Current Stock</th><th>Unit</th><th>Min Stock</th><th>Status</th><th>Last Updated</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i => $item): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                <td><?= htmlspecialchars($item['category']??'—') ?></td>
                <td style="font-weight:700;font-size:16px;color:<?= $item['current_stock']<=$item['minimum_stock']?'var(--danger)':'var(--success)' ?>"><?= $item['current_stock'] ?></td>
                <td><?= htmlspecialchars($item['unit']) ?></td>
                <td><?= $item['minimum_stock'] ?></td>
                <td><span class="badge badge-<?= $item['current_stock']<=0?'rejected':($item['current_stock']<=$item['minimum_stock']?'pending':'ok') ?>"><?= $item['current_stock']<=0?'Out of Stock':($item['current_stock']<=$item['minimum_stock']?'Low Stock':'In Stock') ?></span></td>
                <td style="font-size:12px;color:var(--text-muted)"><?= date('M d, Y', strtotime($item['last_updated'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px">No inventory items found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
