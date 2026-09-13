# PostgreSQL robustness fuzzing

These targets follow the SQLFaker adoption in repository PR #303, including its
byte-driven generation and persistent grammar coverage. The grammar is pinned to
`pg-17.2`; the SQLFaker revision comes from this repository checkout.

Each input selects a statement family with its first byte. `BytePlanCompiler`
freezes the remaining budget, production and lexical choices into one plan, and
`PostgreSqlProvider::generate()` renders that plan once. There is no hashed seed
or second random generation pass. The 20,005-byte limit accommodates the selector,
a four-byte budget and structural/lexical choices for a 1,000-expansion budget.
Families include the complete `stmt` grammar, SELECT, INSERT, UPDATE, DELETE,
CREATE TABLE, ALTER TABLE, DROP, TRUNCATE, MERGE, COPY, VIEW, DO and CREATE DOMAIN.

| Target | Command | Contract |
| --- | --- | --- |
| classify | `composer fuzz:robustness:classify` | Classification accepts generated input without an unexpected exception and is deterministic for the same SQL. |
| rewrite | `composer fuzz:robustness:rewrite` | Each accepted plan retains its classification, nonempty SQL, write/mutation relationship and all explicit TRUNCATE targets. |
| full | `composer fuzz:robustness` | The rewrite contract plus applying its mutation with an empty result set, committing rewrite state and retaining valid shadow table keys. |

Rewriting creates a fresh catalog, shadow store and rewriter for every input.
Each plan is built once. `UnsupportedSqlException` and `UnknownSchemaException`
are the only accepted rewrite failures: ZTD supports a SQL subset, and generated
identifiers do not necessarily exist in the fixture catalog. Every other failure
reaches PHP-Fuzzer, with the grammar version, input bytes and generated SQL printed
to stderr. Classification accepts no exceptions.

SQLFaker generates grammar-conforming statements. As documented in SQLFaker PR
#313, it does not coordinate schemas, types or result rows across statements.
The full target exercises the zero-result mutation path; it does not establish
that an arbitrary generated query would return zero rows. These are in-process
robustness checks. Native PostgreSQL syntax validation belongs to SQLFaker's
`fuzz:pg` target; database result equivalence is covered by adapter integration
tests with explicit schemas and data.

## Run and replay

Install the package's locked dependencies on PHP 8.5 with `composer install`.
For other maintained PHP versions, select the corresponding graph as described
in [CONTRIBUTING.md](../CONTRIBUTING.md). PHP-Fuzzer needs `pcntl`.

Run a bounded target from this package directory:

```sh
composer fuzz:robustness:rewrite -- --max-runs=10000
```

The corpus starts empty and evolves through PHP-Fuzzer. CI caches each target's
corpus independently. Corpus version 3 uses the byte-plan format above; previous
hashed-seed corpus entries have different meanings and must be regenerated.

To reproduce a CI crash, check out the failing commit, install its locked graph,
and download the crash input and log artifacts. Use the script named by that job:

```sh
vendor/bin/php-fuzzer run-single fuzz/fuzz_robustness_rewrite.php crash-<hash>
```

Use `fuzz_robustness_classify.php` for classify and `fuzz_robustness.php` for full.
Keep the input binary unchanged. A minimized input is still tied to its grammar,
SQLFaker revision and target; changing these can change the generated SQL.

## Grammar coverage

`GrammarCoverage` records each target separately in `fuzz/coverage/<target>/`.
SQLFaker owns the inventory, revision checks, periodic persistence (every 100
generations) and shutdown flush. CI restores and saves this directory separately
from the PHP-Fuzzer corpus and uploads coverage with execution logs. A grammar or
generator revision change gets a separate coverage history.

Set `SQLFAKER_COVERAGE=0` to disable measurement during diagnosis. Recording does
not change the compiled plan or generated SQL. The seeded PHPUnit property tests
in this directory remain available through `composer test:fuzz`; native targets
provide the evolving coverage-guided search.
