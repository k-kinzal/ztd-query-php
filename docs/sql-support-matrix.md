# ZTD SQL Support Matrix

A list of SQL statements supported by ZTD. Since ZTD simulates query results without modifying the actual database, all operations are executed virtually.

## Legend

| Status | Meaning |
|--------|---------|
| Supported | Fully supported |
| Unsupported | Not supported (behavior depends on configuration) |
| Unsupported (Parser Limitation) | Not supported due to SQL parser limitations |

---

## DML (Data Manipulation Language)

### SELECT

| Syntax | Status | Notes |
|--------|--------|-------|
| `SELECT ... FROM ...` | Supported | Basic SELECT |
| `SELECT ... WHERE ...` | Supported | Conditional SELECT |
| `SELECT ... JOIN ...` | Supported | SELECT with JOIN |
| `SELECT ... GROUP BY ...` | Supported | Aggregation query |
| `SELECT ... HAVING ...` | Supported | Aggregation condition |
| `SELECT ... ORDER BY ...` | Supported | SELECT with sorting |
| `SELECT ... LIMIT ...` | Supported | Row count limit |
| `SELECT ... OFFSET ...` | Supported | Offset specification |
| `SELECT ... UNION ...` | Supported | Combining multiple SELECTs |
| `SELECT ... UNION ALL ...` | Supported | Combining with duplicates allowed |
| `SELECT ... EXCEPT ...` | Supported | Set difference |
| `SELECT ... INTERSECT ...` | Supported | Set intersection |
| `SELECT ... SUBQUERY` | Supported | Subquery |
| `WITH ... SELECT ...` | Supported | SELECT with CTE |
| `WITH RECURSIVE ... SELECT ...` | Supported | Recursive CTE |
| `SELECT ... WINDOW ...` | Supported | Window function definition |
| `SELECT ... FOR UPDATE` | Supported | Row lock (ignored in ZTD) |
| `SELECT ... FOR SHARE` | Supported | Shared lock (ignored in ZTD) |
| `SELECT ... LOCK IN SHARE MODE` | Supported | Shared lock (ignored in ZTD) |
| `SELECT ... PARTITION (...)` | Unsupported (Parser Limitation) | PhpMyAdmin SQL parser does not support this |
| `SELECT ... INTO OUTFILE ...` | Unsupported | File output is out of scope |
| `SELECT ... INTO DUMPFILE ...` | Unsupported | File output is out of scope |
| `SELECT ... INTO @var` | Unsupported | Variable assignment is out of scope |

### INSERT

| Syntax | Status | Notes |
|--------|--------|-------|
| `INSERT INTO ... VALUES (...)` | Supported | Single row insert |
| `INSERT INTO ... VALUES (...), (...)` | Supported | Multi-row insert |
| `INSERT INTO ... SET col=val` | Supported | Insert using SET syntax |
| `WITH ... INSERT ...` | Supported | INSERT with CTE |
| `INSERT ... SELECT ...` | Supported | Insert from subquery |
| `INSERT ... ON DUPLICATE KEY UPDATE` | Supported | UPSERT operation |
| `INSERT IGNORE ...` | Supported | Skip on duplicate |
| `INSERT LOW_PRIORITY ...` | Supported | Priority hint (ignored in ZTD) |
| `INSERT DELAYED ...` | Supported | Delayed insert (ignored in ZTD) |
| `INSERT HIGH_PRIORITY ...` | Supported | Priority hint (ignored in ZTD) |
| `INSERT ... PARTITION (...)` | Unsupported (Parser Limitation) | PhpMyAdmin SQL parser does not support this |

### REPLACE

| Syntax | Status | Notes |
|--------|--------|-------|
| `REPLACE INTO ... VALUES (...)` | Supported | Replace insert |
| `REPLACE INTO ... SET col=val` | Supported | Replace insert using SET syntax |
| `REPLACE ... SELECT ...` | Supported | Replace insert from subquery |
| `REPLACE LOW_PRIORITY ...` | Supported | Priority hint (ignored in ZTD) |
| `REPLACE DELAYED ...` | Supported | Delayed replace (ignored in ZTD) |

### UPDATE

| Syntax | Status | Notes |
|--------|--------|-------|
| `UPDATE ... SET ... WHERE ...` | Supported | Single table update |
| `UPDATE ... SET ... ORDER BY ... LIMIT ...` | Supported | Update with row count limit |
| `WITH ... UPDATE ...` | Supported | UPDATE with CTE |
| `UPDATE t1, t2 SET ... WHERE ...` | Supported | Multi-table update |
| `UPDATE ... JOIN ... SET ...` | Supported | Update with JOIN |
| `UPDATE LOW_PRIORITY ...` | Supported | Priority hint (ignored in ZTD) |
| `UPDATE IGNORE ...` | Supported | Ignore errors |
| `UPDATE ... PARTITION (...)` | Unsupported (Parser Limitation) | PhpMyAdmin SQL parser does not support this |

### DELETE

| Syntax | Status | Notes |
|--------|--------|-------|
| `DELETE FROM ... WHERE ...` | Supported | Single table delete |
| `DELETE FROM ... ORDER BY ... LIMIT ...` | Supported | Delete with row count limit |
| `DELETE ... USING ...` | Supported | Delete using USING syntax |
| `DELETE t1 FROM t1 JOIN t2 ...` | Supported | JOIN delete (single table target) |
| `WITH ... DELETE ...` | Supported | DELETE with CTE |
| `DELETE t1, t2 FROM ...` | Supported | Multi-table delete |
| `DELETE LOW_PRIORITY ...` | Supported | Priority hint (ignored in ZTD) |
| `DELETE QUICK ...` | Supported | Quick delete (ignored in ZTD) |
| `DELETE IGNORE ...` | Supported | Ignore errors |
| `DELETE ... PARTITION (...)` | Unsupported (Parser Limitation) | PhpMyAdmin SQL parser does not support this |

### TRUNCATE

| Syntax | Status | Notes |
|--------|--------|-------|
| `TRUNCATE TABLE ...` | Supported | Clears table data in ShadowStore |

### CALL

| Syntax | Status | Notes |
|--------|--------|-------|
| `CALL procedure_name(...)` | Unsupported | Stored procedure calls are opaque and out of scope |

### DO

| Syntax | Status | Notes |
|--------|--------|-------|
| `DO expr [, expr] ...` | Unsupported | Expression execution is out of scope |

### HANDLER

| Syntax | Status | Notes |
|--------|--------|-------|
| `HANDLER ... OPEN` | Unsupported | Low-level table access is out of scope |
| `HANDLER ... READ` | Unsupported | Low-level table access is out of scope |
| `HANDLER ... CLOSE` | Unsupported | Low-level table access is out of scope |

### LOAD DATA

| Syntax | Status | Notes |
|--------|--------|-------|
| `LOAD DATA INFILE ...` | Unsupported | Bulk loading is too complex and out of scope |
| `LOAD DATA LOCAL INFILE ...` | Unsupported | Bulk loading is too complex and out of scope |
| `LOAD XML ...` | Unsupported | XML loading is out of scope |

---

## DDL (Data Definition Language)

DDL in ZTD does not modify the actual database schema; it operates on virtual schemas within the SchemaRegistry.

### CREATE TABLE

| Syntax | Status | Notes |
|--------|--------|-------|
| `CREATE TABLE ...` | Supported | Registers a virtual table in SchemaRegistry |
| `CREATE TABLE IF NOT EXISTS ...` | Supported | Registers a virtual table only if it does not exist |
| `CREATE TABLE ... LIKE ...` | Supported | Copies an existing table structure |
| `CREATE TABLE ... AS SELECT ...` | Supported | Creates a table from SELECT results |
| `CREATE TEMPORARY TABLE ...` | Supported | Creates a temporary table (treated the same as a regular table) |

### CREATE (Others)

| Syntax | Status | Notes |
|--------|--------|-------|
| `CREATE INDEX ...` | Unsupported | Not needed as ShadowStore handles this |
| `CREATE UNIQUE INDEX ...` | Unsupported | Not needed as ShadowStore handles this |
| `CREATE FULLTEXT INDEX ...` | Unsupported | Not needed as ShadowStore handles this |
| `CREATE SPATIAL INDEX ...` | Unsupported | Not needed as ShadowStore handles this |
| `CREATE DATABASE ...` | Unsupported | Database creation is out of scope |
| `CREATE SCHEMA ...` | Unsupported | Database creation is out of scope |
| `CREATE VIEW ...` | Unsupported | View creation is out of scope |
| `CREATE OR REPLACE VIEW ...` | Unsupported | View creation is out of scope |
| `CREATE PROCEDURE ...` | Unsupported | Stored procedures are out of scope |
| `CREATE FUNCTION ...` | Unsupported | User-defined functions are out of scope |
| `CREATE TRIGGER ...` | Unsupported | Triggers are out of scope |
| `CREATE EVENT ...` | Unsupported | Event scheduler is out of scope |
| `CREATE USER ...` | Unsupported | User management is out of scope |
| `CREATE ROLE ...` | Unsupported | Role management is out of scope |
| `CREATE SERVER ...` | Unsupported | Server connection definitions are out of scope |
| `CREATE TABLESPACE ...` | Unsupported | Tablespace management is out of scope |
| `CREATE LOGFILE GROUP ...` | Unsupported | Logfile groups are out of scope |
| `CREATE RESOURCE GROUP ...` | Unsupported | Resource groups are out of scope |
| `CREATE SPATIAL REFERENCE SYSTEM ...` | Unsupported | Spatial reference systems are out of scope |

### ALTER TABLE

| Syntax | Status | Notes |
|--------|--------|-------|
| `ALTER TABLE ... ADD COLUMN ...` | Supported | Add virtual column |
| `ALTER TABLE ... ADD [COLUMN] (col1, col2, ...)` | Supported | Add multiple columns |
| `ALTER TABLE ... DROP COLUMN ...` | Supported | Drop virtual column |
| `ALTER TABLE ... MODIFY COLUMN ...` | Supported | Modify virtual column |
| `ALTER TABLE ... CHANGE COLUMN ...` | Supported | Rename virtual column |
| `ALTER TABLE ... ALTER COLUMN ... SET DEFAULT ...` | Supported | Change default value |
| `ALTER TABLE ... ALTER COLUMN ... DROP DEFAULT` | Supported | Drop default value |
| `ALTER TABLE ... ADD INDEX ...` | Unsupported | Not needed as ShadowStore handles this |
| `ALTER TABLE ... ADD KEY ...` | Unsupported | Not needed as ShadowStore handles this |
| `ALTER TABLE ... ADD FULLTEXT ...` | Unsupported | Not needed as ShadowStore handles this |
| `ALTER TABLE ... ADD SPATIAL ...` | Unsupported | Not needed as ShadowStore handles this |
| `ALTER TABLE ... DROP INDEX ...` | Unsupported | Not needed as ShadowStore handles this |
| `ALTER TABLE ... DROP KEY ...` | Unsupported | Not needed as ShadowStore handles this |
| `ALTER TABLE ... ADD PRIMARY KEY ...` | Supported | Add virtual primary key |
| `ALTER TABLE ... DROP PRIMARY KEY` | Supported | Drop virtual primary key |
| `ALTER TABLE ... ADD FOREIGN KEY ...` | Supported | Add virtual foreign key (No-op) |
| `ALTER TABLE ... DROP FOREIGN KEY ...` | Supported | Drop virtual foreign key (No-op) |
| `ALTER TABLE ... ADD CONSTRAINT ...` | Supported | Add constraint (No-op) |
| `ALTER TABLE ... DROP CONSTRAINT ...` | Supported | Drop constraint (No-op) |
| `ALTER TABLE ... RENAME TO ...` | Supported | Rename virtual table |
| `ALTER TABLE ... RENAME COLUMN ...` | Supported | Rename column |
| `ALTER TABLE ... RENAME INDEX ...` | Unsupported | Not needed as ShadowStore handles this |
| `ALTER TABLE ... RENAME KEY ...` | Unsupported | Not needed as ShadowStore handles this |
| `ALTER TABLE ... ORDER BY ...` | Unsupported | Physical ordering is out of scope |
| `ALTER TABLE ... CONVERT TO CHARACTER SET ...` | Unsupported | Character set conversion is out of scope |
| `ALTER TABLE ... ENGINE = ...` | Unsupported | Storage engine change is out of scope |
| `ALTER TABLE ... ADD PARTITION ...` | Unsupported | Partition management is out of scope |
| `ALTER TABLE ... DROP PARTITION ...` | Unsupported | Partition management is out of scope |
| `ALTER TABLE ... TRUNCATE PARTITION ...` | Unsupported | Partition management is out of scope |
| `ALTER TABLE ... COALESCE PARTITION ...` | Unsupported | Partition management is out of scope |
| `ALTER TABLE ... REORGANIZE PARTITION ...` | Unsupported | Partition management is out of scope |
| `ALTER TABLE ... EXCHANGE PARTITION ...` | Unsupported | Partition management is out of scope |
| `ALTER TABLE ... ANALYZE PARTITION ...` | Unsupported | Partition management is out of scope |
| `ALTER TABLE ... CHECK PARTITION ...` | Unsupported | Partition management is out of scope |
| `ALTER TABLE ... OPTIMIZE PARTITION ...` | Unsupported | Partition management is out of scope |
| `ALTER TABLE ... REBUILD PARTITION ...` | Unsupported | Partition management is out of scope |
| `ALTER TABLE ... REPAIR PARTITION ...` | Unsupported | Partition management is out of scope |
| `ALTER TABLE ... REMOVE PARTITIONING` | Unsupported | Partition management is out of scope |

### ALTER (Others)

| Syntax | Status | Notes |
|--------|--------|-------|
| `ALTER DATABASE ...` | Unsupported | Database modification is out of scope |
| `ALTER SCHEMA ...` | Unsupported | Database modification is out of scope |
| `ALTER VIEW ...` | Unsupported | View modification is out of scope |
| `ALTER PROCEDURE ...` | Unsupported | Procedure modification is out of scope |
| `ALTER FUNCTION ...` | Unsupported | Function modification is out of scope |
| `ALTER EVENT ...` | Unsupported | Event modification is out of scope |
| `ALTER USER ...` | Unsupported | User modification is out of scope |
| `ALTER SERVER ...` | Unsupported | Server modification is out of scope |
| `ALTER TABLESPACE ...` | Unsupported | Tablespace modification is out of scope |
| `ALTER LOGFILE GROUP ...` | Unsupported | Logfile group modification is out of scope |
| `ALTER INSTANCE ...` | Unsupported | Instance modification is out of scope |
| `ALTER RESOURCE GROUP ...` | Unsupported | Resource group modification is out of scope |

### DROP

| Syntax | Status | Notes |
|--------|--------|-------|
| `DROP TABLE ...` | Supported | Removes virtual table from SchemaRegistry |
| `DROP TABLE IF EXISTS ...` | Supported | Removes virtual table only if it exists |
| `DROP TEMPORARY TABLE ...` | Supported | Drop temporary table |
| `DROP INDEX ...` | Unsupported | Not needed as ShadowStore handles this |
| `DROP DATABASE ...` | Unsupported | Database deletion is out of scope |
| `DROP SCHEMA ...` | Unsupported | Database deletion is out of scope |
| `DROP VIEW ...` | Unsupported | View deletion is out of scope |
| `DROP PROCEDURE ...` | Unsupported | Stored procedure deletion is out of scope |
| `DROP FUNCTION ...` | Unsupported | Function deletion is out of scope |
| `DROP TRIGGER ...` | Unsupported | Trigger deletion is out of scope |
| `DROP EVENT ...` | Unsupported | Event deletion is out of scope |
| `DROP USER ...` | Unsupported | User deletion is out of scope |
| `DROP ROLE ...` | Unsupported | Role deletion is out of scope |
| `DROP SERVER ...` | Unsupported | Server deletion is out of scope |
| `DROP TABLESPACE ...` | Unsupported | Tablespace deletion is out of scope |
| `DROP LOGFILE GROUP ...` | Unsupported | Logfile group deletion is out of scope |
| `DROP RESOURCE GROUP ...` | Unsupported | Resource group deletion is out of scope |
| `DROP SPATIAL REFERENCE SYSTEM ...` | Unsupported | Spatial reference system deletion is out of scope |

### RENAME

| Syntax | Status | Notes |
|--------|--------|-------|
| `RENAME TABLE ... TO ...` | Unsupported | Use ALTER TABLE ... RENAME TO instead |
| `RENAME USER ... TO ...` | Unsupported | User management is out of scope |

---

## TCL (Transaction Control Language)

| Syntax | Status | Notes |
|--------|--------|-------|
| `START TRANSACTION` | Unsupported | Ignored (No-op) |
| `START TRANSACTION WITH CONSISTENT SNAPSHOT` | Unsupported | Ignored (No-op) |
| `START TRANSACTION READ ONLY` | Unsupported | Ignored (No-op) |
| `START TRANSACTION READ WRITE` | Unsupported | Ignored (No-op) |
| `BEGIN` | Unsupported | Ignored (No-op) |
| `BEGIN WORK` | Unsupported | Ignored (No-op) |
| `COMMIT` | Unsupported | Ignored (No-op) |
| `COMMIT WORK` | Unsupported | Ignored (No-op) |
| `COMMIT AND CHAIN` | Unsupported | Ignored (No-op) |
| `COMMIT AND NO CHAIN` | Unsupported | Ignored (No-op) |
| `ROLLBACK` | Unsupported | Ignored (No-op) |
| `ROLLBACK WORK` | Unsupported | Ignored (No-op) |
| `ROLLBACK AND CHAIN` | Unsupported | Ignored (No-op) |
| `ROLLBACK AND NO CHAIN` | Unsupported | Ignored (No-op) |
| `SAVEPOINT ...` | Unsupported | Ignored (No-op) |
| `RELEASE SAVEPOINT ...` | Unsupported | Ignored (No-op) |
| `ROLLBACK TO SAVEPOINT ...` | Unsupported | Ignored (No-op) |
| `SET TRANSACTION ...` | Unsupported | Ignored (No-op) |
| `SET autocommit = ...` | Unsupported | Ignored (No-op) |

### XA Transactions

| Syntax | Status | Notes |
|--------|--------|-------|
| `XA START ...` | Unsupported | Distributed transactions are out of scope |
| `XA BEGIN ...` | Unsupported | Distributed transactions are out of scope |
| `XA END ...` | Unsupported | Distributed transactions are out of scope |
| `XA PREPARE ...` | Unsupported | Distributed transactions are out of scope |
| `XA COMMIT ...` | Unsupported | Distributed transactions are out of scope |
| `XA ROLLBACK ...` | Unsupported | Distributed transactions are out of scope |
| `XA RECOVER ...` | Unsupported | Distributed transactions are out of scope |

**Design Decision**: Transaction control is ignored (No-op). Since the ZTD session itself performs virtual change management (ShadowStore), explicit transaction control is unnecessary.

---

## DCL (Data Control Language)

### Privilege Management

| Syntax | Status | Notes |
|--------|--------|-------|
| `GRANT ... ON ... TO ...` | Unsupported | Granting privileges is out of scope |
| `GRANT ... TO ...` | Unsupported | Granting roles is out of scope |
| `GRANT ... WITH GRANT OPTION` | Unsupported | Granting privileges is out of scope |
| `REVOKE ... ON ... FROM ...` | Unsupported | Revoking privileges is out of scope |
| `REVOKE ... FROM ...` | Unsupported | Revoking roles is out of scope |
| `REVOKE ALL PRIVILEGES ...` | Unsupported | Revoking privileges is out of scope |

### User and Role Management

| Syntax | Status | Notes |
|--------|--------|-------|
| `CREATE USER ...` | Unsupported | User creation is out of scope |
| `ALTER USER ...` | Unsupported | User modification is out of scope |
| `DROP USER ...` | Unsupported | User deletion is out of scope |
| `RENAME USER ...` | Unsupported | User renaming is out of scope |
| `CREATE ROLE ...` | Unsupported | Role creation is out of scope |
| `DROP ROLE ...` | Unsupported | Role deletion is out of scope |
| `SET ROLE ...` | Unsupported | Role activation is out of scope |
| `SET DEFAULT ROLE ...` | Unsupported | Default role setting is out of scope |
| `SET PASSWORD ...` | Unsupported | Password setting is out of scope |

---

## Utility Statements

### Information Retrieval

| Syntax | Status | Notes |
|--------|--------|-------|
| `SHOW DATABASES` | Unsupported | Metadata query (can be passed through) |
| `SHOW SCHEMAS` | Unsupported | Metadata query (can be passed through) |
| `SHOW TABLES` | Unsupported | Metadata query (can be passed through) |
| `SHOW FULL TABLES` | Unsupported | Metadata query (can be passed through) |
| `SHOW TABLE STATUS` | Unsupported | Metadata query (can be passed through) |
| `SHOW CREATE TABLE ...` | Unsupported | Metadata query (can be passed through) |
| `SHOW CREATE DATABASE ...` | Unsupported | Metadata query (can be passed through) |
| `SHOW CREATE VIEW ...` | Unsupported | Metadata query (can be passed through) |
| `SHOW CREATE PROCEDURE ...` | Unsupported | Metadata query (can be passed through) |
| `SHOW CREATE FUNCTION ...` | Unsupported | Metadata query (can be passed through) |
| `SHOW CREATE TRIGGER ...` | Unsupported | Metadata query (can be passed through) |
| `SHOW CREATE EVENT ...` | Unsupported | Metadata query (can be passed through) |
| `SHOW CREATE USER ...` | Unsupported | Metadata query (can be passed through) |
| `SHOW COLUMNS ...` | Unsupported | Metadata query (can be passed through) |
| `SHOW FIELDS ...` | Unsupported | Metadata query (can be passed through) |
| `SHOW INDEX ...` | Unsupported | Metadata query (can be passed through) |
| `SHOW KEYS ...` | Unsupported | Metadata query (can be passed through) |
| `SHOW STATUS` | Unsupported | Metadata query (can be passed through) |
| `SHOW VARIABLES` | Unsupported | Metadata query (can be passed through) |
| `SHOW GLOBAL STATUS` | Unsupported | Metadata query (can be passed through) |
| `SHOW GLOBAL VARIABLES` | Unsupported | Metadata query (can be passed through) |
| `SHOW SESSION STATUS` | Unsupported | Metadata query (can be passed through) |
| `SHOW SESSION VARIABLES` | Unsupported | Metadata query (can be passed through) |
| `SHOW PROCESSLIST` | Unsupported | Metadata query (can be passed through) |
| `SHOW FULL PROCESSLIST` | Unsupported | Metadata query (can be passed through) |
| `SHOW GRANTS ...` | Unsupported | Metadata query (can be passed through) |
| `SHOW PRIVILEGES` | Unsupported | Metadata query (can be passed through) |
| `SHOW ENGINES` | Unsupported | Metadata query (can be passed through) |
| `SHOW STORAGE ENGINES` | Unsupported | Metadata query (can be passed through) |
| `SHOW PLUGINS` | Unsupported | Metadata query (can be passed through) |
| `SHOW EVENTS` | Unsupported | Metadata query (can be passed through) |
| `SHOW TRIGGERS` | Unsupported | Metadata query (can be passed through) |
| `SHOW PROCEDURE STATUS` | Unsupported | Metadata query (can be passed through) |
| `SHOW FUNCTION STATUS` | Unsupported | Metadata query (can be passed through) |
| `SHOW WARNINGS` | Unsupported | Metadata query (can be passed through) |
| `SHOW ERRORS` | Unsupported | Metadata query (can be passed through) |
| `SHOW COUNT(*) WARNINGS` | Unsupported | Metadata query (can be passed through) |
| `SHOW COUNT(*) ERRORS` | Unsupported | Metadata query (can be passed through) |
| `SHOW CHARACTER SET` | Unsupported | Metadata query (can be passed through) |
| `SHOW CHARSET` | Unsupported | Metadata query (can be passed through) |
| `SHOW COLLATION` | Unsupported | Metadata query (can be passed through) |
| `SHOW BINARY LOGS` | Unsupported | Metadata query (can be passed through) |
| `SHOW MASTER LOGS` | Unsupported | Metadata query (can be passed through) |
| `SHOW MASTER STATUS` | Unsupported | Metadata query (can be passed through) |
| `SHOW SLAVE STATUS` | Unsupported | Metadata query (can be passed through) |
| `SHOW REPLICA STATUS` | Unsupported | Metadata query (can be passed through) |
| `SHOW RELAYLOG EVENTS` | Unsupported | Metadata query (can be passed through) |
| `SHOW BINLOG EVENTS` | Unsupported | Metadata query (can be passed through) |
| `SHOW OPEN TABLES` | Unsupported | Metadata query (can be passed through) |
| `SHOW PROFILES` | Unsupported | Metadata query (can be passed through) |
| `SHOW PROFILE` | Unsupported | Metadata query (can be passed through) |
| `DESCRIBE ...` | Unsupported | Metadata query (can be passed through) |
| `DESC ...` | Unsupported | Metadata query (can be passed through) |
| `EXPLAIN ...` | Unsupported | Execution plan (can be passed through) |
| `EXPLAIN ANALYZE ...` | Unsupported | Execution plan (can be passed through) |
| `EXPLAIN FORMAT=...` | Unsupported | Execution plan (can be passed through) |
| `HELP ...` | Unsupported | Help is out of scope |

### Session Management

| Syntax | Status | Notes |
|--------|--------|-------|
| `USE ...` | Unsupported | Database switching |
| `SET ...` | Unsupported | Session variable setting |
| `SET NAMES ...` | Unsupported | Character set setting |
| `SET CHARACTER SET ...` | Unsupported | Character set setting |
| `SET GLOBAL ...` | Unsupported | Global variable setting |
| `SET SESSION ...` | Unsupported | Session variable setting |
| `SET @@...` | Unsupported | Variable setting |

### Table Maintenance

| Syntax | Status | Notes |
|--------|--------|-------|
| `ANALYZE TABLE ...` | Unsupported | Table statistics collection is out of scope |
| `ANALYZE LOCAL TABLE ...` | Unsupported | Table statistics collection is out of scope |
| `ANALYZE NO_WRITE_TO_BINLOG TABLE ...` | Unsupported | Table statistics collection is out of scope |
| `CHECK TABLE ...` | Unsupported | Table verification is out of scope |
| `CHECKSUM TABLE ...` | Unsupported | Checksum verification is out of scope |
| `OPTIMIZE TABLE ...` | Unsupported | Table optimization is out of scope |
| `OPTIMIZE LOCAL TABLE ...` | Unsupported | Table optimization is out of scope |
| `OPTIMIZE NO_WRITE_TO_BINLOG TABLE ...` | Unsupported | Table optimization is out of scope |
| `REPAIR TABLE ...` | Unsupported | Table repair is out of scope |
| `REPAIR LOCAL TABLE ...` | Unsupported | Table repair is out of scope |
| `REPAIR NO_WRITE_TO_BINLOG TABLE ...` | Unsupported | Table repair is out of scope |

### Locking

| Syntax | Status | Notes |
|--------|--------|-------|
| `LOCK TABLES ...` | Unsupported | Table locking is out of scope |
| `LOCK TABLES ... READ` | Unsupported | Table locking is out of scope |
| `LOCK TABLES ... WRITE` | Unsupported | Table locking is out of scope |
| `UNLOCK TABLES` | Unsupported | Table unlocking is out of scope |
| `LOCK INSTANCE FOR BACKUP` | Unsupported | Instance locking is out of scope |
| `UNLOCK INSTANCE` | Unsupported | Instance unlocking is out of scope |
| `GET_LOCK(...)` | Unsupported | User-level locking is out of scope |
| `RELEASE_LOCK(...)` | Unsupported | User-level locking is out of scope |

### Cache and Flush

| Syntax | Status | Notes |
|--------|--------|-------|
| `FLUSH ...` | Unsupported | Cache flush is out of scope |
| `FLUSH TABLES` | Unsupported | Cache flush is out of scope |
| `FLUSH TABLES WITH READ LOCK` | Unsupported | Cache flush is out of scope |
| `FLUSH PRIVILEGES` | Unsupported | Privilege flush is out of scope |
| `FLUSH LOGS` | Unsupported | Log flush is out of scope |
| `FLUSH BINARY LOGS` | Unsupported | Log flush is out of scope |
| `FLUSH ENGINE LOGS` | Unsupported | Log flush is out of scope |
| `FLUSH ERROR LOGS` | Unsupported | Log flush is out of scope |
| `FLUSH GENERAL LOGS` | Unsupported | Log flush is out of scope |
| `FLUSH RELAY LOGS` | Unsupported | Log flush is out of scope |
| `FLUSH SLOW LOGS` | Unsupported | Log flush is out of scope |
| `FLUSH STATUS` | Unsupported | Status flush is out of scope |
| `FLUSH USER_RESOURCES` | Unsupported | Resource flush is out of scope |
| `FLUSH HOSTS` | Unsupported | Host cache is out of scope |
| `RESET ...` | Unsupported | Reset is out of scope |
| `RESET MASTER` | Unsupported | Master reset is out of scope |
| `RESET SLAVE` | Unsupported | Slave reset is out of scope |
| `RESET REPLICA` | Unsupported | Replica reset is out of scope |
| `RESET QUERY CACHE` | Unsupported | Query cache reset is out of scope |
| `CACHE INDEX ...` | Unsupported | Index cache is out of scope |
| `LOAD INDEX INTO CACHE ...` | Unsupported | Index preloading is out of scope |

### Server Administration

| Syntax | Status | Notes |
|--------|--------|-------|
| `KILL ...` | Unsupported | Connection termination is out of scope |
| `KILL CONNECTION ...` | Unsupported | Connection termination is out of scope |
| `KILL QUERY ...` | Unsupported | Query termination is out of scope |
| `SHUTDOWN` | Unsupported | Server shutdown is out of scope |
| `RESTART` | Unsupported | Server restart is out of scope |
| `PURGE BINARY LOGS ...` | Unsupported | Binary log purge is out of scope |
| `PURGE MASTER LOGS ...` | Unsupported | Master log purge is out of scope |

### Plugins and Components

| Syntax | Status | Notes |
|--------|--------|-------|
| `INSTALL PLUGIN ...` | Unsupported | Plugin management is out of scope |
| `UNINSTALL PLUGIN ...` | Unsupported | Plugin management is out of scope |
| `INSTALL COMPONENT ...` | Unsupported | Component management is out of scope |
| `UNINSTALL COMPONENT ...` | Unsupported | Component management is out of scope |

### Prepared Statements

| Syntax | Status | Notes |
|--------|--------|-------|
| `PREPARE ... FROM ...` | Unsupported | Server-side prepared statements are out of scope |
| `EXECUTE ...` | Unsupported | Server-side prepared statements are out of scope |
| `EXECUTE ... USING ...` | Unsupported | Server-side prepared statements are out of scope |
| `DEALLOCATE PREPARE ...` | Unsupported | Server-side prepared statements are out of scope |
| `DROP PREPARE ...` | Unsupported | Server-side prepared statements are out of scope |

### Replication

| Syntax | Status | Notes |
|--------|--------|-------|
| `START SLAVE ...` | Unsupported | Replication control is out of scope |
| `START REPLICA ...` | Unsupported | Replication control is out of scope |
| `STOP SLAVE ...` | Unsupported | Replication control is out of scope |
| `STOP REPLICA ...` | Unsupported | Replication control is out of scope |
| `CHANGE MASTER TO ...` | Unsupported | Replication configuration is out of scope |
| `CHANGE REPLICATION SOURCE TO ...` | Unsupported | Replication configuration is out of scope |
| `CHANGE REPLICATION FILTER ...` | Unsupported | Replication configuration is out of scope |
| `START GROUP_REPLICATION` | Unsupported | Group replication is out of scope |
| `STOP GROUP_REPLICATION` | Unsupported | Group replication is out of scope |
| `CLONE ...` | Unsupported | Instance cloning is out of scope |

### Others

| Syntax | Status | Notes |
|--------|--------|-------|
| `BINLOG ...` | Unsupported | Binary log is out of scope |
| `SIGNAL ...` | Unsupported | Signal/error handling is out of scope |
| `RESIGNAL ...` | Unsupported | Signal/error handling is out of scope |
| `GET DIAGNOSTICS ...` | Unsupported | Diagnostics retrieval is out of scope |
| `GET STACKED DIAGNOSTICS ...` | Unsupported | Diagnostics retrieval is out of scope |

---

## Multiple Statements

| Syntax | Status | Notes |
|--------|--------|-------|
| `SELECT 1; SELECT 2` | Supported | Each statement is processed sequentially (`rewriteMultiple()`) |
| `INSERT ...; UPDATE ...` | Supported | Each statement is processed sequentially (`rewriteMultiple()`) |

**Design**: The `SqlRewriter::rewriteMultiple()` method processes each statement sequentially through ZTD. Results are returned as a `MultiRewritePlan`, providing access to each statement's `RewritePlan`. Result retrieval via `nextRowset()` is also supported.

---

## Design Principles

1. **Zero impact on the real DB**: All operations are executed virtually; the actual database is never modified
2. **Virtual schema management**: DDL is managed as virtual schemas within the SchemaRegistry
3. **Virtual data management**: DML is managed as virtual data within the ShadowStore
4. **Security first**: Prohibition of multiple statements, restriction of dangerous operations
5. **Simplicity**: Complex features such as stored procedures, triggers, and views are out of scope

---

## Behavior Configuration for Unsupported SQL

You can configure the behavior when an unsupported SQL statement is executed.

### Behavior Modes

| Mode | Description | Use Case |
|------|-------------|----------|
| `ignore` | Silently ignore (default) | Testing in production-like environments |
| `notice` | Output a warning but continue | Debugging during development |
| `exception` | Throw an exception | Strict testing, detecting unsupported SQL |

### Configuration Example

```php
use ZtdQuery\Config\UnsupportedSqlBehavior;

$config = new ZtdConfig(
    unsupportedBehavior: UnsupportedSqlBehavior::Notice,
);
```

### Behavior Rules

You can use `behaviorRules` to configure individual behavior for specific SQL patterns.
These override the default `unsupportedBehavior`.

```php
$config = new ZtdConfig(
    unsupportedBehavior: UnsupportedSqlBehavior::Exception,
    behaviorRules: [
        // Prefix-based (case-insensitive)
        'BEGIN' => UnsupportedSqlBehavior::Ignore,
        'COMMIT' => UnsupportedSqlBehavior::Ignore,
        'ROLLBACK' => UnsupportedSqlBehavior::Ignore,
        'SET autocommit' => UnsupportedSqlBehavior::Ignore,
        'SET NAMES' => UnsupportedSqlBehavior::Ignore,
    ],
);
```

### Pattern Matching

Regex patterns (strings starting with `/`) can also be used.

```php
$config = new ZtdConfig(
    unsupportedBehavior: UnsupportedSqlBehavior::Exception,
    behaviorRules: [
        '/^SET\s+SESSION/i' => UnsupportedSqlBehavior::Ignore,  // Ignore SET SESSION
        '/^SET\s+/i' => UnsupportedSqlBehavior::Notice,         // Warn for other SET statements
        '/^SAVEPOINT\s+/i' => UnsupportedSqlBehavior::Ignore,   // Ignore SAVEPOINT
    ],
);
```

**First-match-wins**: Rules are evaluated in array order, and the behavior of the first matching rule is applied.
Place more specific patterns first.

### Behavior Details

| Status | ignore mode | notice mode | exception mode |
|--------|-------------|-------------|----------------|
| Supported | Normal execution | Normal execution | Normal execution |
| Unsupported | Ignored | Warning + ignored | Exception (can be overridden by behaviorRules) |

### Notice Output Example

```
[ZTD Notice] Unsupported SQL ignored: BEGIN
[ZTD Notice] Unsupported SQL ignored: SET NAMES utf8mb4
[ZTD Notice] Unsupported SQL ignored: CREATE DATABASE test
```

### Exception Example

```php
// When using UnsupportedSqlBehavior::Exception mode
try {
    $ztdPdo->exec('CREATE DATABASE test');
} catch (UnsupportedSqlException $e) {
    // $e->getSql() => 'CREATE DATABASE test'
    // $e->getCategory() => 'Unsupported'
}
```

### Recommended Settings

| Environment | Recommended Mode | Reason |
|-------------|-----------------|--------|
| Development | `notice` | Continue development while tracking unsupported SQL |
| CI/Testing | `exception` + behaviorRules | Detect unintended unsupported SQL |
| Production-equivalent testing | `ignore` | Test actual application behavior |
