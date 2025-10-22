<?php
require __DIR__ . '/../lib/db.php';
$owner = $_GET['owner'] ?? 'MONITOR';
db_exec("BEGIN sp_recompile_invalids(:owner); END;", [":owner" => $owner]);
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'msg' => "Objetos inválidos recompilados en $owner"]);
