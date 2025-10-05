<?php
require __DIR__ . '/../../config.php';
require_role('admin');

$admin_id = (int) current_user()['id'];
$msg = ''; $err = '';

// -------- Actions (approve / reject) --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $req_id = (int)($_POST['request_id'] ?? 0);

  // Load request with type info for checks + nicer messages
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
    $note = trim($_POST['review_note'] ?? '');
    if ($note === '') {
      $err = "Please enter a short reason for approving.";
    } else {
      // Re-check remaining (in case something else got approved meanwhile)
      $used = db()->prepare("
        SELECT COALESCE(SUM(days_requested),0)
        FROM leave_requests
        WHERE user_id=? AND leave_type_id=? AND status='approved'
          AND YEAR(start_date)=YEAR(?) AND id<>?
      ");
      $used->execute([$req['user_id'], $req['leave_type_id'], $req['start_date'], $req['id']]);
      $remaining = (int)$req['max_days'] - (int)$used->fetchColumn();

      if ((int)$req['days_requested'] > $remaining) {
        $err = "Cannot approve: exceeds remaining {$remaining} day(s).";
      } else {
        $upd = db()->prepare("
          UPDATE leave_requests
          SET status='approved',
              rejection_reason=NULL,
              review_note=?,
              reviewed_by=?,
              reviewed_at=NOW()
          WHERE id=?
        ");
        $upd->execute([$note, $admin_id, $req_id]);
        $msg = "Request #{$req_id} approved.";

        // Notify employee (with note)
        notify_user(
          (int)$req['user_id'],
          "Approved: {$req['leave_type_name']} ({$req['start_date']} → {$req['end_date']}). Note: {$note}",
          "/eban-leave/public/employee/my_requests.php"
        );
      }
    }
  } elseif ($action === 'reject') {
    $note = trim($_POST['review_note'] ?? '');
    if ($note === '') {
      $err = "Rejection reason is required.";
    } else {
      $upd = db()->prepare("
        UPDATE leave_requests
        SET status='rejected',
            rejection_reason=?,
            review_note=?,
            reviewed_by=?,
            reviewed_at=NOW()
        WHERE id=?
      ");
      $upd->execute([$note, $note, $admin_id, $req_id]);
      $msg = "Request #{$req_id} rejected.";

      // Notify employee (with reason)
      notify_user(
        (int)$req['user_id'],
        "Rejected: {$req['leave_type_name']} ({$req['start_date']} → {$req['end_date']}). Reason: {$note}",
        "/eban-leave/public/employee/my_requests.php"
      );
    }
  }
}

// -------- Filter + fetch list --------
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
  <style>
    /* Let dropdown menus overflow beyond the responsive table wrapper (no scrolling needed) */
    .table-responsive { overflow: visible; }
  </style>
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
              <th>Reason (employee)</th><th>Review Note</th>
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
              <td class="text-muted">
                <?= nl2br(htmlspecialchars($r['review_note'] ?? ($r['rejection_reason'] ?? ''))) ?>
              </td>
              <td class="text-muted">
                <?= $r['reviewer_name'] ? htmlspecialchars($r['reviewer_name']) : '' ?>
                <?= $r['reviewed_at'] ? '<br><span class="small">'.htmlspecialchars($r['reviewed_at']).'</span>' : '' ?>
              </td>

              <!-- DROPDOWN ACTIONS (no clipping; no scroll) -->
              <td style="min-width:250px;">
  <?php if ($r['status'] === 'pending'): ?>
    <div class="dropdown">
      <button class="btn btn-secondary btn-sm dropdown-toggle w-100" type="button" data-bs-toggle="dropdown">
        Actions
      </button>
      <div class="dropdown-menu p-2" style="min-width: 250px;">
        <div class="d-flex gap-2">
          <!-- Approve -->
          <button
            type="button"
            class="btn btn-sm btn-success flex-fill"
            data-bs-toggle="modal"
            data-bs-target="#approveModal"
            data-id="<?= (int)$r['id'] ?>"
            data-emp="<?= htmlspecialchars($r['employee_name']) ?>"
            data-type="<?= htmlspecialchars($r['leave_type_name']) ?>"
            data-dates="<?= htmlspecialchars($r['start_date']) ?> → <?= htmlspecialchars($r['end_date']) ?>">
            Approve
          </button>

          <!-- Reject -->
          <button
            type="button"
            class="btn btn-sm btn-danger flex-fill"
            data-bs-toggle="modal"
            data-bs-target="#rejectModal"
            data-id="<?= (int)$r['id'] ?>"
            data-emp="<?= htmlspecialchars($r['employee_name']) ?>"
            data-type="<?= htmlspecialchars($r['leave_type_name']) ?>"
            data-dates="<?= htmlspecialchars($r['start_date']) ?> → <?= htmlspecialchars($r['end_date']) ?>">
            Reject
          </button>
        </div>
      </div>
    </div>
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

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="approve">
      <input type="hidden" name="request_id" id="app-id">
      <div class="modal-header">
        <h5 class="modal-title">Approve Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2 small text-muted" id="app-context"></div>
        <label class="form-label">Reason (required)</label>
        <textarea name="review_note" id="app-note" class="form-control" rows="3" required
                  placeholder="Why are you approving this request?"></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success">Approve</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="request_id" id="rej-id">
      <div class="modal-header">
        <h5 class="modal-title">Reject Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2 small text-muted" id="rej-context"></div>
        <label class="form-label">Reason (required)</label>
        <textarea name="review_note" id="rej-note" class="form-control" rows="3" required
                  placeholder="Why are you rejecting this request?"></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-danger">Reject</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Approve modal fill
const approveModal = document.getElementById('approveModal');
approveModal.addEventListener('show.bs.modal', (event) => {
  const btn = event.relatedTarget;
  document.getElementById('app-id').value = btn.getAttribute('data-id');
  const ctx = `${btn.getAttribute('data-emp')} — ${btn.getAttribute('data-type')} (${btn.getAttribute('data-dates')})`;
  document.getElementById('app-context').textContent = ctx;
  document.getElementById('app-note').value = '';
});

// Reject modal fill
const rejectModal = document.getElementById('rejectModal');
rejectModal.addEventListener('show.bs.modal', (event) => {
  const btn = event.relatedTarget;
  document.getElementById('rej-id').value = btn.getAttribute('data-id');
  const ctx = `${btn.getAttribute('data-emp')} — ${btn.getAttribute('data-type')} (${btn.getAttribute('data-dates')})`;
  document.getElementById('rej-context').textContent = ctx;
  document.getElementById('rej-note').value = '';
});
</script>
</body>
</html>
