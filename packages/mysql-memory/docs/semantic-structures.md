# Constructing semantic structures directly

SQL Faker exercises the SQL-to-execution path. The direct structure campaign exercises a different input: objects built using SQL Semantics' public constructors, without first generating or parsing SQL. It tests whether the semantic model's types and invariants are sufficient for its consumer. It does not measure grammar coverage; SQL Faker owns that measure.

From `packages/mysql-memory` in a repository checkout:

```sh
composer test:property
composer fuzz:structures -- 100
composer fuzz:structures -- 100 example-seed
```

The campaign discovers concrete types from the reviewed semantic value inventories. Its generator interprets native constructor types and PHPDoc collection types, chooses enum cases, and builds fresh nested objects. A readonly field's declared `@var` type takes precedence over the corresponding constructor parameter: for example, a constructor may accept a broad list and check that every element is a select item before retaining it as a `non-empty-list<SelectExpression|Star|TableWildcard>`. Constructor invariant violations cause another construction attempt. The generator has finite depth and attempt budgets; exhaustion is reported as an incomplete trial and never as a successful execution. Primitive values and collection lengths are bounded, so passing a finite campaign is evidence about the sampled inputs.

Before choosing a nested type, the generator checks whether its constructor types admit a value within the remaining depth. Nullable values, empty collections and default arguments provide terminating alternatives. Each subtree also has a finite attempt budget, so an invalid nested combination cannot spend the entire trial retrying itself. These bounds inspect types only; they do not inspect SQL diagnostics or execution outcomes. A selected root is never replaced by a different root when generation fails.

The generated root is a MySQL statement or an ordinary expression placed in a SELECT item. The runner derives its semantic facts against a fresh in-memory session and sends the result directly to statement execution. The generator has no list of supported SQL commands and does not use execution success or semantic diagnostics to select inputs.

Direct submission reports the semantic warnings of a newly submitted statement, including deprecations, just as SQL submission does. Only execution of a previously created stored-program statement suppresses those initial warnings. A separate inventory check requires an explicit command route for every public MySQL statement variant. Internal parser entry points and debug-only commands produce their native client rejection; they do not fall through to a generic missing implementation.

For MySQL 5.6 and 5.7, `UpgradeDatabaseName` consumes the typed database name. Its command moves table declarations and data to the upgraded name, retains view and trigger references, and removes stored routines and events. SQL Semantics supplies the 5.7 deprecation fact. Differential histories against isolated servers check the resulting rows, metadata, warnings and errors, including invalid references after the move.

Every failure record includes its seed index, concrete root class, exception, and constructor rejection counts. To replay one trial from the same revision, give its index as the final argument:

```sh
php fuzz/structures.php 1 example-seed - 8.4.7 22
```

The arguments are `COUNT [SEED] [CLASS|-|@each] [RELEASE] [START]`. Omitting the seed chooses a random seed and prints it before the first trial. An explicit class selects a root from the same discovered inventory. `@each` visits the sorted root inventory in order, wrapping after its end; the initial record reports the number of root types. Use at least that many trials to exercise construction of every root type. This enumerates model types and does not measure SQL Faker grammar coverage. Reproduction requires the same revision because changes to types and constructors change deterministic choices.

```sh
composer fuzz:structures -- 254 all-model-roots @each
```

Exit status 0 requires every trial to execute successfully. Status 1 reports an execution or structural failure. Status 2 reports an incomplete generation campaign, including an exhausted budget or unsupported constructor metadata. SQL errors are recorded separately, but currently also prevent success: the runner does not yet establish whether an individual error depends on execution state. A missing table and a state-independent model defect must not silently become the same successful outcome.

The unrestricted campaign currently finds counterexamples. The `property` test suite contains bounded regression properties for repaired model domains, including parameter binding across nested scopes, decoded strings across SQL escape modes, and system-variable assignments followed by reads in the same session. `SystemTarget` is an assignment operand; only `SystemVariable` is a general read expression, and its scope excludes `PERSIST` and `PERSIST_ONLY`. Query and composite-expression constructors also reject known combinations of features that cannot coexist in any supported grammar generation, including WITH combined with PROCEDURE ANALYSE anywhere in its operand graph.

Every MySQL statement constructor checks the composition of its nested structures before returning. An inventory-driven architecture test checks that this is the final action of each constructor and that no earlier return bypasses it. The same composition check runs in composite expressions. The check rejects contradictory uses of `HIGH_NOT_PRECEDENCE` and `PIPES_AS_CONCAT`, including across separate select items, assignments, and subqueries. It also rejects the known combinations of pre-8.0 features with features introduced in 8.0 or later. This is a structural consistency check, with no access to a memory database or execution results; its feature catalogue is not a complete language-availability specification. A consistent structure can still require a different release or SQL mode from the session used by a trial.

The bounded lexical-mode property constructs expressions and selects the matching session mode before execution, then checks their results over the 8.0, 8.4 and 9.1 releases. XOR has no symbolic spelling. A modern JSON table or ODBC escape cannot be used as a single parenthesized table factor; parentheses around an unaliased derived query belong to the query. KEY subpartitioning requires explicit columns even though top-level KEY partitioning permits an empty list.

`EXPLAIN` accepts the `Explainable` statement role: queries, INSERT/REPLACE, UPDATE and DELETE. Condition numbers use the same literal/variable/name domain as condition information. PREPARE sources, replication credentials and log file names require quoted text, and CREATE/DROP INDEX reject duplicate ALGORITHM or LOCK clauses. A select item with an observable spelling retains its `spellingProfile` (grammar release and lexical interpretation). Construction checks that each spelling slot is one complete token, that the layout has no leading or trailing trivia, and that its tokens have the same grammatical meanings as the rendered expression. A self-named item or a canonical spelling keeps no layout. This validation uses temporary parser results and retains no source SQL or AST. SQL lowering supplies the original language profile; direct construction defaults to MySQL 8.4 when a layout is supplied without an explicit profile. All observable spellings in a composed statement must declare a compatible profile, and their lexical interpretation must agree with operators elsewhere in the graph. Execution must use that profile. The direct layout property checks values and column names across five releases and different lexical modes.

Passing these bounded regressions does not establish the property for the whole semantic model. Known outstanding categories include release-dependent forms, literal-only positions, and structures that produce state-independent SQL errors. Quantified comparisons reject null-safe equality; native MySQL rejects `<=> ALL/ANY/SOME` as syntax. A set-chain operand rejects unparenthesized ORDER BY, INTO and PROCEDURE ANALYSE. The MySQL 5.6 left-operand LIMIT form remains constructible, but cannot coexist with modern-only forms such as EXCEPT. SQL_CACHE cannot occur in the query blocks of an expression subquery; parentheses around a top-level legacy query remain allowed. Derived tables use a separate query grammar and retain invalid cache placement as a semantic diagnostic, including when nested inside an expression subquery.

These failures remain visible in the campaign until the corresponding type or invariant is addressed. A SQL rejection cannot be counted as a successful trial merely because it has a server error number; state dependence must be demonstrated separately.
