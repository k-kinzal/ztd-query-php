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

`TransactionExecutionProperties` generates all fourteen concrete transaction
command alternatives. It also composes dependent savepoint and XA sequences before
execution: each sequence shares its generated name or XID through shrinking.
These properties check transaction state, access mode, isolation, prepared XA
branches and balanced statement journals without parsing or rendering
inside the assertion callback. Direct execution, SQL preparation, binary
preparation and stored programs use the same command lifecycle as concrete INSERT
and legacy operations. Syntax notices occur during reading/preparation, not again
when a prepared value is executed. Existing families still using `Operation`
remain explicit at their command boundaries.

Transaction control has no schema dependencies or parameter markers. Preparation
retains the same concrete value and does not build alternative interpretations
for metadata invalidation. Changing table definitions therefore cannot rebind a
prepared savepoint name or transaction characteristic.

The property suite currently exercises the stated CREATE TABLE, INSERT, transaction
and interval behaviors. It does not cover every statement family. The old constructor sampler
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
The MySQL semantic verification workflow runs reconstruction and execution over
all nine releases, plus the supplied 8.4.7 mode and diagnostic supplements. Text
and native prepared execution use separate jobs. Each job replays its supplied
profile and then runs the fifteen-minute search from a different empty directory.
Differences, crashes, timeouts, and unstable reference observations fail the check.
The selected seeds and duration establish only the cases explored by that run.

To reproduce one default-profile replay with the ordinary tools:

```sh
# Run SQL Faker's existing coverage check from its package first.
cd ../sql-faker
php bin/seeds.php check --tag mysql-8.4.7
cd ../mysql-memory
corpus=$(mktemp -d)
cp ../sql-faker/seeds/mysql/mysql-8.4.7/*.txt "$corpus/"
MYSQL_VERSION=8.4.7 MYSQL_MEMORY_EMULATE=0 php -d memory_limit=-1 vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_execution.php "$corpus/" --max-runs=0 --timeout=60
```

Use a fresh replay directory and copy only the selected profile's `.txt` files.
Mode supplements require their matching `SQLFAKER_SQL_MODE`; diagnostic seeds
require `SQLFAKER_DIAGNOSTICS=1`. `MYSQL_MEMORY_EMULATE=0` selects native prepared
execution, and `1` selects text execution. Profile setup is not concatenated to
the statement under test. Common fixture SQL uses the text protocol on both
servers; the selected input protocol is restored even when setup fails. Each
native connection's advertised MySQL release must match the grammar before
fixture cleanup or configuration import begins. A successful replay prints `Verified N inputs.`;
require `N` to equal the number of copied files. Missing or empty corpora cannot
establish a passing replay.

The observer retains raw protocol results and compares deterministic metadata,
rows, completion information, errors, warnings, table contents and definitions.
SELECT rows retain order when the outer query has an ordering clause; otherwise
they are compared as multisets, preserving duplicates and value types.
Other result-producing commands retain order. Raw observations in counterexample
reports are base64-encoded PHP serialization, preserving binary values as well
as types. The target records transaction status, selected database, session
variables and global settings before restoration. State-read failures are failures
of the check. Account/privilege catalogs, stored-program definitions, replication
configuration, server catalog state and committed rows in other application
databases are observed independently through the administrator connection.
Views contribute definitions without evaluating their bodies during observation.
On MySQL 5.7 and later, an independent administrator discovers the tested session's
user variables through Performance Schema. The observation retains their stored
bytes and reads their result metadata, character sets, collations and coercibility
on the owning connection. An unavailable owner or inventory fails the check;
it cannot become an empty variable snapshot. Metadata locks are read through the
administrator immediately after primary results and conditions, before further
owner queries can change them. Their object names, modes, durations and statuses
are compared with duplicates preserved. Instrumentation is enabled before the
input, and missing owners, disabled instrumentation or lost records fail the
check. The native observation mechanism tests require Docker and start the
pinned Testcontainers releases.
Each campaign owns a fresh reference container, and a failed reset cannot pass
or contaminate a later campaign. An incomplete restoration makes its target
unusable: subsequent verification, comparison and execution calls refuse further
input. Setup runs inside the same connection lifetime as observation, so a failed
setup also releases its connection. The administrator terminates that captured
connection and confirms its disappearance before restoring catalog objects.
Failure reports retain completed result sets and state observations, including
binary values, together with separate observation and cleanup phases. A cleanup
failure cannot turn an earlier observation failure into a successful comparison.

Top-level SHUTDOWN and RESTART use disposable servers, selected through the
official grammar rather than text matching. Two independently initialized native
servers must agree before MemoryDB is compared. Both native initializations keep
the ordinary SQL readiness check. A command must terminate its original connection
within ten seconds; RESTART must then become SQL-ready within thirty seconds.
Exceeding either deadline fails observation, even if both implementations time out.
A shutdown-in-progress response is distinct from completed disconnection. Other
SQL refusals remain failures of readiness, not evidence that a server stopped.
The observer retains primary results or full SQL errors, conditions for refused
commands, and post-restart table rows, definitions and status. Partial lifecycle
observations use the same failure reports as ordinary statements. Startup, probes
and disposal remain inside the existing sixty-second input limit. The pinned
images include AMD64-only releases, which require CPU emulation on an ARM Docker
host. A matching Docker host can be selected through the standard `DOCKER_HOST`
setting. Record the host architecture and image identity with execution evidence;
changing hosts does not establish additional SQL or profile coverage.
Networks with restricted published ports can set
`MYSQL_MEMORY_LIFECYCLE_NATIVE_PORT` and `MYSQL_MEMORY_LIFECYCLE_REPEATED_PORT`
to two distinct available ports on that Docker host. They must also differ from
the ordinary campaign's optional `MYSQL_MEMORY_CONTAINER_PORT`.

Temporary objects, MySQL 5.6 session inventories,
row locks, advisory-lock recursion, contention and environment-specific catalog
fields still need complete contracts; these runs do not yet establish equivalence
for every state change.

Each target records its release, lexical profile and mutation seed. Set
`PHP_FUZZER_SEED` to reproduce PHP-Fuzzer's random sequence on the same revision
and corpus. The retained input bytes are the direct way to replay a finding with
`vendor/bin/php-fuzzer run-single`.
