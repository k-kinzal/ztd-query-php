# sql-fixture fuzz targets

Install the package's locked development dependencies with `composer install`.
Run from `packages/sql-fixture`:

```sh
composer fuzz:create-table -- --max-runs=10000
composer fuzz:insert-select -- --max-runs=10000
```

The insert/select target starts a disposable MySQL 8.4.7 Testcontainers instance,
so Docker and `pdo_mysql` are required. The commands create empty corpus directories when needed;
PHP-Fuzzer owns input mutation and the ignored `fuzz/corpus/` contents.

The CREATE TABLE target uses sql-faker's `BytePlanCompiler`: the input controls
expansion count, grammar choices and lexical values. `MYSQL_VERSION` selects the
grammar (default `mysql-8.4.7`), and `MAX_EXPANSIONS` bounds expansion (default 128).
The SQL parser supports a subset of that grammar, so `SchemaParseException` is an
expected rejection. For accepted schemas, the target checks that every writable
column is generated and explicit overrides are preserved exactly. Other exceptions
and engine errors are findings.

The insert/select target seeds fixture generation from the input and checks actual
MySQL storage results, including BIT, decimals, floating point, JSON and SET values.
It binds native PHP integers as integers, uses a temporary table, and rolls back
every input in `finally`, including failed insertions and failed comparisons.

Both targets reject all exceptions by default at the PHP-Fuzzer boundary. A finding
includes the raw input; the workflow also uploads the campaign output and fails
when the output reports a crash (PHP-Fuzzer can return zero after recording one).
Replay the saved input with the same dependencies and database/grammar version:

```sh
vendor/bin/php-fuzzer run-single fuzz/fuzz_create_table.php crash-INPUT.txt
vendor/bin/php-fuzzer run-single fuzz/fuzz_insert_select.php crash-INPUT.txt
```

Add a focused regression test after fixing a finding. Corpus cache keys include
a format revision because the CREATE TABLE input carries structural choices
instead of only a random seed.
