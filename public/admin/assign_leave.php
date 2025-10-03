<?php
require __DIR__ . '/../../config.php';
require_role('admin');

$employees = db()->query("SELECT id, name, email FROM users WHERE role='employee' ORDER BY name ASC")->fetchAll();
$user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
  $user_id = (int) $_POST['user_id'];
  $selected = array_map('intval', $_POST['leave_types'] ?? []);

  $check = db()->prepare("SELECT id FROM users WHERE id = ? AND role='employee'");
  $check->execute([$user_id]);
  if (!$check->fetchColumn()) {
    header('Location: /eban-leave/public/admin/assign_leave.php?err=unknown_employee');
    exit;
  }

  try {
    db()->beginTransaction();
    db()->prepare("DELETE FROM employee_leave_types WHERE user_id = ?")->execute([$user_id]);
    if ($selected) {
      $ins = db()->prepare("INSERT INTO employee_leave_types (user_id, leave_type_id) VALUES (?, ?)");
      foreach ($selected as $ltid) $ins->execute([$user_id, $ltid]);
    }
    db()->commit();
    header('Location: /eban-leave/public/admin/assign_leave.php?user_id='.$user_id.'&saved=1');
    exit;
  } catch (Throwable $e) {
    db()->rollBack();
    header('Location: /eban-leave/public/admin/assign_leave.php?user_id='.$user_id.'&err=save_failed');
    exit;
  }
}

$leave_types = db()->query("SELECT id, name FROM leave_types ORDER BY name ASC")->fetchAll();
$current_ids = [];
if ($user_id) {
  $stmt = db()->prepare("SELECT leave_type_id FROM employee_leave_types WHERE user_id = ?");
  $stmt->execute([$user_id]);
  $current_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Admin | Assign Leave Types</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Assign Leave Types to Employees</h3>
    <div>
      <a href="/eban-leave/public/admin/dashboard.php" class="btn btn-link">← Back</a>
      <a href="/eban-leave/public/logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
    </div>
  </div>

  <?php if (isset($_GET['saved'])): ?><div class="alert alert-success">Assignments updated.</div>
  <?php elseif (isset($_GET['err'])): ?><div class="alert alert-danger">There was a problem. Try again.</div><?php endif; ?>

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Select Employee</label>
          <select name="user_id" class="form-select" required>
            <option value="">-- choose employee --</option>
            <?php foreach ($employees as $emp): ?>
              <option value="<?= (int)$emp['id'] ?>" <?= $user_id===(int)$emp['id']?'selected':'' ?>>
                <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['email']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-primary w-100">Load</button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($user_id && $leave_types): ?>
  <div class="card">
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="user_id" value="<?= (int)$user_id ?>">
        <div class="col-12">
          <h5 class="mb-3">Allowed Leave Types</h5>
          <div class="row">
            <?php foreach ($leave_types as $t): ?>
              <div class="col-md-4 mb-2">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox"
                         id="lt<?= (int)$t['id'] ?>" name="leave_types[]"
                         value="<?= (int)$t['id'] ?>"
                         <?= in_array((int)$t['id'], $current_ids, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="lt<?= (int)$t['id'] ?>">
                    <?= htmlspecialchars($t['name']) ?>
                  </label>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="col-12">
          <button class="btn btn-primary">Save Assignments</button>
        </div>
      </form>
    </div>
  </div>
  <?php elseif (!$leave_types): ?>
    <div class="alert alert-warning">
      No leave types found. Create some first on
      <a href="/eban-leave/public/admin/leave_types.php">Manage Leave Types</a>.
    </div>
  <?php endif; ?>
</div>
</body>
</html>
