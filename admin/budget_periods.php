<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');
$pageTitle = 'Budget Period Approvals';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bid    = (int)$_POST['budget_id'];
    $rmk    = trim($_POST['remarks'] ?? '');
    $uid    = $_SESSION['user_id'];

    if ($action === 'approve') {
        // Log & deactivate any currently active budget before switching
        $prevActive = $conn->query("SELECT id, period_label FROM budgets WHERE is_active=1 LIMIT 1")->fetch_assoc();
        if ($prevActive && $prevActive['id'] != $bid) {
            $conn->query("UPDATE budgets SET is_active=0 WHERE is_active=1");
            logActivity($conn, 'ADMIN_DEACTIVATE_BUDGET',
                "Auto-deactivated budget period \"{$prevActive['period_label']}\" (ID {$prevActive['id']}) when approving new period ID $bid");
        }
        $stmt = $conn->prepare("UPDATE budgets SET approval_status='approved', is_active=1, approved_by=?, approved_at=NOW() WHERE id=?");
        $stmt->bind_param("ii", $uid, $bid);
        $stmt->execute(); $stmt->close();
        logActivity($conn, 'ADMIN_APPROVE_BUDGET', "Admin approved budget period ID $bid");
        $prevNote = $prevActive && $prevActive['id'] != $bid
            ? " Previous active budget <strong>\"{$prevActive['period_label']}\"</strong> has been deactivated."
            : '';
        $msg = 'Budget approved and activated.' . $prevNote; $msgType = 'success';
    }

    if ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE budgets SET approval_status='rejected', approved_by=?, approved_at=NOW(), rejection_reason=? WHERE id=?");
        $stmt->bind_param("isi", $uid, $rmk, $bid);
        $stmt->execute(); $stmt->close();
        logActivity($conn, 'ADMIN_REJECT_BUDGET', "Admin rejected budget period ID $bid. Reason: $rmk");
        $msg = 'Budget proposal rejected.'; $msgType = 'success';
    }
}

$statusFilter = $_GET['status'] ?? 'pending';
$where = "WHERE 1=1";
if ($statusFilter !== 'all') $where .= " AND b.approval_status='" . $conn->real_escape_string($statusFilter) . "'";

$budgets = $conn->query("
    SELECT b.*, u.full_name AS created_by_name, a.full_name AS approver_name
    FROM budgets b
    LEFT JOIN users u ON b.created_by = u.id
    LEFT JOIN users a ON b.approved_by = a.id
    $where
    ORDER BY b.created_at DESC
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
        <span class="card-title">Budget Period Proposals</span>
        <span style="font-size:13px;color:var(--text-muted)"><?= count($budgets) ?> record(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Period Label</th><th>Type</th><th>Amount</th><th>Start</th><th>End</th><th>Submitted By</th><th>Submitted At</th><th>Status</th><th>Approver</th><th>Rejection Reason</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($budgets as $i => $b): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($b['period_label']) ?></strong></td>
                <td><?= ucfirst($b['period_type']) ?></td>
                <td><strong><?= formatCurrency($b['allocated_amount']) ?></strong></td>
                <td><?= $b['start_date'] ?></td>
                <td><?= $b['end_date'] ?></td>
                <td><?= htmlspecialchars($b['created_by_name']??'—') ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= $b['created_at'] ? date('M d, Y', strtotime($b['created_at'])) : '—' ?></td>
                <td><span class="badge badge-<?= $b['approval_status']==='approved'?'approved':($b['approval_status']==='rejected'?'rejected':'pending') ?>"><?= ucfirst($b['approval_status']) ?></span></td>
                <td style="font-size:12px;"><?= htmlspecialchars($b['approver_name']??'—') ?></td>
                <td style="font-size:12px;color:var(--danger);max-width:160px;"><?= htmlspecialchars($b['rejection_reason']??'—') ?></td>
                <td>
                    <?php if ($b['approval_status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-success"
                        data-id="<?= $b['id'] ?>"
                        data-action="approve"
                        data-label="<?= htmlspecialchars($b['period_label'], ENT_QUOTES) ?>"
                        data-amount="<?= $b['allocated_amount'] ?>"
                        onclick="openDecision(this.dataset.id, this.dataset.action, this.dataset.label, this.dataset.amount)">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="btn btn-sm btn-danger"
                        data-id="<?= $b['id'] ?>"
                        data-action="reject"
                        onclick="openDecision(this.dataset.id, this.dataset.action)">
                        <i class="fas fa-times"></i>
                    </button>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--text-muted);">Done</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($budgets)): ?><tr><td colspan="12" style="text-align:center;color:var(--text-muted);padding:32px">No budget proposals found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Decision Modal -->
<div class="modal-overlay" id="decisionModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="decisionTitle">Review Budget</span>
            <button class="modal-close" onclick="document.getElementById('decisionModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="budget_id" id="decisionBid">
            <input type="hidden" name="action" id="decisionAction">
            <div id="activeWarning" class="alert alert-warning" style="display:none;margin-bottom:14px;font-size:13px;"></div>
            <div class="form-group">
                <label id="rmkLabel">Remarks / Reason</label>
                <textarea name="remarks" id="rmkField" class="form-control" rows="3" placeholder="Optional remarks..."></textarea>
            </div>
            <button type="submit" class="btn btn-gold" id="decisionBtn" style="width:100%"><i class="fas fa-check"></i> Confirm</button>
        </form>
    </div>
</div>

<script>
// Pass currently active budget to JS so the modal can warn the admin
const activeBudget = <?= json_encode(
    $conn->query("SELECT id, period_label, allocated_amount FROM budgets WHERE is_active=1 AND approval_status='approved' LIMIT 1")->fetch_assoc()
    ?? null
) ?>;

function openDecision(id, action, label, amount) {
    label = label || '';
    amount = amount || 0;
    document.getElementById('decisionBid').value = id;
    document.getElementById('decisionAction').value = action;
    document.getElementById('decisionTitle').textContent = action === 'approve' ? 'Approve Budget Proposal' : 'Reject Budget Proposal';
    const rmk = document.getElementById('rmkField');
    rmk.required = (action === 'reject');
    rmk.placeholder = action === 'reject' ? 'Reason for rejection (required)...' : 'Optional remarks...';
    const btn = document.getElementById('decisionBtn');
    btn.className = 'btn ' + (action==='approve'?'btn-success':'btn-danger');
    btn.innerHTML = '<i class="fas fa-' + (action==='approve'?'check':'times') + '"></i> ' + (action==='approve'?'Approve':'Reject');

    // Show deactivation warning only when approving and another budget is currently active
    const warn = document.getElementById('activeWarning');
    if (action === 'approve' && activeBudget && activeBudget.id != id) {
        warn.style.display = 'block';
        warn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> Approving this will deactivate the current active budget: <strong>"' + activeBudget.period_label + '"</strong> (₱' + parseFloat(activeBudget.allocated_amount).toLocaleString('en-PH',{minimumFractionDigits:2}) + '). Any encoders using it will lose access immediately.';
    } else {
        warn.style.display = 'none';
    }

    document.getElementById('decisionModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }));
</script>
<?php include '../includes/footer.php'; ?>
