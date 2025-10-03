<?php
require __DIR__ . '/../../config.php';
require_role('employee');

$u = current_user();
$user_id = (int)$u['id'];

// ----- Stats for this employee (safe prepared statements) -----
$stmt = db()->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id=? AND status='pending'");
$stmt->execute([$user_id]);
$pending_count = (int)$stmt->fetchColumn();

$stmt = db()->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id=? AND status='approved'");
$stmt->execute([$user_id]);
$approved_count = (int)$stmt->fetchColumn();

$stmt = db()->prepare("SELECT COUNT(*) FROM leave_requests WHERE user_id=? AND status='rejected'");
$stmt->execute([$user_id]);
$rejected_count = (int)$stmt->fetchColumn();

$total_count = $pending_count + $approved_count + $rejected_count;

// ----- Allowed leave types + remaining days this year -----
$allowed_types = db()->prepare("
  SELECT lt.id, lt.name, lt.max_days
  FROM employee_leave_types elt
  JOIN leave_types lt ON lt.id = elt.leave_type_id
  WHERE elt.user_id = ?
  ORDER BY lt.name ASC
");
$allowed_types->execute([$user_id]);
$allowed_types = $allowed_types->fetchAll();

$remaining_map = [];
if ($allowed_types) {
  $sum_stmt = db()->prepare("
    SELECT COALESCE(SUM(days_requested), 0) AS used
    FROM leave_requests
    WHERE user_id = ? AND leave_type_id = ? AND status='approved'
      AND YEAR(start_date) = YEAR(CURDATE())
  ");
  foreach ($allowed_types as $t) {
    $sum_stmt->execute([$user_id, (int)$t['id']]);
    $used = (int)$sum_stmt->fetchColumn();
    $remaining_map[(int)$t['id']] = max(0, (int)$t['max_days'] - $used);
  }
}

// ----- Recent 5 requests -----
$recent = db()->prepare("
  SELECT lr.*, lt.name AS leave_type_name
  FROM leave_requests lr
  JOIN leave_types lt ON lt.id = lr.leave_type_id
  WHERE lr.user_id = ?
  ORDER BY lr.created_at DESC
  LIMIT 5
");
$recent->execute([$user_id]);
$recent = $recent->fetchAll();

// ----- Unread notifications (show up to 3, then mark read) -----
$notes = unread_notifications_for($user_id, 3);
$note_ids = array_map(fn($n) => (int)$n['id'], $notes);

function badge(string $status): string {
  if ($status === 'approved') return '<span class="badge bg-success">Approved</span>';
  if ($status === 'rejected') return '<span class="badge bg-danger">Rejected</span>';
  return '<span class="badge bg-secondary">Pending</span>';
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Employee | Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Employee Dashboard</h3>
    <div>
      <span class="me-3">Hello, <?= htmlspecialchars($u['name']) ?></span>
      <a href="/eban-leave/public/logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
    </div>
  </div>

  <!-- Quick actions -->
  <div class="mb-3">
    <a href="/eban-leave/public/employee/apply_leave.php" class="btn btn-primary me-2">Apply for Leave</a>
    <a href="/eban-leave/public/employee/my_requests.php" class="btn btn-outline-primary">My Requests</a>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Total Requests</div>
          <div class="h4 mb-0"><?= $total_count ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Pending</div>
          <div class="h4 mb-0"><?= $pending_count ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Approved</div>
          <div class="h4 mb-0"><?= $approved_count ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Rejected</div>
          <div class="h4 mb-0"><?= $rejected_count ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Allowed types + remaining -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="mb-3">Your Allowed Leave Types (remaining this year)</h5>
      <?php if (!$allowed_types): ?>
        <div class="alert alert-warning mb-0">
          No leave types assigned yet. Please contact your admin.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle">
            <thead>
              <tr>
                <th>#</th>
                <th>Type</th>
                <th>Max Days</th>
                <th>Remaining (this year)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allowed_types as $t): ?>
                <tr>
                  <td><?= (int)$t['id'] ?></td>
                  <td><?= htmlspecialchars($t['name']) ?></td>
                  <td><?= (int)$t['max_days'] ?></td>
                  <td><?= (int)($remaining_map[(int)$t['id']] ?? (int)$t['max_days']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent requests -->
  <div class="card">
    <div class="card-body">
      <h5 class="mb-3">Recent Requests</h5>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Type</th>
              <th>Dates</th>
              <th>Days</th>
              <th>Status</th>
              <th>Rejection Reason</th>
              <th>Submitted</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$recent): ?>
              <tr><td colspan="7" class="text-center text-muted">No requests yet.</td></tr>
            <?php else: foreach ($recent as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= htmlspecialchars($r['leave_type_name']) ?></td>
                <td><?= htmlspecialchars($r['start_date']) ?> → <?= htmlspecialchars($r['end_date']) ?></td>
                <td><?= (int)$r['days_requested'] ?></td>
                <td><?= badge($r['status']) ?></td>
                <td class="text-muted">
                  <?= $r['status']==='rejected' ? nl2br(htmlspecialchars($r['rejection_reason'])) : '' ?>
                </td>
                <td><?= htmlspecialchars($r['created_at']) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <a href="/eban-leave/public/employee/my_requests.php" class="btn btn-link">View all →</a>
    </div>
  </div>
</div>

<?php if (!empty($notes)): ?>
<!-- Toasts (one-time popups) -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:1080; top: 70px;">

  <?php foreach ($notes as $n): ?>
    <div class="toast show mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="8000">
      <div class="toast-header">
        <strong class="me-auto">Notification</strong>
        <small><?= htmlspecialchars($n['created_at']) ?></small>
        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body">
        <?= htmlspecialchars($n['message']) ?>
        <?php if (!empty($n['link'])): ?>
          <div class="mt-2">
            <a href="<?= htmlspecialchars($n['link']) ?>" class="btn btn-sm btn-primary">Open</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Bootstrap JS + auto-show toasts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.querySelectorAll('.toast').forEach(t => new bootstrap.Toast(t, { delay: 8000 }).show());
</script>

<?php
// mark as read so they only show once
if (!empty($note_ids)) { mark_notifications_read($note_ids); }
?>

</body>
</html>
