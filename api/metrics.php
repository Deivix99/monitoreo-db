<?php
// api/metrics.php (robusto con fallbacks)
require __DIR__ . '/../lib/db.php';
header('Content-Type: application/json');

function q($sql, $binds = []) { return db_all($sql, $binds); }

try {
  /* 1) Intento con HISTÓRICO (30 muestras) */
  $sys = q("
    WITH base AS (
      SELECT DISTINCT end_time
      FROM v\$sysmetric_history
      WHERE group_id IN (2,3)
      ORDER BY end_time DESC
      FETCH FIRST 30 ROWS ONLY
    ),
    host AS (
      SELECT end_time, value AS val
      FROM v\$sysmetric_history
      WHERE group_id IN (2,3)
        AND metric_name IN ('Host CPU Utilization (%)')
    ),
    dbcpu AS (
      SELECT end_time, value*100 AS val
      FROM v\$sysmetric_history
      WHERE group_id IN (2,3)
        AND metric_name IN ('Database CPU Time Ratio','DB CPU Time Ratio')
    ),
    pgahit AS (
      SELECT end_time, value AS val
      FROM v\$sysmetric_history
      WHERE group_id IN (2,3)
        AND metric_name IN ('PGA Cache Hit %','PGA cache hit %')
    )
    SELECT
      TO_CHAR(b.end_time,'YYYY-MM-DD HH24:MI:SS') AS SAMPLE_TIME,
      h.val AS HOST_CPU_PCT,
      d.val AS DB_CPU_RATIO,
      p.val AS PGA_HIT_PCT
    FROM base b
    LEFT JOIN host  h ON h.end_time = b.end_time
    LEFT JOIN dbcpu d ON d.end_time = b.end_time
    LEFT JOIN pgahit p ON p.end_time = b.end_time
    ORDER BY b.end_time DESC
  ");

  /* 2) Muestra “en vivo” si history vino vacío */
  if (count($sys) === 0) {
    $one = q("
      SELECT
        TO_CHAR(SYSDATE,'YYYY-MM-DD HH24:MI:SS') AS SAMPLE_TIME,
        MAX(CASE WHEN metric_name IN ('Host CPU Utilization (%)') THEN value END) AS HOST_CPU_PCT,
        MAX(CASE WHEN metric_name IN ('Database CPU Time Ratio','DB CPU Time Ratio') THEN value*100 END) AS DB_CPU_RATIO,
        MAX(CASE WHEN metric_name IN ('PGA Cache Hit %','PGA cache hit %') THEN value END) AS PGA_HIT_PCT
      FROM v\$sysmetric
      WHERE group_id IN (2,3)
    ");
    $sys = $one ?: [];
  }

  /* 3) Fallbacks métrica por métrica (por si alguna viene NULL) */
  // Tomamos la última fila de sys (o creamos una vacía “en vivo”)
  $live = $sys[0] ?? ['SAMPLE_TIME'=>date('Y-m-d H:i:s'),
                      'HOST_CPU_PCT'=>null,'DB_CPU_RATIO'=>null,'PGA_HIT_PCT'=>null];

  // 3a) DB CPU % desde V$SYS_TIME_MODEL
  if ($live['DB_CPU_RATIO'] === null || $live['DB_CPU_RATIO'] === '') {
    $tm = q("SELECT
               SUM(CASE WHEN stat_name='DB CPU'  THEN value END) AS DB_CPU,
               SUM(CASE WHEN stat_name='DB time' THEN value END) AS DB_TIME
             FROM v\$sys_time_model");
    if ($tm && $tm[0]['DB_TIME'] > 0) {
      $live['DB_CPU_RATIO'] = round(($tm[0]['DB_CPU']/$tm[0]['DB_TIME'])*100, 2);
    }
  }

  // 3b) PGA Hit % desde V$PGASTAT
  if ($live['PGA_HIT_PCT'] === null || $live['PGA_HIT_PCT'] === '') {
    $pg = q("SELECT value FROM v\$pgastat WHERE name='cache hit percentage'");
    if ($pg && is_numeric($pg[0]['VALUE'])) {
      $live['PGA_HIT_PCT'] = round($pg[0]['VALUE'], 2);
    }
  }

  // 3c) Host CPU % por DELTA en V$OSSTAT (para que cambie en cada request)
  if ($live['HOST_CPU_PCT'] === null || $live['HOST_CPU_PCT'] === '') {
    $s1 = q("SELECT
               MAX(CASE WHEN stat_name='BUSY_TIME' THEN value END) AS BUSY,
               MAX(CASE WHEN stat_name='IDLE_TIME' THEN value END) AS IDLE
             FROM v\$osstat");
    usleep(300000); // 300 ms
    $s2 = q("SELECT
               MAX(CASE WHEN stat_name='BUSY_TIME' THEN value END) AS BUSY,
               MAX(CASE WHEN stat_name='IDLE_TIME' THEN value END) AS IDLE
             FROM v\$osstat");
    $dbusy = max(0, ($s2[0]['BUSY'] - $s1[0]['BUSY']));
    $didle = max(0, ($s2[0]['IDLE'] - $s1[0]['IDLE']));
    $den   = $dbusy + $didle;
    if ($den > 0) $live['HOST_CPU_PCT'] = round(($dbusy / $den) * 100, 2);
  }

  // Si no había historia, devolvemos al menos la muestra en vivo
  if (count($sys) === 0) $sys = [$live];
  else $sys[0] = $live; // aseguramos que la última tenga fallbacks cubiertos

  /* 4) SGA y PGA (ya en MB) */
  $sga = q("
    SELECT REPLACE(component,' ','_') AS COMPONENT,
           ROUND(current_size/1024/1024, 2) AS CURRENT_MB
    FROM v\$sga_dynamic_components
    WHERE current_size > 0
    ORDER BY current_size DESC
  ");

  $pga = q("
    SELECT name AS NAME,
           ROUND(value/1024/1024, 2) AS VALUE_MB
    FROM v\$pgastat
    WHERE name IN ('total PGA inuse','total PGA allocated')
    ORDER BY name
  ");

  echo json_encode(['sys'=>$sys,'sga'=>$sga,'pga'=>$pga]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>true,'msg'=>$e->getMessage()]);
}
