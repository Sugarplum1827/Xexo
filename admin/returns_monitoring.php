<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');
$pageTitle = 'Returns Monitoring';

$typeFilter   = $_GET['type'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';

$where = "WHERE 1=1";
if ($typeFilter !== 'all') $where .= " AND rr.return_type='" . $conn->real_escape_string($typeFilter) . "'";
if ($statusFilter !== 'all') $where .= " AND rr.return_status='" . $conn->real_escape_string($statusFilter) . "'";

$returns = $conn->query("
    SELECT rr.*,
           u.full_name AS encoder_name,
           ba.allocation_title,
           ei.item_name AS inv_item, ei.unit AS inv_unit,
           v.full_name AS verifier_name
    FROM return_requests rr
    JOIN users u ON rr.encoder_id = u.id
    LEFT JOIN budget_allocations ba ON rr.budget_allocation_id = ba.id
    LEFT JOIN encoder_inventory ei ON rr.encoder_inventory_id = ei.id
    LEFT JOIN users v ON rr.verified_by = v.id
    $where
    ORDER BY rr.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;">
    <span style="font-size:13px;font-weight:600;color:var(--text-muted);align-self:center;">Type:</span>
    <?php foreach (['all'=>'All','budget'=>'Budget','inventory'=>'Inventory'] as $k=>$v): ?>
    <a href="?type=<?= $k ?>&status=<?= $statusFilter ?>" class="btn btn-sm <?= $typeFilter===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
    <?php endforeach; ?>
    &nbsp;
    <span style="font-size:13px;font-weight:600;color:var(--text-muted);align-self:center;">Status:</span>
    <?php foreach (['all'=>'All','not_yet_returned'=>'Pending','returned'=>'Returned'] as $k=>$v): ?>
    <a href="?type=<?= $typeFilter ?>&status=<?= $k ?>" class="btn btn-sm <?= $statusFilter===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">All Return Requests</span>
        <span style="font-size:13px;color:var(--text-muted)"><?= count($returns) ?> record(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Type</th><th>Encoder</th><th>Item/Budget</th><th>Return Amt/Qty</th><th>Purpose</th><th>Due Date</th><th>Return Status</th><th>Attachment</th><th>Verified By</th><th>Verified At</th></tr></thead>
            <tbody>
            <?php foreach ($returns as $i => $r): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><span class="badge <?= $r['return_type']==='budget'?'badge-approved':'badge-pending' ?>"><?= ucfirst($r['return_type']) ?></span></td>
                <td><?= htmlspecialchars($r['encoder_name']) ?></td>
                <td><strong><?= htmlspecialchars($r['return_type']==='budget' ? ($r['allocation_title']??'—') : ($r['inv_item']??'—')) ?></strong></td>
                <td><?= $r['return_type']==='budget' ? formatCurrency($r['return_amount']??0) : ($r['return_quantity'].' '.($r['inv_unit']??'')) ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($r['original_purpose']??'—') ?></td>
                <td style="font-size:12px;"><?= date('M d, Y H:i', strtotime($r['due_datetime'])) ?></td>
                <td><span class="badge <?= $r['return_status']==='returned'?'badge-approved':'badge-pending' ?>"><?= $r['return_status']==='returned'?'Returned':'Not Yet Returned' ?></span></td>
                <td>
                    <?php if ($r['attachment_path']): ?>
                    <a href="../<?= htmlspecialchars($r['attachment_path']) ?>" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-file"></i> View</a>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--danger);">None</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;"><?= $r['verifier_name'] ? htmlspecialchars($r['verifier_name']) : '—' ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= $r['verified_at'] ? date('M d, Y', strtotime($r['verified_at'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($returns)): ?><tr><td colspan="11" style="text-align:center;color:var(--text-muted);padding:32px">No return records found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
