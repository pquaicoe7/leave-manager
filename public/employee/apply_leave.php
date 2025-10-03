<?php
require __DIR__ . '/../../config.php';
require_role('employee');

$u = current_user();
$user_id = (int)$u['id'];

$msg = '';
$err = '';

// ------------------ Allowed leave types for this employee ------------------
$types_stmt = db()->prepare("
  SELECT lt.id, lt.name, lt.max_days
  FROM employee_leave_types elt
  JOIN leave_types lt ON lt.id = elt.leave_type_id
  WHERE elt.user_id = ?
  ORDER BY lt.name ASC
");
$types_stmt->execute([$user_id]);
$allowed_types = $types_stmt->fetchAll();

// Map remaining days for each type (approved days in current year are counted)
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

// ------------------ Handle submit ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $leave_type_id = (int)($_POST['leave_type_id'] ?? 0);
  $start_date    = trim($_POST['start_date'] ?? '');
  $end_date      = trim($_POST['end_date'] ?? '');
  $reason        = trim($_POST['reason'] ?? '');

  // ensure the chosen leave type is actually assigned to this employee
  $allowed_ids = array_map(fn($x) => (int)$x['id'], $allowed_types);
  if (!in_array($leave_type_id, $allowed_ids, true)) {
    $err = "You are not allowed to request this leave type.";
  } else {
    try {
      $start = new DateTime($start_date);
      $end   = new DateTime($end_date);

      if ($end < $start) {
        $err = "End date cannot be before start date.";
      } else {
        // inclusive day count
        $days_requested = $start->diff($end)->days + 1;

        $remaining = (int)($remaining_map[$leave_type_id] ?? 0);
        if ($days_requested <= 0) {
          $err = "Requested days must be at least 1.";
        } elseif ($days_requested > $remaining) {
          $err = "You are requesting {$days_requested} day(s) but only {$remaining} day(s) remain for this leave type this year.";
        } else {
          // -------- Overlap check with existing pending/approved requests --------
          $overlap = db()->prepare("
            SELECT COUNT(*) FROM leave_requests
            WHERE user_id = ?
              AND status IN ('pending','approved')
              AND NOT (end_date < ? OR start_date > ?)
          ");
          $overlap->execute([
            $user_id,
            $start->format('Y-m-d'),
            $end->format('Y-m-d')
          ]);
          if ((int)$overlap->fetchColumn() > 0) {
            $err = "These dates overlap another pending or approved request. Choose different dates.";
          } else {
            // -------- Insert pending request --------
            $ins = db()->prepare("
              INSERT INTO leave_requests
                (user_id, leave_type_id, start_date, end_date, days_requested, reason, status)
              VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $ins->execute([
              $user_id,
              $leave_type_id,
              $start->format('Y-m-d'),
              $end->format('Y-m-d'),
              $days_requested,
              $reason
            ]);

            // go to list with success flag
            header('Location: /eban-leave/public/employee/my_requests.php?submitted=1');
            exit;
          }
        }
      }
    } catch (Throwable $e) {
      $err = "Invalid date(s).";
    }
  }
}

$today = date('Y-m-d');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Employee | Apply for Leave</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Apply for Leave</h3>
    <div>
      <a href="/eban-leave/public/employee/dashboard.php" class="btn btn-link">← Back</a>
      <a href="/eban-leave/public/logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <?php if (!$allowed_types): ?>
    <div class="alert alert-warning">
      You do not have any leave types assigned yet. Please contact your admin.
    </div>
  <?php else: ?>
    <div class="card">
      <div class="card-body">
        <form method="post" class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Leave Type</label>
            <select name="leave_type_id" class="form-select" required>
              <option value="">-- choose --</option>
              <?php foreach ($allowed_types as $t): ?>
                <?php $rem = (int)($remaining_map[(int)$t['id']] ?? 0); ?>
                <option value="<?= (int)$t['id'] ?>">
                  <?= htmlspecialchars($t['name']) ?> (max <?= (int)$t['max_days'] ?>, remaining <?= $rem ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" required min="<?= $today ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" required min="<?= $today ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Reason / Comment</label>
            <textarea name="reason" class="form-control" rows="3" placeholder="Optional"></textarea>
          </div>

          <div class="col-12">
            <button class="btn btn-primary">Submit Request</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
