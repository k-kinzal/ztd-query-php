# MySQL Memory

[![Packagist Downloads](https://img.shields.io/packagist/dt/k-kinzal/mysql-memory.svg?label=Packagist)](https://packagist.org/packages/k-kinzal/mysql-memory)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-mysql--memory-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/mysql-memory/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

MySQL Memory is an in-memory MySQL server for tests, written in PHP. It parses each statement with [SQL Parser](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-parser/README.md), which is built from the official MySQL grammars. It resolves names and types with [SQL Semantics](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics-mysql/README.md), then plans and runs the statement over rows held in memory. It speaks the MySQL client/server protocol, so PDO and mysqli connect to it as they would to a real server, and it can also be used inside the test process with no socket at all. It aims to reproduce the results, column metadata, errors and warnings of the emulated MySQL release; compatibility is incomplete. The emulator is checked against real MySQL servers by differential fuzzing with [SQL Faker](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-faker/README.md).

Nothing is written to disk. A server starts in a fraction of a second, holds only what the test creates, and is gone when it stops.

## Requirements

- PHP 8.1+
- The `bcmath`, `intl` and `mbstring` extensions
- `pdo_mysql` or `mysqli` to connect over the protocol (not needed in process)

## Supported Releases

The releases below have been exercised against real servers. This is evidence for the tested statements, not a guarantee that every statement SQL Faker can generate behaves identically. Known differences remain; see [docs/compatibility.md](docs/compatibility.md).

| Release | `version` argument | Checked against a server |
|---------|--------------------|--------------------------|
| MySQL 8.4 | `8.4.7` (default) | Yes |
| MySQL 8.0 | `8.0.44` | Yes |
| MySQL 9.1 | `9.1.0` | Yes |
| MySQL 5.7 | `5.7.44` | Yes |
| MySQL 5.6 | `5.6.51` | Yes |
| MySQL 8.1, 8.2, 8.3, 9.0 | `8.1.0`, `8.2.0`, `8.3.0`, `9.0.1` | No |

Historical campaign percentages are not a syntax coverage measure. Earlier campaigns did not compare subsequent result sets or insert ids and included unstable reference runs in their denominator. Use the current campaign report for separate compared, unstable and generation-failure counts.

The `version` argument names the release to emulate; it defaults to `8.4.7`. It must be one of the MySQL grammar releases of SQL Parser: `5.6.51`, `5.7.44`, `8.0.44`, `8.1.0`, `8.2.0`, `8.3.0`, `8.4.7`, `9.0.1` or `9.1.0`. Any other value fails at the first statement with an `InvalidArgumentException`. The release decides the grammar, the keywords, the system variables and their defaults (for example the default `sql_mode`), and what `VERSION()` reports.

## Installation

```bash
composer require --dev k-kinzal/mysql-memory
```

## Usage

### In process

`Instance` is a server: its databases, accounts and global variables. `connect()` opens a session, as a client connection does, and `query()` runs a text of one or more statements. Each statement answers a `Reply`: a `ResultSet` for statements that return rows, or a `Completion` for the rest. The first statement that fails throws `SqlError`, with the error number, SQLSTATE and message the server reports.

```php
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;

$session = (new Instance())->connect();
$session->query('CREATE DATABASE shop');
$session->query('USE shop');
$session->query('CREATE TABLE items (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(20) NOT NULL, price DECIMAL(8,2))');

$insert = $session->query("INSERT INTO items (name, price) VALUES ('pen', 1.20), ('ink', 3.50)")[0];
[$insert->affectedRows, $insert->lastInsertId, $insert->info]; // => [2, 1, 'Records: 2  Duplicates: 0  Warnings: 0']

$result = $session->query('SELECT name, price * 2 AS doubled FROM items ORDER BY id')[0];
$result->rows; // => [['pen', '2.40'], ['ink', '7.00']]
[$result->columns[1]->name, $result->columns[1]->type->name, $result->columns[1]->decimals]; // => ['doubled', 'NewDecimal', 2]

try {
    $session->query('SELECT missing FROM items');
} catch (SqlError $error) {
    [$error->getCode(), $error->sqlState(), $error->getMessage()]; // => [1054, '42S22', "Unknown column 'missing' in 'field list'"]
}
```

A value in a row is the text the server sends in the text protocol, or `null` for SQL NULL. A `ResultColumn` describes the column as the server does: name, type code, length, decimals, flags, collation number, and the table, original table and database it comes from.

A text of several statements answers one reply for each statement. The statements before one that does not parse run, as on the server, and the syntax error quotes the text from where it was found to the end of the text, later statements included. The warnings of the last statement are read with `SHOW WARNINGS`. `run()` answers the reply of each statement up to the first that fails, and that failure as a `SqlError` value, instead of throwing it:

```php
$replies = $session->query('UPDATE items SET price = price + 1; SELECT COUNT(*) FROM items WHERE price > 2');
[count($replies), $replies[0]->affectedRows, $replies[1]->rows]; // => [2, 2, [['2']]]

$session->query("SELECT 'x' + 1");
$session->query('SHOW WARNINGS')[0]->rows; // => [['Warning', '1292', "Truncated incorrect DOUBLE value: 'x'"]]

$answers = $session->run('SELECT 1; SELECT * FROM nowhere; SELECT 2');
array_map(static fn ($answer) => $answer::class, $answers); // => ['MySqlMemory\Result\ResultSet', 'MySqlMemory\Error\SqlError']
$answers[1]->getMessage(); // => "Table 'shop.nowhere' doesn't exist"
```

Sessions of one `Instance` share its databases, accounts and global variables. `connect()` takes the user, the host and the database to use: `connect(user: 'root', host: 'localhost', database: 'shop')`. It throws `SqlError` (1049, Unknown database) when the database does not exist.

### A server for PDO and mysqli

`Server::start()` runs a server in a child PHP process that listens on a free port of `127.0.0.1`. `dsn()` answers the PDO data source name, with a database when one is given, and `host` and `port` name the address for mysqli. `stop()` ends the process and its data; the process also ends when the `Server` object is destroyed.

```php
use MySqlMemory\Server\Server;

$server = Server::start(databases: ['shop']);
$pdo = new PDO($server->dsn('shop'), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(40) NOT NULL, active BOOLEAN NOT NULL)');
$pdo->exec("INSERT INTO users VALUES (1, 'Alice', TRUE), (2, 'Bob', FALSE)");

$statement = $pdo->prepare('SELECT name FROM users WHERE active = ? ORDER BY id');
$statement->execute([1]);
$statement->fetchAll(PDO::FETCH_COLUMN); // => ['Alice']

$pdo->exec("INSERT INTO users VALUES (1, 'Carol', TRUE)");
// throws PDOException: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1' for key 'users.PRIMARY'

$mysqli = new mysqli($server->host, 'root', '', 'shop', $server->port);
$mysqli->query('SELECT COUNT(*) FROM users')->fetch_row(); // => ['2']

$server->stop();
```

Any user name and any password are accepted. Text queries, multiple statements in one query, and server-side prepared statements (`PDO::ATTR_EMULATE_PREPARES => false`, `mysqli::prepare()`) are all served.

A server is cheap enough to start once per test class, and a database is cheap enough to create once per test:

```php
use MySqlMemory\Server\Server;
use PHPUnit\Framework\TestCase;

final class UserRepositoryTest extends TestCase
{
    private static Server $server;

    private PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        self::$server = Server::start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    protected function setUp(): void
    {
        $this->pdo = new PDO(self::$server->dsn(), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('DROP DATABASE IF EXISTS app');
        $this->pdo->exec('CREATE DATABASE app');
        $this->pdo->exec('USE app');
        $this->pdo->exec('CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) NOT NULL UNIQUE)');
    }

    public function testRejectsADuplicateEmail(): void
    {
        $this->pdo->exec("INSERT INTO users (email) VALUES ('a@example.com')");

        $this->expectExceptionMessage("Duplicate entry 'a@example.com' for key 'users.email'");
        $this->pdo->exec("INSERT INTO users (email) VALUES ('a@example.com')");
    }

    public function testStartsEmpty(): void
    {
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }
}
```

Each test process starts its own servers on its own ports, so test suites run in parallel without sharing state.

### The `mysql-memory` command

`vendor/bin/mysql-memory` runs a server in the foreground until it is killed. It prints `ready ADDRESS` once it accepts connections.

```bash
vendor/bin/mysql-memory --listen=tcp://127.0.0.1:33061 --release=8.4.7 --database=app --global=sql_mode=ANSI
# ready tcp://127.0.0.1:33061
```

```bash
mysql -h127.0.0.1 -P33061 -uroot -e 'SELECT @@sql_mode, 1+1'
# @@sql_mode	1+1
# ANSI	2
```

| Option | Default | Meaning |
|--------|---------|---------|
| `--listen=ADDRESS` | `tcp://127.0.0.1:3306` | Where to listen: `tcp://HOST:PORT` (port 0 picks a free port) or `unix:///PATH` for a Unix socket (`mysql:unix_socket=/PATH` in PDO) |
| `--release=VERSION` | `8.4.7` | The release to emulate |
| `--database=NAME` | none | A database to create at start; repeat it for more |
| `--global=NAME=VALUE` | none | The global value of a system variable at start; repeat it for more |
| `--client-host=HOST` | the peer address | The host every client is seen connecting from |
| `--routine-time=KIND:NAME:CREATED:MODIFIED` | current time | Installation epochs for a standard `sys` routine; repeat for more routines |

Options it does not know are ignored.

## Configuration

`Instance` and `Server::start()` take the same settings. Their positional order differs, so pass them by name.

```php
use MySqlMemory\Instance;
use MySqlMemory\Server\Server;

$instance = new Instance(
    version: '8.0.44',
    globals: ['sql_mode' => 'STRICT_ALL_TABLES', 'hostname' => 'db.test'],
    databases: ['app'],
);
$session = $instance->connect(user: 'root', host: 'localhost', database: 'app');
$session->query('SELECT VERSION(), @@sql_mode, @@hostname, DATABASE(), USER()')[0]->rows;
// => [['8.0.44', 'STRICT_ALL_TABLES', 'db.test', 'app', 'root@localhost']]

$server = Server::start(version: '8.0.44', databases: ['app'], globals: ['sql_mode' => 'STRICT_ALL_TABLES'], clientHost: '10.0.0.5');
$pdo = new PDO($server->dsn('app'), 'app_user', 'any password');
$pdo->query('SELECT VERSION(), @@sql_mode, USER()')->fetch(PDO::FETCH_NUM);
// => ['8.0.44', 'STRICT_ALL_TABLES', 'app_user@10.0.0.5']
```

- **`version`**: the release to emulate. See [Supported Releases](#supported-releases).
- **`globals`**: global values of system variables, by name. Every session starts with these values for the variables that have a session scope, as a MySQL server started with these options does. They are stored as written, without the checks and normalization `SET GLOBAL` applies, so give each value as `SELECT @@GLOBAL.name` reports it. A variable not given keeps the default of the release.
- **`databases`**: databases created at start, besides `information_schema`, `mysql`, `performance_schema` and, from MySQL 5.7, `sys`, which always exist.
- **`clientHost`**: the host every client of the server is seen connecting from, as `USER()` reports it. By default it is the peer address, with `127.0.0.1` and `::1` read as `localhost`. In process, the host is the `host` argument of `connect()`.
- **`routineTimestamps`**: creation and modification epochs for installed `sys` routines, keyed by `FUNCTION:name` or `PROCEDURE:name`. Omitted entries use the instance's creation time. The catalog includes MySQL 5.7, 8.x and 9.x signatures and attributes; installed routine bodies are not implemented.

The server starts with the accounts of a new MySQL installation: `root` at `localhost` and at `%` with every privilege, and the locked system accounts. Variables, accounts, databases and everything else can then be changed with SQL, as on a real server.

## What Is Emulated

The full list, with the rules each statement follows, is in [docs/compatibility.md](docs/compatibility.md). In summary:

- **Queries**: `SELECT` with joins of every kind (inner, outer, cross, natural, `USING`, `STRAIGHT_JOIN`, `LATERAL`), derived tables, common table expressions including recursive ones, subqueries (scalar, row, `IN`, `EXISTS`, `ANY`/`ALL`), `GROUP BY` with `WITH ROLLUP`, `HAVING`, window functions with `PARTITION BY`, `ORDER BY`, frames and named windows, `DISTINCT`, `ORDER BY`, `LIMIT`, `UNION`, `INTERSECT` and `EXCEPT`, `VALUES` and `TABLE`, `SELECT ... INTO` variables, `SQL_CALC_FOUND_ROWS` and `FOUND_ROWS()`. The checks of `ONLY_FULL_GROUP_BY` are applied.
- **Expressions**: operators, comparisons and collations, implicit conversions with their warnings, `CAST` and `CONVERT`, date arithmetic with `INTERVAL`, `CASE`, the JSON operators `->`, `->>` and `MEMBER OF`, the JSON functions and `JSON_TABLE` with the types JSON values keep, aggregate functions, and a library of built-in functions for strings, numbers, dates and session information.
- **Writes**: `INSERT` with `VALUES`, `SET` or a query, `INSERT IGNORE`, `ON DUPLICATE KEY UPDATE` with `VALUES(col)` or the row alias of `INSERT ... AS`, `REPLACE`, `UPDATE` and `DELETE` of one table with `ORDER BY` and `LIMIT`, multiple-table `UPDATE` and `DELETE`, `TRUNCATE TABLE`. Values are stored as the column type says, with the warnings or, under a strict `sql_mode`, the errors of the server. Primary and unique keys, foreign keys with their `ON DELETE` and `ON UPDATE` actions, and `CHECK` constraints are enforced, generated columns are computed, and `AUTO_INCREMENT` counts as the server counts.
- **Definitions**: `CREATE`, `ALTER`, `RENAME` and `DROP` of databases, tables and indexes, `CREATE TABLE ... LIKE` and `CREATE TABLE ... SELECT`, temporary tables of each session, column defaults including expressions that read other columns and `ON UPDATE CURRENT_TIMESTAMP`, invisible and generated columns, `CHECK` constraints, foreign keys, and `RANGE`, `LIST`, `HASH` and `KEY` partitioning. `SHOW CREATE TABLE` writes the definition back as the server does.
- **Views**: `CREATE`, `ALTER` and `DROP VIEW`, reading through views, and `INSERT`, `UPDATE` and `DELETE` through updatable views.
- **Stored programs**: procedures, functions, triggers and events are created, altered, dropped and shown. `CALL` runs procedures with `IN`, `OUT` and `INOUT` parameters and answers their result sets; stored functions are called in expressions; triggers fire for each row. Bodies run with local variables, flow control, cursors, handlers, `SIGNAL`, `RESIGNAL` and `GET DIAGNOSTICS`.
- **Accounts**: users and roles with `CREATE`, `ALTER`, `RENAME` and `DROP`, passwords, `GRANT` and `REVOKE` of privileges and roles, `SET ROLE` and default roles, `SHOW GRANTS` and `SHOW CREATE USER`.
- **Transactions**: `START TRANSACTION`, `BEGIN`, `COMMIT`, `ROLLBACK`, autocommit, savepoints, XA transactions, the implicit commits of the statements that cause them, and statement atomicity: a statement that fails leaves no partial change. `SET TRANSACTION`, the four isolation levels with InnoDB's snapshots, read-only transactions, and row locks with `FOR UPDATE`, `FOR SHARE`, `NOWAIT` and `SKIP LOCKED`, which wait between the connections of a server.
- **Sessions**: user variables, system variables at session and global scope, named key caches (`SET GLOBAL kc.key_buffer_size`), `SET NAMES`, the diagnostics area with `SHOW WARNINGS`, `SHOW ERRORS` and `GET DIAGNOSTICS`, `SIGNAL` and `RESIGNAL`, prepared statements in SQL (`PREPARE`, `EXECUTE`) and over the protocol, `LOCK TABLES`, `HANDLER`.
- **Inspection**: `SHOW` statements for tables, columns, indexes, table status, variables, collations, character sets, engines, plugins, privileges, triggers, routines and events; `DESCRIBE`; `EXPLAIN`; `CHECK`, `ANALYZE`, `OPTIMIZE`, `REPAIR` and `CHECKSUM TABLE`.
- **Administration**: tablespaces, resource groups, foreign servers, spatial reference systems, plugins and components, `FLUSH`, the binary log and replication statements are accepted or refused as a server without files, plugins or replicas accepts or refuses them.

A statement or a function the emulator does not run fails with error 1235 (`ER_NOT_SUPPORTED_YET`) naming it, for example `This version of MySQL doesn't yet support 'function ST_ASTEXT'`, rather than giving a wrong answer.

## Limits

The emulator is a test double. These differences are known:

- **Nothing persists.** Data lives in the memory of the process and is gone when the `Instance` is released or the server stops.
- **Privileges are not enforced.** Accounts, roles and grants are kept and shown, but every session may run every statement, whatever its account. Any password is accepted when connecting.
- **Constraints follow InnoDB, with a few gaps.** Foreign keys, `CHECK` constraints and generated columns are enforced and computed as the server does (see [docs/compatibility.md](docs/compatibility.md#writes)). The server's refusal of a partitioning expression that reads a random value (a syntax error) is answered with error 1564 instead.
- **Events never run.** They are created, shown and dropped, and `event_scheduler` reads `ON` (`OFF` in MySQL 5.6 and 5.7), but the emulator never runs them, so that tests do not depend on the clock. See [docs/compatibility.md](docs/compatibility.md#stored-programs) for the other differences of stored programs.
- **Not every built-in function exists.** Full-text search and the spatial functions fail with error 1235. See [docs/compatibility.md](docs/compatibility.md#built-in-functions) for the functions that are evaluated.
- **Some write and definition forms are missing.** Writing through a view of a view fails with error 1235. Rows of a table partitioned by `KEY` are not placed in their partitions: selecting its partitions (`FROM t PARTITION (p0)`) fails with error 1235, and a scan reads its rows in the order of the table. `ALTER TABLE` actions on partitions other than `PARTITION BY`, `REMOVE PARTITIONING`, `ADD`, `DROP`, `TRUNCATE` and `COALESCE PARTITION` fail with error 1235.
- **Spatial types are missing.** Spatial columns can be declared, but no spatial value can be made or read.
- **No optimizer.** Tables are scanned, joins are nested loops, and indexes are never used to find rows, so large tables are slow. Output that depends on the optimizer differs: `EXPLAIN` plans of statements that read tables, and the order of rows a query returns without `ORDER BY`.
- **Row locks wait only over the protocol.** Sessions are isolated as InnoDB isolates transactions, at every isolation level, and locking reads and writes lock whole rows. Through `Server`, a statement waits for a row lock another connection holds, up to `innodb_lock_wait_timeout` (error 1205), and deadlocks fail with error 1213. Sessions used in process run one statement at a time, so a conflicting lock fails at once with error 1205. There are no gap locks, and a statement locks only the rows that meet its `WHERE` condition, where the server locks every row it reads through its index. Table locks (`LOCK TABLES`) and user-level locks never wait: `GET_LOCK()` answers 0 once its timeout has passed on the server's clock, and `SLEEP()` returns at once, passing its time on that clock instead of stalling the caller. See [docs/compatibility.md](docs/compatibility.md#transactions).
- **Only InnoDB tables are transactional.** A `MyISAM`, `MEMORY` or other table takes no lock and no snapshot, and keeps its changes when a statement fails or a transaction rolls back, with the server's warning 1196.
- **System tables are computed from the emulator's state.** Every table of `information_schema`, `mysql` and `performance_schema` exists with the columns and column metadata of the emulated release, and can be read like any table. The tables that describe databases, tables, columns, keys, constraints, views, stored programs, accounts and grants, character sets, collations, engines, plugins, keywords, sessions, variables and time zone names hold what the server holds; the others, which describe its internals (InnoDB, Performance Schema instruments, events and threads, help topics, time zone transitions), are empty. Status counters read 0. System tables cannot be written. The `sys` database holds no table. See [docs/compatibility.md](docs/compatibility.md#system-tables).
- **No files.** `LOAD DATA`, `SELECT ... INTO OUTFILE` and `IMPORT TABLE` are refused as a server with `--secure-file-priv` and `local_infile` off refuses them. In MySQL 5.6 and 5.7, where `local_infile` is on, `LOAD DATA LOCAL` is checked as the server checks it and then refused with error 3948, where the server would ask the client for the file.
- **The system time zone is UTC.** `SYSTEM` is UTC; `time_zone` takes offsets and names from the release catalogs in `resources/time-zones`, generated from real servers. Conversion rules use PHP's tz database, which can differ from the server's rules. `SET timestamp = n` pins `NOW()` and the other clocks of the session, as on the server.
- **Server lifecycle.** `KILL QUERY` interrupts a waiting statement; `KILL CONNECTION` also disconnects and rolls back the target session. `SHUTDOWN` stops the instance. `RESTART` disconnects sessions, reloads startup variables and retains regular table contents; `MEMORY` tables lose their rows. Use `supervised: false` with `Instance` or `Server::start()`, or `--unsupervised` with the CLI, to reproduce error 3707 for a server without a restart supervisor. `CLONE` reports that the clone plugin is not loaded. Persisted variable settings are not yet retained across restart.
- **Optimizer hints are checked, not followed.** Hint comments (`/*+ ... */`) are read in MySQL 5.7 and later with the server's warnings (syntax errors, conflicting and unresolved hints), and `SET_VAR` sets its variable for the statement; the other hints change no plan, `MAX_EXECUTION_TIME` sets no timer and `RESOURCE_GROUP` binds no thread. See [docs/compatibility.md](docs/compatibility.md#optimizer-hints).
- **Protocol.** The server offers `mysql_native_password` and no TLS, compression, or `CLIENT_DEPRECATE_EOF`. The `CLIENT_FOUND_ROWS` flag (`PDO::MYSQL_ATTR_FOUND_ROWS`) counts matched rows for UPDATE and one row for an unchanged ON DUPLICATE KEY UPDATE; ROW_COUNT() follows that choice. Packets of 16 MiB or more are not supported. The storage engines the statements that name one accept are those of MySQL 8.4.7 for every release.
- **An error inside the emulator** is reported over the protocol as error 1105 with a message starting `mysql-memory internal error:`, and thrown as is in process.

## How Correctness Is Checked

Rules are written from the MySQL reference manual and black-box observations of live servers. Differential fuzzing generates statements with SQL Faker, runs them on a real MySQL server and mysql-memory from the same fixture tables, and compares the metadata PDO exposes, every result set, affected rows, insert ids, errors, warnings and table contents. The fixture pins `timestamp` so that NOW() and related functions have the same clock on both servers. Both servers disable automatic statistics recalculation and execute `ANALYZE TABLE` synchronously after inserting the fixture rows, then execute `FLUSH TABLES`. This makes the initial statistics and table-handle cache independent of background recalculation and earlier inputs; it does not filter the tested statement's observations. Stateful regressions also exercise populated caches, named flushes, HANDLER cursors and locks. SHOW WARNINGS uses the text protocol even when the tested statement uses native prepares, so preparing the inspection cannot replace the diagnostics being inspected.

The real server runs each input twice. Inputs whose reference observations differ after the bounded comparison contracts described below are counted separately and skipped; they are not counted as matches. The harness does not compare OK-packet information text or metadata PDO does not expose. It uses a textual ORDER BY check to decide whether to compare row order, including queries with LIMIT; nested ORDER BY clauses and unordered ties can still produce misleading findings. Finite fuzz campaigns do not establish exhaustive grammar coverage or universal equivalence.

`composer test:integration` includes differential regressions against Testcontainers, covering text and prepared execution with and without CLIENT_FOUND_ROWS, insert ids, multiple result sets and resolution errors. Set `MYSQL_VERSION` to repeat them on another release.
The fuzzer is part of the repository, not of the installed package. From `packages/mysql-memory` in a checkout:

```bash
composer fuzz                                  # each target for 100 inputs
composer fuzz:expression                       # expressions over the fixture tables, until stopped
composer fuzz:query                            # queries over the fixture tables
composer fuzz:statement                        # statements using the campaign constraints
composer fuzz:seeds                            # the complete canonical sql-faker seed corpus

php fuzz/campaign.php select 500 7             # MODE COUNT [SEED]: differences grouped by kind
MYSQL_VERSION=8.0.44 php fuzz/campaign.php statement 1000
```

The campaign modes are `expression`, `query`, `select`, `write` and `statement`. Each kind of difference is printed with its count and its shortest statement. Exit code 0 means all compared inputs matched, 1 means a difference was found, and 2 means invalid arguments, a generation failure, or no comparable input. `MYSQL_MEMORY_REPORT=/path/report.json` also saves the seed, counts and every finding, with the exact SQL bytes and generation input in hexadecimal. The parent directory must already exist.

The seed replay uses the same root and byte decoding as SQL Faker's `bin/seeds.php check`, with no excluded statement forms. Isolated observations also reset enabled binary logs after fixture setup, using the same SQL on both servers, so each input starts with one empty log. Generated statements retain their effects on that log; event rows, file sizes and positions are not normalized. This establishes only the initial-log behavior: writing DML events remains unimplemented, and the harness still detects the resulting size differences in stateful regressions. SHUTDOWN and RESTART each run in a separate Testcontainers database and emulator. Their completion, disconnection and reconnection behavior is checked; a successful restart must preserve the fixture rows. Successful lifecycle statements do not inspect warnings after the connection starts closing.

`php fuzz/seeds.php [SEED_DIRECTORY] [REPORT_PREFIX]` writes per-input JSON Lines, a grammar coverage snapshot and a summary (by default under `build/fuzz/seeds-mysql-VERSION`). It reports reached and emitted productions separately, plus reached productions belonging to successfully compared inputs. Exit 0 requires every input to match and the entire reachable statement grammar to be covered. Differences, volatile observations and incomplete coverage fail the replay. Reaching every production during generation does not mean every production survives SQL Faker's output rewrites; the emitted count makes that distinction visible. This gate currently exposes remaining compatibility gaps and is not yet passing.

Pull requests changing this package or its SQL dependencies run the complete canonical gate in GitHub Actions. To request another run, dispatch **Fuzz (mysql-memory)** with `canonical_seeds=true` and the desired `mysql_version`. That mode runs only the canonical gate and uploads the per-input observations, coverage, summary and console log, including when the gate fails.

Six bounded comparison contracts handle measured nondeterministic fields:

- MySQL 5.6 and 5.7 can report OS errno 2 or 11 for the same missing shared library. The harness accepts those two values only when SQL error 1126, SQLSTATE HY000 and the complete missing-file loader message match. The library name, path, warnings, result sets and fixture table contents still compare exactly. The report records `missing-library-os-errno-2-or-11`.
- Plain `SHOW TABLE STATUS` on MySQL 8.0 and later reports each server's actual commit clock, independently of `SET timestamp`. The harness samples `SYSDATE()` immediately before and after each fixture table's INSERT on each server. Each `Update_time` must be a valid datetime inside that table's own sampled interval. Only these validated instants are interchangeable; NULL, the pinned statement clock and out-of-range times fail. Every other result field, metadata, warnings and fixture contents remain exact. This applies only to the plain statement, with no predicates or additional statements, and records `table-update-time-within-fixture-insert-interval`.

- `random-primary-password-length-and-replacement` applies only to an isolated random password change for the current account, without retention. The returned account, factor and configured length are checked; `SET PASSWORD ... REPLACE` must reject a different password and accept the returned one. Only that validated random text is interchangeable. This verifies SQL password storage and replacement, not wire authentication or entropy; other-account changes remain outside this contract.

- `random-password-retains-primary-and-discards-secondary` additionally applies when the isolated current-account random change uses `RETAIN CURRENT PASSWORD`. Each server must store its own independently sampled previous primary hash as the secondary, preserve it through an ordinary primary update, then remove it with `DISCARD OLD PASSWORD` while keeping the primary unchanged. Failed checks retain the raw observation. This contract verifies SQL credential state; connections still accept any password.

- `statement-id-between-samples-and-pseudo-id-equals-connection` applies only to unfiltered `SHOW [SESSION|LOCAL] VARIABLES` on modern releases. Each server's statement ID must fall strictly between independently sampled statement IDs around the observation, and its pseudo thread ID must equal the sampled connection ID. Only those two validated values are interchangeable. Other variables, metadata, warnings and fixture contents remain exact.

- `process-identities-and-ports-stable-between-samples-with-bounded-time` applies only to isolated `SHOW [FULL] PROCESSLIST`. The fixture restarts an enabled scheduler after replacing the database, preventing a previous event's pending wait from leaking into this observation. Each connection identifies itself independently; process-table reads before and after the statement must contain the same complete set of identities and valid, unchanged TCP source ports. The current query's elapsed time must fall between independent `SYSDATE()` minus `@@timestamp` samples; idle and daemon times must fall between their own process-table samples. Only validated IDs, port numbers and elapsed times are interchangeable. Every row, host name, user, database, command, state, query text, column definition, warning and fixture value remains compared. Additional, missing or malformed rows leave the observation unnormalized.

Applied contracts appear in the input report's `contracts` list. Other unstable reference observations remain failures of the canonical seed gate.

| Variable | Meaning |
|----------|---------|
| `MYSQL_VERSION` | The release to compare, `8.4.7` by default; both servers run it |
| `MYSQL_MEMORY_NATIVE_DSN` | The PDO DSN of a running MySQL server to compare with; without it a container of the release is started with Testcontainers, which needs Docker |
| `MYSQL_MEMORY_NATIVE_USER`, `MYSQL_MEMORY_NATIVE_PASSWORD` | The account of that server, `root`/`root` by default |
| `MYSQL_MEMORY_FOUND_ROWS` | `1` requests CLIENT_FOUND_ROWS on both connections |
| `MYSQL_MEMORY_REPORT` | Optional JSON report path for a campaign |
| `MYSQL_MEMORY_EMULATE` | `0` makes the campaign use server-side prepared statements instead of PDO's emulated ones |
| `MYSQL_MEMORY_BUDGET` | The expansion budget of the generated statements, 96 by default |
| `MYSQL_MEMORY_VERBOSE` | `1` makes the campaign print every differing statement |
| `MYSQL_MEMORY_DIFF_BYTES` | How many bytes of each difference the campaign prints, 900 by default |

mysql-memory starts with the global variables of the MySQL server it is compared with, so both report the same host name, paths and identities.

## License

MIT License. See [LICENSE](LICENSE) for details.
