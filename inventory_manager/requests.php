<?php
require_once '../includes/auth.php';
requireRole('inventory_manager', '../index.php');
$pageTitle = 'Inventory Requests';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $rid    = (int)$_POST['request_id'];
    $rmk    = trim($_POST['remarks'] ?? '');
    $uid    = $_SESSION['user_id'];

    if ($action === 'approve') {
        $qty_released = (float)$_POST['quantity_released'];
        $inv_id = (int)$_POST['inventory_id'];
        $req = $conn->query("SELECT * FROM inventory_requests WHERE id=$rid")->fetch_assoc();
        $inv = $conn->query("SELECT * FROM inventory WHERE id=$inv_id")->fetch_assoc();

        if ($req && $inv) {
            if ($inv['current_stock'] < $qty_released) {
                $msg = 'Insufficient stock in master inventory.'; $msgType = 'danger';
            } else {
                // Update request
                $stmt = $conn->prepare("UPDATE inventory_requests SET status='approved', reviewed_by=?, review_remarks=?, reviewed_at=NOW(), quantity_released=?, release_date=NOW(), inventory_id=?, updated_at=NOW() WHERE id=?");
                $stmt->bind_param("isdii", $uid, $rmk, $qty_released, $inv_id, $rid);
                $stmt->execute(); $stmt->close();
                // Deduct from master inventory
                $conn->query("UPDATE inventory SET current_stock=current_stock-$qty_released WHERE id=$inv_id");
                // Add to encoder_inventory
                $enc_id = $req['encoder_id'];
                $iname  = $conn->real_escape_string($req['item_name']);
                $iunit  = $conn->real_escape_string($inv['unit']);
                $purpose= $conn->real_escape_string($req['description']??'');
                $conn->query("INSERT INTO encoder_inventory (inventory_request_id, encoder_id, inventory_id, item_name, unit, quantity_assigned, quantity_consumed, assigned_date, purpose) VALUES ($rid,$enc_id,$inv_id,'$iname','$iunit',$qty_released,0,NOW(),'$purpose')");
                $ei_id = $conn->insert_id;

                // Auto-create a return_request due at the request's end_datetime
                // so the encoder is prompted to return unused inventory when time is up.
                if (!empty($req['end_datetime'])) {
                    $dueEsc = $conn->real_escape_string($req['end_datetime']);
                    $existing = $conn->query("
                        SELECT id FROM return_requests
                        WHERE encoder_inventory_id = $ei_id AND return_type = 'inventory'
                        LIMIT 1
                    ")->fetch_assoc();

                    if (!$existing) {
                        $conn->query("
                            INSERT INTO return_requests
                                (return_type, encoder_id, encoder_inventory_id, original_purpose,
                                 return_quantity, return_status, due_datetime, created_at)
                            VALUES
                                ('inventory', $enc_id, $ei_id, '$purpose',
                                 $qty_released, 'not_yet_returned', '$dueEsc', NOW())
                        ");
                    }
                }

                logActivity($conn,'APPROVE_INVENTORY_REQUEST',"Approved inventory request ID $rid: released $qty_released {$inv['unit']} of {$req['item_name']} — return request auto-created");
                $msg = 'Request approved and inventory assigned to encoder. A return request has been scheduled for the stated end date.';
                $msgType = 'success';
            }
        }
    }

    if ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE inventory_requests SET status='rejected', reviewed_by=?, review_remarks=?, reviewed_at=NOW(), updated_at=NOW() WHERE id=?");
        $stmt->bind_param("isi", $uid, $rmk, $rid);
        $stmt->execute(); $stmt->close();
        logActivity($conn,'REJECT_INVENTORY_REQUEST',"Rejected inventory request ID $rid");
        $msg = 'Request rejected.'; $msgType = 'success';
    }
}

$statusFilter = $_GET['status'] ?? 'pending';
$where = "WHERE 1=1";
if ($statusFilter !== 'all') $where .= " AND ir.status='" . $conn->real_escape_string($statusFilter) . "'";

$requests = $conn->query("
    SELECT ir.*, u.full_name AS encoder_name
    FROM inventory_requests ir
    JOIN users u ON ir.encoder_id = u.id
    $where
    ORDER BY ir.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$invItems = $conn->query("SELECT * FROM inventory WHERE current_stock > 0 ORDER BY item_name")->fetch_all(MYSQLI_ASSOC);

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
        <span class="card-title">Inventory Requests from Encoders</span>
        <span style="font-size:13px;color:var(--text-muted)"><?= count($requests) ?> record(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Encoder</th><th>Item</th><th>Qty Requested</th><th>Unit</th><th>Purpose</th><th>End Date</th><th>Status</th><th>Qty Released</th><th>Remarks</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $i => $r): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($r['encoder_name']) ?></td>
                <td><strong><?= htmlspecialchars($r['item_name']) ?></strong></td>
                <td><?= $r['quantity_requested'] ?></td>
                <td><?= htmlspecialchars($r['unit']) ?></td>
                <td style="font-size:12px;color:var(--text-muted);max-width:160px;"><?= htmlspecialchars($r['description']??'—') ?></td>
                <td style="font-size:12px;"><?= date('M d, Y H:i', strtotime($r['end_datetime'])) ?></td>
                <td><span class="badge badge-<?= $r['status']==='approved'?'approved':($r['status']==='rejected'?'rejected':'pending') ?>"><?= ucfirst($r['status']) ?></span></td>
                <td><?= $r['quantity_released'] !== null ? $r['quantity_released'].' '.$r['unit'] : '—' ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($r['review_remarks']??'—') ?></td>
                <td>
                    <?php if ($r['status'] === 'pending'): ?>
                    <button class="btn btn-sm btn-success" onclick='openApprove(<?= json_encode($r) ?>, <?= json_encode($invItems) ?>)'><i class="fas fa-check"></i> Approve</button>
                    <button class="btn btn-sm btn-danger" onclick="openReject(<?= $r['id'] ?>)"><i class="fas fa-times"></i> Reject</button>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--text-muted);">Processed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($requests)): ?><tr><td colspan="11" style="text-align:center;color:var(--text-muted);padding:32px">No requests found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal-overlay" id="approveModal">
    <div class="modal" style="max-width:580px;">
        <div class="modal-header">
            <span class="modal-title">Approve Inventory Request</span>
            <button class="modal-close" onclick="document.getElementById('approveModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="request_id" id="approveRid">
            <p id="approveInfo" style="font-size:13px;margin-bottom:14px;padding:10px;background:var(--grey-100);border-radius:8px;"></p>
            <div class="form-group">
                <label>Select Inventory Item to Release *</label>
                <select name="inventory_id" id="approveInvId" class="form-control" required onchange="updateStock(this)">
                    <option value="">-- Select Item --</option>
                </select>
            </div>
            <div id="stockInfo" style="font-size:13px;color:var(--text-muted);margin-bottom:12px;display:none;padding:8px 12px;background:var(--grey-100);border-radius:8px;"></div>
            <div class="form-group">
                <label>Quantity to Release *</label>
                <input type="number" name="quantity_released" id="approveQty" class="form-control" step="0.01" min="0.01" required>
            </div>
            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" class="form-control" rows="2" placeholder="Optional remarks..."></textarea>
            </div>
            <button type="submit" class="btn btn-success" style="width:100%"><i class="fas fa-check"></i> Confirm Approval</button>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Reject Inventory Request</span>
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
let invData = [];
function openApprove(req, items) {
    invData = items;
    document.getElementById('approveRid').value = req.id;
    document.getElementById('approveInfo').textContent = 'Encoder: ' + req.encoder_name + ' | Requested: ' + req.quantity_requested + ' ' + req.unit + ' of ' + req.item_name;
    document.getElementById('approveQty').value = req.quantity_requested;
    document.getElementById('approveQty').max = '';
    // Populate select
    const sel = document.getElementById('approveInvId');
    sel.innerHTML = '<option value="">-- Select Item --</option>';
    items.forEach(it => {
        const opt = document.createElement('option');
        opt.value = it.id;
        opt.textContent = it.item_name + ' (' + it.current_stock + ' ' + it.unit + ' available)';
        opt.dataset.stock = it.current_stock;
        opt.dataset.unit = it.unit;
        sel.appendChild(opt);
    });
    document.getElementById('stockInfo').style.display = 'none';
    document.getElementById('approveModal').classList.add('open');
}
function updateStock(sel) {
    const opt = sel.options[sel.selectedIndex];
    const info = document.getElementById('stockInfo');
    if (opt.dataset.stock) {
        info.textContent = 'Available stock: ' + opt.dataset.stock + ' ' + opt.dataset.unit;
        info.style.display = 'block';
        document.getElementById('approveQty').max = opt.dataset.stock;
    } else {
        info.style.display = 'none';
    }
}
function openReject(rid) {
    document.getElementById('rejectRid').value = rid;
    document.getElementById('rejectModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }));
</script>
<?php include '../includes/footer.php'; ?>
