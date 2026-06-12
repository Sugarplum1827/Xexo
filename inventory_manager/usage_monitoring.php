<?php
require_once '../includes/auth.php';
requireRole('inventory_manager', '../index.php');
$pageTitle = 'Inventory Usage Monitoring';

// Filters
$filterEncoder = $_GET['encoder'] ?? '';
$filterDate    = $_GET['date'] ?? '';
$where = "WHERE 1=1";
if ($filterEncoder) $where .= " AND ei.encoder_id=" . (int)$filterEncoder;
if ($filterDate)    $where .= " AND DATE(ei.assigned_date)='" . $conn->real_escape_string($filterDate) . "'";

$releases = $conn->query("
    SELECT ei.*, u.full_name AS encoder_name, ir.description AS purpose
    FROM encoder_inventory ei
    JOIN users u ON ei.encoder_id = u.id
    JOIN inventory_requests ir ON ei.inventory_request_id = ir.id
    $where
    ORDER BY ei.assigned_date DESC
")->fetch_all(MYSQLI_ASSOC);

$encoders = $conn->query("SELECT id, full_name FROM users WHERE role='user' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-header"><span class="card-title">Filter</span></div>
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div class="form-group" style="margin:0;min-width:200px;">
            <label style="font-size:12px;">Encoder</label>
            <select name="encoder" class="form-control">
                <option value="">All Encoders</option>
                <?php foreach ($encoders as $e): ?>
                <option value="<?= $e['id'] ?>" <?= $filterEncoder==$e['id']?'selected':'' ?>><?= htmlspecialchars($e['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label style="font-size:12px;">Release Date</label>
            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
        </div>
        <button class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
        <a href="usage_monitoring.php" class="btn btn-outline btn-sm">Clear</a>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Released Inventory Items</span>
        <span style="font-size:13px;color:var(--text-muted)"><?= count($releases) ?> record(s)</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Encoder</th><th>Item</th><th>Unit</th><th>Qty Released</th><th>Qty Consumed</th><th>Qty Remaining</th><th>Purpose</th><th>Release Date</th></tr></thead>
            <tbody>
            <?php foreach ($releases as $i => $r):
                $rem = $r['quantity_assigned'] - $r['quantity_consumed'];
            ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($r['encoder_name']) ?></td>
                <td><strong><?= htmlspecialchars($r['item_name']) ?></strong></td>
                <td><?= htmlspecialchars($r['unit']) ?></td>
                <td><?= $r['quantity_assigned'] ?></td>
                <td><?= $r['quantity_consumed'] ?></td>
                <td style="font-weight:700;color:<?= $rem>0?'var(--success)':'var(--text-muted)' ?>"><?= round($rem,3) ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($r['purpose']??'—') ?></td>
                <td style="font-size:12px;"><?= date('M d, Y H:i', strtotime($r['assigned_date'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($releases)): ?><tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:32px">No records found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
