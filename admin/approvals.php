<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');
$pageTitle = 'Purchase Approvals';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid    = (int)$_POST['purchase_id'];
    $action = $_POST['action'];
    $notes  = trim($_POST['notes'] ?? '');
    $uid    = $_SESSION['user_id'];

    if (in_array($action, ['approved', 'rejected', 'correction_needed'])) {

        // Fetch full purchase record (including allocation_id)
        $p = $conn->query("SELECT * FROM purchases WHERE id = $pid")->fetch_assoc();

        if ($p) {
            // Update purchase status
            $stmt = $conn->prepare("UPDATE purchases SET status=?, reviewed_by=?, review_notes=?, reviewed_at=NOW() WHERE id=?");
            $stmt->bind_param("sisi", $action, $uid, $notes, $pid);
            $stmt->execute(); $stmt->close();

            $total     = (float)$p['total_price'];
            $alloc_id  = (int)($p['allocation_id'] ?? 0);

            if ($action === 'approved') {
                // ── Budget: amount was already reserved (amount_used++) on submission.
                //    On approval we keep the reservation — it is now confirmed spent.
                //    Log in expense_log for reporting.
                $conn->query("INSERT INTO expense_log
                    (purchase_id, amount, category, logged_date, budget_id, created_at)
                    VALUES ($pid, $total, 'Purchase', '{$p['purchase_date']}', {$p['budget_id']}, NOW())");

                // ── Inventory: add stock
                $iname = $conn->real_escape_string($p['item_name']);
                $qty   = (float)$p['quantity'];
                $exists = $conn->query("SELECT id FROM inventory WHERE item_name='$iname' LIMIT 1")->fetch_assoc();
                if ($exists) {
                    $conn->query("UPDATE inventory SET current_stock=current_stock+$qty, last_updated=NOW() WHERE id={$exists['id']}");
                } else {
                    $iunit  = $conn->real_escape_string($p['unit']);
                    $iprice = (float)$p['unit_price'];
                    $conn->query("INSERT INTO inventory (item_name, unit, current_stock, unit_cost) VALUES ('$iname','$iunit',$qty,$iprice)");
                }

                logActivity($conn, 'APPROVED_PURCHASE',
                    "Approved purchase #$pid ({$p['item_name']} ₱$total). Budget allocation #$alloc_id confirmed. Notes: $notes");
                $msg = "Purchase approved. ₱" . number_format($total, 2) . " confirmed from the encoder's allocation.";
                $msgType = 'success';

            } elseif ($action === 'rejected') {
                // ── Budget: REVERSE the reservation so the encoder gets the money back
                if ($alloc_id > 0) {
                    $conn->query("UPDATE budget_allocations
                                  SET amount_used = GREATEST(0, amount_used - $total)
                                  WHERE id = $alloc_id");
                }

                logActivity($conn, 'REJECTED_PURCHASE',
                    "Rejected purchase #$pid ({$p['item_name']} ₱$total). ₱$total returned to allocation #$alloc_id. Notes: $notes");
                $msg = "Purchase rejected. ₱" . number_format($total, 2) . " has been returned to the encoder's budget allocation.";
                $msgType = 'success';

            } elseif ($action === 'correction_needed') {
                // ── Budget: REVERSE the reservation — encoder will resubmit
                if ($alloc_id > 0) {
                    $conn->query("UPDATE budget_allocations
                                  SET amount_used = GREATEST(0, amount_used - $total)
                                  WHERE id = $alloc_id");
                }

                logActivity($conn, 'CORRECTION_NEEDED_PURCHASE',
                    "Sent correction request for purchase #$pid ({$p['item_name']} ₱$total). ₱$total returned to allocation #$alloc_id. Notes: $notes");
                $msg = "Correction requested. ₱" . number_format($total, 2) . " returned to encoder's allocation — they may resubmit.";
                $msgType = 'success';
            }
        }
    }
}

$filter  = $_GET['filter'] ?? 'pending';
$allowed = ['pending', 'approved', 'rejected', 'correction_needed'];
if (!in_array($filter, $allowed)) $filter = 'pending';

$purchases = $conn->query("
    SELECT p.*, u.full_name AS submitter, r.full_name AS reviewer,
           ba.allocation_title, ba.allocated_amount AS alloc_total, ba.amount_used AS alloc_used
    FROM purchases p
    LEFT JOIN users u ON p.submitted_by = u.id
    LEFT JOIN users r ON p.reviewed_by  = r.id
    LEFT JOIN budget_allocations ba ON p.allocation_id = ba.id
    WHERE p.status = '$filter'
    ORDER BY p.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <?php foreach (['pending'=>'⏳ Pending','approved'=>'✅ Approved','rejected'=>'❌ Rejected','correction_needed'=>'🔄 Correction'] as $k => $v): ?>
    <a href="?filter=<?= $k ?>" class="btn <?= $filter===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title"><?= ucwords(str_replace('_', ' ', $filter)) ?> Purchases</span>
        <span style="font-size:13px;color:var(--text-muted)"><?= count($purchases) ?> record(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Item</th><th>Qty</th><th>Unit Price</th><th>Total</th>
                    <th>Supplier</th><th>Date</th><th>Submitted By</th>
                    <th>Budget Allocation</th>
                    <?= $filter!=='pending' ? '<th>Reviewed By</th><th>Notes</th>' : '' ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($purchases)): ?>
            <tr><td colspan="12" style="text-align:center;color:var(--text-muted);padding:32px;">
                No <?= str_replace('_',' ',$filter) ?> purchases found.
            </td></tr>
            <?php endif; ?>
            <?php foreach ($purchases as $i => $p): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($p['item_name']) ?></strong></td>
                <td><?= $p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?></td>
                <td><?= formatCurrency($p['unit_price']) ?></td>
                <td><strong><?= formatCurrency($p['total_price']) ?></strong></td>
                <td><?= htmlspecialchars($p['supplier'] ?? '—') ?></td>
                <td><?= $p['purchase_date'] ?></td>
                <td><?= htmlspecialchars($p['submitter'] ?? '—') ?></td>
                <td style="font-size:12px;">
                    <?php if ($p['allocation_title']): ?>
                    <span style="display:block;font-weight:600;"><?= htmlspecialchars($p['allocation_title']) ?></span>
                    <span style="color:var(--text-muted);">
                        Used: <?= formatCurrency($p['alloc_used']) ?> /
                        <?= formatCurrency($p['alloc_total']) ?>
                    </span>
                    <?php else: ?>
                    <span style="color:var(--text-muted);">—</span>
                    <?php endif; ?>
                </td>
                <?php if ($filter !== 'pending'): ?>
                <td style="font-size:12px;"><?= htmlspecialchars($p['reviewer'] ?? '—') ?></td>
                <td style="font-size:12px;color:var(--text-muted);max-width:160px;"><?= htmlspecialchars($p['review_notes'] ?? '—') ?></td>
                <?php endif; ?>
                <td>
                    <?php if ($p['receipt_path']): ?>
                    <a href="../<?= htmlspecialchars($p['receipt_path']) ?>"
                       target="_blank" class="btn btn-sm btn-outline" title="View Receipt">
                        <i class="fas fa-file-image"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($filter === 'pending'): ?>
                    <button class="btn btn-sm btn-success"
                            onclick="openReview(<?= $p['id'] ?>, 'approved', <?= $p['total_price'] ?>)">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="btn btn-sm btn-danger"
                            onclick="openReview(<?= $p['id'] ?>, 'rejected', <?= $p['total_price'] ?>)">
                        <i class="fas fa-times"></i>
                    </button>
                    <button class="btn btn-sm btn-primary"
                            onclick="openReview(<?= $p['id'] ?>, 'correction_needed', <?= $p['total_price'] ?>)">
                        <i class="fas fa-redo"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Review Modal -->
<div class="modal-overlay" id="reviewModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Review Purchase</span>
            <button class="modal-close"
                    onclick="document.getElementById('reviewModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="purchase_id" id="reviewPid">
            <input type="hidden" name="action"      id="reviewAction">
            <div id="reviewMsg"
                 style="margin-bottom:16px;padding:12px;border-radius:8px;background:var(--grey-100);font-size:14px;">
            </div>
            <div id="budgetNote"
                 style="display:none;margin-bottom:14px;padding:10px 12px;border-radius:8px;font-size:13px;">
            </div>
            <div class="form-group">
                <label>Notes / Feedback (optional)</label>
                <textarea name="notes" class="form-control" rows="3"
                          placeholder="Add notes for the encoder..."></textarea>
            </div>
            <button type="submit" class="btn btn-gold" id="reviewBtn" style="width:100%">
                <i class="fas fa-check"></i> Confirm Decision
            </button>
        </form>
    </div>
</div>

<script>
function php_fmt(n) {
    return '₱' + parseFloat(n).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
}
function openReview(pid, action, total) {
    document.getElementById('reviewPid').value    = pid;
    document.getElementById('reviewAction').value = action;

    const labels = {
        approved:          'APPROVE',
        rejected:          'REJECT',
        correction_needed: 'REQUEST CORRECTION'
    };
    document.getElementById('reviewMsg').textContent =
        'You are about to: ' + labels[action] + ' this purchase (' + php_fmt(total) + ')';

    const note = document.getElementById('budgetNote');
    if (action === 'approved') {
        note.style.display     = 'block';
        note.style.background  = 'rgba(22,163,74,.08)';
        note.style.color       = 'var(--success)';
        note.innerHTML = '<i class="fas fa-check-circle"></i> ' + php_fmt(total)
            + ' will be <strong>confirmed</strong> as spent from the encoder\'s budget allocation.';
    } else if (action === 'rejected' || action === 'correction_needed') {
        note.style.display    = 'block';
        note.style.background = 'rgba(185,28,28,.07)';
        note.style.color      = 'var(--danger)';
        note.innerHTML = '<i class="fas fa-undo-alt"></i> ' + php_fmt(total)
            + ' will be <strong>returned</strong> to the encoder\'s budget allocation.';
    } else {
        note.style.display = 'none';
    }

    const btn = document.getElementById('reviewBtn');
    btn.className = 'btn ' + (action==='approved'?'btn-success':action==='rejected'?'btn-danger':'btn-primary');
    btn.innerHTML = '<i class="fas fa-' + (action==='approved'?'check':action==='rejected'?'times':'redo')
        + '"></i> ' + (action==='approved'?'Approve':action==='rejected'?'Reject':'Request Correction');

    document.getElementById('reviewModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o =>
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); })
);
</script>
<?php include '../includes/footer.php'; ?>
