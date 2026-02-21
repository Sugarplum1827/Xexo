<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');
$pageTitle = 'End-of-Term Archive';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'archive') {
    $label     = trim($_POST['label']);
    $start     = $_POST['start_date'];
    $end       = $_POST['end_date'];
    $uid       = (int)$_SESSION['user_id'];

    $purchases = $conn->query("SELECT p.*, u.full_name FROM purchases p LEFT JOIN users u ON p.submitted_by=u.id WHERE purchase_date BETWEEN '$start' AND '$end'")->fetch_all(MYSQLI_ASSOC);
    $totalExp  = (float)$conn->query("SELECT COALESCE(SUM(total_price),0) s FROM purchases WHERE status='approved' AND purchase_date BETWEEN '$start' AND '$end'")->fetch_assoc()['s'];
    $budget    = $conn->query("SELECT allocated_amount FROM budgets WHERE is_active=1 LIMIT 1")->fetch_assoc();
    $budgetAmt = (float)($budget['allocated_amount'] ?? 0);
    $snapshot  = json_encode($purchases);

    $stmt = $conn->prepare("INSERT INTO archives (label, semester_start, semester_end, total_expenses, total_budget, archived_by, archived_at, data_snapshot) VALUES (?,?,?,?,?,?,NOW(),?)");
    $stmt->bind_param("sssddis", $label, $start, $end, $totalExp, $budgetAmt, $uid, $snapshot);

    if ($stmt->execute()) {
        logActivity($conn, 'ARCHIVE', "Archived semester: $label");
        $msg = "Archive '$label' created successfully.";
        $msgType = 'success';
    } else {
        $msg = "Error: " . $conn->error;
        $msgType = 'danger';
    }
    $stmt->close();
}

$archives = $conn->query("SELECT a.*, u.full_name FROM archives a LEFT JOIN users u ON a.archived_by=u.id ORDER BY a.archived_at DESC")->fetch_all(MYSQLI_ASSOC);
include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-check-circle"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="grid-2" style="gap:24px;">
    <div class="card">
        <div class="card-header"><span class="card-title">Create New Archive</span></div>
        <form method="POST">
            <input type="hidden" name="action" value="archive">
            <div class="form-group">
                <label>Archive Label</label>
                <input type="text" name="label" class="form-control" required placeholder="e.g. 2nd Semester 2025-2026">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" class="form-control" required>
                </div>
            </div>
            <button type="submit" class="btn btn-gold" style="width:100%" onclick="return confirm('Archive all records for this period?')">
                <i class="fas fa-archive"></i> Create Archive
            </button>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Archived Semesters</span></div>
        <?php if (empty($archives)): ?>
        <p style="color:var(--text-muted);font-size:14px;text-align:center;padding:24px">No archives yet.</p>
        <?php endif; ?>
        <?php foreach ($archives as $a): ?>
        <div style="border:1px solid var(--cream-dark);border-radius:10px;padding:16px;margin-bottom:12px;">
            <div style="font-family:'Fraunces',serif;font-size:16px;font-weight:700;color:var(--forest);margin-bottom:6px;"><?= htmlspecialchars($a['label']) ?></div>
            <div style="font-size:13px;color:var(--text-muted);margin-bottom:8px;"><?= $a['semester_start'] ?> to <?= $a['semester_end'] ?></div>
            <div style="display:flex;gap:16px;font-size:13px;">
                <span>Budget: <strong><?= formatCurrency($a['total_budget']) ?></strong></span>
                <span>Expenses: <strong style="color:var(--danger)"><?= formatCurrency($a['total_expenses']) ?></strong></span>
            </div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:6px;">Archived by <?= htmlspecialchars($a['full_name']??'—') ?> on <?= date('M d, Y', strtotime($a['archived_at'])) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>