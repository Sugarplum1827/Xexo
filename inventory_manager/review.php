<?php
require_once '../includes/auth.php';
requireRole('inventory_manager', '../index.php');
$pageTitle = 'Inventory Review';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    if ($action === 'start_review') {
        $uid   = $_SESSION['user_id'];
        $notes = trim($_POST['notes']);
        $conn->query("INSERT INTO inventory_reviews (review_date, reviewed_by, notes, status, created_at) VALUES (CURDATE(), $uid, '{$conn->real_escape_string($notes)}', 'draft', NOW())");
        $rid = $conn->insert_id;
        logActivity($conn,'START_REVIEW',"Started inventory review ID $rid");
        $msg = "Review #$rid started."; $msgType='success';
    }
    if ($action === 'update_count') {
        $rid     = (int)$_POST['review_id'];
        $iid     = (int)$_POST['inventory_id'];
        $actual  = (float)$_POST['actual_stock'];
        $expected= (float)$_POST['expected_stock'];
        $notes   = $conn->real_escape_string(trim($_POST['notes']??''));
        $conn->query("INSERT INTO inventory_review_items (review_id, inventory_id, expected_stock, actual_stock, notes) VALUES ($rid,$iid,$expected,$actual,'$notes')
                      ON DUPLICATE KEY UPDATE actual_stock=$actual, notes='$notes'");
        // Update actual inventory
        $conn->query("UPDATE inventory SET current_stock=$actual WHERE id=$iid");
        logActivity($conn,'REVIEW_COUNT',"Updated count for inventory ID $iid: actual=$actual");
        $msg = 'Stock count updated.'; $msgType='success';
    }
    if ($action === 'complete_review') {
        $rid = (int)$_POST['review_id'];
        $conn->query("UPDATE inventory_reviews SET status='completed' WHERE id=$rid");
        logActivity($conn,'COMPLETE_REVIEW',"Completed inventory review ID $rid");
        $msg = "Review completed."; $msgType='success';
    }
}

$reviews  = $conn->query("SELECT r.*, u.full_name FROM inventory_reviews r LEFT JOIN users u ON r.reviewed_by=u.id ORDER BY r.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$activeR  = $conn->query("SELECT * FROM inventory_reviews WHERE status='draft' ORDER BY id DESC LIMIT 1")->fetch_assoc();
$items    = $conn->query("SELECT * FROM inventory ORDER BY item_name")->fetch_all(MYSQLI_ASSOC);
include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-check-circle"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<?php if ($activeR): ?>
<div class="card" style="margin-bottom:24px;border:2px solid var(--gold);">
    <div class="card-header">
        <span class="card-title">Active Review #<?= $activeR['id'] ?> – <?= $activeR['review_date'] ?></span>
        <form method="POST">
            <input type="hidden" name="action" value="complete_review">
            <input type="hidden" name="review_id" value="<?= $activeR['id'] ?>">
            <button class="btn btn-success" onclick="return confirm('Mark this review as complete?')"><i class="fas fa-check-double"></i> Complete Review</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Item</th><th>Unit</th><th>Expected</th><th>Actual Count</th><th>Discrepancy</th><th>Notes</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item):
                $prevEntry = $conn->query("SELECT * FROM inventory_review_items WHERE review_id={$activeR['id']} AND inventory_id={$item['id']} LIMIT 1")->fetch_assoc();
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                <td><?= htmlspecialchars($item['unit']) ?></td>
                <td><?= $item['current_stock'] ?></td>
                <td>
                    <form method="POST" style="display:flex;gap:6px;align-items:center;">
                        <input type="hidden" name="action" value="update_count">
                        <input type="hidden" name="review_id" value="<?= $activeR['id'] ?>">
                        <input type="hidden" name="inventory_id" value="<?= $item['id'] ?>">
                        <input type="hidden" name="expected_stock" value="<?= $item['current_stock'] ?>">
                        <input type="number" name="actual_stock" class="form-control" style="width:90px;padding:7px;" step="0.01" value="<?= $prevEntry['actual_stock'] ?? $item['current_stock'] ?>" required>
                        <input type="text" name="notes" class="form-control" style="width:120px;padding:7px;" placeholder="Notes" value="<?= htmlspecialchars($prevEntry['notes']??'') ?>">
                        <button class="btn btn-sm btn-primary"><i class="fas fa-save"></i></button>
                    </form>
                </td>
                <td style="font-weight:700;color:<?= isset($prevEntry['discrepancy'])&&$prevEntry['discrepancy']!=0?'var(--danger)':'var(--success)' ?>">
                    <?= isset($prevEntry['discrepancy']) ? ($prevEntry['discrepancy']>=0?'+':'').$prevEntry['discrepancy'] : '—' ?>
                </td>
                <td style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($prevEntry['notes']??'') ?></td>
                <td></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><span class="card-title">Start New Inventory Review</span></div>
    <form method="POST" style="max-width:460px;">
        <input type="hidden" name="action" value="start_review">
        <div class="form-group"><label>Review Notes</label><textarea name="notes" class="form-control" rows="3" placeholder="Purpose of this review, special notes…"></textarea></div>
        <button type="submit" class="btn btn-gold"><i class="fas fa-clipboard-check"></i> Start Review</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><span class="card-title">Recent Reviews</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Date</th><th>Reviewed By</th><th>Notes</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($reviews as $r): ?>
            <tr>
                <td><?= $r['id'] ?></td>
                <td><?= $r['review_date'] ?></td>
                <td><?= htmlspecialchars($r['full_name']??'—') ?></td>
                <td style="font-size:13px"><?= htmlspecialchars($r['notes']??'—') ?></td>
                <td><span class="badge <?= $r['status']==='completed'?'badge-approved':'badge-pending' ?>"><?= ucfirst($r['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
