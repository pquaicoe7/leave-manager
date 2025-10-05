<?php
require __DIR__ . '/../../config.php';
require_role('admin');

$msg = '';
$err = '';

// ---- Handle actions: create / update / delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    if ($action === 'create') {
      $name = trim($_POST['name'] ?? '');
      $max  = (int)($_POST['max_days'] ?? 0);

      if ($name === '' || $max < 0) {
        $err = 'Name is required and max days must be 0 or more.';
      } else {
        $stmt = db()->prepare("INSERT INTO leave_types (name, max_days) VALUES (?, ?)");
        $stmt->execute([$name, $max]);
        $msg = 'Leave type created.';
      }
    }

    if ($action === 'update') {
      $id   = (int)($_POST['id'] ?? 0);
      $name = trim($_POST['name'] ?? '');
      $max  = (int)($_POST['max_days'] ?? 0);

      if ($id <= 0 || $name === '' || $max < 0) {
        $err = 'Invalid edit: missing id/name or bad max days.';
      } else {
        // Ensure unique name except for this id
        $dup = db()->prepare("SELECT COUNT(*) FROM leave_types WHERE name = ? AND id <> ?");
        $dup->execute([$name, $id]);
        if ((int)$dup->fetchColumn() > 0) {
          $err = 'Another leave type already uses that name.';
        } else {
          $upd = db()->prepare("UPDATE leave_types SET name = ?, max_days = ? WHERE id = ?");
          $upd->execute([$name, $max, $id]);
          $msg = 'Leave type updated.';
        }
      }
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        $err = 'Invalid delete request.';
      } else {
        // Optional: block delete if in use (comment out to allow cascade)
        $inuse = db()->prepare("SELECT COUNT(*) FROM employee_leave_types WHERE leave_type_id = ?");
        $inuse->execute([$id]);
        if ((int)$inuse->fetchColumn() > 0) {
          $err = 'Cannot delete: type is assigned to one or more employees.';
        } else {
          $del = db()->prepare("DELETE FROM leave_types WHERE id = ?");
          $del->execute([$id]);
          $msg = 'Leave type deleted.';
        }
      }
    }
  } catch (Throwable $e) {
    // Handle duplicate name error gracefully
    if (str_contains(strtolower($e->getMessage()), 'duplicate')) {
      $err = 'That leave type name already exists.';
    } else {
      $err = 'Database error: ' . htmlspecialchars($e->getMessage());
    }
  }
}

// ---- Fetch all types for display ----
$types = db()->query("SELECT id, name, max_days, created_at FROM leave_types ORDER BY name ASC")->fetchAll();
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

  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <!-- Create new -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="mb-3">Create Leave Type</h5>
      <form method="post" class="row g-3">
        <input type="hidden" name="action" value="create">
        <div class="col-md-6">
          <label class="form-label">Name</label>
          <input name="name" class="form-control" placeholder="e.g., Annual Leave" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Max Days (per year)</label>
          <input type="number" name="max_days" class="form-control" min="0" value="10" required>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-primary">Add Type</button>
        </div>
      </form>
    </div>
  </div>

  <!-- List + actions -->
  <div class="card">
    <div class="card-body">
      <h5 class="mb-3">Existing Leave Types</h5>
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead>
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Max Days</th>
              <th>Created</th>
              <th style="min-width:200px;">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$types): ?>
            <tr><td colspan="5" class="text-center text-muted">No leave types yet.</td></tr>
          <?php else: foreach ($types as $t): ?>
            <tr>
              <td><?= (int)$t['id'] ?></td>
              <td><?= htmlspecialchars($t['name']) ?></td>
              <td><?= (int)$t['max_days'] ?></td>
              <td><?= htmlspecialchars($t['created_at']) ?></td>
              <td class="d-flex gap-2">
                <!-- Edit button triggers modal and passes current values via data-attrs -->
                <button type="button"
                        class="btn btn-sm btn-warning"
                        data-bs-toggle="modal"
                        data-bs-target="#editModal"
                        data-id="<?= (int)$t['id'] ?>"
                        data-name="<?= htmlspecialchars($t['name']) ?>"
                        data-max="<?= (int)$t['max_days'] ?>">
                  Edit
                </button>

                <!-- Delete -->
                <form method="post" onsubmit="return confirm('Delete this type?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="edit-id">
      <div class="modal-header">
        <h5 class="modal-title">Edit Leave Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input name="name" id="edit-name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Max Days (per year)</label>
          <input type="number" name="max_days" id="edit-max" class="form-control" min="0" required>
        </div>
        <div class="text-muted small">
          Renaming respects the unique name rule; if the name already exists, you’ll get a friendly error.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Save changes</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Fill the edit modal with the row's data
const editModal = document.getElementById('editModal');
editModal.addEventListener('show.bs.modal', event => {
  const btn = event.relatedTarget;
  document.getElementById('edit-id').value   = btn.getAttribute('data-id');
  document.getElementById('edit-name').value = btn.getAttribute('data-name');
  document.getElementById('edit-max').value  = btn.getAttribute('data-max');
});
</script>
</body>
</html>
