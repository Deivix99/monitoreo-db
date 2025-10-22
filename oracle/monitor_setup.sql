-- Con MONITOR en XEPDB1:
-- CONN MONITOR/TuClaveFuerte_2025@127.0.0.1:1521/XEPDB1
SET DEFINE OFF

-- ==== SP usadas por tu API ====

CREATE OR REPLACE PROCEDURE sp_gather_schema_stats(p_owner IN VARCHAR2) IS
BEGIN
  DBMS_STATS.GATHER_SCHEMA_STATS(
    ownname          => p_owner,
    options          => 'GATHER',
    estimate_percent => DBMS_STATS.AUTO_SAMPLE_SIZE,
    method_opt       => 'FOR ALL COLUMNS SIZE AUTO',
    cascade          => TRUE
  );
END;
/

CREATE OR REPLACE PROCEDURE sp_recompile_invalids(p_owner IN VARCHAR2)
AUTHID DEFINER
AS
BEGIN
  DBMS_UTILITY.COMPILE_SCHEMA(
    schema      => UPPER(p_owner),
    compile_all => FALSE   -- FALSE = solo inválidos (TRUE = todo)
  );
END;
/
SHOW ERRORS;


-- ==== VISTAS que esperan tus endpoints ====

-- a) Métricas del sistema (histórico corto)
CREATE OR REPLACE VIEW vw_sys_metrics AS
SELECT
  metric_name,
  value,
  begin_time,
  end_time,
  intsize_csec
FROM v$sysmetric_history
WHERE group_id = 2                -- "System Metrics Long Duration"
ORDER BY end_time DESC;

-- b) Uso de tablespaces
CREATE OR REPLACE VIEW vw_tablespace_usage AS
WITH t AS (
  SELECT tablespace_name, SUM(bytes) AS total_bytes
  FROM dba_data_files
  GROUP BY tablespace_name
),
f AS (
  SELECT tablespace_name, SUM(bytes) AS free_bytes
  FROM dba_free_space
  GROUP BY tablespace_name
)
SELECT
  t.tablespace_name,
  t.total_bytes,
  NVL(f.free_bytes,0) AS free_bytes,
  (t.total_bytes - NVL(f.free_bytes,0)) AS used_bytes,
  ROUND( ( (t.total_bytes - NVL(f.free_bytes,0)) / NULLIF(t.total_bytes,0) ) * 100, 2) AS used_pct
FROM t
LEFT JOIN f USING (tablespace_name)
ORDER BY used_pct DESC NULLS LAST;

-- d) TOP SQL por CPU
CREATE OR REPLACE VIEW vw_top_sql_cpu AS
SELECT
  sql_id,
  parsing_schema_name,
  executions,
  cpu_time,                -- microsegundos
  elapsed_time,            -- microsegundos
  buffer_gets,
  disk_reads,
  SUBSTR(sql_text,1,1000) AS sql_text
FROM v$sqlarea
ORDER BY cpu_time DESC;

-- e) Sesiones activas de usuario
CREATE OR REPLACE VIEW vw_active_sessions AS
SELECT
  s.sid, s.serial#, s.inst_id,
  s.username,
  s.status,
  s.type,
  s.machine,
  s.program,
  s.module,
  s.sql_id,
  s.event,
  s.state,
  s.wait_class,
  s.seconds_in_wait,
  s.logon_time
FROM gv$session s
WHERE s.status = 'ACTIVE'
  AND s.type = 'USER';

-- f) Objetos inválidos
CREATE OR REPLACE VIEW vw_invalid_objects AS
SELECT owner, object_name, object_type, status, last_ddl_time
FROM dba_objects
WHERE status = 'INVALID'
ORDER BY owner, object_type, object_name;

-- ==== Smoke tests rápidos ====
PROMPT ===== PROBANDO VISTAS =====
SELECT * FROM vw_sys_metrics       WHERE ROWNUM <= 5;
SELECT * FROM vw_tablespace_usage  WHERE ROWNUM <= 5;
SELECT * FROM vw_last_backup       WHERE ROWNUM <= 1;
SELECT * FROM vw_top_sql_cpu       WHERE ROWNUM <= 5;
SELECT * FROM vw_active_sessions   WHERE ROWNUM <= 10;
SELECT * FROM vw_invalid_objects   WHERE ROWNUM <= 10;
