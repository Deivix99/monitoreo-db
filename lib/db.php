<?php
require __DIR__ . '/../config.php';

function db_conn() {
  $connStr = DB_HOST . ':' . DB_PORT . '/' . DB_SERVICE;
  $conn = oci_connect(DB_USER, DB_PASS, $connStr, 'AL32UTF8');
  if (!$conn) {
    $e = oci_error();
    throw new RuntimeException('OCI connect error: ' . $e['message']);
  }
  return $conn;
}

function db_all($sql, $binds = []) {
  $c = db_conn();
  $s = oci_parse($c, $sql);
  foreach ($binds as $k => $v) oci_bind_by_name($s, $k, $binds[$k]);
  $ok = oci_execute($s); // <= valida ejecución
  if (!$ok) {
    $e = oci_error($s);
    oci_free_statement($s);
    oci_close($c);
    throw new RuntimeException('OCI query error: ' . $e['message'] . ' | SQL: ' . $sql);
  }
  $rows = [];
  while (($r = oci_fetch_assoc($s)) !== false) $rows[] = $r;
  oci_free_statement($s);
  oci_close($c);
  return $rows;
}


function db_exec($plsql, $binds = []) {
  $c = db_conn();
  $s = oci_parse($c, $plsql);
  foreach ($binds as $k => $v) oci_bind_by_name($s, $k, $binds[$k]);
  $ok = oci_execute($s);
  if (!$ok) {
    $e = oci_error($s);
    throw new RuntimeException('OCI exec error: ' . $e['message']);
  }
  oci_free_statement($s);
  oci_close($c);
  return true;
}
