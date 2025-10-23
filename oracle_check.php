<?php
require __DIR__ . '/lib/db.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $r = db_all('SELECT 1 AS OK FROM DUAL');
  echo json_encode(['ok' => true, 'result' => $r]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
