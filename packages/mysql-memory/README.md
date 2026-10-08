# MySQL Memory

[![Packagist Downloads](https://img.shields.io/packagist/dt/k-kinzal/mysql-memory.svg?label=Packagist)](https://packagist.org/packages/k-kinzal/mysql-memory)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-mysql--memory-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/mysql-memory/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

MySQL Memory is an in-memory MySQL server for tests, written in PHP. It parses each statement with [SQL Parser](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-parser/README.md), which is built from the official MySQL grammars. It resolves names and types with [SQL Semantics](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics-mysql/README.md), then plans and runs the statement over rows held in memory. It speaks the MySQL client/server protocol, so PDO and mysqli connect to it as they would to a real server, and it can also be used inside the test process with no socket at all. Results, column metadata, errors and warnings follow those of the emulated MySQL release. The emulator is checked against real MySQL servers by differential fuzzing with [SQL Faker](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-faker/README.md).

Nothing is written to disk. A server starts in a fraction of a second, holds only what the test creates, and is gone when it stops.

## Requirements

- PHP 8.1+
- The `bcmath`, `intl` and `mbstring` extensions
- `pdo_mysql` or `mysqli` to connect over the protocol (not needed in process)

## Supported Releases

> **Placeholder.** The maintainer fills in this table with the releases verified against real MySQL servers.

| Release | `version` argument | Verified |
|---------|--------------------|----------|
| _TBD_ | _TBD_ | _TBD_ |

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

A text of several statements answers one reply for each statement. The warnings of the last statement are read with `SHOW WARNINGS`. `run()` answers the reply of each statement up to the first that fails, and that failure as a `SqlError` value, instead of throwing it:

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
- **`databases`**: databases created at start, besides `information_schema`, `mysql`, `performance_schema` and `sys`, which always exist.
- **`clientHost`**: the host every client of the server is seen connecting from, as `USER()` reports it. By default it is the peer address, with `127.0.0.1` and `::1` read as `localhost`. In process, the host is the `host` argument of `connect()`.

The server starts with the accounts of a new MySQL installation: `root` at `localhost` and at `%` with every privilege, and the locked system accounts. Variables, accounts, databases and everything else can then be changed with SQL, as on a real server.

## What Is Emulated

The full list, with the rules each statement follows, is in [docs/compatibility.md](docs/compatibility.md). In summary:

- **Queries**: `SELECT` with joins of every kind (inner, outer, cross, natural, `USING`, `STRAIGHT_JOIN`, `LATERAL`), derived tables, common table expressions including recursive ones, subqueries (scalar, row, `IN`, `EXISTS`, `ANY`/`ALL`), `GROUP BY` with `WITH ROLLUP`, `HAVING`, `DISTINCT`, `ORDER BY`, `LIMIT`, `UNION`, `INTERSECT` and `EXCEPT`, `VALUES` and `TABLE`, `SELECT ... INTO` variables, `SQL_CALC_FOUND_ROWS` and `FOUND_ROWS()`. The checks of `ONLY_FULL_GROUP_BY` are applied.
- **Expressions**: operators, comparisons and collations, implicit conversions with their warnings, `CAST` and `CONVERT`, date arithmetic with `INTERVAL`, `CASE`, the JSON operators `->`, `->>` and `MEMBER OF`, aggregate functions, and a library of built-in functions for strings, numbers, dates and session information.
- **Writes**: `INSERT` with `VALUES`, `SET` or a query, `INSERT IGNORE`, `ON DUPLICATE KEY UPDATE` with `VALUES(col)`, `REPLACE`, `UPDATE` and `DELETE` of one table with `ORDER BY` and `LIMIT`, multiple-table `UPDATE` and `DELETE`, `TRUNCATE TABLE`. Values are stored as the column type says, with the warnings or, under a strict `sql_mode`, the errors of the server. Primary and unique keys are enforced and `AUTO_INCREMENT` counts as the server counts.
- **Definitions**: `CREATE`, `ALTER`, `RENAME` and `DROP` of databases, tables and indexes, `CREATE TABLE ... LIKE` and `CREATE TABLE ... SELECT`, temporary tables, column defaults including expressions and `ON UPDATE CURRENT_TIMESTAMP`, invisible columns. `SHOW CREATE TABLE` writes the definition back as the server does.
- **Views**: `CREATE`, `ALTER` and `DROP VIEW`, reading through views, and `INSERT`, `UPDATE` and `DELETE` through updatable views.
- **Stored programs**: procedures, functions, triggers and events are created, altered, dropped and shown. `CALL` runs a procedure whose body is one statement, or a block of statements without declarations or flow control, with `IN` parameters.
- **Accounts**: users and roles with `CREATE`, `ALTER`, `RENAME` and `DROP`, passwords, `GRANT` and `REVOKE` of privileges and roles, `SET ROLE` and default roles, `SHOW GRANTS` and `SHOW CREATE USER`.
- **Transactions**: `START TRANSACTION`, `BEGIN`, `COMMIT`, `ROLLBACK`, autocommit, savepoints, XA transactions, the implicit commits of the statements that cause them, and statement atomicity: a statement that fails leaves no partial change.
- **Sessions**: user variables, system variables at session and global scope, `SET NAMES`, the diagnostics area with `SHOW WARNINGS`, `SHOW ERRORS` and `GET DIAGNOSTICS`, `SIGNAL` and `RESIGNAL`, prepared statements in SQL (`PREPARE`, `EXECUTE`) and over the protocol, `LOCK TABLES`, `HANDLER`.
- **Inspection**: `SHOW` statements for tables, columns, indexes, table status, variables, collations, character sets, engines, plugins, privileges, triggers, routines and events; `DESCRIBE`; `EXPLAIN`; `CHECK`, `ANALYZE`, `OPTIMIZE`, `REPAIR` and `CHECKSUM TABLE`.
- **Administration**: tablespaces, resource groups, foreign servers, spatial reference systems, plugins and components, `FLUSH`, the binary log and replication statements are accepted or refused as a server without files, plugins or replicas accepts or refuses them.

A statement or a function the emulator does not run fails with error 1235 (`ER_NOT_SUPPORTED_YET`) naming it, for example `This version of MySQL doesn't yet support 'function DATE_FORMAT'`, rather than giving a wrong answer.

## Limits

The emulator is a test double. These differences are known:

- **Nothing persists.** Data lives in the memory of the process and is gone when the `Instance` is released or the server stops.
- **Privileges are not enforced.** Accounts, roles and grants are kept and shown, but every session may run every statement, whatever its account. Any password is accepted when connecting.
- **Constraints other than keys are not enforced.** Foreign keys are kept in the definition, but neither checked on writes nor acted on (`ON DELETE`, `ON UPDATE`). `CHECK` constraints are not checked.
- **Generated columns are not computed.** A column defined `AS (expr)` is stored as NULL.
- **Stored programs mostly do not run.** Calls of stored functions fail with error 1235. Triggers and events are kept but never fire. `CALL` of a procedure with declarations, flow control, or `OUT` and `INOUT` parameters fails with error 1235.
- **Not every built-in function exists.** Window functions, `JSON_TABLE`, the JSON functions (only `->`, `->>` and `MEMBER OF` are evaluated), full-text search, the regular expression functions (the `REGEXP` operator works), the hash, random, UUID and locking functions, and many date and string functions such as `DATE_FORMAT`, `STR_TO_DATE`, `DATEDIFF`, `GREATEST` and `FORMAT` fail with error 1235. See [docs/compatibility.md](docs/compatibility.md#built-in-functions) for the functions that are evaluated.
- **Some write and definition forms are missing.** `ON DUPLICATE KEY UPDATE` that reads the row alias of `INSERT ... AS alias` fails with error 1235 when a row conflicts. Writing through a view of a view, `ALTER TABLE ... PARTITION BY` and partition selection (`FROM t PARTITION (p0)`) fail with error 1235; a partitioned table otherwise behaves as one table.
- **Spatial types are missing.** Spatial columns can be declared, but no spatial value can be made or read.
- **No optimizer.** Tables are scanned, joins are nested loops, and indexes are never used to find rows, so large tables are slow. Output that depends on the optimizer differs: `EXPLAIN` plans of statements that read tables, and the order of rows a query returns without `ORDER BY`.
- **No concurrency control.** Sessions of one server run one statement at a time and see each other's uncommitted changes; `ROLLBACK` restores the rows a table had when the transaction first changed it. Locks never wait. Temporary tables are visible to every session, and one cannot be created with the name of an existing table. `SET TRANSACTION` fails with error 1235.
- **Every table is transactional.** `ENGINE` is kept in the definition, but a `MyISAM`, `MEMORY` or `BLACKHOLE` table behaves as an `InnoDB` table.
- **No system tables.** The `information_schema`, `mysql`, `performance_schema` and `sys` databases exist but hold no tables, so queries of `INFORMATION_SCHEMA.TABLES` and the like fail with error 1146. `SHOW DATABASES` and `SHOW PROCESSLIST` fail with error 1235, and `SHOW STATUS` lists no variable.
- **No files.** `LOAD DATA`, `SELECT ... INTO OUTFILE` and `IMPORT TABLE` are refused as a server with `--secure-file-priv` and `local_infile` off refuses them.
- **The clock is UTC.** The server's time zone is UTC, and `NOW()` reads UTC whatever `time_zone` says.
- **Server lifecycle statements** (`SHUTDOWN`, `RESTART`, `KILL`, `CLONE`) and loadable functions (`CREATE FUNCTION ... SONAME`) fail with error 1235. Optimizer hints (`/*+ ... */`) fail with error 1235.
- **Protocol.** The server offers `mysql_native_password` and no TLS, compression, or `CLIENT_DEPRECATE_EOF`. The `CLIENT_FOUND_ROWS` flag (`PDO::MYSQL_ATTR_FOUND_ROWS`) is not honored: affected rows count changed rows. Packets of 16 MiB or more are not supported. The catalog of engines, plugins and privileges is that of MySQL 8.4.7 for every release.
- **An error inside the emulator** is reported over the protocol as error 1105 with a message starting `mysql-memory internal error:`, and thrown as is in process.

## How Correctness Is Checked

Each rule of the emulator is written from the MySQL reference manual and, where the manual is silent, from the behavior of a live server, and is covered by unit tests. On top of that, differential fuzzing runs the same statements on a real MySQL server and on mysql-memory, both starting from the same fixture tables, and requires that a client observes the same thing on both: the result columns and their metadata, the rows, the affected-row count or the error, the warnings, and the contents of every table afterwards. A statement whose result differs between two runs on the real server, because it reads the clock or a random value, is not compared. The statements are generated from the official MySQL grammar by SQL Faker.

The fuzzer is part of the repository, not of the installed package. From `packages/mysql-memory` in a checkout:

```bash
composer fuzz                                  # each target for 100 inputs
composer fuzz:expression                       # expressions over the fixture tables, until stopped
composer fuzz:query                            # queries over the fixture tables
composer fuzz:statement                        # every statement of the grammar

php fuzz/campaign.php select 500 7             # MODE COUNT [SEED]: differences grouped by kind
MYSQL_VERSION=8.0.44 php fuzz/campaign.php statement 1000
```

The campaign modes are `expression`, `query`, `select`, `write` and `statement`. Each kind of difference is printed with its count and its shortest statement.

| Variable | Meaning |
|----------|---------|
| `MYSQL_VERSION` | The release to compare, `8.4.7` by default; both servers run it |
| `MYSQL_MEMORY_NATIVE_DSN` | The PDO DSN of a running MySQL server to compare with; without it a container of the release is started with Testcontainers, which needs Docker |
| `MYSQL_MEMORY_NATIVE_USER`, `MYSQL_MEMORY_NATIVE_PASSWORD` | The account of that server, `root`/`root` by default |
| `MYSQL_MEMORY_EMULATE` | `0` makes the campaign use server-side prepared statements instead of PDO's emulated ones |
| `MYSQL_MEMORY_BUDGET` | The expansion budget of the generated statements, 96 by default |
| `MYSQL_MEMORY_VERBOSE` | `1` makes the campaign print every differing statement |
| `MYSQL_MEMORY_DIFF_BYTES` | How many bytes of each difference the campaign prints, 900 by default |

mysql-memory starts with the global variables of the MySQL server it is compared with, so both report the same host name, paths and identities.

## License

MIT License. See [LICENSE](LICENSE) for details.
