# SQL Semantics and MySQL Memory

Updated: 2026-10-30, after the user's corrections of the generator shape and the PBT goal.

## Mandatory rules from the user's review (must be followed, no exceptions)

These rules override every other note in this memo and every existing piece of code.
The user had to correct the agent repeatedly on these points; the corrections are
recorded here so that they are not lost and not reinterpreted.

1. **What PBT is.** Eris generators generate the types of SQL Semantics analysis
   results. Those generated values run MemoryDB's phases after analysis. The property
   is that no error occurs. The only allowed exception is an error that depends on
   database state, such as a table definition that does not exist. That is the whole
   property. Write it plainly; the user does not want a clever formulation.
2. **What a generator is.** Every class under `tests/Generator` is a final
   `XxxGenerator` that implements `Eris\Generator`. Its constructor composes one
   delegate generator from the generators of the type's operands, following the
   type's structure and invariants, and nothing else. `__invoke` and `shrink` forward
   to the delegate. There is no other method. The canonical shape is:

   ```php
   final class OrderItemGenerator implements ErisGenerator
   {
       private ErisGenerator $delegate;

       public function __construct()
       {
           $this->delegate = Generators::map(
               static fn (array $data): OrderItem => new OrderItem(sku: $data['sku'], quantity: $data['quantity'], unitPriceYen: $data['unitPriceYen']),
               Generators::associative([
                   'sku' => Generators::elements('SKU-A', 'SKU-B', 'SKU-C'),
                   'quantity' => Generators::choose(1, 100),
                   'unitPriceYen' => Generators::choose(0, 10000),
               ])
           );
       }

       public function __invoke($size, RandomRange $rand) { return ($this->delegate)($size, $rand); }

       public function shrink(GeneratedValue $element) { return $this->delegate->shrink($element); }
   }
   ```

3. **What must not exist.** `forms()`, `additionalForms()`, `executionForms()`,
   `operands()`, `fromOperands()`, `selecting()`, `withPrerequisites()`,
   `refused*()` and every other method beyond the three above are not part of a
   generator. Enumerated fixed statement forms such as `'AlterTable/253'` are not
   derived from the type's structure and break the generator. Narrowing the
   candidates of an enum and substituting component generators have no place
   either, not even through constructor arguments: they distort the property that
   is to be established. The agent's proposal to allow narrowing and substitution
   through constructor arguments was wrong.
4. **Enforcement.** A PHPStan rule checks the shape: under `Tests\Generator` every
   class is a final `XxxGenerator`, implements `Eris\Generator`, declares `__invoke`
   and `shrink`, and declares no other method. The rule is added first, every
   generator is made to pass it, and only then is the rest of the goal pursued.
5. **No invented roles.** The agent's remark that fixed statement forms belong to
   sql-faker was unrelated and wrong. sql-faker is the grammar-based SQL generator of
   the fuzz comparison; it has no role in the Eris generators of semantic types.
6. **Plain work.** The user is not asking for the property to be done well; the user
   is asking for it to be done plainly. Attempts to do it cleverly produced the
   errors above and cost the user time.


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

Snapshot after the generator, property and fuzz work of 2026-10-30/31 on branch
`feat/mysql-memory` (delivery through [PR #665](https://github.com/k-kinzal/ztd-query-php/pull/665),
still a draft). This snapshot is **not** an acceptance claim; the open decisions below
need the user's answer.

| Area | Observed state | What remains unproved or incorrect |
| --- | --- | --- |
| Generator shape | `rules/GeneratorShapeRule.php` is registered: every class under `tests/Generator` is a final `XxxGenerator` implementing `Eris\Generator` with `__construct`, `__invoke` and `shrink` only. All generator files pass it. | None known. |
| Generator inventory | `SemanticCatalogue` now requires a generator for every public model type reachable from the statements through constructor parameters (analysis information and source spans excluded): 1,092 types. 1,103 generator files remain (11 outside the inventory are used by tests); 59 unreachable generators (facts, notices, shapes, contracts, catalogs, prepared-statement internals) were removed. | The reachability follows typed parameters and `@param` docblocks; a type referenced only through an untyped array is not required. |
| Generator smoke (8 values per generator) | 8.4.7: 1,084 of 1,086 generators pass; 5.7.44: 1,033 of 1,036; 5.6.51: 1,023 of 1,027; 8.0.44, 8.1.0, 8.2.0, 8.3.0, 9.0.1 and 9.1.0 passed before the last two batches. | Residual: `Update`, `WindowRefinement` (release mixing at 8.4.7), `Update`, `CreateFunction`, `CreateTrigger` (5.7.44 acceptance), `InsertQuery`, `QuantifiedComparison`, `CreateFunction`, `OrderedSetOperation` (5.6.51). |
| PBT policy | Each property allows only the `SqlError` codes listed in its `MISSING_STATE` (database objects, declarations, credentials, installed components, replication state, server capabilities); every other error fails. Statement generators keep only statements whose analysis records no parse-level refusal and no structural problem (`Tests\Support\Analysis`). | Whether analysis-level refusals may be excluded from generation, and whether server capabilities count as database state, are open decisions (below). |
| Property suite | 482 cases (185 statements across the releases their forms support). Errors fell from 288 to 99 over six runs. The 99 split into MemoryDB defects (26: "No executable type" for operands typed only at run time such as parameters and user variables, one `intdiv()` float `TypeError` in KILL, "retained generation marker" invariant violations in ALTER TABLE, SHOW PARSE_TREE rendering), generator defects (13: release mixing in UPDATE, LOAD DATA, INSERT at 5.x; an impossible native call in CREATE INDEX), and 60 server refusals that do not depend on database state (listed in `build/property-nonstate.txt`). | Not green. The run uses `paratest --processes=3` without Xdebug; `--log-junit` output is kept in the scratchpad per run. |
| Native fuzz path | `ExecutionTarget` compares every reply exactly except: SHOW STATUS counters observed to differ between two fresh servers started one after the other (`build/native-twins`), the startup counters of the image, SHOW VARIABLES instance identity (host name, derived file names, server UUID) and connection/statement identifiers, SHOW ENGINE INNODB STATUS logical sections, and SHOW ENGINE INNODB MUTEX record form. | The connection/statement identifiers (`pseudo_thread_id`, `statement_id`) and the startup counters follow the image's readiness connections, not server variability; keeping them is a decision for the user. The 5.6/5.7 InnoDB monitor is not parsed (`InnoDbStatus::logical` returns null), so those releases cannot pass that comparison. |
| Native grammar replay (8.4.7) | 2,180 seeds (the 100 % production manifest): 2,161 pass. 19 fail and are set aside under `build/native-replay-847-failing` with one log per run (`build/native-replay-847-run*.txt`). | Failing seeds: SHOW [GLOBAL\|SESSION\|LOCAL] STATUS (counters MemoryDB does not model: handler, binlog cache, connection and InnoDB internals), SHOW ENGINE ALL STATUS/MUTEX, SHOW RELAYLOG EVENTS (relay log named after the host), CREATE FULLTEXT INDEX (duplicate-name check order), SHOW INDEX/KEYS (index comment lost), REPAIR TABLE USE_FRM on a temporary table, DESC ANALYZE (plan text), SHOW PROCESSLIST (connection ids), SHOW OPEN TABLES, CREATE AGGREGATE FUNCTION under LOCK TABLES (fixed after the run, not re-verified), XA BEGIN after work outside the transaction, SHOW PROCEDURE/FUNCTION STATUS (creation time). |
| Seedless native fuzz (8.4.7) | 15-minute empty-corpus run: 300 generated inputs verified, no failing case (`build/native-seedless-847.txt`). | The run used the SHOW VARIABLES exception above; without the connection/statement identifier part it would stop at the first SHOW VARIABLES. |
| Ordinary checks | Unit suite: 14,380 tests, 38 failures: 9 `SystemSchemasTest` (104–184 declared system tables without row providers per release, a known gap) and 29 one-second small-test timeouts while the smokes, PHPStan and the fuzz ran in parallel. The `sql-semantics-mysql` tests of the refactored rules pass. | The timed-out tests must be rerun on an idle machine; PHPStan over the whole package was still running when this snapshot was written (see PR #665 for the result). |

### Decisions needed from the user

1. Statements whose analysis records a parse-level refusal (`ParseFailure`) or a structural problem other than a missing object are not generated for execution (`Tests\Support\Analysis::accepted`). MemoryDB turns those problems into errors that do not depend on database state, so the property cannot hold for them. The alternative is a second property that checks the mapped error.
2. Server capabilities are treated as database state: debug-only statements (SHOW PROCEDURE CODE, SHOW PARSE_TREE), XA START/END options the server never supports, components and plugins that are not installed, replication that is not configured.
3. Server refusals that depend on the value written, not on state, remain failures: for example `Incorrect usage/placement of 'SQL_CACHE'`, `Variable 'MYSQL_ERRNO' can't be set to the value of 'NULL'`, invalid SRS definitions. Many were removed by generating valid values (temporal literals, SQLSTATEs, charsets, collations, system variables, EXPLAIN formats, JSON attributes, JSON paths, replication filter patterns, component URNs, password hashes); the remaining 60 are listed in `build/property-nonstate.txt`.
4. Operands typed only at run time (parameter markers, user variables) reach MemoryDB's compiler with no resolved type and raise a `LogicException` instead of executing or failing as the server would. This is a MemoryDB defect class, not a generator issue.
5. `Tests\Support` is a namespace prefix the toolkit lint forbids; the helpers (`Analysis`, `Operands`, `Embedding`, `Bindings`, `Columns`) live there because the rename was declined. A permitted name is needed.
6. Exact comparison of SHOW STATUS cannot pass without modelling InnoDB handler statistics and the image's provisioning connections; the memo's rule leaves only the two options above.

### Existing verification evidence and its limits

Local logs under `packages/mysql-memory/build/` are evidence for particular runs, not
portable completion gates:

- `native-replay-847-loop.txt` and `native-replay-847-run*.txt`: the iterative replay of the 2,180 seeds.
- `native-seedless-847.txt`: the 15-minute seedless campaign.
- `native-twins/twinseq-*.txt`: SHOW STATUS, SHOW VARIABLES and SHOW ENGINE replies of two fresh servers started one after the other (8.4.7 with and without a write history, 5.7.44).
- `property-nonstate.txt`: the server refusals of the last property run that do not depend on database state.

An earlier set of "two-server" logs measured one reused container twice and was discarded; `Testcontainers::run()` returns the running container of the same class, so the experiments stop the first server before starting the second.

### Known implementation gaps

Missing system-table row providers (9 unit failures), the unknown-type compiler
failures above, SHOW STATUS/PROCESSLIST/OPEN TABLES counters and identities, the
5.x InnoDB monitor parser of the fuzz target, EXPLAIN ANALYZE plan text, the index
comment of CREATE INDEX in SHOW INDEX, REPAIR TABLE USE_FRM on temporary tables,
the duplicate-key-name check order of CREATE FULLTEXT INDEX, XA START after work
outside a global transaction, and the earlier recorded findings (prepared execution
after its default database is dropped, implicit base-table locks for views, global
read locks, UPDATE target-table subquery restrictions, KILL connection identities,
legacy performance_schema removal, partial KEY partition routing, legacy temporary-table
IMPORT, oversized VARCHAR conversion, nondeterministic ALTER defaults, replication
receiver state).

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
