<?php
require __DIR__ . '/../lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
  echo json_encode(db_all('SELECT * FROM vw_last_backup'));
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
?>
