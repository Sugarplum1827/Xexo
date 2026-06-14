<?php
require_once '../includes/auth.php';
requireRole('user', '../index.php');
$pageTitle = 'Inventory Requests';
$uid = $_SESSION['user_id'];

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $item  = trim($_POST['item_name']);
        $qty   = (float)$_POST['quantity_requested'];
        $unit  = trim($_POST['unit']);
        $desc  = trim($_POST['description']);
        $end   = str_replace('T', ' ', $_POST['end_datetime']);
        if (strlen($end) === 16) $end .= ':00';
        $stmt = $conn->prepare("INSERT INTO inventory_requests (item_name, quantity_requested, unit, description, end_datetime, encoder_id, status, created_at) VALUES (?,?,?,?,?,?,'pending',NOW())");
        $stmt->bind_param("sdssis", $item, $qty, $unit, $desc, $end, $uid);
        $stmt->execute(); $stmt->close();
        logActivity($conn, 'CREATE_INVENTORY_REQUEST', "Requested inventory: $item x$qty");
        $msg = 'Inventory request submitted!'; $msgType = 'success';
    }
}

$statusFilter = $_GET['status'] ?? 'all';
$where = "WHERE ir.encoder_id=$uid";
if ($statusFilter !== 'all') $where .= " AND ir.status='" . $conn->real_escape_string($statusFilter) . "'";
$requests = $conn->query("SELECT ir.*, u.full_name AS reviewer_name FROM inventory_requests ir LEFT JOIN users u ON ir.reviewed_by=u.id $where ORDER BY ir.created_at DESC")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <span class="card-title">New Inventory Request</span>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="form-row">
            <div class="form-group">
                <label>Item Name *</label>
                <input type="text" name="item_name" class="form-control" required placeholder="e.g. All-Purpose Flour">
            </div>
            <div class="form-group">
                <label>Description / Purpose *</label>
                <input type="text" name="description" class="form-control" required placeholder="What will this be used for?">
            </div>
        </div>
        <div class="form-row three">
            <div class="form-group">
                <label>Quantity Requested *</label>
                <input type="number" name="quantity_requested" class="form-control" required step="0.01" min="0.01" placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Unit *</label>
                <input type="text" name="unit" class="form-control" required placeholder="kg, pcs, L…">
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
        <span class="card-title">My Inventory Requests</span>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach (['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $k=>$v): ?>
            <a href="?status=<?= $k ?>" class="btn btn-sm <?= $statusFilter===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Item</th><th>Qty Requested</th><th>Unit</th><th>Purpose</th><th>End Date</th><th>Status</th><th>Qty Released</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $i => $r): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($r['item_name']) ?></strong></td>
                <td><?= $r['quantity_requested'] ?></td>
                <td><?= htmlspecialchars($r['unit']) ?></td>
                <td style="font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($r['description']??'—') ?></td>
                <td style="font-size:13px;"><?= date('M d, Y H:i', strtotime($r['end_datetime'])) ?></td>
                <td><span class="badge badge-<?= $r['status']==='approved'?'approved':($r['status']==='rejected'?'rejected':'pending') ?>"><?= ucfirst($r['status']) ?></span></td>
                <td><?= $r['quantity_released'] !== null ? $r['quantity_released'].' '.$r['unit'] : '—' ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($r['review_remarks']??'—') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($requests)): ?><tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:32px">No inventory requests found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
