<?php
require __DIR__ . '/../lib/db.php';
$type = ($_GET['type'] ?? 'cpu') === 'elapsed' ? 'elapsed' : 'cpu';
$sql  = $type === 'elapsed' ? "SELECT * FROM vw_top_sql_elapsed" : "SELECT * FROM vw_top_sql_cpu";
header('Content-Type: application/json');
echo json_encode(db_all($sql));
