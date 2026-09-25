# Semantic round-trip property

Each target compiles the input bytes with sql-faker's `BytePlanCompiler`, generates
SQL from the same statement root as sql-faker, sends it through semantic analysis,
and compares the original SQL with `Statement::toString()` after formatting both
with sql-formatter's Compact preset. An empty generated statement, a rejection,
a missing serializer, a serialization failure, a formatting failure, or a
difference is a finding. No statement category or exception is skipped.

The target calls `Facade\Semantics::analyze()`, which needs no schema. Its
`Statement` stores typed model arguments and finite choices, not source SQL or a
parser tree. SQL is reconstructed by the model writers. The schema-dependent
`Binder` API is separate from this property.

## Run

```sh
composer install
composer fuzz:seeds
composer fuzz:sqlite -- --max-runs=0
composer fuzz:mysql -- --max-runs=0
composer fuzz:pg -- --max-runs=0
composer fuzz:smoke
```

Every `fuzz:<database>` command copies the sql-faker seed plans into that target's
corpus first. The existing corpus remains available. `--max-runs=0` replays the
corpus without further mutations; `fuzz:smoke` adds 100 mutations per target.
Individual targets run continuously unless a run budget is supplied. There is no
database connection and no container dependency.

| Target | Default grammar | Statement root | Seed directory in sql-faker |
| --- | --- | --- | --- |
| `fuzz_mysql_roundtrip.php` | `mysql-8.4.7` | `simple_statement_or_begin` | `seeds/mysql/mysql-8.4.7` |
| `fuzz_pg_roundtrip.php` | `pg-17.2` | `stmt` | `seeds/pg/pg-17.2` |
| `fuzz_sqlite_roundtrip.php` | `sqlite-3.47.2` | `cmd` | `seeds/sqlite/sqlite-3.47.2` |

Set `MYSQL_VERSION` to select another shipped release. MySQL 5.6 and 5.7 use
`statement` as their generation root. When sql-faker does not ship seeds for a
release, seeding reports zero inputs and the fuzzer uses its existing corpus and
newly generated plans. The default corpora contain 2,135 MySQL, 2,639 PostgreSQL,
and 294 SQLite inputs. The plan format is unchanged; no SQL-text conversion or
seed filtering is performed.

Grammar coverage accumulates under `fuzz/coverage/<database>`. Set
`SQLFAKER_COVERAGE=0` to disable it. Composer starts PHP-Fuzzer without a PHP memory
limit and with a 60-second per-input timeout to accommodate grammar initialization.

## Replay a finding

```sh
SQLFAKER_COVERAGE=0 php -d memory_limit=-1 vendor/bin/php-fuzzer run-single \
  fuzz/fuzz_sqlite_roundtrip.php fuzz/corpus/sqlite/refact-1.txt --timeout=60
```

The report includes the grammar release, the raw plan in hexadecimal, and the
generated SQL. A mutated finding is saved as `crash-<hash>.txt`; an initial corpus
failure names the original seed. Replay it with the same target and MySQL release.
PHP-Fuzzer 0.0.11 can exit successfully after reporting a crash. The Composer
commands and CI use `fuzz/run.php`, which streams its output and returns failure
for every reported crash or timeout, including initial corpus failures. When
invoking PHP-Fuzzer directly, inspect its output as well as its exit status.

## Continuous integration

`.github/workflows/sql-semantics-fuzz.yml` runs one job per database on relevant
pull requests, nightly, and on demand. Pull requests replay seeds and run 100
mutations; scheduled and manual runs default to 10,000 mutations. Each job restores
the corpus and coverage, copies the seeds, and uploads its log and inputs even on
failure. Only scheduled/manual runs save caches and open deduplicated finding
issues; pull requests do not publish findings as issues.
