<?php
require __DIR__ . '/../../config.php';
require_role('admin');

// quick stats
$employees_count = (int) db()->query("SELECT COUNT(*) FROM users WHERE role='employee'")->fetchColumn();
$types_count     = (int) db()->query("SELECT COUNT(*) FROM leave_types")->fetchColumn();
$pending_count   = (int) db()->query("SELECT COUNT(*) FROM leave_requests WHERE status='pending'")->fetchColumn();

// show red dot if there are NEW pending requests in last N hours
$NEW_WINDOW_HOURS = 24;
$hours = (int)$NEW_WINDOW_HOURS;
$new_24h_count = (int) db()->query("
  SELECT COUNT(*) FROM leave_requests
  WHERE status='pending'
    AND created_at >= (NOW() - INTERVAL $hours HOUR)
")->fetchColumn();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Admin | Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    .dot {
      display:inline-block; width:10px; height:10px; border-radius:50%;
      background:#dc3545; /* red */
      margin-left:8px; vertical-align:middle;
      box-shadow:0 0 0 2px #fff; /* small outline so it pops on light bg */
    }
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Admin Dashboard</h3>
    <div>
      <span class="me-3">Hello, <?= htmlspecialchars(current_user()['name']) ?></span>
      <a href="/eban-leave/public/logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Employees</div>
        <div class="h4 mb-0"><?= $employees_count ?></div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Leave Types</div>
        <div class="h4 mb-0"><?= $types_count ?></div>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm"><div class="card-body">
        <div class="text-muted small">Pending Requests</div>
        <div class="h4 mb-0"><?= $pending_count ?></div>
      </div></div>
    </div>
  </div>

  <!-- Navigation -->
  <div class="list-group">
    <a class="list-group-item list-group-item-action"
       href="/eban-leave/public/admin/leave_types.php">
      Manage Leave Types
    </a>

    <a class="list-group-item list-group-item-action"
       href="/eban-leave/public/admin/assign_leave.php">
      Assign Leave Types to Employees
    </a>

    <a class="list-group-item list-group-item-action"
       href="/eban-leave/public/admin/review_requests.php?status=pending">
      Review Leave Requests
      <?php if ($new_24h_count > 0): ?>
        <span class="dot" title="<?= $new_24h_count ?> new in last <?= $hours ?>h"></span>
      <?php endif; ?>
    </a>
  </div>
</div>
</body>
</html>
