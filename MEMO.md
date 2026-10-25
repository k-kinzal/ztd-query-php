# SQL Semantics and MySQL Memory

## Purpose

The purpose of this work is to check whether `sql-semantics` provides appropriate SQL types and invariants. We are building an executable in-memory MySQL database so that the semantic model must support actual execution, rather than merely describing SQL in a way that looks plausible.

Supporting multiple MySQL versions is a requirement of `mysql-memory`, but validating the semantic model is the main objective. The project targets PHP 8.1+ and MySQL 5.6–9.1 for these packages.

The intended result is a SQL model that preserves the information needed to reconstruct SQL and whose values can be consumed by an implementation of MySQL. That implementation is checked against the real server. The work must remain a simple implementation of the three checks below, following the conventions of the other `sql-*` packages.

This memo records the agreed direction and the remaining work on [PR #665](https://github.com/k-kinzal/ztd-query-php/pull/665). It is not a claim that these checks currently pass.

## Build the verification foundation before expanding coverage

For work with this many statements, clauses, types, and version differences, the foundation is a verification mechanism that can check those variations consistently. Establishing that mechanism is the central engineering task. Broad implementation must proceed on top of it, with counterexamples directing corrections to the model and implementation.

The mechanism itself must remain simple in operation. Use `sql-faker`'s fuzz approach as a reference: the input generation, operation under test, comparison or assertion, and failure must be easy to follow. The three checks below should expose these steps directly, using the existing tools. The size of the SQL space does not justify a private framework or an expanding collection of special verification paths.

Design the complete verification structure and the model/executor contracts before making further statement-specific corrections. This order supersedes the earlier instruction to start with one small concrete example. A working INSERT path beside an unchanged Operation-based system does not establish the architecture. The inventory, ownership of meaning, execution boundary, generation strategy and acceptance procedure must cover the whole supported SQL domain. Implement those structural changes together; use the three checks to expose remaining behavior defects only after their boundaries are sound.

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

The whole-system design below must show the path from statements to their semantic information, enforced invariants, reconstruction and execution. An INSERT example can test that design, but cannot determine the architecture for the remaining SQL families.

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

## Architecture decision (2026-10-25)

The user has changed the implementation order: design and repair the whole
verification structure before resuming individual SQL fixes. This section
supersedes the earlier INSERT-first plan. Work stays in this worktree and branch,
`feat/mysql-memory`, and is delivered through PR #665.

### Findings at the start of the redesign

The following are structural failures, not a backlog of SQL counterexamples:

- `Semantics::analyze()` declares `AnalyzedStatement`, but MySQL's
  `Platform::publish()` publishes only a narrow INSERT form without Operation.
  Other statements retain the context, Facts and source map aggregate.
- `Execution::statement()` dispatches Operation through the old lifecycle and
  concrete INSERT through a separate lifecycle. Access checks, diagnostics,
  preparation, transactions and counters can therefore differ by representation.
- `Session::analyze()` can reach a conditional-definition execution path through
  `Session/Parse/Reader` and throw `CompletedTemplate` carrying an executed reply.
  Moving that exception into an annotation does not establish read-only analysis:
  the definition's typed conditions and effects must enter the common lifecycle.
- Of 1,099 files under `mysql-memory/tests/Property`, 1,096 reference the rejected
  Samples/Construction machinery. They mostly assert that a sampled constructor
  produced an instance. Their file and assertion counts do not measure execution.
  The three actual Eris property classes are limited demonstrations, with
  Operation still used for CREATE TABLE, SELECT and INSERT prerequisites. The
  root generator named AnalyzedStatementGenerator chooses only INSERT.
- Generator coverage is discovered independently, but absence is reported only
  when PHPStan visits `MySqlMemory\Instance`. That trigger is unrelated to the
  model inventory. Discovery also depends on Snapshot usage, so a new ordinary
  model class can escape the intended inventory.
- Command, Evaluation and several other runtime layers are permitted to use the
  parser and semantic front end in the current Deptrac configuration. Prefix
  parsing and reads of `Session::text` are distributed through preparation and
  execution. The desired boundary is not enforced by the current dependency graph.
- The execution target compares driver results and table rows, but those rows
  alone do not describe schema, account, routine or session-state changes. Its
  order detection is a regular expression over SQL. Environment-specific
  observers must be reviewed against a precise field-level comparison contract.
- CI does not run the same complete acceptance matrix for the two fuzz targets.
  The memory workflow defaults to one release; the semantic workflow is a
  main-branch mutation campaign, not the required per-profile replay and separate
  empty-corpus budget. Missing profile directories can be silently skipped.

The previous local results are retained below as history. None closes these
structural failures. The uncommitted default-conversion experiment was saved as
an ignored patch under `mysql-memory/build/insert-foundation/` and removed from
the active changes; no new execution behavior from it is claimed.

### One model and two producers

```text
SQL input -> parser/lowering -> binding -----------------+
                                                        |
Eris generators -> composed semantic values -------------+-> concrete Statement
                                                              |          |
                                                              |          +-> renderer
                                                              +-> execution
                                                                  -> reply and state

SQL Faker -> the same SQL + equivalent setup -> real MySQL -> observation
                                             -> MemoryDB -> observation
```

The analysis result contract is an interface. Its implementations are concrete
SQL statements, with no public Operation replacement containing an inner
statement and analysis indexes. `AnalyzedStatement` is the current interface
name; changing the name is not a migration criterion. INSERT and REPLACE remain
distinct statements. SELECT, CREATE, ALTER, transaction control and stored-program
instructions retain their own SQL alternatives rather than a common payload bag.

Analysis may use AST nodes, environments, symbol tables and indexes internally.
It constructs an immutable public graph with the resulting meaning on its SQL
elements. These temporary analysis objects do not escape in the result. Direct
generators construct that same graph without invoking analysis or rendering.

| Semantic element | Retained meaning | Analysis-only or execution-only input |
| --- | --- | --- |
| Statement | Concrete command, clauses, release/lexical interpretation, optional span | Full analysis environment and parser root are discarded |
| Expression | Operands, resolved or explicitly dependent type, collation, nullability, optional span | Parameter values and row values arrive during execution |
| Relation/column use | Written name, scope/alias role, declared identity or explicit missing/ambiguous resolution | Catalog search is an analysis input; current object existence is execution state |
| Destination | Bound table/column identity and destination type, independently of the source expression | Conversion and constraint checks occur during writes |
| Definition | Written declaration operands, effective column/key information and intrinsic problems at their owning elements | Installation changes the runtime dictionary only after validation |
| Query | Projection names/types, sources, scope/correlation, ordering, grouping, windows and set-operation structure | A plan contains execution operators, never missing SQL meaning |
| Stored program | Declaration scope, instructions, handlers, cursors and typed expression bodies | Invocation frames and handler state are runtime data |
| Source location | Optional byte offset and length on the relevant element | No source text lookup by renderer, planner or executor |

A syntactically possible SQL request is not necessarily an installable declaration.
Constructors reject malformed representations (wrong element kinds, impossible
ownership, invalid structural alternatives). A grammar-valid request which MySQL
rejects must still have reconstructable typed operands and its intrinsic problem.
Catalog-dependent refusal remains an execution condition. This distinction is
applied across families, not by adding an untyped fallback for each failing seed.
In particular, differing VALUES widths can be a SQL request error and must not
make a grammar-valid request impossible to represent.

Condition ownership and condition order are separate concerns. A local element
owns its intrinsic condition; the statement's preparation rules determine when
it is emitted for the selected release. Planning can derive an ordered sequence
of conditions from these fields. Source offsets may locate diagnostics, but cannot
be the sole source of precedence because generated values have no source text.

### Replace the information owners, not just the root name

The migration of the current `Statement/Fact` records has the following ownership
rules. It must not copy a Facts instance onto each node, introduce an untyped
attributes array, or retain identity-keyed semantic lookups in a renamed root.

| Current information | Public owner after migration | Consumer |
| --- | --- | --- |
| `ScalarFact::type`, `nullability` | The corresponding expression; inferred type is distinct from an assignment destination's type | Expression compiler and result metadata |
| `ScalarFact::resolution` | The name/parameter/alias expression's explicit reference alternative | Name access and parameter binding |
| `ScalarFact::replacement` | Retire the generic replacement pointer; preserve the written expression and its SQL-specific interpretation, and derive executable simplification during planning | Renderer retains written operands; compiler uses their typed interpretation |
| `RelationFact::table`, `shape` | The table occurrence and its exposed output slots; join null-extension belongs to join output, not the base declaration | Relation planning and writes |
| `QueryFact::projection`, `shape`, `nameComparison` | The query's ordered output projection and name-lookup rules | Query planning and public output lookup |
| `QueryFact::aggregates` | Aggregate expressions identify their owning query scope; the planner derives each scope's evaluation work | Grouping and correlated evaluation |
| `Facts::references` | Each write target carries its table occurrence reference | Multi-table UPDATE/DELETE |
| `Facts::declarations` | Each definition exposes the declaration it can provide, including why a refused request cannot provide one | Later analysis and dictionary installation |
| `Facts::output` | The row-producing statement exposes its result shape; CALL retains its multiple-result semantics | Protocol metadata and result handling |
| Diagnostics, warnings and warning subjects/instructions | The relevant clause, expression or stored instruction, with explicit preparation/execution phase | The common lifecycle emits them in SQL-defined order |
| `SourceMap` | Optional spans on the corresponding elements | Error locations and tooling only |
| Context profile, charset and mode interpretation | Language profile on the statement and the interpreted literal/operator/name fields where needed | Rendering, type evaluation and binding validation |

References cannot point indiscriminately back to complete statement nodes.
For example, the current `CommonTable::definition` points to a CTE body, and
`ResolvedColumn::relation` points to a relation occurrence. Adding local facts to
those objects would introduce construction cycles for recursive CTEs.

Use immutable SQL declaration identities for query scopes, relation occurrences,
CTE headings, output slots and routine-local declarations. These are shared
leaves of the graph, not a second semantic registry. A column use identifies its
declared column or output slot and relation occurrence; a correlated use also
identifies the enclosing scope. A CTE reference identifies the CTE heading with
its exposed column types, while its definition owns the body. The binder solves
recursive CTE types under the selected dialect's rules using private work data,
then freezes the heading and publishes references to it. Forward references with
unresolved inputs retain explicit dependent types. No public constructor needs a
mutable pointer filled in later.

Scope and occurrence identities have SQL meaning: two aliases of the same base
table are different occurrences. They are not hashes of SQL or process-global
object IDs. Containing query/program constructors validate that each reference
has a reachable owner and a compatible slot. Eris composes the owner and its
uses together; shrinking recreates them together. Runtime maps may bind those
already meaningful identities to row slots or frames, but cannot add semantic
information missing from the public values.

Name resolution must preserve both written operands and their interpretation.
A prepared unqualified table name retains its definition-time database binding;
catalog invalidation may change the declaration/type snapshot, but must not
silently resolve it against a later `USE`. Parameters retain marker identity and
their explicit unresolved/type constraints; execution supplies values. Session
variables read by an expression remain runtime reads. Literal charset and
operator interpretation are analysis-time meaning. The entire session settings
object is not a substitute for distinguishing these responsibilities.

### Construction and binding contracts

Local ownership alone is insufficient if callers can supply contradictory facts.
Construction follows these rules throughout the model:

- Literal values derive their types/nullability from their literal kind and
  explicit encoding/collation interpretation. A caller cannot separately declare
  a string literal to be an integer expression.
- A resolved name derives its type and nullability from its exposed slot. The
  reference identifies both the relation occurrence and the declaration; an
  outer join can change the slot's nullability without changing the declaration.
- Operators and calls use their typed operands and relevant dialect settings to
  derive the result, or validate a binder-supplied result against those same
  rules. Pure type rules may be shared with analysis; they cannot read an AST,
  source text, a global Facts registry or current database rows.
- A statement/clause validates element kinds, ownership and alternative choices.
  Grammar-valid SQL refusals remain typed request conditions, rather than PHP
  construction failures. Installed declarations have stronger invariants than
  the requests which attempt to create them.
- Missing catalog/parameter inputs have named dependency alternatives. Unknown
  is not a universal escape type for unsupported implementation. Failure to
  implement a SQL rule remains an implementation exception.

Binding has a typed-input operation distinct from SQL parsing. It takes the
statement's written operands and captured lexical/name interpretation, a current
declaration snapshot and parameter type inputs, and returns a new concrete
statement. It never changes the existing immutable value. This operation serves
catalog invalidation and parameter-dependent preparation without routing through
`Facade::analyze()` or rendered SQL. Runtime consumers may call this narrow
binding contract during preparation; plans and commands consume its result.

For direct PBT, construction already supplies the local semantic values. A
generated dependent sequence supplies consistent declarations and references.
The executor still checks their current catalog validity and applies the same
documented rebinding conditions as analyzed values. It does not automatically
re-analyze every generated value to repair an incomplete model. Properties must
also exercise stale bindings and missing inputs as explicit scenarios.

### One execution lifecycle

All producers enter `Execution::statement(AnalyzedStatement, parameters)`.
The lifecycle owns release validation, statement counters, diagnostic-area
selection, preparation, access/catalog checks, condition emission, journaling,
execution and final result/error handling. Statement semantics select their
required phases and ordering: DDL implicit commits, diagnostic inspection, CALL
and contained routine instructions cannot all use an unconditional transaction
wrapper. These are SQL distinctions, never branches on legacy versus migrated
representation. An execution failure unwinds temporary state in `finally`;
ordinary SQL errors apply their statement/transaction rollback semantics.

Commands accept concrete statement values (or a common statement interface which
they narrow), not Operation. Query planning reads expression types, resolutions
and output shape directly. Dictionary, transactions, locks, privileges and
parameters are legitimate execution inputs; they cannot supply omitted syntax.
An unimplemented command or broken invariant is an implementation failure, never
success through a generic ER_NOT_SUPPORTED_YET fallback.

SQL input handling is a separate boundary responsible for protocol SQL text,
splitting and syntax-error excerpts. It calls analysis and then the same execution
entry used by PBT. It must not run a second semantic interpretation of prefixes
which changes the meaning of an already published statement. Parsed semantic
conditions belong to analysis, not duplicated parser walks inside commands.

Prepared execution stores the concrete semantic statement, parameter slots and
catalog dependencies. Rebinding after catalog invalidation uses typed operands
and fresh catalog inputs; it is not a rendered-SQL fallback inside execution.
SQL PREPARE legitimately evaluates SQL-valued application data and sends that new
input through the SQL input boundary. This is distinct from reparsing an existing
statement to recover information its model omitted.

The runtime dependency direction is:

```text
Protocol/server -> SQL input -> analysis
Protocol/server -> execution -> commands/plans -> evaluation/storage/dictionary
                                  ^
                            semantic values
```

Runtime consumers cannot depend on Parser, Lowering, Facade, Facts, SourceMap or
AnalysisContext. Analysis utilities can keep transient indexes internally while
being migrated. The shared interface change also requires updates to PostgreSQL,
SQLite and downstream ZTD consumers; retaining Operation in the shared result
contract for them is not completion of its removal. No PHP version bump is part
of this work.

### Existing implementation responsibilities

Paths below are relative to `packages/`. This is the removal/replacement map for
the structural migration, rather than permission to keep parallel APIs.

| Current owner | Required change | Exit condition |
| --- | --- | --- |
| `sql-semantics/src/Facade/Semantics.php`, `Validation/Publication.php`, `Statement/Operation.php` | Publish concrete statements through the result interface; keep construction/binding indexes private to analysis | No published Operation, context, Facts or source map; construction does not render/reparse to discover meaning |
| `sql-semantics-mysql/src/Platform.php`, `Lowering/`, `Rules/` | Lower syntax and bind local semantic values for every grammar-dispatched family | No INSERT-only publication route or default Operation route |
| `sql-semantics*/src/Statement/` | Apply the ownership and identity contracts above; preserve grammar-valid refused requests | Every executable fact is reachable through the corresponding element, including missing-input alternatives |
| `mysql-memory/src/Session/Session.php`, `Session/Parse/`, `Hint/` | Separate SQL input parsing from typed execution; move semantic prefix/tree inspection into analysis | Input text is available for input handling and diagnostic excerpts, never for completing a published statement |
| `mysql-memory/src/Session/Execution.php`, `Session/Problem/`, `Session/Access/` | Implement one lifecycle with explicit SQL-specific phases and local condition ownership | Analyzed and generated instances of the same statement execute the same phases |
| `mysql-memory/src/Command/Command.php`, `Command/Dispatcher.php`, `Command/*` | Consume concrete SQL statements and legitimate runtime services | No command receives Operation/Facts or interprets AST/text |
| `mysql-memory/src/Plan/Planner.php`, `Evaluation/Compile/Compiler.php` | Read query shape, expression types and references locally; derive execution operators | No external fact lookup and no fallback conversion from rendered SQL |
| `mysql-memory/src/Command/Prepared/`, `Session/Statement/Binding.php`, `Program/` | Store typed operands, lexical interpretation and dependencies; rebind from typed inputs | Text/prepared/stored execution shares the same lifecycle and preserves declaration identity |
| `mysql-memory/src/Server/Client.php`, `Server/MemoryDatabase.php` | Keep the wire-protocol SQL reply distinct from internal failure reporting | An internal exception cannot become a matching native SQL error in fuzz/PBT |
| `mysql-memory/tests/Generator/`, `tests/Property/`, `rules/` | Compose the same public values with Eris; enforce existence and use independently | All public model generators exist, are reachable from real properties, and shrink while preserving their dependencies |
| `mysql-memory/fuzz/Target/`, `fuzz/Observation/`, `fuzz/Fixture/` | Observe state families, retain raw evidence, and check fixture restoration | Deterministic content is compared; an unobserved effect or failed reset cannot count as agreement |
| Both packages' Composer fuzz commands and `.github/workflows/*fuzz.yml` | Use the same profiles, failure policy and separate replay/search budgets | Local/CI success means completed checks, not merely exit status zero |

This migration changes public semantic values across all three dialect packages
and downstream ZTD consumers. Update their call sites and examples in the same
branch. Internal lowering can be migrated family by family for implementation
logistics, but do not publish a completed API or count that family as accepted
while the old root or lifecycle remains part of its execution.

`mysql-memory/deptrac.yaml` is human-owned and currently permits several forbidden
dependencies. Its passing result cannot establish the target boundary. The
required final rules remove Parser/FrontEnd access from runtime consumers and
separate the SQL input boundary from Session execution. Prepare that exact change
for the owner once the namespace migration is concrete; do not edit the protected
file or introduce another private dependency-checking script to bypass it.

### Coverage follows SQL families, not test file counts

The existing grammar dispatch provides the family inventory. Definition is a
routing category, not an extra semantic statement family. Each row below uses
all three checks; shared expression/type/reference generators feed every row.

| Family | State and semantic dependencies | Execution properties and observations |
| --- | --- | --- |
| Query | Sources, CTEs, scopes, joins, expressions, windows, set operations | Output metadata and rows, correlation, grouping, ordering and absent references |
| DML | Query/VALUES inputs, destinations, assignments, conflicts, parameters | Conversion, affected rows, identities, constraints, resulting rows and rollback; INSERT/REPLACE are distinct |
| Table definition/change | Types, columns, defaults, generated expressions, keys, partitions, storage | Created/altered declarations, prerequisite-dependent writes, atomic failure, implicit commits |
| Database/view | Database names, defaults, view queries and dependencies | Catalog changes, name resolution and later reads/writes |
| Routine/event/trigger | Declaration scopes, bodies, handlers, cursors and scheduling | Invocation effects, condition handling, control flow and event/trigger state |
| Account/resource group | Accounts, roles, grants and resource declarations | Authentication/privilege state, dependent operations and specific absent-state errors |
| Transaction/locking | Transaction modes, savepoints, XA identifiers and locks | Visibility, commit/rollback, lock ownership and diagnostics |
| Server/maintenance/storage | Variables, instance operations, tablespaces and engine options | Session/global/catalog effects and lifecycle; physical fields follow explicit contracts |
| Replication | Channels, log positions, filters and replica state | Channel/configuration changes, missing prerequisites and version-specific conditions |
| Utility | SHOW, EXPLAIN, HELP, SET and diagnostics | Result shape/types, deterministic contents and session state |

Per-type generators mirror the model namespaces. An interface generator composes
its concrete variants; it cannot silently stand for one implementation. A release
is an input selected before generation, not discovered by rendering candidates
until a profile accepts one. Recursive generators bound size through Eris while
retaining each recursive alternative. Constructor constraints are satisfied by
composition, not an independent retrying constructor interpreter.

Properties are organized by the behaviors in the table. A property's input is
fully constructed before `then()`. A dependent sequence is an Eris composition
which builds prerequisites and dependents together. Shrinking reconstructs that
relationship; the callback creates the execution session, executes the generated
statements and asserts their effects. An absent-state property checks a specific
SQL error. Any other exception fails. Namespaces, `#[Test]` and method names are
conventions, not evidence that these behaviors were exercised.

Generator existence/type coverage and configured class reachability remain two
independent PHPStan checks. Model discovery comes from the model source tree,
not an allowlist of implemented generators or presence of a convenient runtime
class. Ordinary model records must not need a Snapshot trait to be counted.
Runtime execution evidence remains the job of Eris and the two fuzz targets.

### The three executable checks

| Check | Entry and assertion | Failure that must stay visible |
| --- | --- | --- |
| Reconstruction | Existing PHP-Fuzzer target; SQL Faker -> analyze -> typed render; exact same-profile Compact comparison | Refused grammar-valid requests, missing operands, changed spelling, exceptions and timeouts |
| Generated execution | PHPUnit/Eris; concrete values -> the public analysis-result execution boundary | Invalid generated structure, broken shrinking, wrong SQL error, wrong effect, unsupported executor or source fallback |
| Native execution | Existing PHP-Fuzzer target; identical SQL/setup/settings -> Testcontainers and MemoryDB | Metadata/row/completion/error/condition/state differences, unstable reference outside defined contracts and implementation failures |

The native observer records raw observations before any environment treatment.
It compares result metadata, rows, affected rows, insert identities, errors and
ordered conditions. State comparison includes the affected schema definitions,
rows and relevant session/catalog state, not only table contents. Isolation/reset
must restore equivalent initial state; a reset failure is a finding. Native
results must not be copied into MemoryDB to make the outcome agree.

SQL ordering must come from statement structure, not a regex matching text inside
comments, literals or nested queries. Unordered results are multisets retaining
duplicates. An environment contract identifies an exact field and checks its
shape/type/range; it cannot remove a statement, result set or diagnostic. A second
native observation detects unaccounted instability and fails rather than silently
omitting that input. Driver-visible SQL errors cannot conceal an internal MemoryDB
exception: the test server must expose those failures to the target separately.

The observation contract is selected independently of MemoryDB's successful
analysis, using the official grammar structure of the input. It cannot assume
that the semantic model under test has already classified every effect correctly.
Only an outer result ORDER BY orders that result; nested clauses do not. Ties and
unordered LIMIT results can remain nondeterministic: repeated native disagreement
is an unresolved finding, not a skipped input or proof of an emulator defect.

| Effect domain | Observation before restoring the fixture |
| --- | --- |
| Rows and schema | Rows with duplicates, table/view definitions, columns, defaults, keys, constraints and dependent object definitions |
| Session and transactions | Selected database, relevant variable changes, diagnostics/completion captured before observer queries, and visibility through a separate connection where the scenario requires it |
| Accounts and grants | Created/altered identities, roles, privilege changes and relevant connection outcomes; generated secrets receive only their specific contract |
| Routines, events and triggers | Definition and attributes plus effects of generated dependent invocation; installation alone does not prove execution |
| Replication and server configuration | Channel/configuration/lifecycle effects visible through the applicable release's interfaces |
| Environment-specific output | Exact field selectors with retained raw values and validated types/ranges/relationships; deterministic neighbors remain exact |

State probes run after the primary result and conditions have been captured.
They use a separate observer connection when they would perturb the state being
measured. A state-read error is evidence, not an empty snapshot. A failed
restoration abandons that owned fixture and fails the run; later inputs cannot
inherit it. Lifecycle inputs use owned disposable servers with startup and
statement budgets distinguished explicitly.

Direct execution propagates implementation exceptions. At the process boundary,
the server records an internal failure separately from the protocol reply, with
the current request identity. The observer checks that channel even if PDO
received an ordinary error packet. Legitimate MySQL errors arise only from
modeled SQL conditions. In particular, `ImplementationGap` and an unknown compiler
case cannot be converted to ER_NOT_SUPPORTED_YET as a general fallback.

Tests of these mechanisms are normal unit/integration tests: a removed generator
must fail coverage; wrong generic type must fail; a disconnected generator graph
must fail usage; an intentional Eris assertion must shrink without losing the
prerequisite; altered rendered/observed content must fail the corresponding target.
These tests validate the mechanisms. They do not replace grammar or execution
coverage and do not require a new private framework or a Diff suite.

### Acceptance matrix and evidence

Both fuzz targets use the same nine releases: 5.6.51, 5.7.44, 8.0.44, 8.1.0,
8.2.0, 8.3.0, 8.4.7, 9.0.1 and 9.1.0. For each release, replay the complete
default SQL Faker corpus. Also replay every
supplied supplement under its recorded settings: HIGH_NOT_PRECEDENCE,
PIPES_AS_CONCAT and identified diagnostic grammar. These supplements are not
independently complete grammar corpora; the current supplier ships them for
8.4.7. The supplier's profile inventory determines
applicability. An expected missing directory or an empty seed set is a failure,
not a successful skip. Replay must establish the applicable grammar coverage;
counting files or matching exit code zero is insufficient.

Run a separate 900-second search from an empty corpus for each target and
applicable release/profile. Keep text and native prepared driver coverage
explicit; neither stands in for the other. SQL modes not supplied as Faker
profiles, such as strict conversion modes, are explicit analysis/execution
settings in the semantic properties and relevant version regressions. Do not
claim a full power-set of all session settings from those named profiles.

Local runs and CI call the same package targets. PHP-Fuzzer's corpus-crash output
must fail the gate even when its process exits zero. A search passes only after
its completion marker proves the budget was reached with no finding. Evidence
records revision, release, grammar/profile, driver mode, corpus identity/coverage,
seed, trial/input count and duration. Temporary probe scripts and focused passing
subsets are investigation aids, never acceptance substitutes.

### Work goals and order

The goal is fulfillment of every requirement in this memo and a review-ready
PR #665, not completion of a planning or cleanup checkpoint. Work continues on
the existing `feat/mysql-memory` branch until the model/execution migration and
all acceptance checks below are complete. The earlier tool-managed goal's
INSERT-first ordering is superseded by this whole-system order; its unfinished
status must not be changed to complete merely to replace that description.

- [x] **A. Whole-system design and inventory.** Review this architecture against
  every family above; identify the existing owners, boundary violations and
  removal/replacement responsibilities. No further SQL-specific fixes while
  the design still relies on an undefined fallback or unmodeled execution input.
- [ ] **B. Honest verification structure.** Remove constructor-sampling properties
  and their private machinery; keep the independent missing-generator failures.
  Test the two static rules independently of runtime entry files. Align the
  ordinary package commands, CI matrix, failure handling and observation contracts.
  Existing genuine Eris properties remain limited evidence, not completion.
- [ ] **C. Coherent model and execution migration.** Apply the contracts across
  statement families and shared expression/reference/definition types. Remove
  Operation from published results and commands, make the lifecycle common, and
  move parser/text-dependent meaning into analysis. Generate the public model
  directly and connect every family's behavior properties. This is a structural
  migration, not a sequence of isolated green SQL examples.
- [ ] **D. Behavioral correction and acceptance.** Only after A-C are structurally
  established, investigate the exposed SQL counterexamples through the three
  standard checks. Complete all ordinary checks, full applicable seed replay and
  the recorded seedless matrix; then make the PR ready for review.

A design document alone does not close B or C. A smaller test count after deleting
fake properties does not improve coverage. Passing targeted checks while full
checks fail does not close D. These distinctions must remain explicit in reports.

### Structural checkpoint

The whole-system design, ownership contracts, family inventory and replacement
responsibilities above establish goal A. Goals B-D remain open. The changes at
this checkpoint do not add statement-specific execution behavior.

The rejected constructor sampler and 1,096 properties which called it have been
removed. Three actual execution property classes remain. The misleading
AnalyzedStatement/Expression generator aliases have also been removed: an
interface generator which produces only the first migrated concrete type is not
coverage of that interface's domain. Existing Operation-based generators and
their uses are still transitional and do not establish the new execution boundary.

Generator coverage now runs as a global PHPStan collected-data rule. It inventories
public model source independently of runtime entry classes, generator declarations
and Snapshot usage. A collector for analyzed files ensures the rule also runs
when there are no generators at all. Missing generators are reported at the model
declaration. Generator type/instantiability checks and the separate configured
class-usage rule remain ordinary PHPStan checks, with no baseline hiding failures.
The normal Composer PHPStan command disables Composer's 300-second process timeout
so the full package analysis can finish and report its findings.

The shrinking mechanism test exposed a size-control defect: source positions used
`choose(0, 1000)`, whose linear shrink could exhaust PHPUnit's 60-second limit.
Span generation now uses Eris's size-bounded natural endpoints, ordered to preserve
the nonnegative, non-overflowing range invariant. The execution counterexample
still shrinks to two zero-valued rows with the declaration relationship intact;
the time limit and assertion were not weakened.

Verification results are recorded here separately from acceptance. In particular,
this checkpoint does not establish a corrected native observer, unified execution
lifecycle, complete generator inventory or successful full fuzz matrix.

Checks on PHP 8.5.8 / PHPUnit 10.5.64:

- Generator-coverage integration: 9 tests, 18 assertions. The separate configured
  class-usage integration passed all 32 cases, including disconnected cycles.
- The retained execution property suite passed 36 tests and 29,728 assertions
  with `ERIS_SEED=20261025`. After correcting source-span size control, the
  affected INSERT properties passed again: 18 tests, 15,496 assertions.
- The real execution/shrinking regression passed: 1 test, 483 assertions in
  8.3 seconds. Its earlier 60-second timeout remains a failed intermediate run,
  not successful shrink evidence.
- Legacy statement dispatch inventory: 184 tests, 184 assertions. This verifies
  the existing dispatch map, not removal of the Operation-based lifecycle.
- Composer manifest validation, strict autoload, changed-file formatting and
  PHP 8.1 compatibility checks passed.
- The normal complete PHPStan command finished and failed with 2,984 diagnostics:
  1,144 missing generators and 1,840 remaining type/API errors. No errors were
  reported in the changed rules, retained generators, coverage integration or
  INSERT execution/shrinking tests. Legacy dispatch tests still report old
  Operation assumptions. No errors were suppressed or converted to a baseline.

The normal targets and test commands produced these results; logs are under the
ignored `mysql-memory/build/insert-foundation/` directory. No new corpus or
900-second fuzz acceptance result is claimed at this checkpoint.

### Verification boundary migration

The ordinary fuzz targets now count attempted and successfully checked inputs.
An unfinished input, including a caught counterexample or timeout, makes the
process fail even when PHP-Fuzzer returns zero. A zero-input run fails too.
Successful replay reports its verified input count; CI requires it to match the
copied corpus. The per-input alarm is canceled before shutdown cleanup; the final
status check runs after registered container/coverage cleanup. The duration marker
remains separate from the replay count. Fatal PHP errors retain their failing
status and leave PHP-Fuzzer's subsequent input-preservation handler reachable.

MemoryDB no longer translates arbitrary client exceptions, missing semantic
rules or missing evaluators into ordinary SQL errors. The child process reports
internal failures over its process pipe before closing clients. The parent keeps
that failure sticky and exposes `assertNoInternalFailure()` to the observer.
Legitimate SQL errors and intentional SQL SHUTDOWN retain their own behavior.

The native target now sets the lexical profile during setup, independently of the
tested SQL and the driver's text/prepared mode. Result ordering follows the
official grammar's outer SELECT, including the different 5.x UNION forms. Other
commands retain their result order until a specific contract establishes that
reordering is valid. Raw results are retained before multiset sorting or
environment contracts. Failure reports include lossless serialized raw evidence,
so invalid UTF-8 bytes cannot disappear through JSON formatting. Table/schema/view
definitions accompany rows; a missing database is distinct from a failed state
read. These changes still do not cover every state domain in the table above.

One MySQL fuzz workflow now describes both reconstruction and execution over all
nine releases and the three supplied 8.4.7 supplements. Text and native prepared
execution have separate jobs: 36 target/profile combinations. SQL Faker's existing
seed checker establishes grammar coverage first. Each combination replays its
own nonempty corpus and then uses a distinct empty directory for its 900-second
search. Missing directories/manifests fail. Artifacts retain the revision, corpus
hashes, supplier manifest, logs and counterexamples. This is an executable matrix,
not evidence that all its jobs pass. Both targets record their mutation seed and
accept `PHP_FUZZER_SEED`; SQL Faker's diagnostic provider now uses the constructor's
named coverage/start-symbol arguments rather than the obsolete positional call.

Mechanism checks on PHP 8.5.8:

- Process/client/compiler boundary tests: 111 tests, 298 assertions passed.
- Grammar ordering and duplicate/type-preserving multiset checks: 148 tests,
  150 assertions passed across the nine releases.
- Observation state and text/prepared profile checks: five tests passed; they
  distinguish unchanged rows from changed defaults, absent from unreadable state,
  and raw order from comparison order.
- The combined observation mechanism suite passed 153 tests and 171 assertions.
- Both ordinary PHP-Fuzzer targets returned zero with `Verified 1 inputs.` for
  the supplied BEGIN seed. Reconstruction's SRID counterexample and execution's
  CHECK-versus-existing-table counterexample returned status one. These focused
  inputs validate the gate; they are not corpus acceptance.
- A deliberately one-second execution-target timeout also exited with status one
  and retained the failing input in PHP-Fuzzer's output; this validates timeout
  handling, not SQL behavior or the normal sixty-second input budget.
- The complete 8.4.7 execution corpus attempt stopped at `column_attribute-16.txt`
  with the CHECK-versus-existing-table mismatch. The corresponding reconstruction
  attempt stopped at `column_attribute-15.txt`. Neither run passes acceptance.
- The changed PHP files pass focused PHPStan checks. The MySQL and existing
  semantic fuzz workflows pass actionlint. Full-package errors recorded above
  remain open and unsuppressed.
- The complete supplied diagnostic supplement now reaches reconstruction and
  fails on `real_ulong_num-4.txt` with an integer-domain refusal. The previous
  provider argument error no longer prevents that check from running.

Goal B remains open for complete state-domain observation, restoration verification
and the remaining environment contracts. Goal C still requires replacement of the
Operation/Facts publication and dual execution lifecycle. No statement-specific
counterexample has been repaired as part of this boundary migration.

Restoration now discovers objects independently on the connection being restored.
Failed catalog reads and failed cleanup statements abort the input. A postcondition
checks the catalog, spatial definitions, global variables, grants and the tested
account before another input can run. Versioned probes select supported catalog
interfaces explicitly. Initial general tablespaces are preserved, including the
native dictionary space; an absent initial space is an error. This does not yet
establish restoration of every state domain listed above.

Each ordinary campaign owns a fresh reference container, as lifecycle observations
already do. A contaminated fixture cannot be reused by a later campaign or another
test. An explicitly configured external DSN remains the caller's disposable fixture.

Account restoration replays the original CREATE USER definition through the
independent administrator after removing owned schema objects. The previous
CREATE-to-ALTER text substitution was not equivalent: ALTER USER with an
authentication plugin but no credential expires the password under MySQL's
documented rules. This was a harness defect, not a semantic counterexample.

The restoration and observation-state mechanism tests pass together: nine tests,
29 assertions. The changed files pass PHPStan, formatting and PHP 8.1 compatibility.
With the stronger initial-state check, the supplied 8.4.7 BEGIN replay exposes a
missing `mysql` general tablespace in MemoryDB. The earlier one-input success
above predates that check and is not current acceptance evidence. The missing
catalog state remains visible for the coherent model migration; it has not been
excluded from comparison or patched as an isolated SQL case.

The observer also fails on unreadable result metadata or conditions. It preserves
the full SQL diagnostic when PDO uses silent error mode, including a later
result-set error. The expanded observation-state suite passes nine tests and
29 assertions, covering both PDO error modes. A fresh owned-container replay
still fails on the missing initial dictionary tablespace, with status one.

The execution observation now retains transaction status from the driver before
issuing state probes, the selected database, complete exposed session variables,
and globals read through the independent administrator. Raw session identity
values remain in the evidence; only values validated by the existing independent
identity contract are replaced in the comparison copy. Global maps compare by
name, not incidental enumeration order. Tests demonstrate that equal completion
packets do not conceal BEGIN's transaction effect and that read_only is observed
before repair turns it off. These two checks pass with 11 assertions; both existing
text/prepared profile checks also pass (two cases, 14 assertions). Changed-file
PHPStan, formatting and PHP 8.1 compatibility checks pass. This does not yet cover
user variables, transaction visibility scenarios or every catalog domain.

The independent catalog observation now includes account/privilege tables,
routine/parameter/trigger/event definitions, plugin registrations, replication
repositories and status, prepared XA transactions, tablespace declarations, and
modern persisted settings, resource groups and spatial definitions. Versioned
probes come from MySQL's public interfaces. Unclassified differences remain exact
and fail, including environmental values without an established contract.
Application databases outside `fz` retain definitions and committed base-table
rows through the administrator connection. The executing connection's `fz` rows
remain a separate observation, so uncommitted visibility is not collapsed.
Views are recorded as definitions rather than executed during row collection.

The catalog mechanism suite passes four tests and 13 assertions: outside-schema
rows and duplicates, committed visibility, invalid-view definitions without body
execution, and account/routine changes. The two transaction/global-effect target
checks also pass with the expanded catalogs (11 assertions). Changed-file PHPStan,
formatting and PHP 8.1 compatibility checks pass. This broadens observation but
does not close B: temporary objects, user variables, locking effects and the
remaining environment contracts still need their corresponding observation
boundaries. No semantic implementation was changed to make these probes pass.

## Historical investigation results

The following checkpoints predate the whole-system redesign. They preserve
observations and counterexamples, but their sequence is no longer the work plan.

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


### Bound INSERT identity (2026-10-25)

An INSERT target now retains its fully qualified declaration name independently
of the written name. Publication applies the analysis context's name comparison
and assigned declaration schema, then discards those inputs. Execution, read-only
access checks and prepared-statement invalidation use the bound identity, so a
later USE cannot redirect a resolved write. The generated execution property also
varies case-sensitive and ASCII-insensitive bindings while preserving the same
generated table and column identities through shrinking.

The updated execution properties, shrinking and preparation regressions pass
32 tests / 42,949 assertions with ERIS_SEED=20261025. An additional direct-execution
regression verifies that an invalid second integer row rolls back the first row
without reading the request label (1 test / 5 assertions). Publication and target
invariant tests pass 9 / 34. Targeted static analysis and the 1,048-definition
schema check pass. The six-input INSERT corpus again passes exact round-trip and
native prepared execution on MySQL 8.4.7. These remain focused checks; the full
migration, generator coverage and corpus/seedless gates are still outstanding.


### Interval keyword reconstruction (2026-10-25)

IntervalUnit now retains the eight SQL_TSI aliases and exposes their canonical
unit for typing and execution. Date arithmetic, extraction, timestamp differences
and event schedules consume that meaning; source reconstruction retains the
written keyword. Per-type Eris delegates for IntervalUnit and DateArithmetic
replace their old constructor-sampling properties with a statement execution
property. It compares rows and result type metadata for the written and canonical
units, without using a SQL request label. This query path still uses Operation
and remains subject to the concrete SELECT migration.

The new property passes 9 tests / 8,100 assertions (900 trials across the nine
releases, ERIS_SEED=20261025). Related executor tests pass 102 / 217; the subsequent
event subset passes 35 / 77. The full MySQL semantic unit suite passes 9,102 tests /
20,219 assertions after correcting a legacy assertion that discarded SQL_TSI_DAY.
Targeted static analysis and PHP 8.1 compatibility checks pass for the changes.
The existing round-trip fuzz target passes interval_time_stamp-1.txt and
interval_time_stamp-8.txt on 8.4.7. Native execution replay also passes all nine
supplied seeds containing SQL_TSI keywords plus the original recorded event input.

The complete corpus is still failing. The next PHP-Fuzzer finding adds COLUMN in
`ALTER TABLE _sqlfaker_identifier ALTER _sqlfaker_identifier SET DEFAULT NULL`
(input `0c00000000000a0000000000000000000000020000000800000000000000000000`).
A replay of all SQL_TSI keyword seeds additionally finds CAST(... AS SQL_TSI_YEAR)
rendered as CAST(... AS YEAR); a type keyword is distinct from an interval unit.
A temporary diagnostic audit called the unchanged round-trip target for all
2,181 default 8.4.7 seeds: 1,806 passed and 375 failed, including 187 Compact
mismatches and grammar-valid requests rejected for semantic errors. This audit
collects defects; it is not a passing PHP-Fuzzer run or acceptance evidence.


### Optional COLUMN reconstruction (2026-10-25)

ALTER column actions now retain whether COLUMN was written. ADD (single and
multiple columns), CHANGE/MODIFY, ALTER DEFAULT, visibility and DROP render that
choice from a typed field; deferred stored-program defaults retain it too.
Constructor defaults preserve the existing generated spelling. Non-column DROP
requests cannot carry a column keyword choice.

All nine grammar releases have explicit presence/absence regressions for seven
column actions. The full MySQL semantic unit suite passes 9,229 tests / 20,346
assertions. After narrowing legacy Operation-only assertions at their actual
boundary, the final affected subset passes 294 / 382. PHPDoc passes 1,111 / 1,111;
related memory ALTER tests pass 38 / 105. Targeted PHPStan, retained-field schema
checks and PHP 8.1 compatibility pass. The unchanged exact round-trip fuzz target
passes the thirteen supplied seeds selected for COLUMN-only mismatches on 8.4.7.

Native replay of those same thirteen seeds still fails on signed_literal-2.txt:
`ALTER TABLE _sqlfaker_identifier ALTER _sqlfaker_identifier SET DEFAULT
-18446744073709551616`. The final error 1067 agrees, but MySQL records two preceding
1264 conversion errors that Memory currently omits. Input hex:
`0d00000000000a00000000000000000000000200000008000000000000000000020000`.
This is an execution counterexample, not a reason to omit diagnostics.

The full round-trip corpus now first fails on column_attribute-15.txt:
`CREATE TABLE _sqlfaker_identifier (_sqlfaker_identifier BIT NULL SRID 1)`.
Analysis rejects the SRID/non-geometry combination before it can reconstruct the
request. Input hex:
`1600000000001a00000000000000000000000000000000000000000000000000030001000000000000000c00000000000000000000`.
The complete corpus, seedless budgets and concrete-statement migrations remain
open gates.
