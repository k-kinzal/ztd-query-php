# Semantic property fuzzing

Every statement sql-faker generates for a declared release must produce semantic
structure through `Binder::analyze()`. Targets use the same `BytePlanCompiler`,
complete statement rules, default expansion settings, and 80,004-byte maximum
input as sql-parser. They do not filter statements, productions, expressions,
or exceptions. MySQL uses `statement` for 5.6/5.7 and
`simple_statement_or_begin` for 8.0–9.1; PostgreSQL uses `stmt`; SQLite uses `cmd`.

## Properties

The generated statement is analyzed against an empty catalog because arbitrary
syntax does not come with consistent table declarations. Unknown references must
remain structured and carry diagnostics. They must not erase the statement or
hide an implementation failure.

The target checks:

- Exact SQL round-tripping, deterministic analysis, and agreement with strict
  binding when there are no diagnostics.
- Output order, relation scopes, column declaration membership, dialect-consistent
  types, and explicit unresolved references throughout nested graphs.
- Statement-specific structure, including insertion destinations, assignments,
  predicates, MERGE actions, configuration effects, and bound DDL expressions.
- Companion statements against a known schema of the same release, varying input
  values and destination order to verify mappings, diagnostics, and expression
  edits with fresh lineage and binding facts.

The companion properties supplement the complete grammar generator; they do not
replace or constrain its generated SQL. Regression tests belong to the package
implementation's ordinary unit tests.

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

To check every MySQL release with separate corpora, run from this package:

```bash
for version in 5.6.51 5.7.44 8.0.44 8.1.0 8.2.0 8.3.0 8.4.7 9.0.1 9.1.0; do
  mkdir -p "fuzz/corpus/mysql-$version"
  XDEBUG_MODE=off MYSQL_VERSION="$version" php -d memory_limit=2G \
    vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_semantics.php \
    "fuzz/corpus/mysql-$version/" --max-runs=300 --timeout=10
done
XDEBUG_MODE=off composer fuzz:pg -- --max-runs=300
XDEBUG_MODE=off composer fuzz:sqlite -- --max-runs=300
```

CI runs all eleven releases: 300 mutations per release on pull requests and
pushes, 10,000 on daily runs, and a configurable count for manual runs. It caches
only the evolving PHP-Fuzzer corpus and uploads logs, findings, and grammar
coverage. PHP-Fuzzer can exit zero after a finding, so CI also checks its output
for `CRASH` and `ERROR`; local runs require inspecting this output too. A finite
run supplies evidence, not an exhaustive proof over infinitely many SQL strings.

## Reproduce a finding

```bash
XDEBUG_MODE=off MYSQL_VERSION=5.6.51 php -d memory_limit=2G \
  vendor/bin/php-fuzzer run-single fuzz/fuzz_mysql_semantics.php crash-<hash>.txt
```

Use the target, release, and input path reported in the log. Semantic property
exceptions include the grammar release, generated SQL, input hex, and original
exception. CI artifacts retain the raw crash input and corpus, including failures
during corpus replay. Fix the implementation and add an ordinary regression test;
do not allow the exception or restrict generation to avoid the finding.
