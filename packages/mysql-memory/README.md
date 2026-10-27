# MySQL Memory

MySQL Memory is an in-memory MySQL database for PHP tests. Start a database for an
explicit MySQL release, then connect through PDO or mysqli. Each database owns a
separate process and its data disappears when it stops.

SQL Parser selects the release's official grammar. SQL Semantics supplies the
statement model consumed by the memory engine. Compatibility is checked against
Testcontainers MySQL using SQL Faker and PHP-Fuzzer; this is counterexample search,
not a claim that every MySQL statement is supported.

## Requirements

- PHP 8.1+ with `bcmath`, `intl`, and `mbstring`
- `pdo_mysql` or `mysqli`
- PHP process and local TCP socket functions

```sh
composer require --dev k-kinzal/mysql-memory
```

## PDO

```php
use MySqlMemory\Server\MemoryDatabase;

$database = MemoryDatabase::start('8.4.7', databases: ['shop']);
try {
    $pdo = new PDO($database->dsn('shop'), 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('CREATE TABLE items (id INT PRIMARY KEY, name VARCHAR(20))');
    $insert = $pdo->prepare('INSERT INTO items VALUES (?, ?)');
    $insert->execute([1, 'pen']);
    $items = $pdo->query('SELECT * FROM items')->fetchAll(PDO::FETCH_ASSOC);
} finally {
    $database->stop();
}
```

`dsn()` accepts an optional database name. `host` and `port` identify the local
listener. `stop()` is idempotent; destroying the database object also stops it.
Keep the object alive for as long as its connections are needed.

## mysqli

```php
use MySqlMemory\Server\MemoryDatabase;

$database = MemoryDatabase::start('5.7.44', databases: ['shop']);
try {
    $mysqli = new mysqli($database->host, 'root', '', 'shop', $database->port);
    $version = $mysqli->query('SELECT VERSION()')->fetch_row()[0];
    $mysqli->close();
} finally {
    $database->stop();
}
```

## MySQL releases

The version argument is required. Available releases are `5.6.51`, `5.7.44`,
`8.0.44`, `8.1.0`, `8.2.0`, `8.3.0`, `8.4.7`, `9.0.1`, and `9.1.0`.
An unknown release throws `RuntimeException` before a process starts.
The selected release controls the grammar, semantic analysis, system variable
catalog, initial settings, and protocol greeting. Different releases can run
at the same time; they have separate listeners and state.

The default root account has an empty password. Optional startup arguments supply
initial databases, global variable values, and server capabilities. SQL errors are
reported through the connected driver's usual exceptions and result APIs.
An internal implementation failure terminates the server rather than becoming
an SQL error. Call `assertNoInternalFailure()` while retaining the database object
to distinguish that failure from a driver-visible disconnect or SQL refusal.
The failure remains visible on later checks. An intentional SQL `SHUTDOWN` is not
an internal failure.
System-table declarations describe result metadata separately from row execution.
Reading rows without an implemented provider is an internal failure; it does not
return an empty table. Unimplemented system-table mutations likewise fail
internally instead of returning a fabricated SQL refusal. Queries that do not
request rows, such as a zero-limit metadata query, can still use the declaration.
`Instance`, sessions, protocol classes, and execution results are internal APIs.
There is no command-line tool.

Named time zones use PHP's IANA time-zone database by default. Their installation
is independent of the MySQL version. The `timeZones` argument accepts rows of
`[name, identifier, usesLeapSeconds]`; pass `[]` for empty time-zone tables.
Conversion rules still come from PHP's time-zone database.

## Verification

The package uses the repository's ordinary unit, integration and property tests
and static checks. Run them from this directory:

```sh
composer lint
composer test
ERIS_SEED=20261025 vendor/bin/phpunit --testsuite property
```

The property suite composes semantic statements with Eris and executes them
directly. Dependent requests, such as a table definition and an INSERT using its
columns, are generated together before execution. Shrinking retains their
relationships. These checks do not render or reparse generated values.

Every public core and MySQL semantic type has a corresponding generator under
`tests/Generator`. PHPStan checks this mapping and whether the generators are
used. The configurable unused-class rule accepts fully qualified names or `*`
patterns through `parameters.unusedClasses` in `phpstan.neon`. References must be
reachable from outside the selected classes; disconnected cycles do not count.
Run PHPStan over the complete configured paths. Generator mapping and usage are
static checks; they do not establish complete SQL behavior coverage.

### Compare SQL execution

Docker and `mysqli` are required. The fuzz target starts one Testcontainers MySQL
and one MemoryDB for the selected release. It executes each SQL Faker statement
once on each connection and compares the actual replies, including rows, column
metadata, affected rows, insert IDs, warning counts, completion information and
SQL errors. Both connections retain the same statement history. A failure reports
that history and the input bytes, so state-dependent differences can be reproduced.

```sh
MYSQL_VERSION=8.4.7 composer fuzz:smoke
```

To replay SQL Faker's default grammar corpus, first verify its coverage and copy
its input files into a separate directory:

```sh
cd ../sql-faker
php bin/seeds.php check --tag mysql-8.4.7
cd ../mysql-memory
mkdir -p build/fuzz/replay
cp ../sql-faker/seeds/mysql/mysql-8.4.7/*.txt build/fuzz/replay/
MYSQL_VERSION=8.4.7 php -d memory_limit=1G vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_execution.php build/fuzz/replay/ --max-runs=0 --timeout=60
```

Use a fresh directory for each profile. Mode supplements require the matching
`SQLFAKER_SQL_MODE` (`HIGH_NOT_PRECEDENCE` or `PIPES_AS_CONCAT`); diagnostic seeds
require `SQLFAKER_DIAGNOSTICS=1`. A successful replay prints `Verified N inputs.`;
compare that count with the copied files. An empty corpus is not a seed replay.

For a fifteen-minute search without supplied seeds:

```sh
mkdir -p build/fuzz/search
MYSQL_VERSION=8.4.7 MYSQL_MEMORY_FUZZ_SECONDS=900 php -d memory_limit=1G vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_execution.php build/fuzz/search/ --timeout=60
```

The search directory must initially be empty. The target prints its release,
lexical profile and mutation seed; `PHP_FUZZER_SEED` reproduces that random sequence
on the same revision and corpus. Retained input bytes can also be passed to
`vendor/bin/php-fuzzer run-single`.

SQL reconstruction is checked separately in `sql-semantics-mysql`: SQL Faker
input is analyzed into semantic values, rendered, and compared using exact
`sql-formatter` Compact equality. Both fuzz paths search for counterexamples;
passing a run establishes only its explored scope, not complete MySQL equivalence.
