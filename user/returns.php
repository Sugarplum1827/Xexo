<?php
require_once '../includes/auth.php';
requireRole('user', '../index.php');
$pageTitle = 'Returns';
$uid = $_SESSION['user_id'];

$msg = ''; $msgType = '';

// ── Backfill: generate/update return_requests only for PAST-DUE allocations with excess
// Rule: return_amount = allocated - used (the actual unspent excess only).
// If fully consumed (amount_used >= allocated_amount), no return is needed.

// Budget returns — only when past due_datetime AND there is excess
$conn->query("
    INSERT INTO return_requests
        (return_type, encoder_id, budget_allocation_id, original_purpose,
         return_amount, return_status, due_datetime, created_at)
    SELECT 'budget', ba.encoder_id, ba.id,
           COALESCE(ba.purpose, ba.allocation_title, ''),
           (ba.allocated_amount - ba.amount_used),
           'not_yet_returned',
           COALESCE(ba.end_datetime, CONCAT(b.end_date, ' 23:59:59')),
           NOW()
    FROM budget_allocations ba
    JOIN budgets b ON ba.budget_id = b.id
    WHERE ba.admin_approval_status = 'approved'
      AND ba.encoder_id = $uid
      AND ba.encoder_id IS NOT NULL
      AND (ba.allocated_amount - ba.amount_used) > 0.009
      AND NOW() > COALESCE(ba.end_datetime, CONCAT(b.end_date, ' 23:59:59'))
      AND NOT EXISTS (
          SELECT 1 FROM return_requests rr
          WHERE rr.budget_allocation_id = ba.id
            AND rr.return_type = 'budget'
      )
");

// Also update existing return rows with the latest excess amount
// (in case the encoder spent more before the deadline and the excess shrank)
$conn->query("
    UPDATE return_requests rr
    JOIN budget_allocations ba ON rr.budget_allocation_id = ba.id
    SET rr.return_amount = (ba.allocated_amount - ba.amount_used),
        rr.updated_at = NOW()
    WHERE rr.return_type = 'budget'
      AND rr.encoder_id = $uid
      AND rr.return_status = 'not_yet_returned'
      AND (ba.allocated_amount - ba.amount_used) >= 0
");

// Inventory returns — only when past due AND there is unconsumed quantity
$conn->query("
    INSERT INTO return_requests
        (return_type, encoder_id, encoder_inventory_id, original_purpose,
         return_quantity, return_status, due_datetime, created_at)
    SELECT 'inventory', ei.encoder_id, ei.id,
           COALESCE(ir.description, ei.item_name, ''),
           (ei.quantity_assigned - ei.quantity_consumed),
           'not_yet_returned',
           ir.end_datetime,
           NOW()
    FROM encoder_inventory ei
    JOIN inventory_requests ir ON ei.inventory_request_id = ir.id
    WHERE ei.encoder_id = $uid
      AND ir.end_datetime IS NOT NULL
      AND NOW() > ir.end_datetime
      AND (ei.quantity_assigned - ei.quantity_consumed) > 0
      AND NOT EXISTS (
          SELECT 1 FROM return_requests rr
          WHERE rr.encoder_inventory_id = ei.id
            AND rr.return_type = 'inventory'
      )
");

// Update inventory return quantity in case consumption changed before deadline
$conn->query("
    UPDATE return_requests rr
    JOIN encoder_inventory ei ON rr.encoder_inventory_id = ei.id
    SET rr.return_quantity = (ei.quantity_assigned - ei.quantity_consumed),
        rr.updated_at = NOW()
    WHERE rr.return_type = 'inventory'
      AND rr.encoder_id = $uid
      AND rr.return_status = 'not_yet_returned'
      AND (ei.quantity_assigned - ei.quantity_consumed) >= 0
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Upload/update attachment only
    if ($action === 'upload_attachment') {
        $rid = (int)$_POST['return_id'];
        if (!empty($_FILES['attachment']['name'])) {
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','pdf'];
            if (in_array($ext, $allowed)) {
                $fname = uniqid('return_').'.'.$ext;
                $dest  = '../uploads/returns/'.$fname;
                if (!is_dir('../uploads/returns/')) mkdir('../uploads/returns/', 0775, true);
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) {
                    $path = $conn->real_escape_string('uploads/returns/'.$fname);
                    $conn->query("UPDATE return_requests SET attachment_path='$path', attachment_uploaded_at=NOW(), updated_at=NOW() WHERE id=$rid AND encoder_id=$uid");
                    logActivity($conn,'UPLOAD_RETURN_ATTACHMENT',"Uploaded return attachment for request ID $rid");
                    $msg = 'Attachment uploaded successfully.'; $msgType = 'success';
                }
            } else {
                $msg = 'Invalid file type. Allowed: JPG, PNG, PDF.'; $msgType = 'danger';
            }
        }
    }
}

$returns = $conn->query("
    SELECT rr.*,
           ba.allocation_title,
           ei.item_name AS inv_item_name,
           ei.unit AS inv_unit
    FROM return_requests rr
    LEFT JOIN budget_allocations ba ON rr.budget_allocation_id = ba.id
    LEFT JOIN encoder_inventory ei ON rr.encoder_inventory_id = ei.id
    WHERE rr.encoder_id=$uid
    ORDER BY rr.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Return Requests</span>
    </div>
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
        Return requests are automatically generated when an allocation or inventory assignment reaches its end date.
        Upload your supporting evidence (proof of return) to allow the manager to verify your return.
    </p>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Type</th><th>Item/Budget</th><th>Return Amount/Qty</th><th>Purpose</th><th>Due Date</th><th>Status</th><th>Attachment</th><th>Upload Proof</th></tr></thead>
            <tbody>
            <?php foreach ($returns as $i => $r): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><span class="badge <?= $r['return_type']==='budget'?'badge-approved':'badge-pending' ?>"><?= ucfirst($r['return_type']) ?></span></td>
                <td><strong><?= htmlspecialchars($r['return_type']==='budget' ? ($r['allocation_title']??'Budget') : ($r['inv_item_name']??'Item')) ?></strong></td>
                <td><?= $r['return_type']==='budget' ? formatCurrency($r['return_amount']??0) : ($r['return_quantity'].' '.($r['inv_unit']??'')) ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($r['original_purpose']??'—') ?></td>
                <td style="font-size:13px;"><?= date('M d, Y H:i', strtotime($r['due_datetime'])) ?>
                    <?php if ($r['return_status'] !== 'returned' && strtotime($r['due_datetime']) < time()): ?>
                    <span class="badge badge-rejected" style="font-size:10px;margin-left:4px;">OVERDUE</span>
                    <?php endif; ?></td>
                <td>
                    <span class="badge <?= $r['return_status']==='returned'?'badge-approved':'badge-pending' ?>">
                        <?= $r['return_status']==='returned' ? 'Returned' : 'Not Yet Returned' ?>
                    </span>
                </td>
                <td>
                    <?php if ($r['attachment_path']): ?>
                    <a href="../<?= htmlspecialchars($r['attachment_path']) ?>" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-file"></i> View</a>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--text-muted);">None</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($r['return_status'] !== 'returned'): ?>
                    <form method="POST" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center;">
                        <input type="hidden" name="action" value="upload_attachment">
                        <input type="hidden" name="return_id" value="<?= $r['id'] ?>">
                        <input type="file" name="attachment" class="form-control" style="width:160px;padding:5px;" accept=".jpg,.jpeg,.png,.pdf" required>
                        <button class="btn btn-sm btn-gold" type="submit"><i class="fas fa-upload"></i></button>
                    </form>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--success)"><i class="fas fa-check-circle"></i> Verified</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($returns)): ?><tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:32px">No return requests found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
