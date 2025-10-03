<?php
// seed.php
require __DIR__ . '/config.php';

try {
  // Has the seeding already been done?
  $count = db()->query("SELECT COUNT(*) AS c FROM users")->fetch();
  if (($count['c'] ?? 0) > 0) {
    echo "Users already exist. If you want to reseed, empty the 'users' table first.";
    exit;
  }

  $stmt = db()->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");

  // Admin user
  $stmt->execute([
    'Eban Admin',
    'admin@eban.test',
    password_hash('admin123', PASSWORD_BCRYPT),
    'admin'
  ]);

  // Sample employee
  $stmt->execute([
    'Jane Employee',
    'jane@eban.test',
    password_hash('employee123', PASSWORD_BCRYPT),
    'employee'
  ]);

  echo "Seed complete.
  Admin: admin@eban.test / admin123
  Employee: jane@eban.test / employee123";
} catch (Throwable $e) {
  echo "Error seeding: " . htmlspecialchars($e->getMessage());
}
