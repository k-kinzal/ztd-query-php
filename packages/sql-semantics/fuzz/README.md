# Semantic properties over the complete SQL grammar

Every SQL statement that `sql-faker` generates for a declared release must be
analyzable into semantic structure. The targets use the same generators as
`sql-parser`: `BytePlanCompiler`, an unrestricted nonempty statement plan,
default expansion settings, and an 80,004-byte maximum input. No statements,
productions, expressions, or exceptions are excluded.

The MySQL roots are `statement` for 5.6/5.7 and
`simple_statement_or_begin` for 8.0–9.1; PostgreSQL uses `stmt`; SQLite uses
`cmd`. All eleven releases in `docs/support.md` run in CI.

## Properties

`SemanticsTarget` calls the public `Binder::analyze()` API against an empty
catalog, checks the entire returned graph, and repeats analysis to test
determinism. Diagnostic-free results must equal strict `bind()` results.
`GraphProperties` checks ordered output positions, relation scope ownership,
column declaration membership, dialect-consistent declaration and expression types, and explicit
unresolved references. It visits CTEs, branches, relation queries, scalar
subqueries, assignments, VALUES rows, predicates, ordering, and clause
expressions. Shared graph nodes are checked once.

An empty catalog is deliberate: sql-faker generates syntax, including unknown
names and semantically inconsistent combinations. Analysis must return all the
structure and report such problems as diagnostics. An `unknown-table`
diagnostic cannot stand in for a statement, and neither an implementation
exception nor an `invalid-structure` exception is allowed. This exercises the
same semantic lowering used with a complete catalog; unit and regression tests
check the resolved facts and DDL constraints too.

## Run every release

```bash
composer install
composer fuzz:smoke
php fuzz/run.php all 1000
```

The default smoke run performs 300 mutations per release, in addition to replay
of the accumulated corpus. Each release starts with 64 deterministic byte
inputs spanning different structural and lexical choices. Those inputs do not
restrict the generator or subsequent mutations. Corpus and grammar coverage
are retained under `fuzz/corpus/` and `fuzz/coverage/`.

```bash
MYSQL_VERSION=5.6.51 php fuzz/run.php mysql 10000
php fuzz/run.php pg 10000
php fuzz/run.php sqlite 10000
SQLFAKER_COVERAGE=0 php fuzz/run.php all 300
```

The per-input timeout is ten seconds because the property performs semantic
analysis twice and, when resolved, strict binding as well, under instrumentation.
Timeouts are failures. `run.php` also fails on PHP-Fuzzer's crash output: its
process exit code alone is insufficient. Logs, stderr, and binary crash inputs
are written to `build/fuzz/<dialect>-<release>/`.

## Reproduce a finding

```bash
MYSQL_VERSION=5.6.51 vendor/bin/php-fuzzer run-single \
  fuzz/fuzz_mysql_semantics.php build/fuzz/mysql-5.6.51/crash-<hash>.txt
```

Parser, semantic, and property exceptions report the grammar release, generated
SQL and input hex, with the original exception retained as the cause. CI artifacts include the
corpus too, so failures during corpus replay retain their binary reproducer. Keep every finding as a regression and fix the implementation;
do not add exception allowances or alter the generation plan to avoid it.
If a generator serialization bug is found, preserve the selected grammar
alternatives and their operands when repairing the emitted SQL. For example,
legacy MySQL joins use the same grouping repair as modern joins so their
ON/USING predicates stay attached to the generated operands.

PR/push jobs run 300 mutations per release, daily jobs 10,000, and manual jobs
accept a positive run count. CI saves the corpus and uploads logs, crash inputs,
and grammar coverage. Fuzzing supplies repeatable evidence for the property;
a finite run is not an exhaustive proof over infinitely many SQL strings.

## Semantic meaning and model construction

`StatementProperties` checks obligations imposed by the statement: INSERT must
retain input destinations, UPDATE must retain assignments, configuration commands
must retain named effects, and row predicates must not disappear. `GraphProperties`
also visits insertion destinations, assignments, conflict handlers, settings, and
bound declaration expressions. Constructor invariants run under the fuzzer too.

Every fuzz input additionally drives `SchemaProperties` against a known catalog
of the same database release. This independently checks reordered INSERT column
mapping, assigned values, predicate references, diagnostics for unknown storage
destinations and invalid PostgreSQL predicates, and expression edits that must
update lineage and reproduce a freshly bound graph. These companion statements
do not replace, filter, or constrain the SQL generated from the complete grammar.
They supplement the empty-catalog property with known semantic expectations.
