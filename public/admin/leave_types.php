<?php
require __DIR__ . '/../../config.php';
require_role('admin');

$msg = ''; $err = '';

// Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  $name = trim($_POST['name'] ?? '');
  $max_days = (int)($_POST['max_days'] ?? 0);
  if ($name === '' || $max_days <= 0) {
    $err = "Name and a positive 'Max Days' are required.";
  } else {
    try {
      $stmt = db()->prepare("INSERT INTO leave_types (name, max_days) VALUES (?, ?)");
      $stmt->execute([$name, $max_days]);
      $msg = "Leave type created.";
    } catch (PDOException $e) {
      if ($e->getCode() === '23000') $err = "A leave type with that name already exists.";
      else $err = "Error creating type: " . $e->getMessage();
    }
  }
}

// Delete
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  if ($id > 0) {
    try {
      $stmt = db()->prepare("DELETE FROM leave_types WHERE id = ?");
      $stmt->execute([$id]);
      header('Location: /eban-leave/public/admin/leave_types.php?deleted=1');
      exit;
    } catch (PDOException $e) {
      $err = "Cannot delete this type because it is in use.";
    }
  }
}

// List
$types = db()->query("SELECT * FROM leave_types ORDER BY name ASC")->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Admin | Manage Leave Types</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Manage Leave Types</h3>
    <div>
      <a href="/eban-leave/public/admin/dashboard.php" class="btn btn-link">← Back</a>
      <a href="/eban-leave/public/logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
    </div>
  </div>

  <?php if (!empty($_GET['deleted'])): ?><div class="alert alert-success">Leave type deleted.</div><?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="card mb-4">
    <div class="card-body">
      <h5 class="mb-3">Create Leave Type</h5>
      <form method="post" class="row g-3">
        <input type="hidden" name="action" value="create">
        <div class="col-md-6">
          <label class="form-label">Leave Type Name</label>
          <input name="name" class="form-control" placeholder="Annual Leave" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Max Days</label>
          <input name="max_days" type="number" min="1" class="form-control" placeholder="20" required>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-primary w-100">Add Type</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h5 class="mb-3">Existing Types</h5>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead><tr><th>#</th><th>Name</th><th>Max Days</th><th class="text-end"></th></tr></thead>
          <tbody>
            <?php if (!$types): ?>
              <tr><td colspan="4" class="text-center text-muted">No leave types yet.</td></tr>
            <?php else: foreach ($types as $t): ?>
              <tr>
                <td><?= (int)$t['id'] ?></td>
                <td><?= htmlspecialchars($t['name']) ?></td>
                <td><?= (int)$t['max_days'] ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-danger"
                     href="/eban-leave/public/admin/leave_types.php?delete=<?= (int)$t['id'] ?>"
                     onclick="return confirm('Delete this leave type?');">Delete</a>
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
