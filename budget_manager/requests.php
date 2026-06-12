<?php
require_once '../includes/auth.php';
requireRole('budget_manager', '../index.php');
$pageTitle = 'Budget Requests';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rid    = (int)$_POST['request_id'];
    $rmk    = trim($_POST['remarks'] ?? '');
    $uid    = $_SESSION['user_id'];

    if ($action === 'approve') {
        $alloc_amount = (float)$_POST['allocated_amount'];
        $budget_id    = (int)$_POST['budget_id'];
        $req = $conn->query("SELECT * FROM budget_requests WHERE id=$rid")->fetch_assoc();
        if ($req) {
            // Approve the request
            $stmt = $conn->prepare("UPDATE budget_requests SET status='approved', reviewed_by=?, review_remarks=?, reviewed_at=NOW() WHERE id=?");
            $stmt->bind_param("isi", $uid, $rmk, $rid);
            $stmt->execute(); $stmt->close();
            // Create allocation — pending admin approval
            $enc_id = $req['encoder_id'];
            $title  = $conn->real_escape_string($req['request_title']);
            $purpose= $conn->real_escape_string($req['description']??'');
            $date   = date('Y-m-d');
            $conn->query("INSERT INTO budget_allocations (budget_id, budget_request_id, encoder_id, is_shared, allocation_title, purpose, allocated_amount, allocation_date, admin_approval_status, created_by) VALUES ($budget_id,$rid,$enc_id,0,'$title','$purpose',$alloc_amount,'$date','pending',$uid)");
            logActivity($conn,'APPROVE_BUDGET_REQUEST',"Approved budget request ID $rid, created allocation pending admin approval");
            $msg = 'Request approved. Allocation submitted to Admin for approval.'; $msgType = 'success';
        }
    }

    if ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE budget_requests SET status='rejected', reviewed_by=?, review_remarks=?, reviewed_at=NOW() WHERE id=?");
        $stmt->bind_param("isi", $uid, $rmk, $rid);
        $stmt->execute(); $stmt->close();
        logActivity($conn,'REJECT_BUDGET_REQUEST',"Rejected budget request ID $rid");
        $msg = 'Request rejected.'; $msgType = 'success';
    }
}

$statusFilter = $_GET['status'] ?? 'pending';
$where = "WHERE 1=1";
if ($statusFilter !== 'all') $where .= " AND br.status='" . $conn->real_escape_string($statusFilter) . "'";

$requests = $conn->query("
    SELECT br.*, u.full_name AS encoder_name
    FROM budget_requests br
    JOIN users u ON br.encoder_id = u.id
    $where
    ORDER BY br.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$activeBudgets = $conn->query("SELECT * FROM budgets WHERE is_active=1 AND approval_status='approved' ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <?php foreach (['pending'=>'⏳ Pending','approved'=>'✅ Approved','rejected'=>'❌ Rejected','all'=>'All'] as $k=>$v): ?>
    <a href="?status=<?= $k ?>" class="btn <?= $statusFilter===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Budget Requests from Encoders</span>
        <span style="font-size:13px;color:var(--text-muted)"><?= count($requests) ?> record(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Encoder</th><th>Title</th><th>Description</th><th>Requested Amount</th><th>End Date</th><th>Submitted</th><th>Status</th><th>Remarks</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $i => $r): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($r['encoder_name']) ?></td>
                <td><strong><?= htmlspecialchars($r['request_title']) ?></strong></td>
                <td style="font-size:12px;color:var(--text-muted);max-width:160px;"><?= htmlspecialchars($r['description']??'—') ?></td>
                <td><strong><?= formatCurrency($r['requested_amount']) ?></strong></td>
                <td style="font-size:12px;"><?= date('M d, Y H:i', strtotime($r['end_datetime'])) ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                <td><span class="badge badge-<?= $r['status']==='approved'?'approved':($r['status']==='rejected'?'rejected':'pending') ?>"><?= ucfirst($r['status']) ?></span></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($r['review_remarks']??'—') ?></td>
                <td>
                    <?php if ($r['status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-success" onclick='openApprove(<?= json_encode($r) ?>)'><i class="fas fa-check"></i> Approve</button>
                    <button class="btn btn-sm btn-danger" onclick="openReject(<?= $r['id'] ?>)"><i class="fas fa-times"></i> Reject</button>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--text-muted);">Processed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($requests)): ?><tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:32px">No requests found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal-overlay" id="approveModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Approve & Allocate Budget</span>
            <button class="modal-close" onclick="document.getElementById('approveModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="request_id" id="approveRid">
            <p id="approveInfo" style="font-size:13px;padding:10px;background:var(--grey-100);border-radius:8px;margin-bottom:14px;"></p>
            <div class="form-group">
                <label>Budget Period *</label>
                <select name="budget_id" class="form-control" required>
                    <?php foreach ($activeBudgets as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['period_label']) ?> (₱<?= number_format($b['allocated_amount'],2) ?>)</option>
                    <?php endforeach; ?>
                    <?php if (empty($activeBudgets)): ?><option value="">No active approved budgets</option><?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Amount to Allocate (₱) *</label>
                <input type="number" name="allocated_amount" id="approveAmt" class="form-control" step="0.01" min="0.01" required>
            </div>
            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" class="form-control" rows="2" placeholder="Optional remarks..."></textarea>
            </div>
            <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;"><i class="fas fa-info-circle"></i> The allocation will be submitted to Admin for final approval before the encoder can use it.</p>
            <button type="submit" class="btn btn-success" style="width:100%"><i class="fas fa-check"></i> Approve & Create Allocation</button>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Reject Budget Request</span>
            <button class="modal-close" onclick="document.getElementById('rejectModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="request_id" id="rejectRid">
            <div class="form-group">
                <label>Remarks / Reason *</label>
                <textarea name="remarks" class="form-control" rows="3" required placeholder="Reason for rejection..."></textarea>
            </div>
            <button type="submit" class="btn btn-danger" style="width:100%"><i class="fas fa-times"></i> Confirm Rejection</button>
        </form>
    </div>
</div>

<script>
function openApprove(r) {
    document.getElementById('approveRid').value = r.id;
    document.getElementById('approveInfo').textContent = 'Encoder: ' + r.encoder_name + ' | Requested: ₱' + parseFloat(r.requested_amount).toLocaleString('en-PH',{minimumFractionDigits:2}) + ' for ' + r.request_title;
    document.getElementById('approveAmt').value = r.requested_amount;
    document.getElementById('approveModal').classList.add('open');
}
function openReject(rid) {
    document.getElementById('rejectRid').value = rid;
    document.getElementById('rejectModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }));
</script>
<?php include '../includes/footer.php'; ?>
