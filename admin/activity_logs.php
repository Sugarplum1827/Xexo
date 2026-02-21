<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');
$pageTitle = 'Activity Logs';

$search = $conn->real_escape_string(trim($_GET['search'] ?? ''));
$where  = $search ? "WHERE u.full_name LIKE '%$search%' OR l.action LIKE '%$search%' OR l.description LIKE '%$search%'" : '';
$logs   = $conn->query("SELECT l.*, u.full_name, u.role FROM activity_logs l LEFT JOIN users u ON l.user_id=u.id $where ORDER BY l.created_at DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <span class="card-title">System Activity Log</span>
        <form method="GET" style="display:flex;gap:8px;">
            <input type="text" name="search" class="form-control" style="width:220px;padding:8px 12px;" placeholder="Search logs…" value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>User</th><th>Role</th><th>Action</th><th>Description</th><th>IP</th><th>Date & Time</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $i => $l): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($l['full_name'] ?? 'System') ?></strong></td>
                <td><span style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($l['role'] ?? '—') ?></span></td>
                <td><code style="background:var(--cream);padding:2px 8px;border-radius:5px;font-size:12px;"><?= htmlspecialchars($l['action']) ?></code></td>
                <td style="font-size:13px;"><?= htmlspecialchars($l['description']) ?></td>
                <td style="font-size:12px;color:var(--text-muted)"><?= htmlspecialchars($l['ip_address']) ?></td>
                <td style="font-size:12px;color:var(--text-muted);white-space:nowrap"><?= date('M d, Y H:i:s', strtotime($l['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
