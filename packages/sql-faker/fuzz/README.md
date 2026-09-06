# SQL Faker totality fuzzing

This setup follows php-ai-toolkit's `setup-toolkit-fuzzing` skill and PHP-Fuzzer
v0.0.11. It explores SQL Faker's generation domain, using one target per fixed
database and grammar version. Each input follows `decode → Provider::generate →
DB syntax verification`. There are no statement-type targets, fragment campaigns,
coverage-driven schedulers, SQL filters, or retries after a database rejection.

## Running and reproducing

Run from `packages/sql-faker` after `composer install`. PHP-Fuzzer needs `pcntl`
and `mbstring`; the checkers need `pdo_mysql`, `pgsql`, or `pdo_sqlite`. MySQL and
PostgreSQL use disposable Docker containers with synthetic local credentials.

```sh
composer fuzz:prepare sqlite
vendor/bin/php-fuzzer fuzz fuzz/fuzz_sqlite_syntax.php fuzz/corpus/sqlite/ --max-runs=10000

composer fuzz:prepare mysql
vendor/bin/php-fuzzer fuzz fuzz/fuzz_mysql_syntax.php fuzz/corpus/mysql/ --max-runs=10000

composer fuzz:prepare pg
vendor/bin/php-fuzzer fuzz fuzz/fuzz_pg_syntax.php fuzz/corpus/pg/ --max-runs=10000
```

`--max-runs` limits mutation calls after startup corpus replay. PHP-Fuzzer resets
its run counter after replay, so the total target calls can exceed that limit.
A finding during startup stops replay before all initial inputs have run.

`composer fuzz:sqlite`, `fuzz:mysql`, and `fuzz:pg` prepare initial inputs before
starting the corresponding target. `composer test:fuzz` runs deterministic
contract tests. The ordinary test suite does not start a fuzz campaign.

The default grammars are MySQL 8.4.7, PostgreSQL 17.2, and SQLite 3.47.2. MySQL's
`MYSQL_VERSION` selects a supported fixed version before startup. PostgreSQL uses
the 17.2 container. SQLite uses the PHP-linked engine; its actual version, like
both server versions and PHP's version, is recorded in `build/fuzz/<db>/run.json`.
Reproduction requires matching these versions, the grammar fingerprint, generator
revision, decoder format, and expansion limit recorded there.

```sh
vendor/bin/php-fuzzer run-single fuzz/fuzz_sqlite_syntax.php build/fuzz/sqlite/failure.bin
vendor/bin/php-fuzzer minimize-crash fuzz/fuzz_sqlite_syntax.php /path/to/saved-failure.bin
```

PHP-Fuzzer owns its normal `crash-*` files and mutation corpus. The harness also
saves the original bytes as `failure.bin`, SQL, trace, and diagnostics outside the
corpus, including failures during initial corpus replay. Copy a finding before
minimization, since subsequent findings replace the last-failure diagnostics.
PHP-Fuzzer 0.0.11 returns zero for findings; the harness makes a failed target run
exit nonzero, including replay and minimization that exercise failing inputs.
Database/measurement infrastructure faults abort the run rather than count as
successful inputs. A generation failure remains visible if its checkpoint also
fails.

## Input, budget, and initial corpus

The first four bytes are an unsigned little-endian budget selector (missing bytes
are zero). Remaining bytes alternate between structural and lexical choices.
Empty and short inputs are valid. The budget is `Bmin + header % (Bmax-Bmin+1)`,
where Bmin is the minimum non-empty completion cost from the fixed statement root.
An impossible configured limit is a setup failure.

The immutable `GenerationPlan` carries the budget and the two streams. A fresh
cursor is created for every generation; lexical retries continue consuming that
cursor. Normal Faker calls use the same generation path with Faker choices.
Candidate filtering reserves completion costs for all pending siblings and
distinguishes empty from non-empty derivations. Selection supports more than 256
alternatives. Exhausted structural bytes choose minimum completion cost, breaking
ties by original alternative order. Explicit budgets do not inherit the legacy
`maxDepth` cutoff.

`fuzz/Input/ProductionWitness` computes minimum complete witnesses using dynamic
programming over `(uses requested production, emits non-empty output)`. Every
pending sibling is completed. The encoder shares candidate filtering and byte
widths with the generator. Each seed is decoded through the ordinary provider and
checked for its intended production ID. Generated SQL is never rewritten here.

| Location | Owner and contents |
| --- | --- |
| `fuzz/seeds/reviewed/<db>/` | Tracked small raw inputs |
| `fuzz/seeds/generated/<db>/<fingerprint>/` | Fixed generated initial inputs |
| `fuzz/corpus/<db>/` | PHP-Fuzzer's evolving raw inputs only |
| `fuzz/coverage/<db>/` | Coverage snapshots and writer locks only |
| `build/fuzz/<db>/` | Run, last finding, incomplete verification, and seed inventory diagnostics |

The seed inventory retains unsupported, root-external, over-budget, and failed
generation entries with reasons and minimum cost when known. Seed preparation
does not credit the run's coverage. A failed initial replay means the remaining
inputs have not been verified; `corpusReplay` reports that explicitly. Even a
complete production seed set does not cover all recursion counts, combinations,
or lexical values. Those remain the work of normal PHP-Fuzzer mutation.

## Grammar coverage in ordinary Faker use

```php
$faker = \Faker\Factory::create();
$coverage = new \SqlFaker\Coverage\GrammarCoverage(
    storageDirectory: __DIR__ . '/grammar-history',
);
$provider = new \SqlFaker\MySqlProvider($faker, 'mysql-8.4.7', coverage: $coverage);
$faker->selectStatement(maxDepth: 3);
$snapshot = $coverage->snapshot();
$trace = $coverage->lastGeneration();
$coverage->flush();

$plan = \SqlFaker\Grammar\Derivation\GenerationPlan::all()
    ->requiringNonEmpty()
    ->withExpansionBudget(5000)
    ->withChoiceBytes("\x01\x02", "\x03\x04");
$sql = $provider->generate($plan);
```

All three providers accept `coverage:` without changing existing two-argument
construction or string results. Omit it to disable measurement. Omit
`storageDirectory` (or pass null) to measure entirely in memory with no file I/O.
Provider construction automatically registers the inventory and restores matching
history once, before generation. Coverage never reads or replays a corpus.

The denominator contains every effective production reachable from the normal
statement root, before plan, budget, or lexical filters. SELECT-only calls cannot
shrink it. Root-external productions and original productions excluded/transformed
by adapters are listed separately. IDs combine grammar/profile fingerprint, rule,
original ordinal, and effective right-hand-side identity; filtering cannot renumber
them or credit a transformed original production.

Both `current` and `cumulative` expose reached and emitted IDs, counts, rates,
`notReachedIds`, and reached-but-not-emitted `notEmittedIds`. Reached means selected
in any attempt; emitted means selected in the attempt that returned SQL. The
denominator is the same for both rates. These are generator derivations, not DB
parser paths or DB acceptance evidence. DB accepted/rejected/incomplete counts are
separate harness diagnostics. Lexical-only API calls have lexical trace events and
do not credit statement productions.

Only the most recent full generation trace is retained. It includes attempts,
outcomes, production choices, and actual node/parent/RHS-position relationships.
`lastGeneration()['emittedIds']` can be credited to a separate consumer-owned set
when the unchanged SQL actually reaches that consumer; discarded or rewritten SQL
must not inherit that credit.

## Persistence and checkpoints

Snapshots are keyed by format version, grammar fingerprint, and a cached digest
of generator source contents, so different development implementations never merge
merely because both are called `dev-main`. Changed keys create separate files and
leave previous histories intact. Restore verifies the header, root, reconstructed
inventory digest, and every production ID. Corrupt JSON or unknown IDs are errors.

`cumulative = saved ∪ current`. Restored and merged history never populates current
observations or counters. `merge($snapshot)` validates compatibility and unions
cumulative sets. `flush()` atomically replaces a full snapshot via a temporary file
in the same directory, saves dirty cumulative sets, and retains current data.
Repeated merges/flushes/replays cannot double-count IDs. `reset()` clears only
current observations and the last trace; flush first to retain unsaved discoveries.
It does not delete stored history. Memory-only flush is a no-op.

Snapshots include the saved checkpoint's UTC time, run ID, observed generation
count, and in-progress flag. `restoredCheckpoint` identifies the restored boundary.
Live `snapshot()['checkpoint']` describes the boundary that a flush would save.
Counters describe the current run only. A lock rejects concurrent writers for the
same key; use separate directories and explicit merge for independent consumers.
Interrupted temporary files do not replace valid history. Unwritable storage never
silently becomes memory-only measurement. The guarantee is atomic replacement,
not per-input durability or power-loss durability: observations since the last
successful checkpoint may be lost on a forced kill or long interrupted generation.

| Environment setting | Default |
| --- | --- |
| `FUZZ_MAX_EXPANSIONS` | 5000 |
| `FUZZ_CORPUS_DIRECTORY` | `fuzz/corpus/<db>`; an explicit PHP-Fuzzer corpus argument takes precedence |
| `FUZZ_COVERAGE_DIRECTORY` | `fuzz/coverage/<db>` |
| `FUZZ_REPORT_DIRECTORY` | `build/fuzz/<db>` |
| `FUZZ_CHECKPOINT_GENERATIONS` | 100 |
| `FUZZ_CHECKPOINT_SECONDS` | 30 |

Checkpoints run after the configured count or elapsed time, upon findings, and at
normal shutdown. Canonical path checks reject coverage/report directories equal
to or nested under the raw corpus, including symlink aliases. Coverage does not
know the corpus path or control the next generation.

## Database oracles and remaining verification limits

MySQL uses SQL `PREPARE` via server commands, avoiding PDO's native-to-emulated
prepare fallback. Error 1295 is incomplete verification, never acceptance. No
generated statement is executed. PostgreSQL sends the unnamed protocol Parse
request without Execute; no successful SAVEPOINT release can leak executed state.
SQLSTATE 42601 always remains a finding, including errors near parentheses.
SQLite prepares the original SQL in a fresh in-memory session for each input,
because even preparing some PRAGMA statements changes session options. Its engine
and empty starting database remain fixed. `incomplete input` is always a finding.

The checkers retain explicit schema/value-dependent rejections separately from
acceptance. Unknown errors remain investigation candidates. Parser facilities can
reject unsupported statements before fully checking their syntax; those are
reported as incomplete. No coverage rate substitutes for unresolved DB validation.
The exact known-rejection codes/predicates are maintained in `fuzz/Target/*SyntaxCheck.php`.

Local initial-corpus runs exposed findings, including PostgreSQL rejecting
`DEFAULT` in a cursor's VALUES query, MySQL rejecting an unknown LOCK type, and
SQLite rejecting generated schema syntax. These are retained failures, not claims
that all generated SQL or all initial inputs passed. The separate scheduled fuzz
workflow is intended to surface such counterexamples.

## CI inheritance

The ordinary PR workflow runs compatibility tests, strict lint, benchmarks,
mutation testing, and deterministic fuzz contract tests. The separate scheduled /
manual workflow has exactly three DB entries and one target per entry. It restores
and saves the raw corpus and coverage directory using separate caches, including
after findings, and uploads original inputs and diagnostic artifacts. Artifact
upload alone is not treated as restoration. Compatibility is checked inside
Coverage; a cache containing an old generator revision starts a new history file.

References: [PHP-Fuzzer 0.0.11](https://github.com/nikic/PHP-Fuzzer/tree/v0.0.11),
[php-ai-toolkit fuzzing skill](https://github.com/k-kinzal/php-ai-toolkit/tree/main/skills/setup-toolkit-fuzzing),
[MySQL PREPARE](https://dev.mysql.com/doc/refman/8.4/en/prepare.html),
[PostgreSQL protocol flow](https://www.postgresql.org/docs/17/protocol-flow.html).
