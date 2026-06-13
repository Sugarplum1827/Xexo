<?php
require_once '../includes/auth.php';
requireRole('budget_manager', '../index.php');
$pageTitle = 'Excess Budget Returns';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
    $rid  = (int)$_POST['return_id'];
    $rmk  = trim($_POST['remarks'] ?? '');
    $uid  = $_SESSION['user_id'];
    $ret  = $conn->query("SELECT * FROM return_requests WHERE id=$rid AND return_type='budget'")->fetch_assoc();
    if ($ret && $ret['attachment_path']) {
        $stmt = $conn->prepare("UPDATE return_requests SET return_status='returned', verified_by=?, verified_at=NOW(), verification_remarks=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("isi", $uid, $rmk, $rid);
        $stmt->execute(); $stmt->close();
        // Restore to allocation
        if ($ret['budget_allocation_id'] && $ret['return_amount'] > 0) {
            $amt = $ret['return_amount'];
            $conn->query("UPDATE budget_allocations SET amount_used=GREATEST(0, amount_used-$amt) WHERE id={$ret['budget_allocation_id']}");
        }
        logActivity($conn,'VERIFY_BUDGET_RETURN',"Verified budget return ID $rid");
        $msg = 'Return verified and budget restored.'; $msgType = 'success';
    } else {
        $msg = 'Cannot verify — encoder has not uploaded an attachment yet.'; $msgType = 'danger';
    }
}

$statusFilter = $_GET['status'] ?? 'all';
$where = "WHERE rr.return_type='budget'";
if ($statusFilter !== 'all') $where .= " AND rr.return_status='" . $conn->real_escape_string($statusFilter) . "'";

$returns = $conn->query("
    SELECT rr.*, u.full_name AS encoder_name,
           ba.allocation_title,
           v.full_name AS verifier_name
    FROM return_requests rr
    JOIN users u ON rr.encoder_id = u.id
    LEFT JOIN budget_allocations ba ON rr.budget_allocation_id = ba.id
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
        <span class="card-title">Excess Budget Return Requests</span>
        <span style="font-size:13px;color:var(--text-muted)"><?= count($returns) ?> record(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Encoder</th><th>Allocation</th><th>Return Amount</th><th>Purpose</th><th>Due Date</th><th>Status</th><th>Attachment</th><th>Verified By</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($returns as $i => $r): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($r['encoder_name']) ?></td>
                <td><?= htmlspecialchars($r['allocation_title']??'—') ?></td>
                <td><strong><?= formatCurrency($r['return_amount']??0) ?></strong></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($r['original_purpose']??'—') ?></td>
                <td style="font-size:12px;"><?= date('M d, Y H:i', strtotime($r['due_datetime'])) ?>
                    <?php if ($r['return_status'] !== 'returned' && strtotime($r['due_datetime']) < time()): ?>
                    <span class="badge badge-rejected" style="font-size:10px;margin-left:4px;">OVERDUE</span>
                    <?php endif; ?></td>
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
                    <button class="btn btn-sm btn-success"
                        <?= !$r['attachment_path'] ? 'disabled title="Attachment required"' : '' ?>
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
            <span class="modal-title">Verify Budget Return</span>
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
