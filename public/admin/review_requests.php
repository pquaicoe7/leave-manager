<?php
require __DIR__ . '/../../config.php';
require_role('admin');

$admin_id = (int) current_user()['id'];
$msg = ''; $err = '';

// -------- Actions (approve / reject) --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $req_id = (int)($_POST['request_id'] ?? 0);

  // UPDATED: include leave_type_name for nicer messages
  $q = db()->prepare("
    SELECT lr.*, lt.max_days, lt.name AS leave_type_name
    FROM leave_requests lr
    JOIN leave_types lt ON lt.id = lr.leave_type_id
    WHERE lr.id = ?
  ");
  $q->execute([$req_id]);
  $req = $q->fetch();

  if (!$req) {
    $err = "Request not found.";
  } elseif ($action === 'approve') {
    // check remaining before approving
    $used = db()->prepare("
      SELECT COALESCE(SUM(days_requested),0)
      FROM leave_requests
      WHERE user_id=? AND leave_type_id=? AND status='approved'
        AND YEAR(start_date)=YEAR(?) AND id<>?
    ");
    $used->execute([$req['user_id'],$req['leave_type_id'],$req['start_date'],$req['id']]);
    $remaining = (int)$req['max_days'] - (int)$used->fetchColumn();

    if ((int)$req['days_requested'] > $remaining) {
      $err = "Cannot approve: exceeds remaining {$remaining} day(s).";
    } else {
      $upd = db()->prepare("
        UPDATE leave_requests
        SET status='approved', rejection_reason=NULL, reviewed_by=?, reviewed_at=NOW()
        WHERE id=?
      ");
      $upd->execute([$admin_id,$req_id]);
      $msg = "Request #{$req_id} approved.";

      // NEW: notify the employee
      notify_user(
        (int)$req['user_id'],
        "Approved: {$req['leave_type_name']} ({$req['start_date']} → {$req['end_date']})",
        "/eban-leave/public/employee/my_requests.php"
      );
    }
  } elseif ($action === 'reject') {
    $reason = trim($_POST['rejection_reason'] ?? '');
    if ($reason === '') {
      $err = "Rejection reason is required.";
    } else {
      $upd = db()->prepare("
        UPDATE leave_requests
        SET status='rejected', rejection_reason=?, reviewed_by=?, reviewed_at=NOW()
        WHERE id=?
      ");
      $upd->execute([$reason,$admin_id,$req_id]);
      $msg = "Request #{$req_id} rejected.";

      // NEW: notify the employee
      notify_user(
        (int)$req['user_id'],
        "Rejected: {$req['leave_type_name']} ({$req['start_date']} → {$req['end_date']})",
        "/eban-leave/public/employee/my_requests.php"
      );
    }
  }
}

// -------- Filter + fetch --------
$status = $_GET['status'] ?? 'pending';
if (!in_array($status, ['all','pending','approved','rejected'], true)) $status = 'pending';

$where = $status === 'all' ? '' : 'WHERE lr.status = ?';
$params = $status === 'all' ? [] : [$status];

$stmt = db()->prepare("
  SELECT lr.*, lt.name AS leave_type_name,
         u.name AS employee_name, u.email AS employee_email,
         r.name AS reviewer_name
  FROM leave_requests lr
  JOIN users u ON u.id = lr.user_id
  JOIN leave_types lt ON lt.id = lr.leave_type_id
  LEFT JOIN users r ON r.id = lr.reviewed_by
  $where
  ORDER BY lr.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

function badge(string $s): string {
  return $s==='approved' ? '<span class="badge bg-success">Approved</span>'
       : ($s==='rejected' ? '<span class="badge bg-danger">Rejected</span>'
       : '<span class="badge bg-secondary">Pending</span>');
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Admin | Review Requests</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Review Leave Requests</h3>
    <div>
      <a href="/eban-leave/public/admin/dashboard.php" class="btn btn-link">← Back</a>
      <a href="/eban-leave/public/logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <!-- tabs -->
  <ul class="nav nav-tabs mb-3">
    <?php foreach (['pending','approved','rejected','all'] as $tab): ?>
      <li class="nav-item">
        <a class="nav-link <?= $status===$tab?'active':'' ?>"
           href="review_requests.php?status=<?= $tab ?>"><?= ucfirst($tab) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="card">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>#</th><th>Employee</th><th>Type</th><th>Dates</th>
              <th>Days</th><th>Status</th>
              <th>Reason (employee)</th><th>Rejection Reason</th>
              <th>Reviewed By</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="10" class="text-center text-muted">No requests.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td>
                <?= htmlspecialchars($r['employee_name']) ?><br>
                <span class="small text-muted"><?= htmlspecialchars($r['employee_email']) ?></span>
              </td>
              <td><?= htmlspecialchars($r['leave_type_name']) ?></td>
              <td><?= htmlspecialchars($r['start_date']) ?> → <?= htmlspecialchars($r['end_date']) ?></td>
              <td><?= (int)$r['days_requested'] ?></td>
              <td><?= badge($r['status']) ?></td>
              <td class="text-muted"><?= nl2br(htmlspecialchars($r['reason'] ?? '')) ?></td>
              <td class="text-muted"><?= nl2br(htmlspecialchars($r['rejection_reason'] ?? '')) ?></td>
              <td class="text-muted">
                <?= $r['reviewer_name'] ? htmlspecialchars($r['reviewer_name']) : '' ?>
                <?= $r['reviewed_at'] ? '<br><span class="small">'.htmlspecialchars($r['reviewed_at']).'</span>' : '' ?>
              </td>
              <td style="min-width:220px;">
                <?php if ($r['status'] === 'pending'): ?>
                  <form method="post" class="mb-2">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-success w-100">Approve</button>
                  </form>
                  <form method="post">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                    <div class="input-group input-group-sm">
                      <input name="rejection_reason" class="form-control" placeholder="Reason" required>
                      <button class="btn btn-danger">Reject</button>
                    </div>
                  </form>
                <?php else: ?>
                  <span class="text-muted small">No actions</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
