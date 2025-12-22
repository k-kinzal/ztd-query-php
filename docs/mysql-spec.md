# MySQL SQL Specification for ZTD

This document defines how ZTD (Zero Downtime Deployment) handles MySQL SQL statements. ZTD simulates query results without modifying the physical database by using CTEs to shadow tables and virtualize writes.

## Overview

ZTD categorizes SQL statements into three handling modes:

| Mode | Description |
|------|-------------|
| **Rewrite** | Transform the query using CTE shadowing to simulate the operation |
| **Ignored** | Statement has no effect (by design, e.g., transaction control) |
| **Unsupported** | Follows Unsupported SQL behavior (ignore/notice/exception/passthrough) |

---

## DML (Data Manipulation Language)

### SELECT

| Mode | Behavior |
|------|----------|
| Rewrite | Apply CTE shadowing to inject virtual mutations from ShadowStore |

**Supported Syntax:**
- `SELECT ... FROM ...` - Basic select
- `SELECT ... WHERE ...` - Conditional select
- `SELECT ... JOIN ...` - Join operations (INNER, LEFT, RIGHT, CROSS, NATURAL)
- `SELECT ... GROUP BY ...` - Aggregation
- `SELECT ... HAVING ...` - Aggregate filtering
- `SELECT ... ORDER BY ...` - Sorting
- `SELECT ... LIMIT ... OFFSET ...` - Pagination
- `SELECT ... UNION [ALL] ...` - Set union
- `SELECT ... EXCEPT ...` - Set difference
- `SELECT ... INTERSECT ...` - Set intersection
- `SELECT ... (subquery)` - Subqueries
- `WITH ... SELECT ...` - Common Table Expressions
- `WITH RECURSIVE ... SELECT ...` - Recursive CTEs
- `SELECT ... WINDOW ...` - Window function definitions
- `SELECT ... FOR UPDATE` - Row locking (lock ignored, CTE applied)
- `SELECT ... FOR SHARE` - Shared locking (lock ignored, CTE applied)
- `SELECT ... LOCK IN SHARE MODE` - Shared locking (lock ignored, CTE applied)

**Unsupported Syntax:**
- `SELECT ... PARTITION (...)` - Parser limitation (PhpMyAdmin SQL Parser)
- `SELECT ... INTO OUTFILE ...` - File output not supported
- `SELECT ... INTO DUMPFILE ...` - File output not supported
- `SELECT ... INTO @var` - Variable assignment not supported

### INSERT

| Mode | Behavior |
|------|----------|
| Rewrite | Convert to result-select query, store mutation in ShadowStore |

**Transformation:**
```sql
-- Original
INSERT INTO users (id, name) VALUES (1, 'Alice')

-- Transformed (Result Select)
SELECT 1 AS id, 'Alice' AS name
```

**Supported Syntax:**
- `INSERT INTO ... VALUES (...)` - Single row insert
- `INSERT INTO ... VALUES (...), (...)` - Multi-row insert
- `INSERT INTO ... SET col=val` - SET syntax insert
- `INSERT ... SELECT ...` - Insert from subquery
- `INSERT ... ON DUPLICATE KEY UPDATE` - Upsert
- `INSERT IGNORE ...` - Skip duplicates
- `WITH ... INSERT ...` - CTE with INSERT
- `INSERT LOW_PRIORITY ...` - Priority hint (ignored)
- `INSERT DELAYED ...` - Delayed hint (ignored)
- `INSERT HIGH_PRIORITY ...` - Priority hint (ignored)

**Unsupported Syntax:**
- `INSERT ... PARTITION (...)` - Parser limitation

### REPLACE

| Mode | Behavior |
|------|----------|
| Rewrite | Convert to result-select query, store mutation in ShadowStore |

**Supported Syntax:**
- `REPLACE INTO ... VALUES (...)` - Replace insert
- `REPLACE INTO ... SET col=val` - SET syntax replace
- `REPLACE ... SELECT ...` - Replace from subquery
- `REPLACE LOW_PRIORITY ...` - Priority hint (ignored)
- `REPLACE DELAYED ...` - Delayed hint (ignored)

### UPDATE

| Mode | Behavior |
|------|----------|
| Rewrite | Convert to result-select query, store mutation in ShadowStore |

**Transformation:**
```sql
-- Original
UPDATE users SET name = 'Bob' WHERE id = 1

-- Transformed (Result Select with CTE shadowing)
WITH __ztd_users AS (
  SELECT * FROM users
  UNION ALL SELECT ... -- previous mutations
)
SELECT id, 'Bob' AS name FROM __ztd_users WHERE id = 1
```

**Supported Syntax:**
- `UPDATE ... SET ... WHERE ...` - Single table update
- `UPDATE ... SET ... ORDER BY ... LIMIT ...` - Limited update
- `UPDATE t1, t2 SET ... WHERE ...` - Multi-table update
- `UPDATE ... JOIN ... SET ...` - Join update
- `WITH ... UPDATE ...` - CTE with UPDATE
- `UPDATE LOW_PRIORITY ...` - Priority hint (ignored)
- `UPDATE IGNORE ...` - Error ignore hint (applied)

**Unsupported Syntax:**
- `UPDATE ... PARTITION (...)` - Parser limitation

### DELETE

| Mode | Behavior |
|------|----------|
| Rewrite | Convert to result-select query, store mutation in ShadowStore |

**Transformation:**
```sql
-- Original
DELETE FROM users WHERE id = 1

-- Transformed (Result Select with CTE shadowing)
WITH __ztd_users AS (
  SELECT * FROM users
  UNION ALL SELECT ... -- previous mutations
)
SELECT * FROM __ztd_users WHERE id = 1
```

**Supported Syntax:**
- `DELETE FROM ... WHERE ...` - Single table delete
- `DELETE FROM ... ORDER BY ... LIMIT ...` - Limited delete
- `DELETE ... USING ...` - USING syntax delete
- `DELETE t1 FROM t1 JOIN t2 ...` - Join delete
- `DELETE t1, t2 FROM ...` - Multi-table delete
- `WITH ... DELETE ...` - CTE with DELETE
- `DELETE LOW_PRIORITY ...` - Priority hint (ignored)
- `DELETE QUICK ...` - Quick hint (ignored)
- `DELETE IGNORE ...` - Error ignore hint (applied)

**Unsupported Syntax:**
- `DELETE ... PARTITION (...)` - Parser limitation

### TRUNCATE

| Mode | Behavior |
|------|----------|
| Rewrite | Clear all virtual data for the table in ShadowStore |

**Supported Syntax:**
- `TRUNCATE TABLE ...`

### CALL / DO / HANDLER / LOAD DATA

| Mode | Behavior |
|------|----------|
| Unsupported | Follows Unsupported SQL behavior (ignore/notice/exception) |

**Reason:** These operations are opaque or too complex to simulate:
- `CALL` - Stored procedure results cannot be predicted
- `DO` - Expression execution has no visible output
- `HANDLER` - Low-level table access
- `LOAD DATA` - Bulk loading from files

---

## DDL (Data Definition Language)

ZTD manages DDL virtually within SchemaRegistry. Physical database schema is never modified.

### CREATE TABLE

| Mode | Behavior |
|------|----------|
| Rewrite | Register virtual table in SchemaRegistry |

**Supported Syntax:**
- `CREATE TABLE ...` - Create virtual table
- `CREATE TABLE IF NOT EXISTS ...` - Conditional create
- `CREATE TABLE ... LIKE ...` - Copy structure from existing table
- `CREATE TABLE ... AS SELECT ...` - Create from query result
- `CREATE TEMPORARY TABLE ...` - Temporary table (treated as normal)

### ALTER TABLE

| Mode | Behavior |
|------|----------|
| Rewrite | Modify virtual table structure in SchemaRegistry |

**Supported Operations:**
- `ADD COLUMN` - Add virtual column
- `ADD [COLUMN] (col1, col2, ...)` - Add multiple columns
- `DROP COLUMN` - Remove virtual column
- `MODIFY COLUMN` - Modify column definition
- `CHANGE COLUMN` - Rename and modify column
- `ALTER COLUMN ... SET DEFAULT` - Set default value
- `ALTER COLUMN ... DROP DEFAULT` - Remove default value
- `ADD PRIMARY KEY` - Add primary key
- `DROP PRIMARY KEY` - Remove primary key
- `ADD FOREIGN KEY` - Add foreign key definition
- `DROP FOREIGN KEY` - Remove foreign key definition
- `ADD CONSTRAINT` - Add constraint definition
- `DROP CONSTRAINT` - Remove constraint definition
- `RENAME TO` - Rename virtual table
- `RENAME COLUMN` - Rename column

**Unsupported Operations:**
- Index operations (`ADD INDEX`, `DROP INDEX`, `ADD KEY`, `DROP KEY`, etc.)
- `ORDER BY`, `CONVERT TO CHARACTER SET`, `ENGINE = ...`
- Partition operations

### DROP TABLE

| Mode | Behavior |
|------|----------|
| Rewrite | Remove virtual table from SchemaRegistry |

**Supported Syntax:**
- `DROP TABLE ...`
- `DROP TABLE IF EXISTS ...`
- `DROP TEMPORARY TABLE ...`

### Other DDL

| Mode | Behavior |
|------|----------|
| Unsupported | Follows Unsupported SQL behavior (ignore/notice/exception) |

**Unsupported Objects:**
- INDEX, DATABASE, SCHEMA, VIEW, PROCEDURE, FUNCTION
- TRIGGER, EVENT, USER, ROLE, SERVER, TABLESPACE
- LOGFILE GROUP, RESOURCE GROUP, SPATIAL REFERENCE SYSTEM

---

## TCL (Transaction Control Language)

| Mode | Behavior |
|------|----------|
| Ignored | Transaction statements have no effect (by design) |

**Design Decision:** ZTD manages virtual state through fixtures, making explicit transaction control unnecessary. Transaction statements are silently ignored rather than treated as unsupported.

**Ignored Statements:**
- `START TRANSACTION` / `BEGIN` / `BEGIN WORK`
- `COMMIT` / `COMMIT WORK` / `COMMIT AND [NO] CHAIN`
- `ROLLBACK` / `ROLLBACK WORK` / `ROLLBACK AND [NO] CHAIN`
- `SAVEPOINT` / `RELEASE SAVEPOINT` / `ROLLBACK TO SAVEPOINT`
- `SET TRANSACTION ...` / `SET autocommit = ...`
- `XA START/BEGIN/END/PREPARE/COMMIT/ROLLBACK/RECOVER`

---

## DCL (Data Control Language)

| Mode | Behavior |
|------|----------|
| Unsupported | Follows Unsupported SQL behavior (ignore/notice/exception) |

**Unsupported Categories:**
- `GRANT ... TO ...` / `REVOKE ... FROM ...`
- `CREATE/ALTER/DROP USER`
- `CREATE/DROP ROLE`
- `SET ROLE ...` / `SET DEFAULT ROLE ...` / `SET PASSWORD ...`

---

## Utility Statements

### Information Retrieval

| Mode | Behavior |
|------|----------|
| Unsupported | Follows Unsupported SQL behavior (ignore/notice/exception/passthrough) |

**Unsupported Statements:**
- `SHOW DATABASES/TABLES/COLUMNS/INDEX/STATUS/VARIABLES/...`
- `SHOW CREATE TABLE/DATABASE/VIEW/...`
- `DESCRIBE/DESC/EXPLAIN ...`

> **Note:** ZTD works with fixtures, not real database metadata. These statements are unsupported by default.

### Session Management

| Mode | Behavior |
|------|----------|
| Unsupported | Follows Unsupported SQL behavior (ignore/notice/exception/passthrough) |

**Unsupported Statements:**
- `USE ...` - Database switch
- `SET NAMES/CHARACTER SET ...` - Charset setting
- `SET [GLOBAL/SESSION] ...` - Variable setting

### Maintenance / Locking / Cache / Server Admin

| Mode | Behavior |
|------|----------|
| Unsupported | Follows Unsupported SQL behavior (ignore/notice/exception) |

**Unsupported Categories:**
- `ANALYZE/CHECK/CHECKSUM/OPTIMIZE/REPAIR TABLE`
- `LOCK/UNLOCK TABLES`
- `FLUSH/RESET/CACHE INDEX`
- `KILL/SHUTDOWN/RESTART`
- `INSTALL/UNINSTALL PLUGIN/COMPONENT`
- `PREPARE/EXECUTE/DEALLOCATE PREPARE`
- `START/STOP SLAVE/REPLICA`
- `CHANGE MASTER/REPLICATION SOURCE TO`

---

## Multiple Statements

| Mode | Behavior |
|------|----------|
| Rewrite | Process each statement sequentially via `rewriteMultiple()` |

**Supported:**
```sql
SELECT 1; SELECT 2
INSERT INTO t VALUES (1); UPDATE t SET x = 2
```

**Result:** `MultiRewritePlan` containing individual `RewritePlan` for each statement.

**Result Retrieval:** Use `nextRowset()` to iterate through result sets.

---

## Unsupported SQL Handling

When an unsupported SQL statement is executed, behavior depends on configuration. Each statement pattern can be configured with a specific behavior.

### Behavior Modes

| Mode | Description |
|------|-------------|
| `ignore` | Silently ignore, return empty result |
| `notice` | Log warning, continue with empty result |
| `exception` | Throw `UnsupportedSqlException` |
| `passthrough` | Execute against real database as-is |

### Per-Statement Configuration

Different behaviors can be configured per SQL pattern:

| Pattern | Behavior | Reason |
|---------|----------|--------|
| `SET NAMES` | `passthrough` | Affects connection encoding |
| `SET sql_mode` | `passthrough` | Affects SQL parsing behavior |
| `SHOW TABLES` | `passthrough` or `ignore` | Metadata query |
| `CREATE DATABASE` | `exception` | Should not be used in tests |
| `CALL ...` | `exception` | Stored procedures not supported |
| Default | `ignore` | Fallback for unspecified patterns |

### Recommended Configurations

| Environment | Default Behavior | Notes |
|-------------|-----------------|-------|
| Development | `notice` | Identify unsupported SQL while continuing work |
| CI/Testing | `exception` | Detect unexpected unsupported SQL |
| Production-like | `ignore` | Test actual application behavior |

---

## Error Handling

ZTD manages both virtual schema (SchemaRegistry) and virtual data (ShadowStore). Error handling must consider the interaction between virtual and real database state.

### Error Detection Layers

```
┌─────────────────────────────────────────────────────────────┐
│  1. SQL Parser                                              │
│     └─ Syntax errors detected before execution              │
├─────────────────────────────────────────────────────────────┤
│  2. ZTD Virtual Layer                                       │
│     └─ Schema/constraint checks against virtual state       │
├─────────────────────────────────────────────────────────────┤
│  3. Database Engine                                         │
│     └─ Errors from actual query execution                   │
└─────────────────────────────────────────────────────────────┘
```

### Schema Errors

#### Non-existent Table

| Operation | Detection | Behavior |
|-----------|-----------|----------|
| SELECT FROM unknown_table | ZTD | `SchemaNotFoundException` |
| INSERT INTO unknown_table | ZTD | `SchemaNotFoundException` |
| UPDATE unknown_table | ZTD | `SchemaNotFoundException` |
| DELETE FROM unknown_table | ZTD | `SchemaNotFoundException` |
| DROP TABLE unknown_table | ZTD | `SchemaNotFoundException` |
| ALTER TABLE unknown_table | ZTD | `SchemaNotFoundException` |

**Design Decision:** ZTD requires all referenced tables to be registered in SchemaRegistry. If a table schema is not found, ZTD throws `SchemaNotFoundException` before query execution.

**Example:**
```php
// unknown_table is not registered in SchemaRegistry
$pdo->query('SELECT * FROM unknown_table WHERE id = 1');
// Throws SchemaNotFoundException (before any DB execution)
```

#### Non-existent Column

| Operation | Detection | Behavior |
|-----------|-----------|----------|
| SELECT unknown_col FROM t | Database | PDOException from database |
| INSERT INTO t (unknown_col) | Database | PDOException from database |
| UPDATE t SET unknown_col = 1 | Database | PDOException from database |
| ALTER TABLE t DROP unknown_col | ZTD | `ColumnNotFoundException` |
| ALTER TABLE t MODIFY unknown_col | ZTD | `ColumnNotFoundException` |

**Design Decision:** For DML operations, column validation is performed by the database when the CTE-rewritten query is executed. The CTE defines all available columns based on the schema, and requesting a non-existent column results in a database error.

**Example:**
```sql
-- Schema: users (id INT, email VARCHAR)
-- Original
SELECT unknown_col FROM users WHERE id = 1

-- Rewritten (CTE defines only id and email)
WITH users AS (
  SELECT 1 AS id, 'alice@example.com' AS email
)
SELECT unknown_col FROM users WHERE id = 1
-- PDOException: Unknown column 'unknown_col'
```

For DDL operations (ALTER TABLE), ZTD validates against SchemaRegistry directly.

#### Table/Column Already Exists

| Operation | Detection | Behavior |
|-----------|-----------|----------|
| CREATE TABLE existing_table | ZTD | `TableAlreadyExistsException` |
| CREATE TABLE IF NOT EXISTS | ZTD | No action (table already exists) |
| ALTER TABLE t ADD existing_col | ZTD | `ColumnAlreadyExistsException` |

### Empty Fixture Handling

When a table schema exists in SchemaRegistry but has no fixture data, ZTD generates an empty CTE.

| State | Behavior |
|-------|----------|
| Schema registered, no fixture rows | Empty result set (not an error) |
| Schema registered, fixture rows exist | Fixture data returned |

**Example:**
```sql
-- Schema: users (id INT, email VARCHAR) is registered
-- No fixture rows provided

-- Original
SELECT * FROM users WHERE id = 1

-- Rewritten (empty CTE)
WITH users AS (
  SELECT
    CAST(NULL AS SIGNED) AS id,
    CAST(NULL AS CHAR(255)) AS email
  WHERE FALSE  -- Returns 0 rows
)
SELECT * FROM users WHERE id = 1
-- → Empty result set (no error)
```

**Design Decision:** An empty fixture is a valid test state representing "table exists but has no data." This allows testing behavior with empty tables without requiring dummy fixture rows.

### Constraint Violations

ZTD converts INSERT/UPDATE/DELETE to SELECT queries (Result Select Query). Since the database only executes SELECT, it cannot detect constraint violations. Therefore, ZTD must validate constraints before transformation.

#### Primary Key / Unique Constraint

| Scenario | Detection | Behavior |
|----------|-----------|----------|
| Duplicate key in fixture data | ZTD | `DuplicateKeyException` |
| INSERT conflicts with existing fixture | ZTD | `DuplicateKeyException` |

**Design Decision:** ZTD checks for duplicates against existing fixture data before Result Select transformation. If a duplicate is found, transformation is aborted and an exception is thrown.

**Example:**
```sql
-- Existing Fixture: users (id=1, name='Alice')
-- Original
INSERT INTO users (id, name) VALUES (1, 'Bob')

-- ZTD checks: id=1 already exists in fixture
-- Throws DuplicateKeyException (transformation aborted)
```

#### Foreign Key Constraint

| Scenario | Detection | Behavior |
|----------|-----------|----------|
| Referenced row not in fixture | ZTD | `ForeignKeyViolationException` |

**Design Decision:** ZTD always validates FK references against fixture data.

**Example:**
```sql
-- Fixture: departments (id=1)
-- Original
INSERT INTO users (id, department_id) VALUES (1, 999)

-- ZTD checks: department_id=999 not found in departments fixture
-- Throws ForeignKeyViolationException
```

#### NOT NULL Constraint

| Scenario | Detection | Behavior |
|----------|-----------|----------|
| INSERT with NULL in NOT NULL column | ZTD | `NotNullViolationException` |
| UPDATE setting NULL to NOT NULL column | ZTD | `NotNullViolationException` |

**Design Decision:** ZTD validates NOT NULL constraints using SchemaRegistry metadata.

#### CHECK Constraint (MySQL 8.0.16+)

| Status | Behavior |
|--------|----------|
| Unsupported | Follows Unsupported SQL behavior (ignore/notice/exception) |

**Design Decision:** CHECK constraint evaluation requires SQL expression interpretation, which is out of scope.

### Data Type Errors

ZTD applies CAST to all values in INSERT/UPDATE based on SchemaRegistry metadata. Type conversion errors are detected by the database.

| Scenario | Detection | Behavior |
|----------|-----------|----------|
| Type mismatch (convertible) | Database | Implicit conversion via CAST |
| Type mismatch (not convertible) | Database | PDOException from CAST failure |
| Value out of range | Database | PDOException from database |
| Invalid date/time format | Database | PDOException from database |

**Design Decision:** ZTD uses schema metadata to apply appropriate CAST expressions to all values. This mimics MySQL's implicit type conversion behavior and delegates error detection to the database.

**Example:**
```sql
-- Schema: users (id INT, name VARCHAR(255), created_at DATETIME)
-- Original
INSERT INTO users (id, name, created_at) VALUES ('123', 'Alice', '2024-01-01')

-- Result Select (with CAST)
SELECT
  CAST('123' AS SIGNED) AS id,
  CAST('Alice' AS CHAR(255)) AS name,
  CAST('2024-01-01' AS DATETIME) AS created_at

-- '123' is successfully converted to 123
-- 'not_a_number' would cause: PDOException: Truncated incorrect INTEGER value
```

### Syntax Errors

| Scenario | Detection | Behavior |
|----------|-----------|----------|
| Invalid SQL syntax (typo, malformed) | Parser | `SqlParseException` |
| Parser limitation (valid MySQL, parser cannot handle) | ZTD | Follows Unsupported behavior |

**Design Decision:** Invalid SQL syntax results in `SqlParseException`. Parser limitations (valid MySQL syntax that the parser cannot handle, e.g., PARTITION clause) follow the Unsupported SQL behavior configuration.

**Example:**
```php
// Invalid syntax - always error
$pdo->query('SELEC * FROM users');
// Throws SqlParseException

// Parser limitation (PARTITION clause) - follows Unsupported behavior
$pdo->query('SELECT * FROM users PARTITION (p0)');
// ignore: no-op
// notice: log warning, continue
// exception: throws UnsupportedSqlException
```

**Implementation Note:** To distinguish between syntax errors and parser limitations, ZTD checks against a known list of parser limitation patterns when parsing fails.

---

## Design Principles

1. **Zero Physical Impact** - All operations are virtual; physical tables are never modified
2. **SQL as Pure Functions** - Input (fixtures) and output (query results) only, no side effects
3. **Real Database Engine** - Uses actual database for SQL parsing, type inference, and query execution
4. **CTE-Based Isolation** - Tables are shadowed with CTEs, not mocked
5. **Simplicity** - Complex features (stored procedures, triggers, views) are out of scope
6. **Type Conversion via CAST** - Schema metadata used to apply appropriate type casts
