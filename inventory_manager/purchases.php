<?php
require_once '../includes/auth.php';
requireRole('inventory_manager', '../index.php');
$pageTitle = 'Purchase Records';

$filter = $_GET['filter'] ?? 'all';
$where  = $filter !== 'all' ? "WHERE p.status='$filter'" : '';
$purchases = $conn->query("SELECT p.*, u.full_name FROM purchases p LEFT JOIN users u ON p.submitted_by=u.id $where ORDER BY p.created_at DESC")->fetch_all(MYSQLI_ASSOC);
include '../includes/header.php';
?>
<div style="display:flex;gap:10px;margin-bottom:20px;">
    <?php foreach (['all'=>'All','pending'=>'⏳ Pending','approved'=>'✅ Approved','rejected'=>'❌ Rejected'] as $k=>$v): ?>
    <a href="?filter=<?= $k ?>" class="btn <?= $filter===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Purchase Records</span>
        <span style="font-size:13px;color:var(--text-muted)"><?= count($purchases) ?> record(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Supplier</th><th>Date</th><th>Submitted By</th><th>Status</th><th>Receipt</th></tr></thead>
            <tbody>
            <?php foreach ($purchases as $i => $p): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($p['item_name']) ?></strong></td>
                <td><?= $p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?></td>
                <td><?= formatCurrency($p['unit_price']) ?></td>
                <td><strong><?= formatCurrency($p['total_price']) ?></strong></td>
                <td><?= htmlspecialchars($p['supplier']??'—') ?></td>
                <td><?= $p['purchase_date'] ?></td>
                <td><?= htmlspecialchars($p['full_name']??'—') ?></td>
                <td><span class="badge badge-<?= $p['status']==='approved'?'approved':($p['status']==='rejected'?'rejected':'pending') ?>"><?= ucfirst(str_replace('_',' ',$p['status'])) ?></span></td>
                <td><?= $p['receipt_path'] ? '<a href="../'.$p['receipt_path'].'" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-file"></i></a>' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($purchases)): ?><tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:32px">No purchases found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
