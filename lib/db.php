<?php
require_once __DIR__ . '/../config.php';

function db_all($sql) {
  $conn = oci_connect(DB_USER, DB_PASS, "//".DB_HOST.":".DB_PORT."/".DB_SERVICE, "AL32UTF8");
  if (!$conn) {
    $e = oci_error();
    throw new Exception("Oracle connect error: ".$e['message']);
  }

  $stid = oci_parse($conn, $sql);
  if (!$stid) {
    $e = oci_error($conn);
    throw new Exception("Parse error: ".$e['message']);
  }

  oci_execute($stid);
  $rows = [];
  while (($r = oci_fetch_assoc($stid)) != false) {
    $rows[] = array_change_key_case($r, CASE_UPPER);
  }

  oci_free_statement($stid);
  oci_close($conn);
  return $rows;
}
?>
