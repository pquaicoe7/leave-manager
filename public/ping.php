<?php
require __DIR__ . '/../config.php';

try {
  // List tables in our database to prove the connection works
  $stmt = db()->query("SHOW TABLES");
  $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

  header('Content-Type: text/plain');
  echo "✅ Connected to DB: eban_leave_db\n";
  echo "Tables found (" . count($tables) . "):\n";
  foreach ($tables as $t) {
    echo " - {$t}\n";
  }
} catch (Throwable $e) {
  header('Content-Type: text/plain');
  echo "❌ Query failed: " . $e->getMessage();
}
