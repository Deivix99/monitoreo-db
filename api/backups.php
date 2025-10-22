<?php
require __DIR__ . '/../lib/db.php';
header('Content-Type: application/json');
$rows = db_all("SELECT * FROM vw_last_backup");
echo json_encode($rows);
