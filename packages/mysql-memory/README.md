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
`Instance`, sessions, protocol classes, and execution results are internal APIs.
There is no command-line tool.

Named time zones use PHP's IANA time-zone database by default. Their installation
is independent of the MySQL version. The `timeZones` argument accepts rows of
`[name, identifier, usesLeapSeconds]`; pass `[]` for empty time-zone tables.
Conversion rules still come from PHP's time-zone database.

## Verification

MySQL Memory uses the repository's ordinary unit tests and static checks.
Its semantic validation has three parts:

1. Reconstruct SQL Faker input from semantic values and require exact Compact SQL equality.
2. Compose semantic values with Eris, execute them directly in MemoryDB, and shrink failures.
3. Compare SQL Faker statements on MemoryDB and Testcontainers MySQL with PHP-Fuzzer.

PBT classes live under `tests/Property`, use `*Properties` names, and expose
`satisfies*` test methods. Their grouping follows the properties being checked;
there is no required property class or `MODEL` constant for each semantic type.
`tests/Generator` supplies semantic values independently of `fuzz`.

The PHPStan rules in `rules` require a corresponding, instantiable `Eris\Generator<T>`
for every public semantic value type discovered in SQL Semantics and its MySQL
package, including model interfaces, ordinary public model records and enums. The check does not depend on a runtime entry class or on a model using the Snapshot trait. Generator names mirror their semantic type:
`SqlSemantics\Platform\MySql\Statement\Account\AlterUser` requires
`Tests\Generator\MySql\Statement\Account\AlterUserGenerator` generating `AlterUser`.
Core types use the `Tests\Generator\Semantics` prefix. Generic constructor sampling
does not satisfy this requirement.

The unused-class rule selects classes through `parameters.unusedClasses` in
`phpstan.neon`, using fully qualified names or `*` patterns. The package checks
`Tests\Generator\*Generator`. A selected class must be referenced from outside
the selection, directly or through other selected classes. Self references and
disconnected cycles do not count. References include construction, member access,
class-name constants, native type declarations, inheritance, traits and attributes.
Imports, PHPDoc-only names and unresolved dynamic names do not count.
Run PHPStan over all relevant directories; isolated file analysis skips the check.
This checks class usage, independently of constructor declarations and Eris; it
does not establish execution in a property or runtime coverage. Tests of MemoryDB
classes live under `tests/Unit`; generators do not create mysql-memory implementation
types.

`CreateTableExecutionProperties` uses `forAll(new OperationGenerator($release))`.
`OperationGenerator` composes `CreateTableGenerator` and `ColumnNameGenerator`,
all implementing `Eris\Generator`. It publishes the generated single-column CREATE
TABLE statement in a matching analysis context. `then` executes that operation in
MemoryDB and checks the stored column name. Shrinking rebuilds the statement,
context and facts together; structurally unchanged statements end the shrink.
This first example covers valid column-definition names, not every operation or
column-name context. Each MySQL release gets 100 trials by default; `ERIS_SEED`
reproduces the generated inputs.

`InsertExecutionProperties` exercises character-literal VALUES directly as concrete
`InsertStatement` values. It distinguishes an absent-table SQL error from an
implementation failure, then composes a generated CREATE TABLE and dependent
INSERT before the assertion callback. The latter checks conversion, affected rows,
and stored data. A shrinking regression verifies that the declaration and write
remain related as Eris reduces a counterexample. These properties currently cover
small statement forms; most statement families still use the legacy operation API.

The property suite currently exercises the stated CREATE TABLE, INSERT and interval
behaviors. It does not cover every statement family. The old constructor sampler
and its instance-only properties have been removed. Missing generators remain
errors in the complete PHPStan check; deleting those properties does not satisfy
model coverage. Interface generators must compose their concrete alternatives,
so the incomplete statement/expression interface aliases have also been removed.

The generator-coverage integration tests run PHPStan without a MemoryDB Instance
class and with no generators at all. They check missing record/interface/enum
generators, wrong Eris result types, abstract generators and unmatched generator
names. The separate usage tests check configured roots and disconnected cycles.
These validate the static rules, not SQL execution coverage.

```sh
composer lint
composer test
ERIS_SEED=20261025 vendor/bin/phpunit --testsuite property --filter CreateTableExecutionProperties
MYSQL_VERSION=8.4.7 composer fuzz:mysql
```

`fuzz/fuzz_mysql_execution.php` is the single SQL execution target. It accepts SQL
Faker's supplied grammar seeds, including SQL-mode and diagnostic profiles.
`MYSQL_MEMORY_FUZZ_SECONDS=900` bounds a search from an empty corpus after startup.
The CI workflow replays the supplied seeds and then runs that fifteen-minute search.
Differences, crashes, timeouts, and unstable reference observations fail the check.
The selected seeds and duration establish only the cases explored by that run.
