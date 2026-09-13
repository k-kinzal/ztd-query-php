# Fuzzing the SQLite platform

The targets follow SQLFaker's byte-to-plan pipeline: construct the versioned provider once, compile each input into a frozen `GenerationPlan` with `BytePlanCompiler`, generate SQL, and check the target's contract. They use the bundled `sqlite-3.47.2` grammar. Install the package's development dependencies and enable `pdo_sqlite` before running them.

| Command | Input and contract | Expected rejections |
|---|---|---|
| `composer fuzz:robustness:classify` | Grammar SQL or raw bytes; classification must not throw and must return the same result twice. | Unsupported input may classify as `null`. |
| `composer fuzz:robustness:rewrite` | The same input domain, with four fixture tables; verify query-kind agreement, mutation presence, non-empty SQL, and removal of physical schema qualifiers/index hints. | `UnsupportedSqlException` for unsupported statements; `UnknownSchemaException` for names outside the fixtures. Safe passthrough must never take either rejection. |
| `composer fuzz:semantics` | Schema-constrained SQLFaker plans; execute SELECT/INSERT/UPDATE/DELETE against native SQLite and ZTD, comparing reads, shadow rows, and physical sentinel rows after every operation. | None: every generated operation must execute successfully on both paths. |

`composer fuzz:robustness` and `fuzz/fuzz_robustness.php` remain aliases for the complete semantics target. The old pipeline target applied mutations to fabricated empty result rows; the semantics target now executes each result SELECT before applying its mutation. `composer fuzz` runs the three distinct contracts.

## Input encoding and reset

Classification and rewrite accept at most 4,096 bytes. An odd first byte selects raw SQL from the remaining bytes. An even first byte selects a `cmd` plan with non-empty output, depth preference 8, and at most 256 grammar expansions. SQLFaker reads the remaining bytes as its budget header and structural/lexical choices. This explores unsupported and malformed input as well as grammar-generated SQL; it does not claim that all generated SQL is semantically valid.

The semantics target accepts at most 128 bytes, grouped into up to 32 four-byte commands:

1. Operation: filtered SELECT, INSERT, arithmetic UPDATE, DELETE, aggregate SELECT, or name UPDATE.
2. Row selection; inserted primary keys instead increase from 3 to avoid duplicates.
3. Integer value; arithmetic updates exercise addition and subtraction.
4. Text selection: ordinary, quoted, Unicode, or empty text.

`StatementPlans` constrains the actual SQLite productions and lexemes to the `users(id, name, score)` fixture. SQLFaker renders the statements; the harness does not concatenate SQL commands. Each plan is compiled before generation, so replay does not depend on Faker's later random state. Both databases, the registry, the store, and the rewriter are recreated for every input. State is intentionally retained between commands within that input.

The two native PDO databases use the SQLite version linked into the selected PHP runtime. The grammar version is fixed; these targets check ZTD behavior on that runtime, rather than rechecking SQLFaker's syntax contract against a pinned SQLite release. The semantics plans use ordinary SQLite DML. Record `php --version`, `SELECT sqlite_version()`, and the installed SQLFaker reference when reproducing a finding; CI reports them, and crash diagnostics include them.

## Bounded runs and replay

```sh
composer fuzz:robustness:classify -- --max-runs=1000
composer fuzz:robustness:rewrite -- --max-runs=1000
composer fuzz:semantics -- --max-runs=1000

vendor/bin/php-fuzzer run-single fuzz/fuzz_semantics.php crash-<hash>.txt
vendor/bin/php-fuzzer minimize-crash fuzz/fuzz_semantics.php crash-<hash>.txt
```

Unexpected failures surface as `Error` with the contract, exact input in hex, SQL, and runtime information. PHP-Fuzzer saves `crash-*` inputs even when a campaign exits successfully. A non-zero process exit means the campaign itself could not complete. No broad exception is accepted as a successful input.

SQLFaker owns grammar coverage. Each provider records reached and emitted productions under `fuzz/coverage/<contract>`; set `SQLFAKER_COVERAGE=0` to disable it. Generated corpus entries, coverage, and crashes are ignored. Promote a minimized product defect to a deterministic regression test or a reviewed corpus input.

The separate scheduled/manual workflow restores and saves each corpus and uploads crash inputs on either success or failure. Its bounded run count and job timeout are independent safeguards. `composer test:fuzz` retains the existing seeded PHPUnit property checks; coverage-guided campaigns remain outside the default unit/Doctest suite.
