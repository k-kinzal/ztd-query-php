# SQL Semantics and MySQL Memory

## Purpose

The purpose of this work is to check whether `sql-semantics` provides appropriate SQL types and invariants. We are building an executable in-memory MySQL database so that the semantic model must support actual execution, rather than merely describing SQL in a way that looks plausible.

Supporting multiple MySQL versions is a requirement of `mysql-memory`, but validating the semantic model is the main objective. The project targets PHP 8.1+ and MySQL 5.6–9.1 for these packages.

The intended result is a SQL model that preserves the information needed to reconstruct SQL and whose values can be consumed by an implementation of MySQL. That implementation is checked against the real server. The work must remain a simple implementation of the three checks below, following the conventions of the other `sql-*` packages.

This memo records the agreed direction and the remaining work on [PR #665](https://github.com/k-kinzal/ztd-query-php/pull/665). It is not a claim that these checks currently pass.

## Build the verification foundation before expanding coverage

For work with this many statements, clauses, types, and version differences, the foundation is a verification mechanism that can check those variations consistently. Establishing that mechanism is the central engineering task. Broad implementation must proceed on top of it, with counterexamples directing corrections to the model and implementation.

The mechanism itself must remain simple in operation. Use `sql-faker`'s fuzz approach as a reference: the input generation, operation under test, comparison or assertion, and failure must be easy to follow. The three checks below should expose these steps directly, using the existing tools. The size of the SQL space does not justify a private framework or an expanding collection of special verification paths.

First make the three checks work end to end for a small concrete part of the model. Then expand the SQL types, generators, and supported versions through those same verification paths. Each addition must be exercised by the applicable properties, and failures must remain visible as counterexamples. Do not accumulate a broad implementation and postpone checking whether its verification actually tests the intended boundaries.

Keep the verification procedure consistent while preserving the distinct meanings and invariants of the SQL types it checks. A shared property is not a reason to force different SQL concepts into a shared semantic representation. Simplicity means being able to see what is generated, what consumes it, what is asserted, and why a failure is reported.

## The three checks

### 1. SQL Semantics: reconstruct SQL from its meaning

```text
sql-faker SQL
    -> sql-semantics analysis
    -> structured, meaning-bearing SQL values
    -> SQL rendered from those values

Compact(original SQL) == Compact(rendered SQL)
```

Use PHPFuzzer to search for counterexamples to this property. Both sides use `sql-formatter`'s Compact format with matching dialect, version, and relevant settings.

The renderer must reconstruct SQL entirely from the typed semantic structure. Storing the original SQL string and returning it is explicitly prohibited. The same prohibition applies to retaining SQL fragments for replay, extracting text through source spans to fill gaps, or falling back to the parser AST. Generate every clause and expression from its semantic fields. Information required by the equality must be represented in the model.

The fuzz harness retains the original SQL as the comparison input; that does not authorize the semantic renderer to use it as an output source. Formatting a retained SQL string and returning it would make the check pass without establishing that the semantic model preserved anything. Removing inconvenient differences from the comparison is also prohibited.

This checks that analysis and reconstruction preserve the SQL information required by the comparison. It does not establish that the model can be executed correctly; that is why the other two checks are needed.

### 2. MySQL Memory: execute generated semantic values

```text
Eris generators for SQL Semantics types
    -> generated semantic statement values
    -> any planning and execution derived from those values
    -> execution assertions
```

Use PHPUnit and Eris to search for counterexamples to the executability of the generated semantic values. The generators construct the types produced by `sql-semantics` and `sql-semantics-mysql` directly.

The property must run on Eris's actual generation, composition, and shrinking mechanisms. Per-type classes implementing `Eris\Generator` must compose or delegate to Eris generators and participate in Eris's generation and shrinking protocol. Eris must drive the trials and counterexample search. Merely naming a class `Generator`, implementing an interface without using its mechanisms, or replacing Eris with an independent random sampler, manual sample loop, or private generation framework does not meet this requirement. The earlier independent-generator workaround is rejected.

The central requirement is that the semantic types expose all SQL meaning needed by MySQL Memory. Every downstream phase, including plan construction if present, must obtain that information from those types. Planning, dispatch, and execution must not recover or supplement semantic information by inspecting the parser AST, reading retained SQL or source spans, or reparsing rendered SQL. A generated value with no original AST or SQL string must still provide the semantic information required by those phases.

If a plan or executor cannot be built from the semantic values because required information is absent, that is a defect to expose and fix in the semantic types, invariants, or analysis. Filling the gap from an AST or SQL string hides precisely the failure this PBT is intended to find. Execution still uses database state and bound parameter values; these are execution inputs, not an alternative source of missing SQL meaning.

The generated values must enter the execution phase that consumes the result of semantic analysis. Rendering them to SQL and analyzing that SQL again would not check this boundary directly. Checking only that construction succeeds or that an object is an instance of its declared class is not this property.

Generation must account for database state in two stages:

1. Generate semantic values without a preceding generated database definition. Distinguish an ordinary SQL error caused by absent state from a broken type, violated invariant, or implementation failure. Do not treat an arbitrary caught exception as a successful trial.
2. Generate prerequisites and dependent statements together. For example, generate a `CREATE TABLE`, then generate an `INSERT` against that generated definition, execute both, and require the applicable successful outcome and resulting state.

The second stage must preserve the relationship between the definition and dependent statements during generation and shrinking. Constructing or repairing the semantic statement inside `then()` is not generator composition. The callback sets up execution, executes the generated input, and asserts the property.

These are two stages of the same PBT approach, not additional test frameworks.

### 3. MySQL Memory: compare execution with the real server

```text
                           -> real MySQL via Testcontainers -> outcome
sql-faker SQL and setup ---|
                           -> mysql-memory                 -> outcome

Compare the two outcomes.
```

Use PHPFuzzer to search for counterexamples. Both databases receive the same SQL and equivalent initial state and settings, with matching MySQL versions. The real server is the behavioral reference for the in-memory implementation.

Compare deterministic observable results, including relevant result metadata, rows, completion information, and SQL errors. A matching pair of legitimate SQL errors is different from a swallowed implementation failure. Unsupported execution paths and unexpected exceptions must remain visible.

Only values that inherently depend on the execution environment are exempt from exact value equality. For those values, check the expected output structure and value types. For example, physical engine statistics cannot have identical values in an in-memory implementation and a real server, but their output cannot simply be omitted. Keep the exception specific to the environment-dependent fields; continue comparing the deterministic content.

Do not add a dedicated Diff test suite. The database comparison belongs to this fuzz target.

## What the checks establish together

The first check exposes information lost or misrepresented during semantic analysis and rendering. The second exposes semantic types or invariants that do not support execution, as well as defects in the executor. The third tests the executor against actual MySQL behavior, so that its agreement with the semantic model is not the only reference.

A counterexample must be investigated to identify the incorrect model, generator, renderer, executor, or comparison. Fix that cause rather than narrowing generation, dropping output, or suppressing the failure merely to make the run pass.

PBT and fuzzing are property checks and counterexample searches. Reports must state the versions, corpus, and execution budget actually exercised. A passing run must not be described as broader coverage than it provides.

## Why bypasses, incorrect abstraction, and unnecessary complexity are rejected

These requirements protect the validity of the checks. Their repetition responds to recurring implementations that made a check appear satisfied while bypassing the property it was meant to exercise.

- Replaying retained SQL lets round-trip equality pass even when the semantic structure is missing information.
- Supplementing a plan from an AST or SQL text lets execution succeed even when the semantic types are insufficient.
- Running independent samplers behind generator-shaped classes bypasses the Eris generation, composition, and shrinking behavior required for the PBT.
- Catching failures, skipping difficult inputs, or discarding deterministic output differences removes the counterexamples the work exists to find.

Incorrect abstraction has the same effect on the model itself. Combining different SQL meanings because their code looks alike moves distinctions into flags, unrelated containers, or later special cases. Types then permit combinations that should be ruled out, and consumers must reconstruct the meaning that the types were supposed to express. Fewer lines of code do not justify losing a SQL distinction or weakening an invariant. Repeating straightforward code is acceptable when the SQL concepts are different.

Unnecessary layers and private frameworks make it harder to follow where information comes from, which invariants hold, and whether the intended execution path was reached. They also multiply places where missing information can be patched over. Keep each of the three checks direct and readable, use Eris and the existing package conventions, and add an abstraction only when its SQL meaning or concrete execution responsibility justifies it.

The required response to a failure is to identify and repair its cause while preserving the property under test. A green result obtained through one of these bypasses provides no evidence for the intended boundary and cannot count as completion.

## Required SQL Semantics design

### SQL statements are the analysis result

`analyze()` must declare an interface as its return type. Its returned object is a concrete SQL statement type implementing that interface, such as `InsertStatement`. `Statement` is the current illustrative interface name; the essential requirement is the SQL meaning and the interface/concrete-statement relationship.

Do not return a generic concrete `Operation` containing a statement, an `AnalysisContext`, `Facts`, and a `SourceMap`. Renaming that aggregate does not fix the design.

The semantic model consists of structured SQL types with enforced invariants. Its vocabulary and boundaries must come from SQL. Share a type because it represents the same SQL concept, not because two implementations happen to have similar fields or code. In particular, an INSERT and a REPLACE must not become one statement merely through a `replace` flag.

### Meaning belongs to the relevant SQL element

Names, resolved references, SQL data types, nullability, and other necessary semantic information belong to the statement, clause, expression, or reference they describe. They must be accessible through that structure, rather than requiring a separate `Facts` lookup owned by a generic root.

Schema declarations, SQL modes, and version information may be needed as analysis inputs. Do not retain the entire analysis input environment as an `AnalysisContext` in the published result. Represent the resulting SQL meaning at its appropriate location.

Distinguish the type of an expression from the type of its insertion destination. For example, in an INSERT of a string literal into an integer column, the literal's type and the destination column's type are separate information; execution may perform a conversion.

Source information must also be reachable through the SQL structure:

- A source span identifies the relevant location in the input SQL and belongs to the corresponding statement, clause, expression, or reference.
- A resolved reference identifies its originating table or column. A source span alone does not provide this information.
- Directly generated semantic values need not have an original SQL source span. Their semantic information must still be available without one.

If missing declarations prevent resolution, represent that situation explicitly. Do not invent resolved information or hide the absence in an unrelated container.

### The INSERT discussion is not a completed design

The `InsertStatement`, `InsertValues`, and `ValuesClause` examples discussed so far illustrate ownership and SQL alternatives. They have not been implemented as a replacement for the current API or accepted as a complete semantic model.

Checking VALUES row widths alone is insufficient. The structure must also expose the information associated with its values, their SQL types, their references, and their sources. The proposed `Expression` interface with type, nullability, and source accessors is also only an incomplete illustration, not a finalized contract.

The next concrete example must show the complete path from the statement to the relevant semantic information, how its invariants are enforced, how SQL is reconstructed, and how the executor consumes it. Establish that example before spreading an incomplete design across the package.

## Generators and properties are separate concerns

Every SQL semantic model type defined by `sql-semantics` and `sql-semantics-mysql`, including the types nested within statements, must have a corresponding generator. Generator namespaces and file placement must consistently mirror the semantic types, distinguishing the shared and MySQL-specific models.

Generators implement `Eris\Generator` and use Eris composition for their dependencies, generation, and shrinking, as required by check 2. Their use by the Eris property runner must be real, not an interface or naming convention around an independent sampler. Prefer ordinary, readable per-type implementations with delegates. Generic constructor sampling through `Samples` or `Inhabitant` is rejected.

Add a PHPStan custom rule under `rules/` to check semantic-type-to-generator coverage. The earlier proposal to require one property class per semantic type is superseded: generator coverage and property granularity are different questions.

Generators must also be used. A separate configurable PHPStan custom rule checks unused classes, with the selected classes specified in NEON. Existing third-party method-level unused checks do not provide the requested class-level check. A class must not count as used merely because it implements an interface. Generator existence or usage alone does not demonstrate that an execution property is checked.

Properties are organized around the behavior being verified. Executing a generated CREATE TABLE is a statement-execution property; placing it in `ColumnNameProperties` does not make it a property of `ColumnName` in isolation. The final property grouping remains to be determined from the corrected model and executable properties.

Property conventions:

- Put properties in `tests/Property`.
- Name classes `<Name>Properties` and test methods `satisfies<Property>`.
- Every property class must contain at least one such property method.
- Do not define traits or helpers under `tests/Property`.
- Do not reference `fuzz/` from properties.
- Do not substitute manually enumerated samples or `assertInstanceOf` loops for generated execution checks.

The generators in this work cover SQL Semantics values. Creating unrelated generators for MySQL Memory implementation classes does not satisfy that requirement. Execution setup and state coordination must not become a substitute semantic model owned by MySQL Memory.

## Fuzz execution and acceptance

Follow the established fuzz organization and invocation conventions of the other `sql-*` packages, including `sql-faker`. Fuzz is an independent top-level directory within its package, and its target must actually perform the named property check.

Support corpus selection so the same target can run with the grammar-coverage seeds supplied by `sql-faker` or run without those seeds. Required evidence includes:

1. The seeds representing 100% of the applicable SQL Faker grammar coverage can be supplied and all pass the target.
2. The target runs without supplied seeds for 15 minutes without finding a failing case.
3. The report identifies the releases and settings exercised; a run against one release does not establish multi-version behavior.

Grammar coverage, semantic generator coverage, and the properties checked are separate measurements. Do not replace one with another or silently omit problematic productions to claim completion.

## Package conventions and rejected workarounds

The generator coverage, generated-value execution, and real-server fuzz comparison are the special quality requirements of `mysql-memory`. All other quality and organization rules follow the existing `sql-*` packages and the toolkit. No additional special-purpose validation framework is required.

- Keep `mysql-memory` a library. Its public use is through PDO or mysqli, with a database startup API such as `MemoryDatabase::start()` that explicitly selects a MySQL version. Direct semantic execution in the internal PBT is a separate testing boundary, not the public library API.
- Model and test version-specific behavior explicitly. A version parameter that does not affect semantics and execution is not multi-version support.
- Remove the rejected `docs/`, `resources/`, and `bin/` directories from `mysql-memory`. Relocating unexplained data or machinery under a different name does not resolve the design problem.
- Do not use `tests/Connection`, `tests/Result`, `tests/Support`, or the previously introduced `tests/Fake` for this work.
- Use meaningful test categories such as `Property`, `Integration`, `Unit`, `E2E`, and `Contract`. Purpose-specific infrastructure such as `Generator` and `Container` is acceptable when needed.
- Integration and E2E tests must identify the behavior or scenario they verify. A generic `MemoryDatabaseTest` name does not explain that scenario. Tests under `Unit` are not prohibited from using processes or PDO solely because of that directory name.
- Do not place fuzz under `tests/Integration/Fuzz` or conceal infrastructure behind misleading target names and directories. The previously introduced `fuzz/Structure`, `fuzz/response.php`, and `fuzz/fuzz_mysql.php` organization was rejected; align the target with the other packages' conventions.
- Apply the toolkit constraints rather than exploiting gaps in its current detection. Do not add helpers, fake properties, or unused generators to satisfy a superficial file-count check.

This explicitly requested root `MEMO.md` records the work; it does not reintroduce a `mysql-memory/docs` directory.

## Current state and next work

The current implementation does not yet meet this design. In the working tree at the time of this memo:

- [Semantics::analyze()](packages/sql-semantics/src/Facade/Semantics.php) still returns the concrete [Operation](packages/sql-semantics/src/Statement/Operation.php) aggregate, which owns the analysis context, statement, facts, and source map.
- The current [OperationGenerator](packages/mysql-memory/tests/Generator/Semantics/Statement/OperationGenerator.php) composes only CREATE TABLE statements and wraps them in that aggregate.
- [ColumnNameProperties](packages/mysql-memory/tests/Property/MySql/Statement/Name/ColumnNameProperties.php) executes that CREATE TABLE composition. Its class name does not describe the actual property boundary.
- Generator-coverage and unused-class rule changes exist in the working tree, but they do not establish completion of the semantic redesign or execution properties.
- The recent INSERT and Expression examples are discussion examples only. They are not evidence of an implemented, complete replacement.

The implementation order is to establish the following paths for one concrete example before expanding coverage:

1. Establish one concrete INSERT semantic model, following the MySQL grammar, with its actual types, references, source information, and invariants visible throughout the structure. Resolve the incomplete parts of the example before replicating it.
2. Integrate that model with analysis and SQL reconstruction so that analysis returns the concrete statement through the result interface. Exercise the Compact round-trip property through the existing fuzz approach. Replace the rejected aggregate as the migration proceeds, rather than preserving it behind renamed wrappers.
3. Implement the corresponding generators using Eris's generation, composition, and shrinking mechanisms, including dependent state generation. Keep generator coverage and unused-class checks aligned with the actual model.
4. Make MySQL Memory derive any plans and subsequent execution from the generated semantic values, without AST or SQL-text fallback, and check the example's execution properties.
5. Run the SQL Faker comparison against matching real MySQL containers. Investigate mismatches, retaining only the specifically justified treatment of environment-dependent values.
6. Extend the statement forms, semantic types, generators, and version-specific behavior through these established checks. Keep their verification paths simple and their properties intact as coverage grows.
7. Complete the supported-version work, required seed replay, seedless 15-minute fuzz run, PBT, and ordinary package checks. Report actual results and remaining counterexamples.

The work is complete only when these mechanisms exercise the intended boundaries and provide the required passing evidence. A collection of constructors, files named `Properties`, a successful render alone, or suppressed execution differences is not that result.

## Implementation plan and acceptance ledger

Work continues in this worktree on `feat/mysql-memory`, through PR #665. The existing
uncommitted generator and rule changes are inputs to this work, not evidence of
completion. None of the milestones below is complete yet.

1. **Establish the INSERT boundary.** Start with a VALUES insertion of character
   literals into declared integer columns. An analyzed statement exposes its
   insertion target, explicit or implicit destination columns, VALUES rows,
   modifiers and optional source locations. A destination reference identifies
   its table and column declaration; the expression keeps its own character type,
   collation and nullability. Missing declarations are explicit unresolved
   references. Constructor checks cover list membership, row widths and ownership
   of resolved columns, without confusing conversion errors with invalid model
   construction. INSERT and REPLACE have distinct statement types.
2. **Publish and render concrete statements.** Analysis returns an interface with
   concrete SQL statement implementations. Binding uses the analysis environment
   as input only. Local expression and reference information is retained in the
   corresponding values; neither an analysis environment nor a root facts/source
   index is the published result. Rendering traverses these values. The existing
   PHPFuzzer target compares both SQL strings with the same Compact formatter and
   parser settings; it must report differences even when reanalysis produces
   equal structures. Migrate other statements after the first complete path,
   removing the old Operation API and its consumers as that migration proceeds.
3. **Execute the same values.** The session's execution entry point consumes the
   published statement interface. Planning may translate those semantic fields
   into execution data, but must not parse SQL or obtain missing information from
   an AST. Check both absent-state SQL errors and successful inserts after a
   generated CREATE TABLE, including conversion, affected rows and stored data.
4. **Compose Eris generators.** Per-type generators delegate to Eris. Compose
   prerequisites and writes before `then()`; a shared generated input must rebuild
   both sides together when shrinking. Check shrinking with an intentionally
   failing property so broken relationships and nontermination are observable.
   Group properties by execution behavior and maintain the independent generator
   coverage and configurable unused-class checks.
5. **Compare the first path with MySQL.** Feed equivalent setup and SQL to the
   existing PHPFuzzer execution target and matching Testcontainers release. Keep
   result metadata, rows, completion data, errors, warnings and resulting state in
   the comparison. Fix counterexamples in their responsible layer.
6. **Expand through the established paths.** Migrate the remaining SQL statements,
   nested semantic types, generators and consumers. Exercise release-dependent
   grammar, types, resolution and execution on 5.6.51, 5.7.44, 8.0.44, 8.1.0,
   8.2.0, 8.3.0, 8.4.7, 9.0.1 and 9.1.0. Do not mark constructor sampling,
   interface-only generators or unsupported execution as successful coverage.
7. **Record acceptance evidence and deliver.** Run ordinary package checks and
   PBT, replay all supplied applicable SQL Faker grammar seeds, and run each
   required target from an empty corpus for 900 seconds. Record the exact source
   revision, release, settings, seed inventory, execution count, duration and
   remaining findings. Update PR #665 to describe the resulting implementation
   and only the evidence actually obtained.

The first model deliberately has a small execution surface. Other existing paths
remain migration work, not silently excluded acceptance criteria. The final
acceptance still requires every item above and the requirements in this memo.


### First INSERT checkpoint (2026-10-25)

The first migrated form is an INSERT of character literals or empty default rows.
`analyze()` returns `AnalyzedStatement`; this form is a concrete `InsertStatement`
with `InsertTarget`, resolved destination columns, typed expressions, a
`ValuesClause`, and optional byte spans on the corresponding elements. Literal
and destination types remain separate. INTO presence and VALUE/VALUES spelling
are retained fields. Rendering and execution do not consult source text. Existing
nonmigrated forms still use `Operation`: implementing the result interface on that
aggregate is a temporary compatibility step, not fulfillment of its removal.

The executor accepts the new result directly. The first implementation projects
its fields into the existing row writer for conversion, defaults, constraints and
transaction rollback. This projection neither creates an Operation nor analyzes
SQL. Native and SQL PREPARE retain the concrete INSERT and execute its values;
an integration regression exercises the native PDO protocol, and a unit test
executes a prepared value with an invalid SQL request label.

Per-type Eris delegates construct the new values. The dependent property composes
a CREATE TABLE generator and VALUES generator before `then()`, binds the INSERT
to the generated declaration in `map()`, and executes both values directly.
A deliberately false multirow assertion demonstrates actual Eris shrinking to
two zero-valued rows while checking table/column identity and executing each
candidate. Source metadata varies independently of expression values. The
intermediate scenario-generator class was removed; composition uses Eris directly.

Evidence collected on PHP 8.5.8 / PHPUnit 10.5.64:

- The latest INSERT execution properties and statement integration tests pass:
  22 tests, 14,569 assertions, `ERIS_SEED=20261025`. The two execution properties
  run 100 trials each for every supported MySQL grammar release (5.6.51 through
  9.1.0). This does not establish execution of every statement or every release's
  native server.
- The core result/expression/span/platform/equivalence regressions pass: 28 tests,
  49 assertions. The INSERT semantic/publication/literal regressions pass: 63
  tests, 160 assertions. The preparation and row-write regressions pass: 14 tests,
  60 assertions.
- Targeted PHPStan runs for the new semantic values, tests and Eris generators
  pass. An intermediate complete mysql-memory audit reported 7,474 errors,
  including 1,132 missing generators before four more generators were added.
  The complete package checks remain an acceptance failure; these diagnostics
  are migration work and have not been suppressed.
- Both existing PHPFuzzer targets pass a focused six-input INSERT corpus on
  MySQL 8.4.7. Native comparison passes with PDO emulation enabled and disabled,
  after fixing the latter's Operation-only preparation boundary. The five
  supplied grammar seeds are `simple_statement-56.txt`, `opt_INTO-1.txt`,
  `value_or_values-1.txt`, `values_list-0.txt`, and
  `insert_from_constructor-1.txt`. The sixth, generated by BytePlanEncoder, is
  `INSERT _sqlfaker_identifier VALUE ( '42' )`, encoded as
  `2f0000000001380000020033003100000000000000000000000000000000010000000100010000000a00040010000e000600000000000000000000`.
  These are focused evidence, not the required complete corpus.
- The common native/memory fixture now includes SQL Faker's canonical identifier
  as an INT destination. Generated writes therefore reach conversion and stored
  data instead of always failing on an absent table. Table snapshots include it;
  the existing clock contract tracks its setup too.

Outstanding counterexamples and gates:

- The 2,181-input default MySQL 8.4.7 round-trip corpus does not pass. The first
  added-quotation mismatch for `_sqlfaker_identifier` was fixed. The next
  finding is `CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 1 DEFINITION 'text'`,
  rejected for missing NAME (input hex `05000000000011000000000002000000000000`).
  Grammar-valid semantic-error statements still need reconstructable SQL models.
- A seedless round-trip search found `BEGIN WORK` reconstructed as `BEGIN`
  (input hex `bbbbbbbbbbbbbbbbbb`) and was stopped after recording findings.
  The comparison remains exact Compact equality.
- A seedless native search hit the 60-second per-input timeout while starting a
  disposable reference container for RESTART. It was stopped and does not count
  as a 900-second passing search. Startup/lifecycle handling needs investigation.
- Complete Operation removal, all statement and expression migrations, the
  remaining per-type Eris generators, removal of legacy constructor samplers and
  property scaffolding, full version/profile corpus replay, and passing 900-second
  seedless runs remain required. No milestone-wide completion is claimed here.


### Reconstruction counterexamples after the INSERT checkpoint

The following MySQL 8.4.7 counterexamples now pass the existing round-trip target
and the native execution target, with their original inputs preserved:

- `BEGIN WORK` (`bbbbbbbbbbbbbbbbbb`): BEGIN, COMMIT and ROLLBACK retain WORK;
  ROLLBACK TO also retains whether SAVEPOINT was written.
- `CREATE OR REPLACE SPATIAL REFERENCE SYSTEM 1 DEFINITION 'text'`
  (`05000000000011000000000002000000000000`): the request retains its complete
  operands and computed intrinsic declaration problem. Execution still raises
  the missing-NAME error before catalog changes. A request is distinct from a
  successfully installed spatial declaration; syntactic constructor constraints
  remain enforced. DROP similarly retains an unmodifiable SRID and its refusal.
- `ALTER LOGFILE GROUP BINLOG ADD UNDOFILE 'text'`
  (`0600000000000300000001000000160000000000000000`): Name retains a typed
  delimiter choice when the codec's automatic choice would change it. Rendering
  encodes the decoded value with that delimiter; no token text is retained.
  Explicit choices must match in structural comparison. An unspecified choice
  on a generated name may acquire the codec's choice during publication; the
  independent Compact comparison remains exact.

The focused native replay uses PDO emulation and a 180-second per-input limit.
It is three inputs on 8.4.7, not a full corpus or a seedless acceptance run.
The added Name and NameQuote generators delegate to Eris. The CREATE property has
moved from ColumnNameProperties to CreateTableExecutionProperties. The three
statement properties pass 27 tests / 20,562 assertions with ERIS_SEED=20261025
across all nine grammar releases. The latest name/INSERT semantic tests pass
76 tests / 239 assertions, and core name/equivalence tests pass 14 / 26.

Full MySQL semantic unit testing initially reported 3 errors and 49 failures
out of 9,009 tests. These included expected-output assertions that required the
old lossy reconstruction, test fixtures with impossible empty bare tokens, and
legacy INSERT-facts tests using the migrated public result. Those tests are being
updated at their actual boundary; the complete package gates remain pending.

The next complete-corpus round-trip counterexample is
`ALTER EVENT _sqlfaker_identifier ON SCHEDULE EVERY ((((USER())))) SQL_TSI_WEEK`,
reconstructed with WEEK instead of SQL_TSI_WEEK. Input hex:
`150000000000010000000000000001000000000001000000070001000000000000000100000001000000000000000000000000`.
The generator, exact comparison, and full seed inventory remain unchanged.


The complete shared SQL Semantics unit suite now passes (596 tests / 1,245
assertions). MySQL's executable PHPDoc suite passes (1,111 / 1,111), and Deptrac
reports zero violations, skipped violations, uncovered dependencies, warnings or
errors. Targeted PHPStan for the new name generators and reconstruction sources
also passes. These checks do not replace the still-failing complete migration
and full-corpus acceptance gates.

The subsequent complete MySQL semantic unit run passes: 9,009 tests and 20,112
assertions, using four ParaTest workers with a 1 GiB PHP memory limit. The initial
128 MiB worker limit exhausted memory in SystemTablesTest; that incomplete run
was not counted as a result. Retained-field schema checks pass for all 1,048 MySQL
value definitions. This completes the ordinary unit regression check for this
checkpoint, not the complete redesign or generator/static/fuzz acceptance.
