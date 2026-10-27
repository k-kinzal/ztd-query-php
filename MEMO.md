# SQL Semantics and MySQL Memory

## Purpose

The main objective is to validate the types and invariants of `sql-semantics` by
building an executable in-memory MySQL implementation that consumes them. MySQL
Memory must support the project's MySQL versions, but it is also the means of
exposing an insufficient semantic model. [S1:10; S2:23]

This memo distinguishes the user's requirements from design hypotheses and past
AI work. The previous memo mixed them and turned implementation choices into
additional acceptance conditions. Its detailed architecture, work checkpoints
and test counts are not a set of user-approved requirements. This audit makes no
new claim that implementation or acceptance is complete.

## The three requested checks

### 1. Reconstruct SQL from semantic values

```text
sql-faker SQL -> sql-semantics analysis -> semantic values -> rendered SQL
Compact(original SQL) == Compact(rendered SQL)
```

Use PHPFuzzer to search for counterexamples, with `sql-formatter`'s Compact
format for the comparison. Reconstruct from the meaning-bearing structure.
Keeping the original SQL and replaying it does not check that property. The
renderer must not fill gaps with retained SQL fragments or parser information.
[S2:23, 69]

### 2. Execute directly generated semantic values

```text
Eris semantic-type generators -> composed semantic values -> MemoryDB execution
```

Use PHPUnit and Eris to search for values that the downstream MemoryDB execution
phase cannot correctly consume. Planning and execution must obtain the SQL
meaning they need from those values. Filling missing meaning from an AST or SQL
text, or rendering and analyzing the generated input again, bypasses the boundary
being checked. Merely constructing an object or checking its PHP class is not the
requested execution property. [S1:6820, 6852; S2:23, 69]

Generate both values without prerequisite database state and dependent inputs:
for example, a generated CREATE TABLE followed by an INSERT generated for that
definition. Missing-state SQL errors are distinct from invalid representations
or implementation failures. Construct dependent values through generator
composition, preserving the relationship during shrinking. `then()` executes
and asserts; it does not build the semantic input that should have been generated.
[S1:7254, 7496, 7641, 7740]

### 3. Compare execution with real MySQL

```text
                           -> MemoryDB ----------------> execution result
sql-faker SQL ------------|
                           -> MySQL via Testcontainers -> execution result
Compare the two results.
```

Use PHPFuzzer. Send the same generated SQL to both databases and compare the
execution results. Keep the check direct: `fuzz/` contains `Target/` and PHP
entrypoint files, using the existing SQL Faker, database and Testcontainers APIs.
It does not need a separate observation, fixture, lifecycle or generation
framework. Do not add a dedicated Diff test suite. [S1:186; S2:23; S4:217, 311]

For values inherently dependent on the execution environment, check output
content and value types. This exception is limited to those values; it does not
permit removing output or ignoring other differences. The comparison must still
exercise the actual execution results. [S1:6472]

## Model and generator requirements

- `analyze()` declares an interface and returns a concrete SQL statement type
  implementing it. Semantic types express SQL concepts and enforce their
  invariants. Do not gather the meaning into a generic `Operation` aggregate
  containing `Statement`, `AnalysisContext`, `Facts` and `SourceMap`.
  Information and source information belong to the relevant SQL structure.
  Avoid common abstractions that erase distinctions in SQL merely to reduce
  code duplication. [S1:9131]
- The model must expose the information needed by its consumers, including the
  types and sources discussed for INSERT values. The earlier INSERT and
  Expression sketches were incomplete examples, not a finalized model or API.
  [S1:9218; S2:7, 23]
- `tests/Generator/` contains an Eris-based generator corresponding to every
  semantic model type in `sql-semantics` and `sql-semantics-mysql`, including
  nested types. Placement and namespaces consistently correspond to those
  types. Use Eris generation, composition and shrinking, rather than a private
  sampler behind a generator-shaped interface. [S1:5063, 7496, 7973; S2:69; S4:217]
- Generator coverage and property granularity are separate. Check the model-to-
  generator mapping with a PHPStan custom rule under `rules/`. Generators must
  actually be used; implement the requested configurable class-unused rule,
  selecting the checked classes in NEON. [S1:2271, 7961, 7973, 8578]

## Property grouping remains a design question

The properties must test the intended execution behavior. Their final units are
not settled. Grouping them around the principal concrete types returned by
`Semantics::analyze()` is the user's hypothesis, not a mandatory one-class-per-
result mapping. It suggests that most of the previous per-element property
classes disappear as composed generators exercise those elements through the
analysis-result boundary. It is not a deletion quota. [S1:7961, 7973; S4:217, 271]

All semantic types still need generators even when they do not each have a
standalone execution property. Determine the property units from what is being
verified; do not equate generator count, property-file count and behavior coverage.

## Work order and completion evidence

First establish the overall verification structure and a model/executor design
that can run it. Implement that structure across the relevant scope before
returning to individual SQL corrections. The user explicitly replaced the earlier
single-example-first approach with this order. A design document or one working
INSERT path does not establish the overall structure. [S2:105; S3:2621, 2635]

The work therefore needs to change the semantic model, its consumers, genuine
Eris generation and the three checks together. Once those checks operate at the
intended boundaries, use their counterexamples to correct the implementation.
Use the conventions and ordinary quality checks of the other `sql-*` packages;
the user requested these three special verification mechanisms, not an additional
private assurance framework. [S1:186; S2:23, 69, 105]

Fuzz must accept SQL Faker's grammar-coverage seeds, pass the seeds representing
the applicable 100% grammar coverage, and run without supplied seeds for 15
minutes without a failing case. These are counterexample searches. Report the
scope actually exercised. The supported-version obligations still apply; the
previous memo's particular release/profile/driver matrix was an AI choice, not a
separately specified user condition. [S1:186, 2271, 4858, 4904]

Work stays in this worktree and branch and is delivered through PR #665.
Repository rules, including English artifacts, supported versions and protected
files, continue to apply. This memo does not invent an exception to those rules.
[S3:12; repository `AGENTS.md`]

## Other explicitly requested conventions

- Public library usage is through PDO or mysqli, with database startup explicitly
  selecting the MySQL version. `Database::start()` and `MemoryDatabase::start()`
  were the proposed public names. [S1:2271]
- Remove the rejected `mysql-memory/docs`, `resources` and `bin` organization.
  The user allowed considering genuinely necessary resources, but did not accept
  their mere existence as a justification. Renaming or relocating rejected
  helpers is not a resolution. [S1:2271, 2666, 3109, 4932]
- Put properties in `tests/Property`, use `<Name>Properties` classes and
  `satisfies<Property>` methods, and give every property class at least one such
  method. No traits or helpers there, and no references from properties into
  `fuzz/`. [S1:2271, 6740]
- Follow the other packages' test categories and name Integration/E2E tests for
  their actual behavior or scenario. The rejected `tests/Connection`,
  `tests/Result`, `tests/Support` and introduced `tests/Fake` structures must not
  be retained as workarounds. The user did not prohibit processes or PDO in
  `tests/Unit` simply because of that directory name. [S1:2271, 4932, 5079]

## Audit: conditions that the AI added

The following were presented as requirements or fixed completion gates without
support in the user's requests. Their appearance in an earlier memo, implementation
or test does not establish approval. Some are possible engineering choices;
that does not make them mandatory, correct, or necessary for this task.

| Previous memo condition | Correction |
| --- | --- |
| Inspect all catalogs, session/global state, accounts, replication and locks after each generated SQL; restore all of them before continuing. | Remove from the fuzz requirement. The requested property compares the results of the generated SQL. This added audit task created the auxiliary framework the user rejects. |
| Execute real MySQL twice, require the two native observations to agree, and operate a separate two-server lifecycle path. | An AI-added verification design, not part of the requested two-database comparison. |
| Enable metadata-lock instrumentation, use administrator probes, enforce probe ordering, capture lossless observation records and measure protocol traffic for every trial. | Consequences of the added observation framework, not additional user acceptance conditions. |
| Import native catalogs/settings through a bespoke startup synchronization and restoration layer. | Not required by the user's simple fuzz comparison. Existing execution APIs and ordinary configuration do not justify this extra framework. |
| Precompute and retain all 32 lexical-mode combinations and client-encoding alternatives for prepared statements. | An AI-selected model/execution solution. The requirement is to preserve SQL meaning without downstream AST/text supplementation; this particular algorithm was not specified or accepted as the only solution. |
| Use a particular immutable graph, identity scheme, binding API, lifecycle signature, namespace dependency graph and named exception/channel design. | Implementation proposals. Keep the actual semantic information and invariants requirements; do not freeze these proposals as user instructions. |
| Run a separate 900-second search for each item in a fixed nine-release, mode and text/prepared-driver matrix. | The user specified grammar seeds, 15-minute seedless fuzzing and multi-version behavior. The expanded matrix is an AI execution-plan choice, not the wording of the requirement. |
| Preserve every existing timeout and make prepared-test performance fixes a prerequisite for the structural work. | Not a user condition. Existing repository/toolkit requirements still apply, but the memo cannot promote a local test problem into a new work-order requirement. |
| Require one Property class per principal concrete analysis result and enforce removal of the rest. | Withdrawn. Concrete-result grouping is a hypothesis; behavior granularity remains unresolved. Complete generator coverage is the settled requirement. |
| Treat goals A–D, “A complete,” focused test counts and historical checkpoints as the accepted plan and remaining obligations. | These were AI bookkeeping and reports. Reassess progress against the sourced requirements above; do not inherit additional conditions from those records. |

The audit examined the requirements, architecture and checkpoint sections of the
previous memo, and compared their obligations with the human messages in S1–S4.
It is a requirements audit, not a fresh certification of the historical SQL or
test claims. The old detailed design and run records remain in Git history and
the session logs; they no longer form this memo's requirements.

## The recurring user–AI mismatch

The user asked for a small set of mechanisms that can expose defects throughout
a large SQL domain. The AI repeatedly substituted local test success, framework
construction and detailed work reports for establishing those mechanisms.

There is direct evidence beyond a disagreement in wording. The AI reported that
852 of 1,097 counted semantic types only received `assertInstanceOf` checks
(S1:4995). A logged comparison probe returned `raw_equal: false` but
`compared_equal: true` after a missing native/memory row difference was transformed
away (S1:4996–5002). On October 27, the AI acknowledged that the required PBT and
fuzz structure was still incomplete after repeatedly agreeing to prioritize it
(S3:2642, 18498).

The memo amplified this failure: an AI design choice became a written “must,”
new tests enforced it, those tests produced more work, and later reports treated
that work as an outstanding user requirement. For example, the added state audit
can block the ordinary fuzz path on catalog restoration before the generated SQL
reaches MemoryDB. This is visible in `ExecutionTarget::run()`/`observe()` and
`Fixture/Baseline.php`, and was recorded in the previous memo's checkpoints.

In this review, the AI repeated the same mistake by converting the user's
Property-grouping hypothesis into a settled rule. S4:271 explicitly corrects it.
The correction is to retain the stated property and scope, distinguish hypotheses
from requirements, and evaluate implementation choices by whether they realize
the simple verification paths. More conditions in a memo do not resolve the gap.

## Session references

References such as `S1:186` identify physical JSONL lines. Log timestamps are UTC.
Automatically inserted goal continuations and assistant proposals are not human
requests. A request to continue work does not turn every preceding implementation
choice into a fixed acceptance condition.

| ID | File under `~/.codex/sessions/` |
| --- | --- |
| S1 | `2026/10/23/rollout-2026-10-23T08-52-17-01a16607-9bd0-7c90-ab6a-9a87fe2cf1ee.jsonl` |
| S2 | `2026/10/25/rollout-2026-10-25T14-01-21-01a16607-9bd0-7c90-ab6a-9a87fe2cf1ee_01a1716f-495c-7b63-9eb5-ae02044ff1f7.jsonl` |
| S3 | `2026/10/25/rollout-2026-10-25T14-33-23-01a1718c-9e88-7f02-bf62-4c795489b38d.jsonl` |
| S4 | `2026/10/27/rollout-2026-10-27T21-36-11-01a17d5c-6b98-7ce3-a9b0-82afef1490ad.jsonl` |

## Existing uncommitted implementation note

The following pre-existing local note is retained verbatim as historical work
context. Its goals, proposed solutions and acceptance statements are not additional
requirements; the audited requirements above govern this memo.

<details>
<summary>Historical local note</summary>

```text
Fixed duplicate UPDATE aliases and incomplete-input routes remain separate.
At that checkpoint, analysis still mutated shared temporary-table selection and
view caches. The catalog snapshot migration below removes those mutations;
complete read-only analysis is not established. Operation/Facts, runtime derivation and
rendered marker discovery also remain in the migrated families. Complete local
meaning, generators, native catalog providers and state observation, full corpus
replay and the separate 900-second matrix are still required. Goals B-D remain
open, and PR #665 remains a draft.

### Catalog snapshots and explicit session namespaces (2026-10-26)

The shared dictionary now owns persistent objects only. Session table lookup,
installation, removal, planning, hints and transaction publication receive the
owning temporary namespace explicitly. Analysis, rebinding and lifecycle cleanup
no longer swap a dictionary-wide session pointer; lock-wait resumption no longer
needs a callback to restore that pointer.

Stored views retain immutable definitions, creation declarations and dependencies.
A separate dependency traversal computes view bindings before publishing a
declaration snapshot. It resolves persistent dependencies first, propagates invalid
dependencies, and overlays session temporary tables only for the calling scope.
Planning, writable-view column mapping and metadata readers consume these bindings
without installing them in the dictionary. Preparation invalidation records stored
view identities and transitive persistent table identities, not the ephemeral
declarations produced by rebinding. Dependency names remain typed identifiers
instead of being recovered from concatenated strings.

This closes the ambient temporary namespace and mutable view-cache defects, not
goal C. The snapshots still bind through Operation/Facts. Read-time diagnostics,
other retained parser state and incomplete-input paths still need migration.
View write rewriting still uses a flat declaration context: a rewritten persistent
base target and caller subqueries can require distinct bindings for the same
spelling. That remaining boundary must use occurrence-specific semantic references;
removing temporary tables from the whole rewritten statement would be incorrect.
The full generator, corpus and 900-second acceptance requirements remain open.

Verification for this migration is recorded below after the ordinary checks.
```

</details>
