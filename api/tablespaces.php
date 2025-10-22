<?php
// api/tablespaces.php
require __DIR__ . '/../lib/db.php';
header('Content-Type: application/json');

try {
  $rows = db_all("
    SELECT
      tablespace_name AS TABLESPACE_NAME,
      ROUND(total_bytes/1024/1024, 2) AS TOTAL_MB,
      ROUND(used_bytes /1024/1024, 2) AS USED_MB,
      ROUND(CASE WHEN total_bytes>0 THEN (used_bytes/total_bytes)*100 END, 2) AS USED_PCT
    FROM vw_tablespace_usage
    ORDER BY USED_PCT DESC NULLS LAST
  ");
  echo json_encode($rows);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>true,'msg'=>$e->getMessage()]);
}
