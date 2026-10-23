# Constructing semantic structures directly

The PHPUnit property checks whether SQL Semantics' public types and invariants
supply the information MemoryDB needs. It constructs statement and scalar values
without generating or parsing SQL first, then passes those same objects to MemoryDB.
SQL Faker and PHP-Fuzzer separately check the SQL-to-execution path against MySQL.

From `packages/mysql-memory` in a repository checkout:

```sh
composer test:property
MYSQL_MEMORY_PBT_SEED=example-seed MYSQL_MEMORY_PBT_SAMPLES=10 \
  vendor/bin/phpunit tests/Property/Structure/GeneratedStructuresTest.php
```

The default seed is `all-model-roots`, with one sample of every public root. Increase
`MYSQL_MEMORY_PBT_SAMPLES` or change `MYSQL_MEMORY_PBT_SEED` to explore other values.
Each PHPUnit data set names its root and sample; failures include the seed and
constructor-choice index. Reuse the same revision and environment values, and select
that data set with PHPUnit's `--filter`, to reproduce a counterexample.

## Generation

The generator discovers concrete statement and scalar types from the reviewed
semantic value inventories. It interprets constructor PHP types and PHPDoc
collection types, chooses enum cases and constructs fresh nested objects. A
readonly property's declared collection type takes precedence over the constructor
parameter's broader input type.

Constructor invariants are the only reason to reject an argument combination and
try another. The search retains completed dependencies and resamples arguments for
the same root within a shared 10,000-attempt cap. Depth, primitive values and
collection lengths are bounded. Exhaustion and unsupported generator metadata fail
the property; an accepted structure is never discarded because execution fails.

## Direct execution

Before execution, the property finds a language profile under which rendering and
lowering preserve the constructed structure. This selects its grammar release and
lexical modes, without using database execution as a filter. No matching profile is
a counterexample. Execution receives the original object, not the lowered copy.

MemoryDB derives an `Operation` against a fresh session and consumes its statement
and facts. Standalone scalars receive their required query embedding; parameters
receive explicit NULL bindings. Neither input SQL nor a parser AST is supplied to
the executor.

After generation, PHP exceptions, constructor and publication invariant violations,
and missing implementation fail the property. An ordinary `SqlError` is an execution outcome,
not an internal crash. Error 1105 (`UnknownError`) and 1235 (`NotSupportedYet`)
remain failures, except when explicitly requested by SQL SIGNAL. There is no
per-statement or per-error allowlist, and an input is never discarded or replaced
because it produces an SQL error.

This boundary follows [SQL Semantics' guarantees](../../sql-semantics/docs/guarantees.md):
a well-formed model is not a statement guaranteed to succeed. Missing tables,
invalid JSON values, NULL limit bindings and malformed SQL inside PREPARE can all
be represented without violating the model's types. This property checks that the
consumer handles the values; SQL Faker fuzzing checks whether its successful
results and SQL errors agree with MySQL. Neither check substitutes for the other.

This property requires no native comparison or separate database-history test.
Counterexamples are retained and investigated through this PHPUnit entry point and
the SQL Faker PHP-Fuzzer target. The [validation contract](acceptance.md) describes
the three checks and the ordinary package checks that accompany them.
