# Compatibility

mysql-memory aims to answer every statement as the emulated MySQL release answers it, as a client observes the answer. This document explains what that covers, how it is checked, what is emulated, and where the emulator is known to differ. Every difference listed here can be reproduced with the statement given or described.

## What "the same answer" means

For each statement, a client observes:

- for a statement that returns rows: the result columns (name, table, original table and database, type code, length, decimals, flags, collation) and the rows, each value as the text the server sends;
- for any other statement: the affected-row count, the last insert id and the information text (`Records: 2  Duplicates: 0  Warnings: 0`);
- for a statement that fails: the error number, the SQLSTATE and the message;
- the warnings and notes `SHOW WARNINGS` lists afterwards, and the rows of every table afterwards.

mysql-memory reproduces all of them, including the order in which the server checks a statement: when a statement has several problems, the error reported is the one the server reports first.

## How it is checked

- **Specification.** Each rule is written from the MySQL reference manual, which the code cites. Where the manual is silent, the rule was read from a live server, and the code says so ("verified on a live 8.4 server").
- **Unit tests** cover each rule.
- **Differential fuzzing** (`fuzz/`) generates statements from the official MySQL grammar with SQL Faker, runs each one on a real MySQL server and on mysql-memory from the same fixture tables, and requires every observation above to be equal. A statement whose observation differs between two runs on the real server (it reads the clock, a random value, or a server identity) is not compared. mysql-memory is started with the global variables of the real server, so host names, paths and identities agree. The README explains [how to run it](../README.md#how-correctness-is-checked).

## Releases

The `version` argument selects one of the MySQL grammar releases of SQL Parser: `5.6.51`, `5.7.44`, `8.0.44`, `8.1.0`, `8.2.0`, `8.3.0`, `8.4.7`, `9.0.1` or `9.1.0`. It decides:

- the grammar and keywords a statement is parsed with, including version comments (`/*!80000 ... */`);
- the system variables that exist and their defaults, such as the default `sql_mode`;
- what `VERSION()` and `@@version` report.

The rest of the behavior is modeled on MySQL 8.4: the error messages, the default character set and collation (`utf8mb4`, `utf8mb4_0900_ai_ci`), the text of `SHOW CREATE TABLE`, the accounts of a new installation, and the storage engines, plugins and privileges `SHOW` lists. Releases of the 8.x and 9.x series share most of it. MySQL 5.6 and 5.7 differ in many of these points, so expect differences there: for example, 5.7 names a duplicate key `'PRIMARY'` where mysql-memory names it `'t.PRIMARY'`, and writes `int(11)` where mysql-memory writes `int`.

## Connecting

- The server speaks the client/server protocol 4.1: the handshake with `mysql_native_password`, text queries, multiple statements and multiple results in one query, and the binary protocol of server-side prepared statements, including long data.
- Any user name and password are accepted. The session belongs to the user name given, at the host the client connects from (or the `clientHost` of the server); `CURRENT_USER()` reports the user at `%`.
- `COM_INIT_DB`, `COM_PING`, `COM_RESET_CONNECTION` and `COM_QUIT` are answered.
- TLS, compression and `CLIENT_DEPRECATE_EOF` are not offered. `CLIENT_FOUND_ROWS` is offered but not honored: an `UPDATE` reports the rows it changed, not the rows it matched, even when the client asks for found rows (`PDO::MYSQL_ATTR_FOUND_ROWS`).
- Packets of 16 MiB or more, which the protocol splits, are not supported.

## Statements

| Statements | Status |
|------------|--------|
| `SELECT`, `VALUES`, `TABLE`, set operations, `WITH` | Emulated; see [Queries](#queries) |
| `INSERT`, `REPLACE`, `UPDATE`, `DELETE` | Emulated; see [Writes](#writes) |
| `CREATE`, `ALTER`, `DROP DATABASE`; `CREATE`, `ALTER`, `RENAME`, `DROP`, `TRUNCATE TABLE`; `CREATE`, `DROP INDEX` | Emulated; see [Definitions](#definitions) |
| `CREATE`, `ALTER`, `DROP VIEW` | Emulated; see [Views](#views) |
| Procedures, functions, triggers, events | Definitions emulated; see [Stored programs](#stored-programs) |
| `CALL` | Emulated for simple procedures; see [Stored programs](#stored-programs) |
| Users, roles, passwords, `GRANT`, `REVOKE`, `SET ROLE`, `SET DEFAULT ROLE` | Emulated, not enforced; see [Accounts](#accounts) |
| `START TRANSACTION`, `BEGIN`, `COMMIT`, `ROLLBACK`, savepoints, `XA` | Emulated; see [Transactions](#transactions) |
| `SET`, `SET NAMES`, `SET CHARACTER SET`, `SET PERSIST` | Emulated; see [Variables](#variables) |
| `PREPARE`, `EXECUTE`, `DEALLOCATE PREPARE` | Emulated |
| `SIGNAL`, `RESIGNAL`, `GET DIAGNOSTICS` | Emulated outside stored programs |
| `LOCK TABLES`, `UNLOCK TABLES` | Emulated; locks never wait |
| `HANDLER` | Emulated |
| `DO`, `USE` | Emulated |
| `SHOW`, `DESCRIBE`, `EXPLAIN`, `HELP` | See [Inspection](#inspection) |
| `CHECK`, `ANALYZE`, `OPTIMIZE`, `REPAIR`, `CHECKSUM TABLE`, `CACHE INDEX`, `LOAD INDEX` | Emulated as for InnoDB tables; `CHECKSUM TABLE` values are not the server's |
| `FLUSH`, `RESET PERSIST`, `ALTER INSTANCE`, `LOCK INSTANCE FOR BACKUP` | Answered as a server without caches, log files or keyring answers |
| Tablespaces, log file groups, resource groups, foreign servers, spatial reference systems | Kept and checked as the server checks them; no file is written |
| `INSTALL`, `UNINSTALL PLUGIN` and `COMPONENT` | Refused as a server with no plugin library refuses them |
| Replication and binary log statements | Answered as a server that is neither a replica nor a source with replicas |
| `LOAD DATA`, `IMPORT TABLE`, `SELECT ... INTO OUTFILE` / `DUMPFILE` | Refused as a server with `--secure-file-priv` and `local_infile` off refuses them |
| `SET TRANSACTION`, `SHOW DATABASES`, `SHOW PROCESSLIST`, `KILL`, `SHUTDOWN`, `RESTART`, `CLONE`, `CREATE FUNCTION ... SONAME` | Not supported: error 1235 |

A statement, an expression form or a function that the emulator does not run fails with error 1235 (`ER_NOT_SUPPORTED_YET`, SQLSTATE `42000`), whose message names it: `This version of MySQL doesn't yet support 'SetTransaction'`. It fails before it changes anything. The server raises the same error for a few statements it does not support itself; those are not differences.

## Queries

- Query blocks with every clause: `FROM`, `WHERE`, `GROUP BY` with `WITH ROLLUP` and `GROUPING()`, `HAVING`, `DISTINCT`, `ORDER BY` (by expression, alias or position), `LIMIT` and `OFFSET`, `SQL_CALC_FOUND_ROWS`, `FOR UPDATE` and `FOR SHARE`.
- Joins: comma, `INNER`, `CROSS`, `LEFT`, `RIGHT`, `NATURAL`, `USING`, `STRAIGHT_JOIN`; derived tables, including `LATERAL`; index hints are accepted.
- Common table expressions, including recursive ones; `UNION`, `INTERSECT` and `EXCEPT` with `ALL` and `DISTINCT`; `VALUES ROW(...)` and `TABLE t`.
- Subqueries: scalar, row, `IN`, `NOT IN`, `EXISTS`, `ANY`, `SOME`, `ALL`, correlated or not.
- `SELECT ... INTO @variables`.
- The `ONLY_FULL_GROUP_BY` check (error 1055) with the functional dependencies the server recognizes.
- Result column names, including the names of unaliased expressions (`SELECT 1+1` names its column `1+1`), and the column metadata the server sends, which depends on whether a column is read from a base table, a view, a derived table or a temporary table.

Not supported: window functions (`OVER`), `JSON_TABLE`, full-text search (`MATCH ... AGAINST`), partition selection (`FROM t PARTITION (p0)`) and optimizer hints (`/*+ ... */`).

Without `ORDER BY`, a table is read in the order of its clustered index (the primary key, else the first unique key over `NOT NULL` columns, else insertion order), as an InnoDB table scan reads it. The server may choose another access path and return rows in another order; only `ORDER BY` fixes the order on both.

## Expressions

- Literals of every form: numbers, strings with character set introducers, hexadecimal and bit values, `DATE`, `TIME` and `TIMESTAMP` literals, ODBC escapes, `TRUE`, `FALSE`, `NULL`.
- Arithmetic over integers, exact decimals (with `div_precision_increment`) and doubles, with the server's overflow errors (`BIGINT value is out of range`), division by zero (NULL, with a warning or an error as `sql_mode` says), `DIV`, `MOD`, `%`, bit operators.
- Comparison with the server's conversions: a string compared with a number is read as a number, with the warning `Truncated incorrect DOUBLE value`; `=`, `<=>`, `<>`, `<`, `IS [NOT] NULL`, `IS [NOT] TRUE`, `BETWEEN`, `IN`, `LIKE` with `ESCAPE`, `REGEXP`, `SOUNDS LIKE`, row comparisons.
- Collations: the collation of each comparison is decided by coercibility as the server decides it (error 1267 for an illegal mix). The Unicode collations compare with ICU, at the strength of the collation, so `'é' = 'e'` is true under `utf8mb4_0900_ai_ci` and false under `utf8mb4_0900_as_cs`; `PAD SPACE` and `NO PAD` collations treat trailing spaces as the server does.
- `CASE`, `CAST` and `CONVERT` to every target type, `CONVERT ... USING`, `BINARY`, `COLLATE`.
- Date arithmetic: `DATE_ADD`, `DATE_SUB`, `+ INTERVAL`, `- INTERVAL` with every unit, and `EXTRACT`.
- JSON: the `->` and `->>` operators, `MEMBER OF`, and JSON columns, which store the normalized document (`'{"b":1,"a":2}'` reads back as `{"a": 2, "b": 1}`). `CAST(text AS JSON)` differs: it answers the text as written, not the normalized document.
- User variables (`@v`, `@v := expr`) and system variables (`@@v`, `@@SESSION.v`, `@@GLOBAL.v`).
- `NOW()`, `CURRENT_TIMESTAMP`, `CURDATE()`, `CURTIME()`, `LOCALTIME`, `LOCALTIMESTAMP`, the `UTC_` clocks and `SYSDATE()`. Every clock of a statement except `SYSDATE()` reads the instant the statement started.

### Built-in functions

Besides the keyword forms above (`CAST`, `CONVERT`, `TRIM`, `POSITION`, `SUBSTRING ... FROM`, `CHAR`, `EXTRACT`, `DATE_ADD`, `DATE_SUB` and the clocks), these functions are evaluated:

| Family | Functions |
|--------|-----------|
| Strings | `CONCAT`, `CONCAT_WS`, `HEX`, `UNHEX`, `LPAD`, `RPAD`, `LTRIM`, `RTRIM`, `REPEAT`, `REPLACE`, `REVERSE`, `SPACE`, `LOWER`, `LCASE`, `UPPER`, `UCASE`, `INSERT`, `LEFT`, `RIGHT`, `MID`, `SUBSTR`, `SUBSTRING`, `SUBSTRING_INDEX` |
| String measures | `ASCII`, `BIT_LENGTH`, `CHAR_LENGTH`, `CHARACTER_LENGTH`, `LENGTH`, `OCTET_LENGTH`, `FIELD`, `FIND_IN_SET`, `INSTR`, `LOCATE`, `STRCMP` |
| Numbers | `ABS`, `ACOS`, `ASIN`, `ATAN`, `CEIL`, `CEILING`, `COS`, `COT`, `DEGREES`, `EXP`, `FLOOR`, `LN`, `LOG`, `LOG10`, `LOG2`, `PI`, `POW`, `POWER`, `RADIANS`, `ROUND`, `SIGN`, `SIN`, `SQRT`, `TAN`, `TRUNCATE` |
| Control flow | `COALESCE`, `IF`, `IFNULL`, `ISNULL`, `NULLIF` |
| Dates | `DATE`, `DAY`, `DAYOFMONTH`, `DAYOFWEEK`, `DAYOFYEAR`, `HOUR`, `MICROSECOND`, `MINUTE`, `MONTH`, `QUARTER`, `SECOND`, `WEEKDAY`, `YEAR` |
| Information | `CHARSET`, `COERCIBILITY`, `COLLATION`, `CONNECTION_ID`, `CURRENT_ROLE`, `CURRENT_USER`, `DATABASE`, `SCHEMA`, `FOUND_ROWS`, `LAST_INSERT_ID`, `ROW_COUNT`, `SESSION_USER`, `SYSTEM_USER`, `USER`, `VERSION` |
| Aggregates | `COUNT` (with `DISTINCT`), `SUM`, `AVG`, `MIN`, `MAX`, `BIT_AND`, `BIT_OR`, `BIT_XOR`, `STD`, `STDDEV`, `STDDEV_POP`, `STDDEV_SAMP`, `VARIANCE`, `VAR_POP`, `VAR_SAMP`, `GROUP_CONCAT` (with `ORDER BY` and `SEPARATOR`) |

Any other function fails with error 1235 naming it, for example `This version of MySQL doesn't yet support 'function DATE_FORMAT'`. Among them are the JSON functions (`JSON_EXTRACT`, `JSON_OBJECT` and the rest), the regular expression functions, the hash, random and UUID functions, `GREATEST` and `LEAST`, `FORMAT`, the locking functions, the spatial functions, and date functions such as `DATE_FORMAT`, `STR_TO_DATE`, `DATEDIFF`, `TIMESTAMPDIFF`, `LAST_DAY` and `CONVERT_TZ`. `JSON_ARRAYAGG` is accepted but answers NULL, and `JSON_OBJECTAGG` fails with error 1235.

## Writes

- `INSERT` and `REPLACE` with `VALUES`, `VALUE`, `SET` or a query, with or without a column list; `DEFAULT` and `DEFAULT(col)`; `INSERT IGNORE`; `ON DUPLICATE KEY UPDATE` with `VALUES(col)`.
- `UPDATE` and `DELETE` of one table with `ORDER BY` and `LIMIT`; multiple-table `UPDATE` and `DELETE`.
- The affected-row count, the last insert id and the information text are the server's: an `INSERT ... ON DUPLICATE KEY UPDATE` counts 2 for a row it updates, a `REPLACE` 2 for a row it replaces, and an `UPDATE` counts the rows it changed.
- Values are stored as the column type says. Under a non-strict `sql_mode` an integer out of range is clipped, a string too long is cut, and a string that is no number reads as 0, each with the server's warning; under a strict mode each is the server's error. Dates, times, `ENUM`, `SET`, `BIT`, `YEAR`, `DECIMAL` and JSON columns follow the rules of the server, including the zero-date checks of `NO_ZERO_DATE` and `NO_ZERO_IN_DATE`.
- Primary and unique keys are enforced (error 1062, with the key named `table.key` as MySQL 8.0 and later name it). `AUTO_INCREMENT` generates values, honors `NO_AUTO_VALUE_ON_ZERO`, and is reset by `TRUNCATE TABLE`.
- Column defaults: constants, expressions (`DEFAULT (1 + 1)`), `CURRENT_TIMESTAMP` and `ON UPDATE CURRENT_TIMESTAMP`.
- A statement that fails changes nothing, even after some of its rows were written.

Not emulated:

- **Foreign keys** are kept in the table definition but neither checked nor acted on: a row may reference a missing parent, and deleting a parent neither fails nor cascades.
- **`CHECK` constraints** are kept but not checked.
- **Generated columns** are kept but not computed: their values are NULL.
- **`ON DUPLICATE KEY UPDATE` with a row alias** (`INSERT ... AS new ON DUPLICATE KEY UPDATE a = new.a`) fails with error 1235 when a row conflicts.
- **Spatial values**: spatial columns can be declared and hold NULL, but no spatial value can be made.

## Definitions

- `CREATE DATABASE`, `ALTER DATABASE`, `DROP DATABASE`, with character sets and collations.
- `CREATE TABLE` with columns of every non-spatial type, keys, indexes, defaults, `AUTO_INCREMENT`, invisible columns, table options; `CREATE TABLE ... LIKE`; `CREATE TABLE ... SELECT`; `IF NOT EXISTS`.
- `ALTER TABLE` with its actions (columns, keys, renames, options), `CREATE INDEX`, `DROP INDEX`, `RENAME TABLE`, `DROP TABLE`, `TRUNCATE TABLE`. An `ALTER TABLE` converts the rows of the table into the new definition, with the warnings or errors of the server, and changes nothing when it fails.
- `SHOW CREATE TABLE` writes the definition as the server writes it.
- The statements that commit implicitly commit an open transaction first.

Differences:

- **Storage engines** are kept as written but have no effect: every table is transactional and behaves as an InnoDB table.
- **Temporary tables** are visible to every session of the server, outlive the session that created them, and cannot take the name of an existing table.
- **Partitioning**: a partitioned table can be created and behaves as one table; `ALTER TABLE ... PARTITION BY` fails with error 1235.

## Views

`CREATE VIEW`, `CREATE OR REPLACE VIEW`, `ALTER VIEW` and `DROP VIEW` are emulated with `ALGORITHM`, `DEFINER`, `SQL SECURITY` and `WITH CHECK OPTION`; `SHOW CREATE VIEW` writes the view as the server does. A view reads the current definition of its tables, and a view whose tables no longer fit it is invalid (error 1356). `INSERT`, `UPDATE` and `DELETE` through an updatable view write its base table; through a view that is not updatable they fail as on the server. Writing through a view of a view fails with error 1235.

## Stored programs

- `CREATE`, `ALTER` and `DROP` of procedures, functions, triggers and events, with their checks and characteristics, and the `SHOW CREATE`, `SHOW ... STATUS`, `SHOW ... CODE`, `SHOW TRIGGERS` and `SHOW EVENTS` statements that list them.
- `CALL` runs a procedure whose body is one statement, or a `BEGIN ... END` block of statements without declarations or flow control, with `IN` parameters. Each query of the body answers a result set, and the procedure then answers the completion of its last statement.

Not emulated:

- **Stored functions do not run**: a call fails with error 1235 (`calls of stored functions`).
- **Triggers never fire**, and **events never run**.
- **`CALL`** of a procedure with `OUT` or `INOUT` parameters, `DECLARE`, or flow control (`IF`, loops, handlers) fails with error 1235.

## Accounts

The server starts with the accounts of a new installation: `root` at `localhost` and at `%` with every privilege, and the locked system accounts `mysql.infoschema`, `mysql.session` and `mysql.sys`. `CREATE`, `ALTER`, `RENAME` and `DROP USER`, `CREATE` and `DROP ROLE`, `SET PASSWORD`, random passwords, `GRANT` and `REVOKE` of privileges and roles, `SET ROLE`, `SET DEFAULT ROLE`, `SHOW GRANTS` and `SHOW CREATE USER` are emulated, with the server's checks and errors.

**Privileges are not enforced.** Every session may run every statement, whatever its account and grants, and any password is accepted at connection.

## Transactions

- `START TRANSACTION`, `BEGIN`, `COMMIT`, `ROLLBACK`, `autocommit`, `SAVEPOINT`, `ROLLBACK TO SAVEPOINT`, `RELEASE SAVEPOINT`, and the implicit commits of the statements that cause them.
- `XA START`, `END`, `PREPARE`, `COMMIT`, `ROLLBACK` and `RECOVER`, with the XA states and errors of the server.
- Statement atomicity: a statement that fails restores every table it changed.

Differences:

- **No isolation between sessions.** A write is visible to every session at once, before it is committed. `ROLLBACK` restores the rows each table had when the transaction first changed it, which also discards what other sessions wrote to that table in the meantime.
- **No waiting.** Row locks (`FOR UPDATE`) and table locks (`LOCK TABLES`) never block another session.
- **`SET TRANSACTION`** fails with error 1235.

## Variables

- Every system variable of the selected release exists, with its scope (global, session or both), its default and its checks: a read-only variable, a global-only variable set for the session, or a value out of range is refused or adjusted as the server does. `SET` assigns all its variables or none.
- A session starts with the global values of the variables that have both scopes, so `SET GLOBAL` affects later sessions only.
- `SET PERSIST` and `SET PERSIST_ONLY` set the variable for the running server; nothing is written to an option file, and `RESET PERSIST` finds no persisted setting.
- `sql_mode` changes parsing (`ANSI_QUOTES`, `PIPES_AS_CONCAT`, `HIGH_NOT_PRECEDENCE`, `NO_BACKSLASH_ESCAPES`, `IGNORE_SPACE`), storage (the strict modes, `NO_ZERO_DATE`, `NO_ZERO_IN_DATE`, `ERROR_FOR_DIVISION_BY_ZERO`), grouping (`ONLY_FULL_GROUP_BY`) and the other behaviors the server ties to it.

Differences:

- **Values given at start** (`globals`, `--global`) are stored as written, without the checks and normalization of `SET GLOBAL`. `--global=sql_mode=ANSI` makes `@@sql_mode` read `ANSI`, where `SET GLOBAL sql_mode = 'ANSI'` stores the modes `ANSI` stands for.
- **Time zone.** The system time zone is UTC. `time_zone` can be set, but `NOW()` and the other clocks read UTC whatever it says.
- **Status variables** are not kept: `SHOW STATUS` lists none.

## Inspection

- `SHOW TABLES`, `SHOW COLUMNS` and `DESCRIBE`, `SHOW INDEX`, `SHOW TABLE STATUS`, `SHOW OPEN TABLES`, `SHOW CREATE DATABASE`, `TABLE` and `VIEW`, `SHOW VARIABLES`, `SHOW COLLATION`, `SHOW CHARACTER SET`, `SHOW ENGINES`, `SHOW ENGINE`, `SHOW PLUGINS`, `SHOW PRIVILEGES`, `SHOW PROFILES`, `SHOW WARNINGS`, `SHOW ERRORS`, `SHOW COUNT(*) WARNINGS`, and the `SHOW` statements of stored programs, accounts and replication, with the column metadata of the server.
- `SHOW TABLE STATUS` reports the figures InnoDB reports for a table of one page.
- `HELP` finds no topic: the server's help tables are not included.
- `EXPLAIN` answers the plan of a statement as a server that scans every table would: one row per table with access type `ALL` in the traditional format, and the matching `TREE` and `JSON` documents. Only the plan of a statement that reads no table is the server's; the plans of the server come from its optimizer and cost model, which the emulator does not have.

Differences:

- **No system tables.** The databases `information_schema`, `mysql`, `performance_schema` and `sys` exist but hold no table: `SELECT * FROM information_schema.TABLES` fails with error 1146 (`Table 'information_schema.TABLES' doesn't exist`).
- **`SHOW DATABASES`** and **`SHOW PROCESSLIST`** fail with error 1235.
- **`CHECKSUM TABLE`** answers a stable checksum of the rows, which is not the server's.

## Performance

The emulator is meant for the small tables of tests. It has no optimizer and does not use indexes to find rows: every table is scanned, every join reads its inner table once for each row of the outer one, and every sort, grouping and `DISTINCT` reads its whole input. A query over two tables of n rows each costs on the order of n² row reads.
