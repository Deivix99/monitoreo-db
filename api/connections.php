<?php
require __DIR__ . '/../lib/db.php';
$out = [
  'summary' => db_all("SELECT * FROM vw_active_sessions"),
  'detail'  => db_all("SELECT * FROM vw_sessions_detail FETCH FIRST 20 ROWS ONLY")
];
header('Content-Type: application/json');
echo json_encode($out);
