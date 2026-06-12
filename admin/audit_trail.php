<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');
$pageTitle = 'Audit & Verification Trail';

$typeFilter = $_GET['type'] ?? 'all';
$dateFrom   = $_GET['date_from'] ?? '';
$dateTo     = $_GET['date_to'] ?? '';

// Build combined audit entries from multiple sources
$entries = [];

// Activity logs
$where = "WHERE 1=1";
if ($dateFrom) $where .= " AND DATE(al.created_at) >= '" . $conn->real_escape_string($dateFrom) . "'";
if ($dateTo)   $where .= " AND DATE(al.created_at) <= '" . $conn->real_escape_string($dateTo) . "'";
$actLogs = $conn->query("SELECT al.*, u.full_name FROM activity_logs al LEFT JOIN users u ON al.user_id=u.id $where ORDER BY al.created_at DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);
foreach ($actLogs as $l) {
    $entries[] = [
        'type' => 'Activity',
        'actor' => $l['full_name'] ?? 'System',
        'action' => $l['action'],
        'description' => $l['description'],
        'date' => $l['created_at'],
        'badge' => 'badge-pending'
    ];
}

// Return verifications
$rWhere = "WHERE rr.verified_at IS NOT NULL";
if ($dateFrom) $rWhere .= " AND DATE(rr.verified_at) >= '" . $conn->real_escape_string($dateFrom) . "'";
if ($dateTo)   $rWhere .= " AND DATE(rr.verified_at) <= '" . $conn->real_escape_string($dateTo) . "'";
$retVerif = $conn->query("SELECT rr.*, enc.full_name AS encoder_name, v.full_name AS verifier_name FROM return_requests rr JOIN users enc ON rr.encoder_id=enc.id JOIN users v ON rr.verified_by=v.id $rWhere ORDER BY rr.verified_at DESC")->fetch_all(MYSQLI_ASSOC);
foreach ($retVerif as $r) {
    $entries[] = [
        'type' => 'Return Verify',
        'actor' => $r['verifier_name'],
        'action' => 'VERIFY_RETURN',
        'description' => "Verified {$r['return_type']} return by {$r['encoder_name']}. Remarks: " . ($r['verification_remarks']??'—'),
        'date' => $r['verified_at'],
        'badge' => 'badge-approved'
    ];
}

// Budget allocation approvals
$aWhere = "WHERE ba.admin_approved_at IS NOT NULL";
if ($dateFrom) $aWhere .= " AND DATE(ba.admin_approved_at) >= '" . $conn->real_escape_string($dateFrom) . "'";
if ($dateTo)   $aWhere .= " AND DATE(ba.admin_approved_at) <= '" . $conn->real_escape_string($dateTo) . "'";
$allocAppr = $conn->query("SELECT ba.*, adm.full_name AS admin_name, cr.full_name AS manager_name FROM budget_allocations ba JOIN users adm ON ba.admin_approved_by=adm.id JOIN users cr ON ba.created_by=cr.id $aWhere ORDER BY ba.admin_approved_at DESC")->fetch_all(MYSQLI_ASSOC);
foreach ($allocAppr as $a) {
    $entries[] = [
        'type' => 'Budget Approval',
        'actor' => $a['admin_name'],
        'action' => strtoupper($a['admin_approval_status']).'_ALLOCATION',
        'description' => "Admin " . $a['admin_approval_status'] . " allocation '{$a['allocation_title']}' (₱".number_format($a['allocated_amount'],2).") submitted by {$a['manager_name']}",
        'date' => $a['admin_approved_at'],
        'badge' => $a['admin_approval_status']==='approved'?'badge-approved':'badge-rejected'
    ];
}

// Sort all by date desc
usort($entries, fn($a,$b) => strtotime($b['date']) - strtotime($a['date']));

// Filter by type
if ($typeFilter !== 'all') {
    $entries = array_filter($entries, fn($e) => strtolower(str_replace(' ','',$e['type'])) === strtolower(str_replace(' ','',$typeFilter)));
}

include '../includes/header.php';
?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><span class="card-title">Filter Audit Trail</span></div>
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="margin:0;">
            <label style="font-size:12px;">Type</label>
            <select name="type" class="form-control">
                <option value="all" <?= $typeFilter==='all'?'selected':'' ?>>All Types</option>
                <option value="Activity" <?= $typeFilter==='Activity'?'selected':'' ?>>Activity Logs</option>
                <option value="ReturnVerify" <?= $typeFilter==='ReturnVerify'?'selected':'' ?>>Return Verifications</option>
                <option value="BudgetApproval" <?= $typeFilter==='BudgetApproval'?'selected':'' ?>>Budget Approvals</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size:12px;">Date From</label>
            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size:12px;">Date To</label>
            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
        </div>
        <button class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
        <a href="audit_trail.php" class="btn btn-outline btn-sm">Clear</a>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Centralized Audit Trail</span>
        <span style="font-size:13px;color:var(--text-muted)"><?= count($entries) ?> record(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Type</th><th>Actor</th><th>Action</th><th>Description</th><th>Date & Time</th></tr></thead>
            <tbody>
            <?php foreach (array_values($entries) as $i => $e): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><span class="badge <?= $e['badge'] ?>"><?= htmlspecialchars($e['type']) ?></span></td>
                <td><strong><?= htmlspecialchars($e['actor']) ?></strong></td>
                <td style="font-size:12px;font-family:monospace;background:var(--grey-100);padding:3px 6px;border-radius:4px;"><?= htmlspecialchars($e['action']) ?></td>
                <td style="font-size:13px;max-width:300px;"><?= htmlspecialchars($e['description']??'—') ?></td>
                <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;"><?= date('M d, Y H:i:s', strtotime($e['date'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($entries)): ?><tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:32px">No audit records found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
