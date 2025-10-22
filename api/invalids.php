<?php
require __DIR__ . '/../lib/db.php';
header('Content-Type: application/json');
echo json_encode(db_all("SELECT * FROM vw_invalid_objects FETCH FIRST 50 ROWS ONLY"));
