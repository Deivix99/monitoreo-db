<?php
require __DIR__ . '/../config.php';
require __DIR__ . '/db.php'; // el archivo donde tienes db_conn/db_all/db_exec

try {
  $rows = db_all('SELECT 1 AS ok FROM dual');
  echo "OK; SELECT 1 => " . $rows[0]['OK'] . PHP_EOL;
} catch (Throwable $e) {
  http_response_code(500);
  echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
