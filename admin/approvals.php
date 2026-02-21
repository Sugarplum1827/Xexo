<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');
$pageTitle = 'Purchase Approvals';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid    = (int)$_POST['purchase_id'];
    $action = $_POST['action'];
    $notes  = trim($_POST['notes'] ?? '');
    $uid    = $_SESSION['user_id'];

    if (in_array($action, ['approved','rejected','correction_needed'])) {
        $stmt = $conn->prepare("UPDATE purchases SET status=?, reviewed_by=?, review_notes=?, reviewed_at=NOW() WHERE id=?");
        $stmt->bind_param("sisi", $action, $uid, $notes, $pid);
        $stmt->execute(); $stmt->close();

        if ($action === 'approved') {
            // Add to expense log
            $p = $conn->query("SELECT * FROM purchases WHERE id=$pid")->fetch_assoc();
            $conn->query("INSERT INTO expense_log (purchase_id, amount, logged_date, created_at) VALUES ($pid, {$p['total_price']}, '{$p['purchase_date']}', NOW())");
            // Update inventory
            $iname = $conn->real_escape_string($p['item_name']);
            $qty   = $p['quantity'];
            $exists = $conn->query("SELECT id FROM inventory WHERE item_name='$iname' LIMIT 1")->fetch_assoc();
            if ($exists) {
                $conn->query("UPDATE inventory SET current_stock=current_stock+$qty, last_updated=NOW() WHERE id={$exists['id']}");
            } else {
                $unit  = $conn->real_escape_string($p['unit']);
                $price = $p['unit_price'];
                $conn->query("INSERT INTO inventory (item_name, unit, current_stock, unit_cost) VALUES ('$iname','$unit',$qty,$price)");
            }
        }
        logActivity($conn, strtoupper($action).'_PURCHASE', "$action purchase ID $pid. Notes: $notes");
        $msg = "Purchase $action successfully."; $msgType='success';
    }
}

$filter = $_GET['filter'] ?? 'pending';
$allowed = ['pending','approved','rejected','correction_needed'];
if (!in_array($filter, $allowed)) $filter = 'pending';

$purchases = $conn->query("SELECT p.*, u.full_name AS submitter, r.full_name AS reviewer FROM purchases p LEFT JOIN users u ON p.submitted_by=u.id LEFT JOIN users r ON p.reviewed_by=r.id WHERE p.status='$filter' ORDER BY p.created_at DESC")->fetch_all(MYSQLI_ASSOC);
include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-check-circle"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;">
    <?php foreach (['pending'=>'⏳ Pending','approved'=>'✅ Approved','rejected'=>'❌ Rejected','correction_needed'=>'🔄 Correction'] as $k => $v): ?>
    <a href="?filter=<?= $k ?>" class="btn <?= $filter===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= ucwords(str_replace('_',' ',$filter)) ?> Purchases</span>
        <span style="font-size:13px;color:var(--text-muted)"><?= count($purchases) ?> record(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Supplier</th><th>Date</th><th>Submitted By</th><?= $filter!=='pending'?'<th>Reviewed By</th>':'' ?><th>Actions</th></tr></thead>
            <tbody>
            <?php if (empty($purchases)): ?>
            <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:32px;">No <?= $filter ?> purchases found.</td></tr>
            <?php endif; ?>
            <?php foreach ($purchases as $i => $p): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($p['item_name']) ?></strong></td>
                <td><?= $p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?></td>
                <td><?= formatCurrency($p['unit_price']) ?></td>
                <td><strong><?= formatCurrency($p['total_price']) ?></strong></td>
                <td><?= htmlspecialchars($p['supplier'] ?? '—') ?></td>
                <td><?= $p['purchase_date'] ?></td>
                <td><?= htmlspecialchars($p['submitter'] ?? '—') ?></td>
                <?php if ($filter !== 'pending'): ?><td><?= htmlspecialchars($p['reviewer'] ?? '—') ?></td><?php endif; ?>
                <td>
                    <?php if ($p['receipt_path']): ?>
                    <a href="../<?= htmlspecialchars($p['receipt_path']) ?>" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-file-image"></i></a>
                    <?php endif; ?>
                    <?php if ($filter === 'pending'): ?>
                    <button class="btn btn-sm btn-success" onclick="openReview(<?= $p['id'] ?>, 'approved')"><i class="fas fa-check"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="openReview(<?= $p['id'] ?>, 'rejected')"><i class="fas fa-times"></i></button>
                    <button class="btn btn-sm btn-primary" onclick="openReview(<?= $p['id'] ?>, 'correction_needed')"><i class="fas fa-redo"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Review Modal -->
<div class="modal-overlay" id="reviewModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Review Purchase</span>
            <button class="modal-close" onclick="document.getElementById('reviewModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="purchase_id" id="reviewPid">
            <input type="hidden" name="action" id="reviewAction">
            <p id="reviewMsg" style="margin-bottom:16px;font-size:14px;color:var(--text-muted);"></p>
            <div class="form-group">
                <label>Notes / Feedback (optional)</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Add notes for the encoder..."></textarea>
            </div>
            <button type="submit" class="btn btn-gold" style="width:100%"><i class="fas fa-check"></i> Confirm Decision</button>
        </form>
    </div>
</div>

<script>
function openReview(pid, action) {
    document.getElementById('reviewPid').value = pid;
    document.getElementById('reviewAction').value = action;
    document.getElementById('reviewMsg').textContent = 'You are about to mark this purchase as: ' + action.replace('_',' ').toUpperCase();
    document.getElementById('reviewModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }));
</script>
<?php include '../includes/footer.php'; ?>
