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
// --- notifications helpers ---
function notify_user(int $user_id, string $message, ?string $link = null): void {
  $stmt = db()->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
  $stmt->execute([$user_id, $message, $link]);
}

function notify_admins(string $message, ?string $link = null): void {
  $ids = db()->query("SELECT id FROM users WHERE role='admin'")->fetchAll(PDO::FETCH_COLUMN);
  if (!$ids) return;
  $stmt = db()->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
  foreach ($ids as $id) { $stmt->execute([(int)$id, $message, $link]); }
}

function unread_notifications_for(int $user_id, int $limit = 3): array {
  $limit = max(1, min(10, (int)$limit)); // safety
  // Note: we interpolate the int-safe $limit because some PDO drivers dislike binding LIMIT
  $sql = "SELECT id, message, link, created_at FROM notifications
          WHERE user_id=? AND is_read=0
          ORDER BY created_at DESC
          LIMIT $limit";
  $stmt = db()->prepare($sql);
  $stmt->execute([$user_id]);
  return $stmt->fetchAll();
}

function mark_notifications_read(array $ids): void {
  if (!$ids) return;
  $place = implode(',', array_fill(0, count($ids), '?'));
  $stmt = db()->prepare("UPDATE notifications SET is_read=1 WHERE id IN ($place)");
  $stmt->execute($ids);
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

