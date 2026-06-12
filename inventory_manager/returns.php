<?php
require_once '../includes/auth.php';
requireRole('inventory_manager', '../index.php');
$pageTitle = 'Excess Inventory Returns';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
    $rid  = (int)$_POST['return_id'];
    $rmk  = trim($_POST['remarks'] ?? '');
    $uid  = $_SESSION['user_id'];
    // Check attachment uploaded
    $ret = $conn->query("SELECT * FROM return_requests WHERE id=$rid AND return_type='inventory'")->fetch_assoc();
    if ($ret && $ret['attachment_path']) {
        $stmt = $conn->prepare("UPDATE return_requests SET return_status='returned', verified_by=?, verified_at=NOW(), verification_remarks=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("isi", $uid, $rmk, $rid);
        $stmt->execute(); $stmt->close();
        // Return stock to master inventory
        if ($ret['encoder_inventory_id']) {
            $ei = $conn->query("SELECT * FROM encoder_inventory WHERE id={$ret['encoder_inventory_id']}")->fetch_assoc();
            if ($ei && $ret['return_quantity'] > 0) {
                $conn->query("UPDATE inventory SET current_stock=current_stock+{$ret['return_quantity']} WHERE id={$ei['inventory_id']}");
            }
        }
        logActivity($conn,'VERIFY_INVENTORY_RETURN',"Verified inventory return ID $rid");
        $msg = 'Return verified and stock restored.'; $msgType = 'success';
    } else {
        $msg = 'Cannot verify — encoder has not uploaded an attachment yet.'; $msgType = 'danger';
    }
}

$statusFilter = $_GET['status'] ?? 'all';
$where = "WHERE rr.return_type='inventory'";
if ($statusFilter !== 'all') $where .= " AND rr.return_status='" . $conn->real_escape_string($statusFilter) . "'";

$returns = $conn->query("
    SELECT rr.*, u.full_name AS encoder_name,
           ei.item_name AS inv_item, ei.unit AS inv_unit,
           v.full_name AS verifier_name
    FROM return_requests rr
    JOIN users u ON rr.encoder_id = u.id
    LEFT JOIN encoder_inventory ei ON rr.encoder_inventory_id = ei.id
    LEFT JOIN users v ON rr.verified_by = v.id
    $where
    ORDER BY rr.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;">
    <?php foreach (['all'=>'All','not_yet_returned'=>'Pending Return','returned'=>'Returned'] as $k=>$v): ?>
    <a href="?status=<?= $k ?>" class="btn <?= $statusFilter===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Excess Inventory Return Requests</span>
        <span style="font-size:13px;color:var(--text-muted)"><?= count($returns) ?> record(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Encoder</th><th>Item</th><th>Return Qty</th><th>Purpose</th><th>Due Date</th><th>Status</th><th>Attachment</th><th>Verified By</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($returns as $i => $r): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($r['encoder_name']) ?></td>
                <td><strong><?= htmlspecialchars($r['inv_item']??'—') ?></strong></td>
                <td><?= $r['return_quantity'] !== null ? $r['return_quantity'].' '.($r['inv_unit']??'') : '—' ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($r['original_purpose']??'—') ?></td>
                <td style="font-size:12px;"><?= date('M d, Y H:i', strtotime($r['due_datetime'])) ?></td>
                <td><span class="badge <?= $r['return_status']==='returned'?'badge-approved':'badge-pending' ?>"><?= $r['return_status']==='returned'?'Returned':'Not Yet Returned' ?></span></td>
                <td>
                    <?php if ($r['attachment_path']): ?>
                    <a href="../<?= htmlspecialchars($r['attachment_path']) ?>" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-file"></i> View</a>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--danger)">Not uploaded</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;"><?= $r['verifier_name'] ? htmlspecialchars($r['verifier_name']) : '—' ?></td>
                <td>
                    <?php if ($r['return_status'] !== 'returned'): ?>
                    <button class="btn btn-sm btn-success <?= !$r['attachment_path']?'':''; ?>"
                        <?= !$r['attachment_path']?'disabled title="Attachment required"':'' ?>
                        onclick="openVerify(<?= $r['id'] ?>)">
                        <i class="fas fa-check-circle"></i> Verify
                    </button>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--success)"><i class="fas fa-check-circle"></i> Done</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($returns)): ?><tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:32px">No return records found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Verify Modal -->
<div class="modal-overlay" id="verifyModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Verify Return</span>
            <button class="modal-close" onclick="document.getElementById('verifyModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="verify">
            <input type="hidden" name="return_id" id="verifyRid">
            <div class="form-group">
                <label>Verification Remarks</label>
                <textarea name="remarks" class="form-control" rows="3" placeholder="Optional remarks..."></textarea>
            </div>
            <button type="submit" class="btn btn-success" style="width:100%"><i class="fas fa-check"></i> Mark as Returned</button>
        </form>
    </div>
</div>

<script>
function openVerify(rid) {
    document.getElementById('verifyRid').value = rid;
    document.getElementById('verifyModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }));
</script>
<?php include '../includes/footer.php'; ?>
