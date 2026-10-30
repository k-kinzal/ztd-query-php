# SQL Semantics and MySQL Memory

Updated: 2026-10-30, after the user's latest correction in this conversation.

## Goal and authority

Build MySQL Memory so that it correctly executes SQL Semantics' meaning-bearing,
structured values after analysis, and verify its behavior against real MySQL.
The required verification consists of **two mechanisms**:

1. PHP-Fuzzer generates SQL through SQL Faker and compares execution on MemoryDB
   with execution on real MySQL started through Testcontainers.
2. PHPUnit/Eris generates SQL Semantics values and executes MemoryDB's phases
   after SQL Semantics analysis. PHPStan rules guarantee corresponding generators
   exist for all semantic model types.

The user's latest clarification governs this memo. It supersedes the previous
"three checks" description and the previous blanket permission to accept SQL
errors in PBT. SQL -> semantic model -> SQL reconstruction is **not required**.
Its earlier test results do not satisfy either mechanism above and must not be
reported as progress toward their acceptance conditions.

This memo records requirements, observed implementation state, and remaining
work separately. Existing code, previous agent plans, test counts and old goal
labels do not create requirements. Historical notes remain in Git history rather
than being repeated here as competing instructions.

## Required verification

### PHP-Fuzzer: compare execution with real MySQL

```text
PHP-Fuzzer input -> SQL Faker -> the same generated SQL
                                  |              |
                                  v              v
                              MemoryDB       real MySQL
                                             via Testcontainers
                                  |              |
                                  +-- compare ---+
                                   execution results
```

The normal fuzz path uses MemoryDB and **one** real database. The rule about
real-database differences does not require three databases, two native servers
per trial, or a second native execution for every generated input.

Only differences that also occur between real database executions under
comparable versions, settings and input histories may be accepted as differences.
A MemoryDB limitation, a plausible "environmental"
explanation, or two values having the same PHP type is not sufficient. Any
exception needs evidence for the particular real-database variability and must
remain limited to that variability. Deterministic result content must still
match. Do not delete rows, columns, diagnostics or other mismatches to obtain a
passing comparison.

SQL errors in this differential check are observable execution results: compare
the actual errors and any partial results. Matching native and MemoryDB SQL
errors are distinct from PBT's success property below. MemoryDB implementation
failures must fail the fuzz run even if a transport presents them as SQL errors.

The two required fuzz acceptance results are:

- A successful **15-minute run without supplied corpus seeds**, starting with an
  empty corpus and still generating SQL through SQL Faker.
- Successful execution comparison for **all SQL Faker seeds establishing 100%
  grammar coverage** of the applicable grammar. Verify the corpus against SQL
  Faker's coverage contract; neither a file count nor a partial replay proves
  success. Do not omit failing productions or narrow the grammar to pass.

Record the source revision, database release, grammar/profile, commands,
completed inputs, elapsed time and failures. A recorded mutation RNG seed is
useful for reproduction and is not a supplied corpus seed. Supported-version
obligations remain; a separate 900-second run for every conceivable combination
of release, mode and driver was an agent-created matrix, not a user requirement.

Keep the comparison direct, using the existing SQL Faker, database and
Testcontainers APIs. Do not restore the rejected observation/fixture/lifecycle
framework, a dedicated Diff suite, or per-input catalog/state synchronization.
Keep the generated history available for reproducing state-dependent failures.

### Eris: execute generated semantic values after analysis

```text
Eris generators for all semantic types
    -> composed, meaning-bearing concrete analysis results
    -> MemoryDB's post-analysis phases
    -> successful operation
```

Every meaning-bearing, structured model type in `sql-semantics` and
`sql-semantics-mysql` needs a corresponding real Eris generator, including nested
values, interfaces and enums in the model. Generators must be used in executable
compositions. Merely constructing objects, checking their PHP classes, or
referencing generator classes does not establish correct execution.

PHPStan must guarantee the model-to-generator mapping. Discover the model
inventory independently of the generator inventory so that a missing generator
cannot disappear from the checked set. Keep the requested configurable
class-unused rule, with its selected classes configured in NEON. Static existence
and reachability checks do not prove that all value domains are exercised.

Start execution with a complete concrete result of SQL Semantics analysis.
MemoryDB must consume its semantic information through the phases after analysis.
Do not reconstruct SQL and analyze it again, or recover missing meaning from
retained SQL text or parser ASTs. Typed construction/publication in generators
must produce the complete semantic input needed by those phases.

**SQL errors are not generally acceptable in PBT.** An error caused by runtime
state, such as a referenced table not existing, may be allowed. This is not a
permission to catch every `SqlError`: non-state-dependent SQL errors must fail,
as must representation errors and implementation failures. If generated
prerequisites satisfy the statement's state dependencies, the generated sequence
must complete successfully.

Generate dependent inputs through Eris composition, for example a table
definition followed by an insertion for that definition. Preserve those
relationships during standard Eris shrinking. The `then()` callback executes the
generated inputs; it must not reconstruct their dependencies. Fixed expected
error codes, account snapshots, source-text markers and scope-cleanup assertions
can belong to other tests, but they do not define this execution property.

Property units are the **concrete types returned by `analyze()`**, not each small
nested type and not one aggregate search for all statements. Use this structure:

```text
packages/mysql-memory/tests/
├── Generator/
│   ├── Semantics/   # Mirrors the core semantic model types
│   └── MySql/       # Mirrors the MySQL semantic model types
└── Property/
    ├── AlterDefaultRoleProperties.php
    ├── ChecksumTableProperties.php
    ├── FlushTablesProperties.php
    └── <ConcreteAnalysisResult>Properties.php
```

There are no `MySql` or `Statement` subdirectories under `Property`, and no
`tests/Eris` directory. Each property class has a runnable `satisfies*` method
and starts from its concrete generator, such as
`new AlterDefaultRoleGenerator($release)`, rather than a generic factory selected
by a class-string. Nested generators compose into executable roots. Use Eris's
own generation and shrinking; do not relocate rejected custom machinery.

Concrete-type separation allows independent counterexample searches and budgets.
The 15-minute requirement belongs to PHP-Fuzzer; it is not a per-property Eris
requirement. Report actual PBT trials and duration without inventing a new gate.

## Current implementation state

Implementation snapshot before this memo update: branch `feat/mysql-memory`,
pushed implementation commit `c06767f26a2`, with the uncommitted CHECKSUM work
listed below. Delivery remains through
[PR #665](https://github.com/k-kinzal/ztd-query-php/pull/665), which is a draft.
This snapshot is **not** an acceptance claim.

| Area | Observed state | What remains unproved or incorrect |
| --- | --- | --- |
| Property layout | 185 `*Properties.php` classes directly under `tests/Property`; no subdirectories; `tests/Eris` is absent. | Correct layout does not establish the correct property. |
| Generator inventory | 1,162 `*Generator.php` files; the recorded semantic inventory also contains 1,162 types and 185 concrete MySQL analysis-result types. | The inventory selector and actual generated domains still need review against all semantic model types. These counts are observations, not fixed targets. |
| PHPStan enforcement | `SemanticGeneratorCoverageRule`, `SemanticCatalogue` and `UnusedClassRule` are registered. The coverage rule checks instantiable `Eris\Generator<ModelType>` implementations; class usage is configured in NEON. | The current catalogue selects public model types by namespace, visibility and value-shape rules. Confirm that this selection omits no required semantic type. Static use does not prove downstream execution coverage. |
| PBT entry boundary | Concrete generators publish typed structures without SQL rendering/parsing. Properties call `Execution::statement()`; dependent sequences are composed in generators. | Review all paths and nested/conditional values, not only the demonstrated statements. |
| PBT error policy | **All 185 property classes contain an unqualified `catch (SqlError)`; there are 187 such catch blocks.** They accept SQL refusals without checking their cause. | **This violates the clarified requirement. PBT is not complete, and prior passes do not establish the required property.** |
| Native fuzz path | `fuzz_mysql_execution.php` uses SQL Faker, one Testcontainers MySQL and one MemoryDB. `ExecutionTarget` compares returned rows, metadata, completion fields and SQL errors, and checks MemoryDB's internal-failure channel. | Existing status/engine/latch exceptions permit some unequal values using domain checks. Their scope and real-database evidence require review under the clarified exception rule. The comparison is not universally exact. |
| Native grammar replay | The current replay corpus has 2,180 files. The MySQL 8.4.7 default manifest reports 3,113/3,113 reached productions. Replay passes the latest FLUSH counterexample but stops at CHECKSUM. | A complete successful execution comparison of the 100%-coverage seeds is missing. Corpus coverage metadata is not execution-success evidence. |
| Seedless native fuzz | No passing 15-minute empty-corpus native comparison has been established. | The required run remains outstanding. Prior SQL-reconstruction fuzz results do not count. |
| Ordinary checks | Full static checks passed for the pushed FLUSH change. The last full MemoryDB test run still failed. | The current dirty tree has not passed those checks; ordinary failures must be resolved. |

### Existing verification evidence and its limits

Local logs under `packages/mysql-memory/build/` are evidence for particular runs,
not portable completion gates or a replacement for the requirements:

- `remaining-property-shape-audit.json` records the 1,162/185 inventory.
- `remaining-rename-full-property.txt` reports 683 PBT data cases passing in
  11 minutes 58 seconds. It predates later additions and uses the incorrect
  broad SQL-error allowance. It does **not** prove the corrected PBT property.
- `remaining-flush-lock-tests-final.txt` reports 443 focused cases passing;
  `remaining-flush-legacy-tests.txt` reports 373 cases after the legacy follow-up.
  FLUSH comparisons matched 678 replies per release across nine releases.
  These are focused regression results, not full-corpus acceptance.
- `remaining-flush-lock-lint-verified.txt` records the static checks before the
  uncommitted CHECKSUM changes.
- `remaining-flush-lock-native-corpus.txt` stops at
  `opt_checksum_type-2.txt`: `CHECKSUM TABLE _sqlfaker_identifier EXTENDED`
  returns `3539857293` on native MySQL and `2587194292` on the then-current
  MemoryDB implementation.
- `remaining-tablespace-full-suite.txt` reports 11,809 tests, 140,371 assertions,
  nine missing-system-provider failures and one one-second PreparedCache test
  timeout. A separate passing cache test does not turn this full run green.

### Work currently in progress

The last pushed changes corrected table-flush metadata locks, temporary-table
lookup, and legacy diagnostics. Earlier focused fixes covered LOAD destinations,
replication commands, renames and other counterexamples.

The current uncommitted work replaces CHECKSUM's PHP-serialized-row CRC with
column-type-specific record bytes. The changed files are:

- `packages/mysql-memory/src/Command/Maintenance/ChecksumCommand.php`
- `packages/mysql-memory/src/Storage/Checksum.php` (new)
- `packages/mysql-memory/src/Storage/ChecksumField.php` (new)
- `packages/mysql-memory/tests/Unit/Command/Maintenance/ChecksumCommandTest.php`

These changes have not been validated, committed or pushed. They must not be
counted as a resolved counterexample. Correcting the overall PBT error policy and
fuzz exception policy takes priority over continuing isolated SQL fixes.

### Known implementation gaps

Recorded unresolved findings include missing system-table row providers,
prepared execution after its default database is dropped, implicit base-table
locks for views, global read locks, UPDATE target-table subquery restrictions,
and CHECKSUM record encoding. Other recorded differences concern connection
identities in KILL, SHOW OPEN TABLES cache behavior, legacy performance_schema
removal, partial KEY partition routing, legacy temporary-table IMPORT, oversized
VARCHAR conversion, and nondeterministic ALTER defaults. Replication receiver
state and general EXPLAIN plan/cost behavior are also incomplete.

Reproduce and resolve these findings through the corrected checks. They are
implementation findings, not an exhaustive specification or permission to add
comparison exceptions. In particular, classify any alleged native variability
under the user's rule instead of assuming it from the feature's name.

## Work required to reach the goal

Work on the verification design across the relevant scope **before** returning
to one-counterexample-at-a-time fixes. The next work is:

1. **Correct PBT's success criterion across all properties.** Replace the blanket
   `SqlError` acceptance with handling limited to errors actually caused by
   runtime state. Other SQL errors and implementation failures must fail. Ensure
   generated prerequisites lead to successful execution and that a test can
   expose a non-state SQL error instead of swallowing it.
2. **Audit generator completeness and composition.** Check the independently
   discovered semantic inventory, the corresponding generators, their use in
   executable roots, valid combinations, dependent state and shrinking. Include
   nested, conditional and diagnostic-bearing values. Their mere appearance in
   separate error-code tests does not establish this PBT property. Correct the
   generators or semantic model where the required property reveals a gap;
   do not suppress failures or omit difficult types. Run the complete PHPStan
   coverage/unused checks and the corrected concrete-type PBT suite.
3. **Audit fuzz comparison exceptions.** Review `ExecutionTarget::status()`,
   `environment()`, `engines()` and `latches()`. Retain only differences supported
   by real-database variability, with the exception limited to what the evidence
   establishes. Remove unsupported relaxations. Keep one real database in the
   normal comparison path; this audit does not authorize a three-database design.
4. **Resolve exposed implementation defects.** Resume CHECKSUM and the other
   reproduced findings after the verification mechanisms enforce the intended
   contracts. Add relevant regression tests and compare actual native replies.
   Implement missing behavior rather than empty providers or output masking.
5. **Run ordinary checks on the resulting revision.** Resolve the full-suite
   failures and run the relevant repository quality checks. Distinguish focused
   passes, a full-suite pass and checks that predate later edits.
6. **Meet both native fuzz conditions.** Verify the SQL Faker 100%-coverage seed
   inventory and successfully compare all its inputs; separately run the same
   differential target from an empty corpus for 15 minutes without a failing
   case. Fix discovered counterexamples and repeat the affected verification.
   State the releases/profiles actually tested and remaining version coverage.
7. **Deliver and report accurately through PR #665.** Report the two required
   mechanisms, corrected PBT results, PHPStan coverage, ordinary checks and the
   native fuzz evidence. Remove reconstruction from the PR/goal completion
   narrative. Do not declare completion while these requirements remain unmet.

SQL reconstruction work is outside this task's acceptance scope. This memo does
not require deleting unrelated renderer functionality or existing package tests;
it removes reconstruction as a requested workstream and completion condition.

## Corrections the user has repeatedly had to make

| User instruction or correction | Mistake that must not recur |
| --- | --- |
| First refresh the overall verification system and structure, then refine individual behavior. | Continuing local fixes while the verification design remains incorrect, or treating a plan/one working path as the completed refresh. |
| Use the concrete values returned by SQL Semantics `analyze()` as executable PBT roots. | Testing small leaf types alone even though they must compose into a complete request to execute MemoryDB. |
| Put property classes directly under `tests/Property`; show the intended tree clearly. | Retaining unnecessary `MySql`/`Statement` nesting or answering about file contents when the objection is the directory structure. |
| Separate searches by concrete analysis-result type for counterexample efficiency. | Putting every statement into one aggregate property/factory, or confusing generator coverage with property granularity. |
| Use a concrete generator such as `new AlterDefaultRoleGenerator($release)`. | Hiding the root behind `StatementGenerator::accountRequests($release, SomeType::class)` and similar generic selectors. |
| Use standard Eris generation, composition and shrinking. Delete `tests/Eris`. | Building custom sampling/execution/shrinking machinery or relocating the rejected machinery under a different directory. |
| Test successful consumption by the phases after analysis. | Substituting `assertInstanceOf`, expected error codes, account snapshots, source markers and cleanup assertions for the requested execution property. |
| Generate dependencies inside generators. | Building the semantic input or its prerequisite sequence in `then()` and losing coherent shrinking. |
| Provide generators for all meaning-bearing, structured semantic types and enforce their existence with PHPStan rules. | Covering only selected statements, counting files as behavior coverage, or allowing unused generators to give the appearance of completeness. |
| Only state-dependent SQL errors may be allowed in PBT; other SQL errors are failures. | The agent's broad interpretation that any SQL refusal is acceptable without generated prerequisites. The current catches still embody this mistake. |
| PHP-Fuzzer must use SQL Faker and compare MemoryDB with a real DB started through Testcontainers. | Replacing that check with reconstruction, a private assurance framework, state restoration, or locally asserted expected outcomes. |
| Accept only differences that also occur between real DB executions; this does not mean using three databases. | Arbitrary "environmental" exemptions, type-only checks without evidence, or adding a second native server to every trial. |
| Pass SQL Faker's 100%-grammar-coverage seeds and run seedless fuzz for 15 minutes. | Reporting a partial corpus, focused native probes, a different fuzz target, or an invented version/profile/time matrix as fulfillment of the request. |
| SQL -> semantic model -> SQL reconstruction is unnecessary; the latest clarification removes it. | Continuing to call it one of three required checks or counting its successful runs toward this goal. |
| State current status, shortages and the actual goal accurately. | Promoting agent-generated plans into user requirements, preserving contradictory historical notes as instructions, or reporting local passes as overall completion. |

Earlier project conventions continue to apply: use a dedicated worktree and PR;
write artifacts in English; preserve protected files; follow the other `sql-*`
packages' ordinary test organization. Keep semantic meaning in the relevant typed
structures rather than a generic `Operation`/`Facts`/`SourceMap` aggregate or a
parser/text fallback. The public database API uses PDO or mysqli with an explicit
MySQL version. Do not restore the rejected `mysql-memory/docs`, `resources`, `bin`
or auxiliary test directories as workarounds. These conventions do not add new
verification mechanisms beyond the two specified above.
