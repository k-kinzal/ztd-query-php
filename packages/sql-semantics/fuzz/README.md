# Semantic property fuzzing

The fuzz targets check one property for every statement sql-faker can generate
for a declared grammar release:

1. sql-faker generates SQL.
2. sql-semantics binds it into a Statement with `Binder::bind($sql, strict: false)`.
3. `Statement::toString()` returns exactly the generated SQL.

Nothing else is checked. sql-parser is not called by the targets; it is used inside
sql-semantics. A generated request that the database would reject raises
`InvalidSql` and produces no Statement; that outcome is not a failure. Any other
exception, including `UnclassifiedSql`, `InvalidStructure` and `SemanticException`,
and any difference between the generated SQL and the written SQL is a failure.

Targets use the same `BytePlanCompiler`, complete statement rules, default expansion
settings, and 80,004-byte maximum input as sql-parser. They do not filter
statements, productions, expressions, or exceptions. MySQL uses `statement` for
5.6/5.7 and `simple_statement_or_begin` for 8.0–9.1; PostgreSQL uses `stmt`; SQLite
uses `cmd`. Statements are bound against an empty schema of the same release,
because arbitrary syntax does not come with consistent table declarations; unknown
references are retained as diagnostics.

Structural serialization, rebinding, and statement-specific facts are covered by the
package's unit tests, not by the fuzz property.

## Run

PHP-Fuzzer runs directly. There are no committed seed inputs or custom runner.
On the first run the corpus directory is empty; PHP-Fuzzer creates its own inputs
and saves interesting mutations. The evolving corpus and grammar coverage are
ignored by Git and retained under `fuzz/corpus/` and `fuzz/coverage/`.

```bash
composer install
XDEBUG_MODE=off composer fuzz:smoke
XDEBUG_MODE=off MYSQL_VERSION=5.6.51 composer fuzz:mysql -- --max-runs=10000
XDEBUG_MODE=off composer fuzz:pg -- --max-runs=10000
XDEBUG_MODE=off composer fuzz:sqlite -- --max-runs=10000
```

The smoke command runs 100 mutation iterations for each dialect's default
release, after replaying any accumulated corpus. Individual `fuzz:*` commands
run continuously unless `--max-runs` is supplied. Set `SQLFAKER_COVERAGE=0` to
turn off grammar coverage recording. Disable Xdebug to avoid its nesting limit
on deeply generated SQL. Each input has a ten-second timeout.

## CI runs

CI runs all eleven releases as separate jobs: MySQL 5.6.51, 5.7.44, 8.0.44, 8.1.0,
8.2.0, 8.3.0, 8.4.7, 9.0.1 and 9.1.0, PostgreSQL 17.2, and SQLite 3.47.2. Each job
replays its cached corpus under `fuzz/corpus/<dialect>-<version>/` and then runs
300 mutations on pull requests and pushes, 10,000 on daily runs, and a configurable
count for manual runs. It caches only the evolving corpus and uploads logs,
findings, and grammar coverage. PHP-Fuzzer can exit zero after a finding, so CI
also fails when its output contains `CRASH` or `ERROR`; local runs require
inspecting this output too.

To run every release the way CI does, from this package:

```bash
for target in mysql:5.6.51 mysql:5.7.44 mysql:8.0.44 mysql:8.1.0 mysql:8.2.0 \
  mysql:8.3.0 mysql:8.4.7 mysql:9.0.1 mysql:9.1.0 pg:17.2 sqlite:3.47.2; do
  dialect="${target%%:*}"; version="${target#*:}"
  mkdir -p build "fuzz/corpus/$dialect-$version"
  XDEBUG_MODE=off MYSQL_VERSION="$version" php -d memory_limit=2G \
    vendor/bin/php-fuzzer fuzz "fuzz/fuzz_${dialect}_semantics.php" \
    "fuzz/corpus/$dialect-$version/" --max-runs=300 --timeout=10 2>&1 \
    | tee "build/fuzz-$dialect-$version.log" | grep -E 'CRASH|ERROR'
done
```

`MYSQL_VERSION` selects the MySQL release and is ignored by the PostgreSQL and
SQLite targets. A finite run supplies evidence, not an exhaustive proof over
infinitely many SQL strings.

## Replaying sql-faker seeds

sql-faker keeps grammar coverage seeds for its default releases under
`../sql-faker/seeds/<dialect>/<release>/`. These seeds are not copied into
sql-semantics and are not run in CI. As a local check before a change, replay them
once through the same targets, without mutation:

```bash
XDEBUG_MODE=off composer fuzz:seeds
XDEBUG_MODE=off composer fuzz:seeds:mysql   # MySQL 8.4.7 only
XDEBUG_MODE=off composer fuzz:seeds:pg      # PostgreSQL 17.2 only
XDEBUG_MODE=off composer fuzz:seeds:sqlite  # SQLite 3.47.2 only
```

Each command runs PHP-Fuzzer with `--max-runs=0`, so it only replays the seed
directory, with grammar coverage recording turned off. A clean replay prints no
findings. PHP-Fuzzer stops at the first seed that fails and prints `CORPUS CRASH`
with the failure, still exiting zero; fix it, add a regression test, and run the
command again until the whole directory replays cleanly.

## Reproduce a finding

```bash
XDEBUG_MODE=off MYSQL_VERSION=5.6.51 php -d memory_limit=2G \
  vendor/bin/php-fuzzer run-single fuzz/fuzz_mysql_semantics.php crash-<hash>.txt
```

Use the target, release, and input path reported in the log. The failure message
shows the generated SQL and the SQL written by `toString()`, or the exception that
binding raised. CI artifacts retain the raw crash input and corpus, including
failures during corpus replay. Fix the implementation and add an ordinary
regression test; do not allow the exception or restrict generation to avoid the
finding.
