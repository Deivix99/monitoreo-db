<?php
require __DIR__ . '/../lib/db.php';
$owner = $_GET['owner'] ?? 'MONITOR';  // o el esquema de tu app
db_exec("BEGIN sp_gather_schema_stats(:owner); END;", [":owner" => $owner]);
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'msg' => "Estadísticas recalculadas para $owner"]);
