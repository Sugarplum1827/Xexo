<?php
require_once '../includes/auth.php';
requireRole('user', '../index.php');
$pageTitle = 'Budget Requests';
$uid = $_SESSION['user_id'];

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title  = trim($_POST['request_title']);
        $desc   = trim($_POST['description']);
        $amount = (float)$_POST['requested_amount'];
        $end    = str_replace('T', ' ', $_POST['end_datetime']);
        if (strlen($end) === 16) $end .= ':00';
        $stmt = $conn->prepare("INSERT INTO budget_requests (request_title, description, requested_amount, end_datetime, encoder_id, status, created_at) VALUES (?,?,?,?,?,'pending',NOW())");
        $stmt->bind_param("ssdsi", $title, $desc, $amount, $end, $uid);
        $stmt->execute(); $stmt->close();
        logActivity($conn, 'CREATE_BUDGET_REQUEST', "Created budget request: $title - ₱$amount");
        $msg = 'Budget request submitted successfully!'; $msgType = 'success';
    }

    if ($action === 'edit') {
        $rid    = (int)$_POST['request_id'];
        $title  = trim($_POST['request_title']);
        $desc   = trim($_POST['description']);
        $amount = (float)$_POST['requested_amount'];
        $end    = str_replace('T', ' ', $_POST['end_datetime']);
        if (strlen($end) === 16) $end .= ':00';
        // Only editable if pending
        $check = $conn->query("SELECT status FROM budget_requests WHERE id=$rid AND encoder_id=$uid")->fetch_assoc();
        if ($check && $check['status'] === 'pending') {
            $stmt = $conn->prepare("UPDATE budget_requests SET request_title=?, description=?, requested_amount=?, end_datetime=?, updated_at=NOW() WHERE id=? AND encoder_id=?");
            $stmt->bind_param("ssdsii", $title, $desc, $amount, $end, $rid, $uid);
            $stmt->execute(); $stmt->close();
            logActivity($conn, 'EDIT_BUDGET_REQUEST', "Edited budget request ID $rid");
            $msg = 'Budget request updated.'; $msgType = 'success';
        } else {
            $msg = 'Cannot edit — request is no longer pending.'; $msgType = 'danger';
        }
    }
}

// Filters
$statusFilter = $_GET['status'] ?? 'all';
$where = "WHERE encoder_id=$uid";
if ($statusFilter !== 'all') $where .= " AND status='" . $conn->real_escape_string($statusFilter) . "'";
$requests = $conn->query("SELECT * FROM budget_requests $where ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <span class="card-title">New Budget Request</span>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
            <div class="form-group">
                <label>Request Title *</label>
                <input type="text" name="request_title" class="form-control" required placeholder="e.g. School Anniversary">
            </div>
            <div class="form-group">
                <label>Requested Amount (₱) *</label>
                <input type="number" name="requested_amount" class="form-control" required step="0.01" min="0.01" placeholder="0.00">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Description / Purpose *</label>
                <textarea name="description" class="form-control" rows="2" required placeholder="Describe what the budget will be used for..."></textarea>
            </div>
            <div class="form-group">
                <label>End Date and Time *</label>
                <input type="datetime-local" name="end_datetime" class="form-control" required>
            </div>
        </div>
        <button type="submit" class="btn btn-gold"><i class="fas fa-paper-plane"></i> Submit Request</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">My Budget Requests</span>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach (['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $k=>$v): ?>
            <a href="?status=<?= $k ?>" class="btn btn-sm <?= $statusFilter===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Title</th><th>Description</th><th>Amount</th><th>End Date</th><th>Status</th><th>Remarks</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $i => $r): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($r['request_title']) ?></strong></td>
                <td style="font-size:13px;color:var(--text-muted);max-width:200px;"><?= htmlspecialchars($r['description']??'') ?></td>
                <td><strong><?= formatCurrency($r['requested_amount']) ?></strong></td>
                <td style="font-size:13px;"><?= date('M d, Y H:i', strtotime($r['end_datetime'])) ?></td>
                <td><span class="badge badge-<?= $r['status']==='approved'?'approved':($r['status']==='rejected'?'rejected':'pending') ?>"><?= ucfirst($r['status']) ?></span></td>
                <td style="font-size:12px;color:var(--text-muted);max-width:160px;"><?= htmlspecialchars($r['review_remarks']??'—') ?></td>
                <td>
                    <?php if ($r['status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-primary" onclick='openEdit(<?= json_encode($r) ?>)'><i class="fas fa-edit"></i> Edit</button>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--text-muted)">Locked</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($requests)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px">No budget requests found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Edit Budget Request</span>
            <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="request_id" id="editRid">
            <div class="form-group"><label>Request Title *</label><input type="text" name="request_title" id="editTitle" class="form-control" required></div>
            <div class="form-group"><label>Description / Purpose</label><textarea name="description" id="editDesc" class="form-control" rows="2"></textarea></div>
            <div class="form-row">
                <div class="form-group"><label>Requested Amount (₱) *</label><input type="number" name="requested_amount" id="editAmount" class="form-control" step="0.01" required></div>
                <div class="form-group"><label>End Date and Time *</label><input type="datetime-local" name="end_datetime" id="editEnd" class="form-control" required></div>
            </div>
            <button type="submit" class="btn btn-gold" style="width:100%"><i class="fas fa-save"></i> Save Changes</button>
        </form>
    </div>
</div>

<script>
function openEdit(r) {
    document.getElementById('editRid').value = r.id;
    document.getElementById('editTitle').value = r.request_title;
    document.getElementById('editDesc').value = r.description || '';
    document.getElementById('editAmount').value = r.requested_amount;
    // Format datetime-local
    let dt = r.end_datetime ? r.end_datetime.replace(' ', 'T').slice(0,16) : '';
    document.getElementById('editEnd').value = dt;
    document.getElementById('editModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }));
</script>
<?php include '../includes/footer.php'; ?>
