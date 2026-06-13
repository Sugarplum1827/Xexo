<?php
require_once '../includes/auth.php';
requireRole('user', '../index.php');
$pageTitle = 'My Inventory';
$uid = $_SESSION['user_id'];

$msg = ''; $msgType = '';

// Handle consumption
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'consume') {
    $ei_id  = (int)$_POST['encoder_inventory_id'];
    $qty    = (float)$_POST['quantity_consumed'];
    $purpose= trim($_POST['purpose']);
    $ei = $conn->query("SELECT * FROM encoder_inventory WHERE id=$ei_id AND encoder_id=$uid")->fetch_assoc();
    if ($ei) {
        $remaining = $ei['quantity_assigned'] - $ei['quantity_consumed'];
        // Check if usage period has expired
        if (!empty($ei['end_datetime']) && strtotime($ei['end_datetime']) < time()) {
            $msg = 'This inventory assignment expired on '
                 . date('M d, Y h:i A', strtotime($ei['end_datetime']))
                 . '. You can no longer log consumption. A return request has been generated if there is unused stock.';
            $msgType = 'danger';
        } elseif ($qty > $remaining) {
            $msg = 'Consumption exceeds remaining stock.'; $msgType = 'danger';
        } else {
            $stmt = $conn->prepare("INSERT INTO inventory_consumption_log (encoder_inventory_id, encoder_id, quantity_consumed, purpose, consumed_at) VALUES (?,?,?,?,NOW())");
            $stmt->bind_param("iids", $ei_id, $uid, $qty, $purpose);
            $stmt->execute(); $stmt->close();
            $conn->query("UPDATE encoder_inventory SET quantity_consumed=quantity_consumed+$qty WHERE id=$ei_id");
            logActivity($conn, 'CONSUME_INVENTORY', "Consumed {$qty} {$ei['unit']} of {$ei['item_name']}");
            $msg = 'Consumption recorded successfully.'; $msgType = 'success';
        }
    } else {
        $msg = 'Invalid inventory record.'; $msgType = 'danger';
    }
}

// My assigned inventory
$myInventory = $conn->query("
    SELECT ei.*, ir.end_datetime, ir.description AS request_desc
    FROM encoder_inventory ei
    JOIN inventory_requests ir ON ei.inventory_request_id = ir.id
    WHERE ei.encoder_id=$uid
    ORDER BY ei.assigned_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Consumption logs
$logs = $conn->query("
    SELECT icl.*, ei.item_name, ei.unit
    FROM inventory_consumption_log icl
    JOIN encoder_inventory ei ON icl.encoder_inventory_id = ei.id
    WHERE icl.encoder_id=$uid
    ORDER BY icl.consumed_at DESC LIMIT 30
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <span class="card-title">My Assigned Inventory</span>
        <a href="inventory_requests.php" class="btn btn-gold btn-sm"><i class="fas fa-plus"></i> Request Items</a>
    </div>
    <?php if (empty($myInventory)): ?>
    <div style="text-align:center;padding:40px;color:var(--text-muted);">No inventory assigned yet.</div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Item</th><th>Unit</th><th>Assigned</th><th>Consumed</th><th>Remaining</th><th>Purpose</th><th>Due</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($myInventory as $i => $ei):
                $remaining = $ei['quantity_assigned'] - $ei['quantity_consumed'];
                $pct = $ei['quantity_assigned'] > 0 ? min(100, ($ei['quantity_consumed']/$ei['quantity_assigned'])*100) : 0;
                $rowExpired = !empty($ei['end_datetime']) && strtotime($ei['end_datetime']) < time();
            ?>
            <tr style="<?= $rowExpired ? 'opacity:.6;background:rgba(185,28,28,.04);' : '' ?>">
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($ei['item_name']) ?></strong></td>
                <td><?= htmlspecialchars($ei['unit']) ?></td>
                <td><?= $ei['quantity_assigned'] ?></td>
                <td><?= $ei['quantity_consumed'] ?></td>
                <td style="font-weight:700;color:<?= $remaining>0?'var(--success)':'var(--danger)' ?>"><?= round($remaining, 3) ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($ei['purpose']??$ei['request_desc']??'—') ?></td>
                <td style="font-size:12px;"><?= $ei['end_datetime'] ? date('M d, Y H:i', strtotime($ei['end_datetime'])) : '—' ?></td>
                <td>
                    <?php
                    $isExpired = !empty($ei['end_datetime']) && strtotime($ei['end_datetime']) < time();
                    if ($isExpired): ?>
                    <span style="font-size:12px;color:var(--danger);font-weight:600;"><i class="fas fa-lock"></i> Expired</span>
                    <?php elseif ($remaining > 0): ?>
                    <button class="btn btn-sm btn-gold" onclick="openConsume(<?= $ei['id'] ?>, '<?= htmlspecialchars(addslashes($ei['item_name'])) ?>', '<?= htmlspecialchars($ei['unit']) ?>', <?= $remaining ?>)"><i class="fas fa-minus"></i> Use</button>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--text-muted)">Depleted</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Consumption Log</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Item</th><th>Qty Used</th><th>Unit</th><th>Purpose</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $i => $l): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($l['item_name']) ?></strong></td>
                <td><?= $l['quantity_consumed'] ?></td>
                <td><?= htmlspecialchars($l['unit']) ?></td>
                <td style="font-size:13px;"><?= htmlspecialchars($l['purpose']??'—') ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= date('M d, Y H:i', strtotime($l['consumed_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?><tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:24px">No consumption records yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Consume Modal -->
<div class="modal-overlay" id="consumeModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Record Inventory Usage</span>
            <button class="modal-close" onclick="document.getElementById('consumeModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="consume">
            <input type="hidden" name="encoder_inventory_id" id="consumeEiId">
            <p id="consumeInfo" style="font-size:13px;color:var(--text-muted);margin-bottom:16px;"></p>
            <div class="form-group">
                <label>Quantity to Use *</label>
                <input type="number" name="quantity_consumed" id="consumeQty" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Purpose *</label>
                <input type="text" name="purpose" class="form-control" required placeholder="How was this used?">
            </div>
            <button type="submit" class="btn btn-gold" style="width:100%"><i class="fas fa-check"></i> Confirm</button>
        </form>
    </div>
</div>

<script>
function openConsume(id, item, unit, remaining) {
    document.getElementById('consumeEiId').value = id;
    document.getElementById('consumeInfo').textContent = 'Item: ' + item + ' | Remaining: ' + remaining + ' ' + unit;
    document.getElementById('consumeQty').max = remaining;
    document.getElementById('consumeModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }));
</script>
<?php include '../includes/footer.php'; ?>
