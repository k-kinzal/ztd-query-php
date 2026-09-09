# Native SQLFaker verification

`composer fuzz:mysql`, `composer fuzz:pg` and `composer fuzz:sqlite` run the
statement entry points with PHP-Fuzzer. MySQL and PostgreSQL use disposable
Testcontainers. SQLite must link PHP's PDO SQLite extension to **3.47.2**.
The pinned live engines are MySQL **8.4.7**, PostgreSQL **17.2** and SQLite
**3.47.2**. Other MySQL releases have source/profile and unit verification;
these campaigns do not claim live coverage for them.

The generator reads bytes only when compiling a `GenerationPlan`. The first four
bytes select an affordable expansion budget. The remaining bytes supply separate
production and lexical decisions. Lexical decisions select representatives or
construct values from bounded declared domains; the resulting spellings and
candidate keys are frozen in the plan. Repeating a plan must produce identical
SQL independently of later Faker state. Output compatibility across generator
revisions is not promised.

The expansion budget is checked against the exact minimum under all applicable
occurrence-specific production patterns, pending siblings and the non-empty
requirement. An admissible unconstrained lower bound prunes the finite search;
independent subtrees are reduced with exact empty/non-empty costs. Feasible
completion witnesses are cached separately from exact minima and impossible
budget ranges. A short constructive proof may establish feasibility first; its
256-expansion probe limit never proves impossibility or an exact minimum, and
an unsuccessful probe delegates to the complete search. Forced derivation steps reuse their frozen choices while still
checking the actual budget and output requirement. No search generates SQL to
retry, and no finite value sample is used to prove that a domain is empty.

## Verdicts and persistent evidence

The oracles never execute generated SQL. MySQL uses server `PREPARE`, PostgreSQL
uses extended-protocol `Parse`, and SQLite uses prepare without step. Preparation
may stop during name/type resolution before all syntax is examined. Results are
therefore recorded separately:

- `accepted`: the selected parser mode accepted the complete checked input.
- `semantic-inconclusive`: a recognized analysis/schema restriction prevented a
  complete decision. This does not count as accepted syntax.
- `unsupported`: an explicitly recognized unsupported feature or parser entry.
- `finding`: syntax, scanner, grammar-action or otherwise unclassified rejection;
  the oracle raises `SyntaxFailure` and PHP-Fuzzer saves the input.
- `infrastructure-failure`: verification could not run; the target exits with
  status 2 rather than treating the input as valid or as a generator finding.

PostgreSQL's `0A000` is not blanket-accepted. Grammar/scanner diagnostics remain
findings; only specifically identified unsupported features and analysis sources
are classified separately. Native controls include grammar actions returning
codes other than ordinary syntax errors.

`fuzz/coverage/<target>/` stores the original grammar denominator, reached and
emitted sets independently of the evolving corpus. Its `verification/` directory
stores verdict-specific production, lexeme-definition, compound, version-case,
rewrite and spacing features. The identity includes the grammar fingerprint,
inventory digest, generator revision, complete oracle-source revision, actual
server version/settings, parser mode and exact SQL wrappers. Changing any of
these creates separate history. `acceptedRate` is accepted original productions
divided by the unchanged source denominator, not the fraction of successful
executions or an assertion that every production should be valid SQL.

Each feature/verdict keeps its first witness. Raw input bytes (`inputHex`) and the
exact checked SQL are saved independently under `verification/witnesses/` so
corpus minimization cannot delete the reproduction material. A witness file's
key is `sha256(inputHash + ':' + sqlHash)`. The snapshot contains those two
hashes, context and source identities. The most recent observation also includes
rewrite before/after occurrences, candidate sources/rejections and the complete
generation trace. Preserve the entire coverage directory when exporting evidence.
The finite feature IDs exclude random string contents; native guidance uses a
separate bounded hash range while snapshots retain exact IDs.

A finding can be replayed with the same target/configuration and source revision:

```sh
vendor/bin/php-fuzzer run-single fuzz/fuzz_pg_syntax.php crash-<hash>.txt
```

To start independent exploration, create an empty corpus directory and select a
new coverage directory where applicable. `--len-control-factor 0` grows input
length faster; keep ordinary length growth campaigns too. A native fuzzer may
exit successfully after reporting a crash, so inspect the `CRASH` marker and
saved input as well as the process status.

## Original program roots and parser selector modes

`fuzz/fuzz_program_syntax.php` is the supplementary target previously used only
as a temporary campaign script. It has a 100-expansion budget and selects these
contexts with its first input byte:

| Dialect | Entry points and checks |
| --- | --- |
| MySQL | All six `start_entry` alternatives, using declared SELECT/CREATE TABLE/subquery wrappers where required. Empty programs are checked as the documented `PREPARE` rejection, without accepted credit. |
| PostgreSQL | All six `parse_toplevel` raw-parser modes; additional JSON behavior and bare-label contexts. Raw acceptance means grammar parsing, separately from extended Parse's analysis. |
| SQLite | Original `input` program root, preparing every successive statement tail in one fresh connection without executing statements. A semantic failure stops the loop and leaves the tail explicitly inconclusive. |

Provision disposable pinned engines, then supply connection settings. These
connections must use the scanner settings described in
[the source audit](../docs/source-audit.md). The targets validate actual settings
before running.

```sh
mkdir -p fuzz/corpus/program-mysql
SQLFAKER_DIALECT=mysql \
SQLFAKER_MYSQL_DSN='mysql:host=127.0.0.1;port=3306;charset=utf8mb4' \
SQLFAKER_MYSQL_USER=root SQLFAKER_MYSQL_PASSWORD=root \
vendor/bin/php-fuzzer fuzz --max-runs 10000 --timeout 10 \
  fuzz/fuzz_program_syntax.php fuzz/corpus/program-mysql
```

For PostgreSQL, compile `oracles/sqlfaker_raw_parse.c` against **17.2** server
headers on the server's OS/architecture. The source rejects a different header
version at compile time. The supplied helper uses `pg_config` (override with
`SQLFAKER_PG_CONFIG`) and a C compiler:

```sh
sh fuzz/oracles/build-pg-raw.sh /tmp/sqlfaker_raw_parse.so
```

Copy the resulting module into the disposable server at that path. The PHP
oracle registers a `pg_temp` C function for the current session and binds SQL as
a parameter to `raw_parser`; it never executes that parameter as SQL.

```sh
mkdir -p fuzz/corpus/program-pg
SQLFAKER_DIALECT=pg \
SQLFAKER_PG_CONNECTION='host=127.0.0.1 port=5432 dbname=fuzz_test user=test password=test' \
SQLFAKER_PG_MODULE=/tmp/sqlfaker_raw_parse.so \
vendor/bin/php-fuzzer fuzz --max-runs 10000 --timeout 10 \
  fuzz/fuzz_program_syntax.php fuzz/corpus/program-pg
```

For SQLite, enable PHP FFI and set `SQLFAKER_SQLITE_LIBRARY` to the absolute
shared-library path for **3.47.2**. PDO must also load 3.47.2 for the independent
error classifier. The program oracle records compile options from its actual
FFI library and detects a disagreement with PDO. Use the platform's loader
configuration when PHP would otherwise select a different SQLite library.

```sh
mkdir -p fuzz/corpus/program-sqlite
SQLFAKER_DIALECT=sqlite SQLFAKER_SQLITE_LIBRARY=/absolute/path/libsqlite3.so \
vendor/bin/php-fuzzer fuzz --max-runs 10000 --timeout 10 \
  fuzz/fuzz_program_syntax.php fuzz/corpus/program-sqlite
```

`SQLFAKER_COVERAGE_DIR` selects an independent program-campaign coverage root.
`composer test:oracles` runs classifier and recording controls plus live controls
for the connection/module/library variables provided above. Missing optional
native infrastructure is reported as skipped tests; a complete native control run
must have no skips. These checks include invalid second statements and the
inconclusive SQLite-tail case, so first-statement acceptance cannot be mistaken
for complete-program acceptance.
