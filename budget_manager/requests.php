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
        $req    = $conn->query("SELECT * FROM budget_requests WHERE id=$rid")->fetch_assoc();
        $budget = $conn->query("SELECT * FROM budgets WHERE id=$budget_id AND approval_status='approved'")->fetch_assoc();

        if ($req && $budget) {
            // Per-budget remaining: sum only allocations tied to this budget
            $alreadyAllocated = (float)$conn->query("
                SELECT COALESCE(SUM(allocated_amount), 0) AS s
                FROM budget_allocations
                WHERE budget_id = $budget_id AND admin_approval_status = 'approved'
            ")->fetch_assoc()['s'];
            $budgetRemaining = $budget['allocated_amount'] - $alreadyAllocated;

            if ($alloc_amount > $budgetRemaining) {
                $msg = 'Cannot approve: allocation amount (₱' . number_format($alloc_amount, 2)
                     . ') exceeds the unallocated balance of the selected budget period (₱'
                     . number_format($budgetRemaining, 2) . ').';
                $msgType = 'danger';
            } else {
                // Mark request as approved
                $stmt = $conn->prepare("UPDATE budget_requests SET status='approved', reviewed_by=?, review_remarks=?, reviewed_at=NOW() WHERE id=?");
                $stmt->bind_param("isi", $uid, $rmk, $rid);
                $stmt->execute(); $stmt->close();

                // Create allocation — directly APPROVED (encoder can use immediately)
                $enc_id  = $req['encoder_id'];
                $title   = $conn->real_escape_string($req['request_title']);
                $purpose = $conn->real_escape_string($req['description'] ?? '');
                $date    = date('Y-m-d');
                $conn->query("
                    INSERT INTO budget_allocations
                        (budget_id, budget_request_id, encoder_id, is_shared,
                         allocation_title, purpose, allocated_amount, allocation_date,
                         admin_approval_status, admin_approved_by, admin_approved_at, created_by)
                    VALUES
                        ($budget_id, $rid, $enc_id, 0,
                         '$title', '$purpose', $alloc_amount, '$date',
                         'approved', $uid, NOW(), $uid)
                ");

                logActivity($conn, 'APPROVE_BUDGET_REQUEST',
                    "Approved request #$rid — ₱$alloc_amount from budget #$budget_id to encoder #$enc_id");
                $msg = 'Request approved. ₱' . number_format($alloc_amount, 2)
                     . ' is now immediately available to the encoder from "'
                     . htmlspecialchars($budget['period_label']) . '".';
                $msgType = 'success';
            }
        } else {
            $msg = 'Budget request or selected budget period not found.';
            $msgType = 'danger';
        }
    }

    if ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE budget_requests SET status='rejected', reviewed_by=?, review_remarks=?, reviewed_at=NOW() WHERE id=?");
        $stmt->bind_param("isi", $uid, $rmk, $rid);
        $stmt->execute(); $stmt->close();
        logActivity($conn, 'REJECT_BUDGET_REQUEST', "Rejected budget request ID $rid");
        $msg = 'Request rejected.'; $msgType = 'success';
    }
}

$statusFilter  = $_GET['status'] ?? 'pending';
$where = "WHERE 1=1";
if ($statusFilter !== 'all') $where .= " AND br.status='" . $conn->real_escape_string($statusFilter) . "'";

$requests = $conn->query("
    SELECT br.*, u.full_name AS encoder_name, rv.full_name AS reviewer_name
    FROM budget_requests br
    JOIN users u ON br.encoder_id = u.id
    LEFT JOIN users rv ON br.reviewed_by = rv.id
    $where
    ORDER BY br.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$activeBudgets = $conn->query("SELECT * FROM budgets WHERE is_active=1 AND approval_status='approved' ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

// Remaining budget available for allocation
$totalApproved  = $activeBudgets[0]['allocated_amount'] ?? 0;
$totalAllocated = $conn->query("SELECT COALESCE(SUM(allocated_amount),0) s FROM budget_allocations WHERE admin_approval_status='approved'")->fetch_assoc()['s'];
$availableForAlloc = $totalApproved - $totalAllocated;

include '../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Remaining budget banner -->
<?php if ($availableForAlloc > 0): ?>
<div style="margin-bottom:16px;padding:12px 16px;background:rgba(22,163,74,.07);border:1px solid rgba(22,163,74,.2);border-radius:10px;display:flex;gap:20px;flex-wrap:wrap;font-size:13px;">
    <span><strong>Total Approved Budget:</strong> <?= formatCurrency($totalApproved) ?></span>
    <span><strong>Already Allocated:</strong> <?= formatCurrency($totalAllocated) ?></span>
    <span style="color:var(--success);font-weight:700;"><i class="fas fa-check-circle"></i> Available to Allocate: <?= formatCurrency($availableForAlloc) ?></span>
</div>
<?php elseif ($totalApproved > 0): ?>
<div class="alert alert-warning" style="margin-bottom:16px;">
    <i class="fas fa-exclamation-triangle"></i> All approved budget has been allocated. Request a new budget period first.
</div>
<?php endif; ?>

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
            <thead>
                <tr>
                    <th>#</th><th>Encoder</th><th>Title</th><th>Description</th>
                    <th>Requested</th><th>End Date</th><th>Submitted</th>
                    <th>Status</th><th>Reviewed By</th><th>Remarks</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $i => $r): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($r['encoder_name']) ?></td>
                <td><strong><?= htmlspecialchars($r['request_title']) ?></strong></td>
                <td style="font-size:12px;color:var(--text-muted);max-width:160px;">
                    <?= htmlspecialchars($r['description'] ?? '—') ?>
                </td>
                <td><strong><?= formatCurrency($r['requested_amount']) ?></strong></td>
                <td style="font-size:12px;"><?= date('M d, Y H:i', strtotime($r['end_datetime'])) ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                <td>
                    <span class="badge badge-<?= $r['status']==='approved'?'approved':($r['status']==='rejected'?'rejected':'pending') ?>">
                        <?= ucfirst($r['status']) ?>
                    </span>
                </td>
                <td style="font-size:12px;"><?= htmlspecialchars($r['reviewer_name'] ?? '—') ?></td>
                <td style="font-size:12px;color:var(--text-muted);max-width:140px;">
                    <?= htmlspecialchars($r['review_remarks'] ?? '—') ?>
                </td>
                <td>
                    <?php if ($r['status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-success"
                            onclick='openApprove(<?= json_encode($r) ?>)'>
                        <i class="fas fa-check"></i> Approve
                    </button>
                    <button class="btn btn-sm btn-danger"
                            onclick="openReject(<?= $r['id'] ?>)">
                        <i class="fas fa-times"></i> Reject
                    </button>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--text-muted);">Processed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($requests)): ?>
            <tr><td colspan="11" style="text-align:center;color:var(--text-muted);padding:32px">No requests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal-overlay" id="approveModal">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header">
            <span class="modal-title">Approve Budget Request</span>
            <button class="modal-close" onclick="document.getElementById('approveModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="request_id" id="approveRid">

            <div style="padding:12px 14px;background:var(--cream);border-radius:8px;margin-bottom:16px;font-size:13px;">
                <div id="approveInfo"></div>
                <div style="margin-top:8px;color:var(--success);font-weight:600;">
                    <i class="fas fa-check-circle"></i>
                    Approval is final — the encoder can use this budget immediately after you approve.
                </div>
            </div>

            <div class="form-group">
                <label>Budget Period *</label>
                <select name="budget_id" class="form-control" required>
                    <?php foreach ($activeBudgets as $b):
                        // Per-budget remaining
                        $bAllocated = (float)$conn->query("SELECT COALESCE(SUM(allocated_amount),0) s FROM budget_allocations WHERE budget_id={$b['id']} AND admin_approval_status='approved'")->fetch_assoc()['s'];
                        $bRemaining = $b['allocated_amount'] - $bAllocated;
                    ?>
                    <option value="<?= $b['id'] ?>" <?= $bRemaining <= 0 ? 'disabled' : '' ?>>
                        <?= htmlspecialchars($b['period_label']) ?>
                        — Unallocated: ₱<?= number_format($bRemaining, 2) ?>
                        <?= $bRemaining <= 0 ? ' (fully allocated)' : '' ?>
                    </option>
                    <?php endforeach; ?>
                    <?php if (empty($activeBudgets)): ?>
                    <option value="">No active approved budgets available</option>
                    <?php endif; ?>
                </select>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">
                    Only the unallocated balance of the selected period can be assigned.
                </div>
            </div>

            <div class="form-group">
                <label>Amount to Allocate (₱) *</label>
                <input type="number" name="allocated_amount" id="approveAmt"
                       class="form-control" step="0.01" min="0.01" required>
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">
                    Available to allocate: <strong style="color:var(--success)"><?= formatCurrency($availableForAlloc) ?></strong>
                </div>
            </div>

            <div class="form-group">
                <label>Remarks (optional)</label>
                <textarea name="remarks" class="form-control" rows="2"
                          placeholder="Optional remarks for the encoder..."></textarea>
            </div>

            <?php if (empty($activeBudgets)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> No active approved budgets. Please create and approve a budget period first.</div>
            <?php endif; ?>

            <button type="submit" class="btn btn-success" style="width:100%"
                    <?= empty($activeBudgets) ? 'disabled' : '' ?>>
                <i class="fas fa-check"></i> Approve &amp; Allocate Budget
            </button>
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
                <label>Reason for Rejection *</label>
                <textarea name="remarks" class="form-control" rows="3" required
                          placeholder="Explain why this request is being rejected..."></textarea>
            </div>
            <button type="submit" class="btn btn-danger" style="width:100%">
                <i class="fas fa-times"></i> Confirm Rejection
            </button>
        </form>
    </div>
</div>

<script>
function openApprove(r) {
    document.getElementById('approveRid').value = r.id;
    document.getElementById('approveInfo').innerHTML =
        '<strong>' + r.encoder_name + '</strong> requested '
        + '<strong>₱' + parseFloat(r.requested_amount).toLocaleString('en-PH', {minimumFractionDigits:2})
        + '</strong> for: ' + r.request_title;
    document.getElementById('approveAmt').value = r.requested_amount;
    document.getElementById('approveModal').classList.add('open');
}
function openReject(rid) {
    document.getElementById('rejectRid').value = rid;
    document.getElementById('rejectModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o =>
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); })
);
</script>
<?php include '../includes/footer.php'; ?>
