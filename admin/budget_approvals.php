<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');
$pageTitle = 'Budget Allocation Approvals';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $aid    = (int)$_POST['allocation_id'];
    $rmk    = trim($_POST['remarks'] ?? '');
    $uid    = $_SESSION['user_id'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE budget_allocations SET admin_approval_status='approved', admin_approved_by=?, admin_approved_at=NOW(), admin_remarks=? WHERE id=?");
        $stmt->bind_param("isi", $uid, $rmk, $aid);
        $stmt->execute(); $stmt->close();

        // Auto-create a return_request due at the budget period end_date
        // so the encoder is prompted to return any unspent balance when the period closes.
        $alloc = $conn->query("
            SELECT ba.*, b.end_date, b.period_label,
                   ba.end_datetime
            FROM budget_allocations ba
            JOIN budgets b ON ba.budget_id = b.id
            WHERE ba.id = $aid
        ")->fetch_assoc();

        if ($alloc && $alloc['end_date'] && $alloc['encoder_id']) {
            $encId      = (int)$alloc['encoder_id'];
            $retAmt     = (float)$alloc['allocated_amount'];
            $purpose    = $conn->real_escape_string($alloc['purpose'] ?? $alloc['allocation_title'] ?? '');
            // Prefer the allocation's own end_datetime (encoder's stated deadline);
            // fall back to the budget period end_date only if not set.
            $dueDate    = !empty($alloc['end_datetime'])
                            ? $alloc['end_datetime']
                            : ($alloc['end_date'] . ' 23:59:59');
            $dueDateEsc = $conn->real_escape_string($dueDate);

            // Only create if one doesn't already exist for this allocation
            $existing = $conn->query("
                SELECT id FROM return_requests
                WHERE budget_allocation_id = $aid AND return_type = 'budget'
                LIMIT 1
            ")->fetch_assoc();

            if (!$existing) {
                $conn->query("
                    INSERT INTO return_requests
                        (return_type, encoder_id, budget_allocation_id, original_purpose,
                         return_amount, return_status, due_datetime, created_at)
                    VALUES
                        ('budget', $encId, $aid, '$purpose',
                         $retAmt, 'not_yet_returned', '$dueDateEsc', NOW())
                ");
            }
        }

        logActivity($conn,'ADMIN_APPROVE_ALLOCATION',"Admin approved budget allocation ID $aid — return request auto-created");
        $msg = 'Allocation approved. A return request has been queued for the encoder at period end.';
        $msgType = 'success';
    }

    if ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE budget_allocations SET admin_approval_status='rejected', admin_approved_by=?, admin_approved_at=NOW(), admin_remarks=? WHERE id=?");
        $stmt->bind_param("isi", $uid, $rmk, $aid);
        $stmt->execute(); $stmt->close();
        logActivity($conn,'ADMIN_REJECT_ALLOCATION',"Admin rejected budget allocation ID $aid");
        $msg = 'Allocation rejected.'; $msgType = 'success';
    }
}

$statusFilter = $_GET['status'] ?? 'pending';
$where = "WHERE 1=1";
if ($statusFilter !== 'all') $where .= " AND ba.admin_approval_status='" . $conn->real_escape_string($statusFilter) . "'";

$allocations = $conn->query("
    SELECT ba.*, b.period_label,
           u.full_name AS encoder_name,
           cr.full_name AS created_by_name
    FROM budget_allocations ba
    JOIN budgets b ON ba.budget_id = b.id
    LEFT JOIN users u ON ba.encoder_id = u.id
    JOIN users cr ON ba.created_by = cr.id
    $where
    ORDER BY ba.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

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
        <span class="card-title">Budget Allocation Approvals</span>
        <span style="font-size:13px;color:var(--text-muted)"><?= count($allocations) ?> record(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Title</th><th>Budget Period</th><th>Recipient</th><th>Type</th><th>Amount</th><th>Purpose</th><th>Submitted By</th><th>Date</th><th>Status</th><th>Admin Remarks</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($allocations as $i => $a): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($a['allocation_title']??'—') ?></strong></td>
                <td style="font-size:12px;"><?= htmlspecialchars($a['period_label']) ?></td>
                <td><?= $a['is_shared'] ? '<span class="badge badge-pending" style="font-size:10px;">All Encoders</span>' : htmlspecialchars($a['encoder_name']??'—') ?></td>
                <td><span class="badge <?= $a['is_shared']?'badge-pending':'badge-approved' ?>"><?= $a['is_shared']?'Shared':'Personal' ?></span></td>
                <td><strong><?= formatCurrency($a['allocated_amount']) ?></strong></td>
                <td style="font-size:12px;color:var(--text-muted);max-width:140px;"><?= htmlspecialchars($a['purpose']??'—') ?></td>
                <td><?= htmlspecialchars($a['created_by_name']) ?></td>
                <td style="font-size:12px;"><?= $a['allocation_date'] ?></td>
                <td><span class="badge badge-<?= $a['admin_approval_status']==='approved'?'approved':($a['admin_approval_status']==='rejected'?'rejected':'pending') ?>"><?= ucfirst($a['admin_approval_status']) ?></span></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($a['admin_remarks']??'—') ?></td>
                <td>
                    <?php if ($a['admin_approval_status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-success" onclick="openDecision(<?= $a['id'] ?>, 'approve')"><i class="fas fa-check"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="openDecision(<?= $a['id'] ?>, 'reject')"><i class="fas fa-times"></i></button>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--text-muted);">Done</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($allocations)): ?><tr><td colspan="12" style="text-align:center;color:var(--text-muted);padding:32px">No allocations found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Decision Modal -->
<div class="modal-overlay" id="decisionModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="decisionTitle">Review Allocation</span>
            <button class="modal-close" onclick="document.getElementById('decisionModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="allocation_id" id="decisionAid">
            <input type="hidden" name="action" id="decisionAction">
            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" class="form-control" rows="3" placeholder="Optional remarks..."></textarea>
            </div>
            <button type="submit" class="btn btn-gold" id="decisionBtn" style="width:100%"><i class="fas fa-check"></i> Confirm</button>
        </form>
    </div>
</div>

<script>
function openDecision(id, action) {
    document.getElementById('decisionAid').value = id;
    document.getElementById('decisionAction').value = action;
    document.getElementById('decisionTitle').textContent = action === 'approve' ? 'Approve Allocation' : 'Reject Allocation';
    const btn = document.getElementById('decisionBtn');
    btn.className = 'btn ' + (action==='approve'?'btn-success':'btn-danger');
    btn.innerHTML = '<i class="fas fa-' + (action==='approve'?'check':'times') + '"></i> ' + (action==='approve'?'Approve':'Reject');
    document.getElementById('decisionModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }));
</script>
<?php include '../includes/footer.php'; ?>
