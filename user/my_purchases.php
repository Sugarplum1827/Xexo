<?php
require_once '../includes/auth.php';
requireRole('user', '../index.php');
$pageTitle = 'My Purchases';
$uid = $_SESSION['user_id'];

$filter = $_GET['filter'] ?? 'all';
$where  = $filter !== 'all' ? "AND status='$filter'" : '';
$purchases = $conn->query("SELECT * FROM purchases WHERE submitted_by=$uid $where ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php foreach (['all'=>'All','pending'=>'⏳ Pending','approved'=>'✅ Approved','rejected'=>'❌ Rejected','correction_needed'=>'🔄 Correction'] as $k=>$v): ?>
        <a href="?filter=<?= $k ?>" class="btn <?= $filter===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>
    <a href="submit_purchase.php" class="btn btn-gold"><i class="fas fa-plus"></i> New Submission</a>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">My Purchase History</span>
        <span style="font-size:13px;color:var(--text-muted)"><?= count($purchases) ?> record(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Supplier</th><th>Date</th><th>Status</th><th>Review Notes</th><th>Receipt</th><th>Action</th></tr></thead>
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
                <td><span class="badge badge-<?= $p['status']==='approved'?'approved':($p['status']==='rejected'?'rejected':'pending') ?>"><?= ucfirst(str_replace('_',' ',$p['status'])) ?></span></td>
                <td style="font-size:12px;color:var(--text-muted);max-width:180px;"><?= htmlspecialchars($p['review_notes']??'') ?></td>
                <td><?= $p['receipt_path'] ? '<a href="../'.$p['receipt_path'].'" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-file"></i></a>' : '—' ?></td>
                <td>
                    <?php if ($p['status'] === 'pending'): ?>
                    <a href="submit_purchase.php?repeat=<?= $p['id'] ?>" class="btn btn-sm btn-outline" title="Reorder"><i class="fas fa-redo"></i></a>
                    <?php elseif ($p['status'] === 'correction_needed'): ?>
                    <a href="submit_purchase.php" class="btn btn-sm btn-gold" title="Resubmit"><i class="fas fa-edit"></i> Fix</a>
                    <?php else: ?>
                    <a href="submit_purchase.php?repeat=<?= $p['id'] ?>" class="btn btn-sm btn-outline" title="Repeat order"><i class="fas fa-copy"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($purchases)): ?><tr><td colspan="11" style="text-align:center;color:var(--text-muted);padding:40px">No purchases found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
