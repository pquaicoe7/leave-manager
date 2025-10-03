<?php
require __DIR__ . '/../config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $password = $_POST['password'] ?? '';

  $stmt = db()->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->execute([$email]);
  $user = $stmt->fetch();

  if ($user && password_verify($password, $user['password_hash'])) {
    $_SESSION['user'] = [
      'id'    => $user['id'],
      'name'  => $user['name'],
      'email' => $user['email'],
      'role'  => $user['role'],
    ];
    if ($user['role'] === 'admin') {
      header('Location: /eban-leave/public/admin/dashboard.php');
    } else {
      header('Location: /eban-leave/public/employee/dashboard.php');
    }
    exit;
  } else {
    $error = 'Invalid email or password';
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Eban Leave | Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-4">
        <div class="card shadow-sm">
          <div class="card-body">
            <h4 class="mb-3">Login</h4>
            <?php if ($error): ?>
              <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
              <div class="mb-3">
                <label class="form-label">Email</label>
                <input name="email" type="email" class="form-control" required />
              </div>
              <div class="mb-3">
                <label class="form-label">Password</label>
                <input name="password" type="password" class="form-control" required />
              </div>
              <button class="btn btn-primary w-100">Sign in</button>
            </form>
            <p class="text-muted small mt-3">
              Admin: admin@eban.test / admin123<br>
              Employee: jane@eban.test / employee123
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
