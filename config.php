<?php
// config.php
session_start();

// Edit these if your MySQL creds differ
$DB_HOST = '127.0.0.1';
$DB_NAME = 'eban_leave_db';
$DB_USER = 'root';
$DB_PASS = ''; // XAMPP default is empty

$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
  // Show a clear error during setup; later we’ll hide this.
  die("DB connection failed: " . $e->getMessage());
}

// Small helper to access PDO anywhere
function db(): PDO {
  global $pdo;
  return $pdo;
  
}
// Auth helpers
function is_logged_in(): bool {
  return isset($_SESSION['user']);
}
function current_user() {
  return $_SESSION['user'] ?? null;
}
function require_role(string $role): void {
  $u = current_user();
  if (!$u || $u['role'] !== $role) {
    header('Location: /eban-leave/public/index.php');
    exit;
  }
}

