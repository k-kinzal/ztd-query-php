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

1. Generate public semantic values from constructor types and invariants.
2. Consume generated executable roots directly in MemoryDB with PHPUnit PBT.
3. Compare SQL Faker statements on MemoryDB and Testcontainers MySQL with PHP-Fuzzer.

PBT classes live under `tests/Property`, use `*Properties` names, and expose
`satisfies*` test methods. `tests/Generator` supplies their values independently of
`fuzz`. Type-specific generators and properties mirror the same semantic namespace:
`SqlSemantics\Platform\MySql\Statement\Account\AlterUser` maps to
`tests/Generator/MySql/Statement/Account/AlterUserGenerator.php` (when a specialized
generator is needed) and `tests/Property/MySql/Statement/Account/AlterUserProperties.php`.
The core package uses the `Semantics` prefix on both sides. Types without a specialized
generator use `Construction`, which samples their declared constructor arguments.
Semantic counterexample regressions live beside their corresponding properties.
Tests of MemoryDB classes live under `tests/Unit`; generators do not create
mysql-memory implementation types.
The PHPStan rules in `rules` require a corresponding property class for every
public immutable value class and enum discovered in SQL Semantics and its MySQL
package. Specialized generators must follow the same namespace mapping, use the
`Generator` suffix, and declare the corresponding semantic return type.
Operation, statement and scalar properties execute the original generated objects;
operation execution retains the generated facts without rebinding the statement.
Query properties use MemoryDB's query preparation, planning and row iteration;
INSERT-only VALUES clauses use an INSERT fixture retaining the generated query.
Facts and scalar, query, and relation facts retain their owning generated operation,
which is passed unchanged to MemoryDB. Other component properties check construction;
their presence in the PHPStan inventory check does not establish standalone execution
for each component type.
Each model property generates ten samples by default. The sample count and seed
can be changed through `MYSQL_MEMORY_PBT_SAMPLES` and `MYSQL_MEMORY_PBT_SEED`.

```sh
composer lint
composer test
MYSQL_MEMORY_PBT_SAMPLES=10 MYSQL_MEMORY_PBT_SEED=example composer test:property
MYSQL_VERSION=8.4.7 composer fuzz:mysql
```

`fuzz/fuzz_mysql_execution.php` is the single SQL execution target. It accepts SQL
Faker's supplied grammar seeds, including SQL-mode and diagnostic profiles.
`MYSQL_MEMORY_FUZZ_SECONDS=900` bounds a search from an empty corpus after startup.
The CI workflow replays the supplied seeds and then runs that fifteen-minute search.
Differences, crashes, timeouts, and unstable reference observations fail the check.
The selected seeds and duration establish only the cases explored by that run.
