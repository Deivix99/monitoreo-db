-- demo_carga.sql  (ejecutar como MONITOR en XEPDB1)
-- Objetivo: provocar actividad visible en CPU/PGA, Top SQL, sesiones y espacio usado.

SET SERVEROUTPUT ON
PROMPT === Usuario y tablespace por defecto ===
SELECT USER AS current_user, default_tablespace FROM user_users;

PROMPT === 1) Datos base: tabla grande para carga ===
BEGIN
  EXECUTE IMMEDIATE 'DROP TABLE t_demo PURGE';
EXCEPTION WHEN OTHERS THEN NULL;
END;
/

CREATE TABLE t_demo NOLOGGING AS
WITH g AS (SELECT LEVEL lv FROM dual CONNECT BY LEVEL <= 20000)
SELECT ROW_NUMBER() OVER (ORDER BY 1) AS id,
       MOD(ROWNUM, 100)         AS gkey,
       DBMS_RANDOM.VALUE(1,1e6) AS n1,
       DBMS_RANDOM.VALUE(1,1e6) AS n2,
       RPAD(DBMS_RANDOM.STRING('A',20), 200, '*') AS pad
FROM g
CROSS JOIN (SELECT 1 FROM dual CONNECT BY LEVEL <= 3);

CREATE INDEX t_demo_ix1 ON t_demo(gkey);

EXEC DBMS_STATS.GATHER_TABLE_STATS(USER, 'T_DEMO');
PROMPT t_demo creada y con estadísticas.

PROMPT === 2) Consumir espacio en USERS con CLOBs ===
BEGIN
  EXECUTE IMMEDIATE 'DROP TABLE t_lob PURGE';
EXCEPTION WHEN OTHERS THEN NULL;
END;
/

CREATE TABLE t_lob (id NUMBER, data CLOB);

DECLARE
  chunk CLOB := TO_CLOB(RPAD('X', 4000, 'X')); -- 4 KB
  big   CLOB;
BEGIN
  FOR i IN 1..10 LOOP
    big := NULL;
    -- ~200 KB por fila (50 * 4KB)
    FOR j IN 1..50 LOOP
      big := big || chunk;
    END LOOP;
    INSERT INTO t_lob VALUES (i, big);
  END LOOP;
  COMMIT;
END;
/

EXEC DBMS_STATS.GATHER_TABLE_STATS(USER, 'T_LOB');
PROMPT t_lob creada (~2 MB) y con estadísticas.

-- [OPCIONAL] Aumentar volumen para ver % de tablespace subir más:
-- DECLARE
--   chunk CLOB := TO_CLOB(RPAD('X', 4000, 'X'));
--   big   CLOB;
-- BEGIN
--   FOR i IN 11..200 LOOP
--     big := NULL;
--     FOR j IN 1..50 LOOP
--       big := big || chunk;
--     END LOOP;
--     INSERT INTO t_lob VALUES (i, big);
--     IF MOD(i,25)=0 THEN COMMIT; END IF;
--   END LOOP;
--   COMMIT;
-- END;
-- /
-- EXEC DBMS_STATS.GATHER_TABLE_STATS(USER, 'T_LOB');

PROMPT === 3) Carga de CPU/PGA ~60s (agregaciones, sort, join) ===
DECLARE
  t_end TIMESTAMP := SYSTIMESTAMP + INTERVAL '60' SECOND;
  v1 NUMBER; v2 NUMBER; v3 NUMBER;
BEGIN
  WHILE SYSTIMESTAMP < t_end LOOP
    -- Agregación “pesada” pero que devuelve UNA fila:
    SELECT SUM(n1), SUM(n2), COUNT(*) INTO v1, v2, v3
    FROM (
      SELECT /*+ NO_MERGE */ MOD(id, 50) grp, n1, n2
      FROM t_demo
    );

    -- Ordenamiento (consume PGA):
    SELECT COUNT(*) INTO v1
    FROM (SELECT /*+ NO_MERGE */ pad FROM t_demo ORDER BY pad);

    -- Join + agrupación (computariza el conjunto y lo cuenta):
    SELECT COUNT(*) INTO v2
    FROM (
      SELECT d1.gkey, COUNT(*) c
      FROM t_demo d1
      JOIN t_demo d2 ON d2.gkey = d1.gkey
      WHERE d1.id <= 10000
      GROUP BY d1.gkey
    );

    -- Espacia las muestras para que el gráfico temporal avance
    DBMS_LOCK.SLEEP(1);
  END LOOP;
END;
/
PROMPT Carga de CPU finalizada (60s).

PROMPT === 4) Crear objetos inválidos y uno reparado ===
BEGIN
  EXECUTE IMMEDIATE 'DROP PROCEDURE p_broken';
EXCEPTION WHEN OTHERS THEN NULL;
END;
/

CREATE OR REPLACE PROCEDURE p_broken AS
  v NUMBER;
BEGIN
  -- Referencia inexistente para quedar INVALID
  SELECT COUNT(*) INTO v FROM no_such_table;
END;
/

BEGIN
  EXECUTE IMMEDIATE 'DROP TABLE fix_later PURGE';
EXCEPTION WHEN OTHERS THEN NULL;
END;
/

BEGIN
  EXECUTE IMMEDIATE 'DROP PROCEDURE p_will_be_fixed';
EXCEPTION WHEN OTHERS THEN NULL;
END;
/

CREATE OR REPLACE PROCEDURE p_will_be_fixed AS
  v NUMBER;
BEGIN
  -- Aún no existe la tabla -> INVALID inicialmente
  SELECT COUNT(*) INTO v FROM fix_later;
END;
/

-- Crear la tabla y recompilar para dejarlo VALID
CREATE TABLE fix_later (id NUMBER);
ALTER PROCEDURE p_will_be_fixed COMPILE;

PROMPT === 5) Recolectar estadísticas del esquema ===
BEGIN
  DBMS_STATS.GATHER_SCHEMA_STATS(ownname => USER, options => 'GATHER AUTO');
END;
/

PROMPT === 6) Verificaciones rápidas ===
SELECT COUNT(*) AS filas_demo FROM t_demo;

-- USER_OBJECTS no tiene columna OWNER
SELECT object_name, object_type, status
FROM   user_objects
WHERE  object_name IN ('P_BROKEN','P_WILL_BE_FIXED')
ORDER  BY object_name;

-- “Top” manual para calentar la cache
SELECT /* cpu */ gkey, COUNT(*) c, SUM(n1) s
FROM   t_demo
GROUP  BY gkey
ORDER  BY 2 DESC
FETCH FIRST 5 ROWS ONLY;


HOST rman target "sys/root@localhost:1521/XE as sysdba" @rman_test_backup.rman log=rman_test_backup.log

PROMPT === Fin de demo_carga.sql ===

-- [Opcional] Limpieza
-- DROP TABLE t_lob PURGE;
-- DROP TABLE t_demo PURGE;
-- DROP TABLE fix_later PURGE;
-- DROP PROCEDURE p_broken;
-- DROP PROCEDURE p_will_be_fixed;
