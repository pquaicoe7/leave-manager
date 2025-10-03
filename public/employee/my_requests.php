<?php
require __DIR__ . '/../../config.php';
require_role('employee');

$u = current_user();
$user_id = (int)$u['id'];

$stmt = db()->prepare("
  SELECT lr.*, lt.name AS leave_type_name
  FROM leave_requests lr
  JOIN leave_types lt ON lt.id = lr.leave_type_id
  WHERE lr.user_id = ?
  ORDER BY lr.created_at DESC
");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Employee | My Requests</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>My Requests</h3>
    <div>
      <a href="/eban-leave/public/employee/dashboard.php" class="btn btn-link">← Back</a>
      <a href="/eban-leave/public/logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
    </div>
  </div>

  <?php if (isset($_GET['submitted'])): ?>
    <div class="alert alert-success">Your leave request was submitted and is pending review.</div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
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
            <?php if (!$rows): ?>
              <tr><td colspan="7" class="text-center text-muted">No requests yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= htmlspecialchars($r['leave_type_name']) ?></td>
                <td><?= htmlspecialchars($r['start_date']) ?> → <?= htmlspecialchars($r['end_date']) ?></td>
                <td><?= (int)$r['days_requested'] ?></td>
                <td>
                  <?php if ($r['status'] === 'approved'): ?>
                    <span class="badge bg-success">Approved</span>
                  <?php elseif ($r['status'] === 'rejected'): ?>
                    <span class="badge bg-danger">Rejected</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Pending</span>
                  <?php endif; ?>
                </td>
                <td class="text-muted">
                  <?= $r['status']==='rejected' ? nl2br(htmlspecialchars($r['rejection_reason'])) : '' ?>
                </td>
                <td><?= htmlspecialchars($r['created_at']) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <p class="text-muted small mt-3">
    You can track whether your requests are Pending, Approved, or Rejected here.
    If rejected, the admin’s reason will appear in the table. :contentReference[oaicite:6]{index=6}
  </p>
</div>
</body>
</html>
