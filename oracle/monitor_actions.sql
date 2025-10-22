/* =======================================================================
   Universidad Nacional – EIF402 Administración de Bases de Datos
   PROYECTO: Monitoreo básico de recursos y auditoría (Oracle)
   Archivo : monitor_views.sql
   Ejecutar: CONN MONITOR/tu_clave@127.0.0.1:1521/XEPDB1
   -----------------------------------------------------------------------
   Crea vistas para:
     - Métricas de CPU/RAM (sistémicas y de instancia)
     - Uso de tablespaces (almacenamiento)
     - Top consultas por CPU y por tiempo
     - Último backup (controlfile)
     - Sesiones activas y detalle de sesiones
     - Objetos inválidos
   -----------------------------------------------------------------------
   PRIVILEGIOS PREVIOS (otorgar con SYS si falta algo):
     GRANT SELECT_CATALOG_ROLE, SELECT ANY DICTIONARY TO MONITOR;
     GRANT SELECT ON SYS.V_$SYSMETRIC              TO MONITOR;
     GRANT SELECT ON SYS.V_$SYSMETRIC_HISTORY      TO MONITOR;
     GRANT SELECT ON SYS.V_$OSSTAT                 TO MONITOR;
     GRANT SELECT ON SYS.V_$MEMORY_DYNAMIC_COMPONENTS TO MONITOR;
     GRANT SELECT ON SYS.V_$SGA_DYNAMIC_COMPONENTS TO MONITOR;
     GRANT SELECT ON SYS.V_$TABLESPACE             TO MONITOR;
     GRANT SELECT ON SYS.V_$DATAFILE               TO MONITOR;
     GRANT SELECT ON SYS.DBA_TABLESPACES           TO MONITOR;
     GRANT SELECT ON SYS.DBA_DATA_FILES            TO MONITOR;
     GRANT SELECT ON SYS.DBA_FREE_SPACE            TO MONITOR;
     GRANT SELECT ON SYS.V_$SQLAREA                TO MONITOR;
     GRANT SELECT ON SYS.V_$SQLSTATS               TO MONITOR;
     GRANT SELECT ON SYS.GV_$SESSION               TO MONITOR;
     GRANT SELECT ON SYS.GV_$SQL                   TO MONITOR;
     GRANT SELECT ON SYS.V_$BACKUP_SET             TO MONITOR;
     GRANT SELECT ON SYS.V_$BACKUP_PIECE           TO MONITOR;
   ======================================================================= */

SET DEFINE OFF

/* ===========================
   A) MÉTRICAS DE SISTEMA
   =========================== */

-- Histórico corto de métricas (aprox. intervalo de 1 min)
CREATE OR REPLACE VIEW vw_sys_metrics AS
SELECT
  metric_name,
  value,
  begin_time,
  end_time,
  intsize_csec
FROM v$sysmetric_history
WHERE group_id = 2  -- System Metrics Long Duration (~1 min)
ORDER BY end_time DESC;

-- Resumen CPU host y memoria SGA/PGA de la instancia
CREATE OR REPLACE VIEW vw_host_mem_cpu AS
WITH os AS (
  SELECT
    MAX(CASE WHEN stat_name = 'NUM_CPU_CORES'            THEN value END) AS num_cpu_cores,
    MAX(CASE WHEN stat_name = 'NUM_CPUS'                 THEN value END) AS num_cpus_logical,
    MAX(CASE WHEN stat_name = 'PHYSICAL_MEMORY_BYTES'    THEN value END) AS host_mem_bytes
  FROM v$osstat
),
mem AS (
  SELECT component, current_size AS bytes
  FROM v$memory_dynamic_components
  WHERE current_size > 0
)
SELECT
  (SELECT num_cpu_cores    FROM os) AS num_cpu_cores,
  (SELECT num_cpus_logical FROM os) AS num_cpus_logical,
  (SELECT host_mem_bytes   FROM os) AS host_mem_bytes,
  SUM(CASE WHEN component LIKE 'SGA%' THEN bytes ELSE 0 END) AS sga_bytes,
  SUM(CASE WHEN component LIKE 'PGA%' THEN bytes ELSE 0 END) AS pga_bytes
FROM mem;

-- **NUEVA**: Resumen de SGA por componente (MB), para tu /api/metrics.php
CREATE OR REPLACE VIEW vw_sga_summary AS
SELECT
  REPLACE(component, ' ', '_') AS component,
  ROUND(current_size / 1024 / 1024, 2) AS current_mb
FROM v$sga_dynamic_components
WHERE current_size > 0
ORDER BY current_mb DESC;

/* ===========================
   B) ALMACENAMIENTO
   =========================== */

-- Uso de tablespaces: total, libre, usado y % usado
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

/* ===========================
   C) AUDITORÍA DE BACKUPS
   =========================== */

-- Último backup registrado en controlfile (puede estar vacío en XE)
CREATE OR REPLACE VIEW vw_last_backup AS
SELECT
  bs.set_stamp,
  bs.set_count,
  bs.incremental_level,
  CASE
    WHEN bs.incremental_level IS NULL THEN 'FULL'
    ELSE 'INCR' || bs.incremental_level
  END AS backup_type,
  bs.start_time,
  bs.completion_time,
  bs.bytes
FROM v$backup_set bs
WHERE bs.completion_time IS NOT NULL
ORDER BY bs.completion_time DESC;

/* ===========================
   D) TOP CONSULTAS
   =========================== */

-- Top por CPU (microsegundos acumulados)
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

-- Top por tiempo transcurrido
CREATE OR REPLACE VIEW vw_top_sql_elapsed AS
SELECT
  sql_id,
  parsing_schema_name,
  executions,
  cpu_time,
  elapsed_time,
  buffer_gets,
  disk_reads,
  SUBSTR(sql_text,1,1000) AS sql_text
FROM v$sqlarea
ORDER BY elapsed_time DESC;

/* ===========================
   E) CONEXIONES / SESIONES
   =========================== */

-- Sesiones activas de usuario (vista ligera para “conexiones activas”)
CREATE OR REPLACE VIEW vw_active_sessions AS
SELECT
  s.inst_id,
  s.sid,
  s.serial#,
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

-- **NUEVA**: Detalle de sesiones (para /api/connections.php)
-- Incluye el SQL actual si está disponible (truncado)
CREATE OR REPLACE VIEW vw_sessions_detail AS
SELECT
  s.inst_id,
  s.sid,
  s.serial#,
  s.username,
  s.osuser,
  s.machine,
  s.program,
  s.module,
  s.status,
  s.type,
  s.event,
  s.wait_class,
  s.state,
  s.seconds_in_wait,
  s.sql_id,
  SUBSTR(q.sql_text, 1, 1000) AS sql_text,
  s.logon_time,
  s.blocking_session,
  s.blocking_session_status
FROM gv$session s
LEFT JOIN gv$sql q
  ON q.inst_id = s.inst_id
 AND q.sql_id  = s.sql_id
WHERE s.type = 'USER'
ORDER BY s.status DESC, s.seconds_in_wait DESC;

/* ===========================
   F) OBJETOS INVÁLIDOS
   =========================== */

CREATE OR REPLACE VIEW vw_invalid_objects AS
SELECT owner, object_name, object_type, status, last_ddl_time
FROM dba_objects
WHERE status = 'INVALID'
ORDER BY owner, object_type, object_name;


-- Uso de tablespaces: total, libre, usado y % usado
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
  NVL(f.free_bytes, 0) AS free_bytes,
  (t.total_bytes - NVL(f.free_bytes, 0)) AS used_bytes,
  ROUND(((t.total_bytes - NVL(f.free_bytes, 0)) / NULLIF(t.total_bytes, 0)) * 100, 2) AS used_pct
FROM t
LEFT JOIN f
  ON t.tablespace_name = f.tablespace_name;

-- Con MONITOR en XEPDB1

-- 2.1) Tabla de auditoría del “backup” lógico
CREATE TABLE monitor_backup_log (
  id           NUMBER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
  backup_type  VARCHAR2(30),           -- 'DATAPUMP_SCHEMA'
  start_time   TIMESTAMP,
  end_time     TIMESTAMP,
  status       VARCHAR2(1000),         -- COMPLETED / FAILED: <error> / otros estados
  dumpfile     VARCHAR2(260),
  logfile      VARCHAR2(260),
  bytes        NUMBER                  -- tamaño del .dmp (aprox)
);

-- 2.2) Procedimiento: exporta el esquema con DBMS_DATAPUMP y registra en la tabla
CREATE OR REPLACE PROCEDURE sp_backup_schema(
  p_owner   VARCHAR2 DEFAULT USER,
  p_dir     VARCHAR2 DEFAULT 'DATA_PUMP_DIR'
) AS
  h           NUMBER;          -- handle de Data Pump
  job_state   VARCHAR2(30);
  sts         KU$_STATUS;
  v_dump      VARCHAR2(128);
  v_log       VARCHAR2(128);
  v_id        NUMBER;
  v_start     TIMESTAMP := SYSTIMESTAMP;
  v_exists    BOOLEAN;
  v_len       NUMBER;
  v_bs        NUMBER;
BEGIN
  v_dump := LOWER(p_owner)||'_'||TO_CHAR(SYSTIMESTAMP,'YYYYMMDD_HH24MISS')||'.dmp';
  v_log  := LOWER(p_owner)||'_'||TO_CHAR(SYSTIMESTAMP,'YYYYMMDD_HH24MISS')||'.log';

  INSERT INTO monitor_backup_log(backup_type, start_time, status, dumpfile, logfile)
  VALUES ('DATAPUMP_SCHEMA', v_start, 'RUNNING', v_dump, v_log)
  RETURNING id INTO v_id;
  COMMIT;

  -- Abre job de EXPORT a nivel SCHEMA
  h := DBMS_DATAPUMP.OPEN(operation => 'EXPORT', job_mode => 'SCHEMA', job_name => NULL, version => 'LATEST');

  -- Archivos (dump y log)
  DBMS_DATAPUMP.ADD_FILE(h, v_dump, p_dir, NULL, DBMS_DATAPUMP.KU$_FILE_TYPE_DUMP_FILE);
  DBMS_DATAPUMP.ADD_FILE(h, v_log,  p_dir, NULL, DBMS_DATAPUMP.KU$_FILE_TYPE_LOG_FILE);

  -- Filtra por esquema
  DBMS_DATAPUMP.METADATA_FILTER(h, 'SCHEMA_EXPR', 'IN ('''||UPPER(p_owner)||''')');

  -- Inicia y espera a que termine
  DBMS_DATAPUMP.START_JOB(h);
  DBMS_DATAPUMP.WAIT_FOR_JOB(h, job_state, sts);
  DBMS_DATAPUMP.DETACH(h);

  -- Intenta obtener tamaño del .dmp
  BEGIN
    UTL_FILE.FGETATTR(p_dir, v_dump, v_exists, v_len, v_bs);
  EXCEPTION WHEN OTHERS THEN
    v_exists := FALSE; v_len := NULL;
  END;

  UPDATE monitor_backup_log
     SET end_time = SYSTIMESTAMP,
         status   = NVL(job_state,'COMPLETED'),
         bytes    = v_len
   WHERE id = v_id;
  COMMIT;

EXCEPTION
  WHEN OTHERS THEN
    UPDATE monitor_backup_log
       SET end_time = SYSTIMESTAMP,
           status   = 'FAILED: '||SUBSTR(SQLERRM,1,900)
     WHERE id = v_id;
    COMMIT;
    RAISE;
END;
/

-- 2.3) Vista para el dashboard (columnas que tu frontend ya usa)
CREATE OR REPLACE VIEW vw_last_backup AS
SELECT
  end_time       AS last_backup_end,
  status         AS last_status,
  backup_type,
  dumpfile,
  logfile,
  bytes
FROM monitor_backup_log
WHERE end_time IS NOT NULL
ORDER BY end_time DESC;




/* ===========================
   FIN
   =========================== */
