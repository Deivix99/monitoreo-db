<?php
echo "Has oci_connect? ", function_exists('oci_connect') ? "YES\n" : "NO\n";
$c = @oci_connect('USUARIO', 'CLAVE', '127.0.0.1:1521/XEPDB1', 'AL32UTF8');
if (!$c) { $e = oci_error(); die("Connect error: {$e['message']}\n"); }
$s = oci_parse($c, 'SELECT 1 AS ok FROM dual');
oci_execute($s);
$r = oci_fetch_assoc($s);
var_dump($r);
oci_free_statement($s);
oci_close($c);
