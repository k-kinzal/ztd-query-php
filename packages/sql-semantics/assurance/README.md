# SemanticsIR evidence and acceptance

The [initial contract](contract-v1.json) fixes the first executable SQLite 3.47.2 domain, the broader required scope, the observation contract, the finite domain, the exploration budget and F01–F12. Human review and a repository-level independent acceptance gate are pending. A result from this runner is provisional.

## Reproduce the evidence

Run from a trusted checkout with Docker, PHP, Python 3.9 or newer, Composer, a C compiler and the standard download tools. Install audit dependencies from the trusted checkout. Prepare the pinned SQLite engine and the pinned PHP image.

```sh
export COMPOSER_ROOT_VERSION=dev-main
composer install --working-dir=packages/sql-semantics-sqlite --no-plugins --no-scripts --prefer-dist
third_party=$(mktemp -d)
curl -fsSL https://sqlite.org/2024/sqlite-amalgamation-3470200.zip -o "$third_party/sqlite.zip"
echo "aa73d8748095808471deaa8e6f34aa700e37f2f787f4425744f53fdd15a89c40  $third_party/sqlite.zip" | shasum -a 256 -c
unzip -q "$third_party/sqlite.zip" -d "$third_party"
cc -O2 -DSQLITE_THREADSAFE=1 -shared -fPIC "$third_party/sqlite-amalgamation-3470200/sqlite3.c" -o "$third_party/libsqlite3.so"
docker pull php@sha256:f1ef73e865793418aac8cf6d8dbb44b33b527cdac62dbcf2e47061ed8f40b81a
```

Point the runner at a separate candidate checkout. The output directory must be outside the checkout and must be new.

```sh
evidence=$(mktemp -d)/semantics-ir-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$evidence"
```

The host owns the case generation, comparator, SQLite database, and evidence path. The candidate worker mounts the verified source and resource trees read-only. It has no network, no added capabilities, and memory and process limits. The [SQLite observer policy](sqlite-observer-v1.json) permits the registered fixture operations during preparation and evaluation. Authorization failures abort the observation. Separate controls check that a healthy query succeeds and that database attachment, file output, an unregistered PRAGMA and an unregistered function are denied. The static inventory reads PHP as data without loading the candidate's Composer autoloader. `--local` disables worker isolation for debugging. The evidence records the actual isolation mode.

Use `--verify-distribution "$evidence/distribution.json"` with the same arguments to compare a checkout with the captured files. The manifest covers source, resources and locked dependencies of the four Semantics packages and SQL Parser. These files must match the paths and blobs in the candidate commit before and after observation collection. An archive that omits locked files must restore them from the candidate commit first. It does not pin the contents of a downstream Composer installation. Use the library and tool digests recorded in the evidence when comparing runs.

## Verify an installed Composer layout

Run `verify_release_layout.py --candidate /path/to/candidate --tool-autoload /path/to/verifier/vendor/autoload.php --out release-evidence` from a separate copy of the reviewed assurance tools. Give it a clean full checkout of the candidate and the pinned PHP container image used by the assurance workflow. Bash, jq and Composer must be available on the controller.

For a host PHP diagnostic, add `--local` and use a separate evidence directory. The verifier still checks the exact installed bytes, role paths, native enum routes and post-install mutation. The report records the host PHP version and a separate provisional status. The acceptance bundle requires the isolated worker image. Reproduce the host logs with `replay_release_layout_local.py --candidate /path/to/candidate --evidence release-evidence --out release-evidence/local-replay.json`. The replay verifies each observed publication and enum case, rejects changes to the recorded installation mutation, and verifies that the host report fails the isolation requirement.

The verifier applies the pinned split workflow's Composer transformation and uses offline mirrored packages for the development version and an example tagged version. It compares the installed source and resource bytes to the measured commit, loads the dialect classes through installed autoload in a read-only container, and exercises each statement root. It then injects a change into an installed PHP file and verifies that the byte comparison rejects the change. The report identifies the staged artifacts, observed paths and installation profiles. The review bundle reconstructs the exact installed mutation from the candidate source and verifies both the unified patch and detector receipt. It also loads every registered concrete role and each shared interface through Composer, records the reflected source path and source hash, and checks each role’s kind and finality. The review bundle replays the raw probe log and links each constructor to both installed profiles. Use the same comparison against the final published package artifacts at release.

## Publication contract

The [publication contract](publication-v74.json) fixes the complete runtime surface. The dialect façade binds input SQL, verifies semantic diagnostics and publishes through the registered value constructors. The catalog builder accepts relation definitions. Grammar fixture rendering runs in the test harness. Public return declarations are inspected for generic routes, including nullable arrays, iterators, and missing return types. The staged Composer probes check each registered enum case by direct access, enumeration, selection and native restoration, and compare the ordered cases against the independent source inventory.

## Read the evidence

- `index.json` identifies the candidate commit, contract, tool files, SQLite build, supported scope, executed checks and fault records.
- `inventory.json` identifies named types, visibility, properties, traits and constructor or factory signatures across all packages. The baseline inventory procedure and gaps are in [baseline.md](baseline.md).
- `grammar-scope.json` enumerates the versioned grammar rules independently of the PHP model and generators. The original recorded counts cover nonempty alternatives. The new index gives both counts and preserves the empty alternatives. The links to partial domain records identify related checks. Every syntax alternative still needs semantic classification.
- `events.jsonl` contains input, constructed shape, emitted SQL, storage-class-tagged results, binding metadata and reported rejections. Finite completion is checked against independently computed symbolic IDs.
- `counterexample.json` is written on failure inside the main run. The original input and observed failure remain available even if a smaller reproducing input cannot be found. A shrink is accepted only when it constructs and still fails the independent comparison.
- `faults/` contains F01–F12 patches, original and mutated digests, fixture inputs, the healthy control and the observed rejection signals. The fault results establish detection of these particular faults.

The independent evaluator reads the direct construction inputs and implements selection, projection, literal values, parameter slots and SQLite arithmetic for the fixed test data. It shares the structure vocabulary and fixture restrictions with the case generator. PHP supplies the observed structure and SQL only. SQLite supplies ordered names, native parameter slot labels, runtime values and storage types through its C API. The comparator preserves repeats and compares rows as a multiset under the contract's lack of guaranteed ordering. The fixed table contains distinct column values, repeated rows, NULL, values of each SQLite storage class, and an alternate table in the temp schema. Analyzed-SQL examples separately compare the original and emitted SQL on the same engine.

The checked domain supports a composition argument. Every leaf has one of the closed roles. Each arithmetic node has two ordered children of those roles. The constructor visits every occurrence, checks the source slot range, and bounds expansion. All lists and children have immutable value semantics. The renderer emits the two operands with the recorded operator inside parentheses, or an explicit leaf representation. A schema-qualified source and an explicit output alias preserve the external identity and result names. This is an argument for human review. The finite and seeded SQLite comparisons are empirical evidence. The PHP code shape audit checks the recorded property types, method names, traits, dependencies and known dynamic constructs. It does not prove arbitrary method bodies or establish the correctness of parser lowering and declaration resolution.

Keep each native record in `$evidence/native/<engine>-<release>/<domain>/` for local runs. To replay evidence downloaded from the native workflow, keep its artifact directories under `$evidence/native/` and select `--layout workflow`. The collector attempts every registered domain, records each exit code, and stores the per-domain receipts and replay logs. It fails if a required release or its receipt is missing.

```sh
python3 "$verifier/collect_native_replays.py" --candidate "$candidate_sha" \
  --candidate-root "$candidate" --evidence-root "$evidence" --layout release
python3 "$verifier/index_evidence.py" --candidate "$candidate" --verifier "$verifier" \
  --evidence-root "$evidence" --out "$evidence/review"
```

Use the same archived assurance directory in both commands. Keep the initial SQLite records in `semantics-ir-evidence/` and the SQLite replay in `sqlite-index/index.json` under the evidence root. The native collector writes `native-replay/` with paths relative to the receipts. Preserve the original raw records. The indexer verifies the candidate, recorded tool digests and replayed event references. The initial assurance workflow also generates a provisional `review-bundle/` alongside the SQLite records, native construction audit and staged Composer evidence. Combine the independently collected native matrix under the same evidence root to include its release receipts.
It also emits `constructors.json` with each audited type, source digest, constructor signature, publication routes and contract pointers. The trace links selected method templates and installed root examples. The [constructor declaration supplement](constructor-declarations-v12.json) fills the required descriptive fields and fixes the common P01–P06 and Q01–Q05 criteria for the registered roles. The trace verifies complete role declarations and records their approval status. Each linked receipt records coverage within its measured domain. Remaining obligations stay open. The assurance controls remove a constructor and a publication path from the recorded inventory and weaken the output obligation in a copy of the declaration contract. Each change must trigger its specified check. It lists missing evidence and open acceptance requirements in the index.

The [folded SQLite binding record](sqlite-folded-binding-sources-v4.json) ties the empty-membership checks to the exact transient syntax sources. The graph audit follows all syntax edges. The binding walk omits only the operand edge identified by the SQLite wiring for an empty IN list. Two source mutations exercise the full audit and the edge classifier independently.

### Check the PostgreSQL rebuild spelling proof

Use a Linux ARM64 controller with Bubblewrap and the official Lean 4.36.0 release archive named in the [frozen lexical challenge](formal/postgres_reindex/contract-v7.json). Unpack the release outside the candidate, and keep the verified archive for the toolchain check. Use a separate trusted verifier checkout and its PHP parser autoload file.

```sh
python3 "$verifier/verify_postgres_reindex_proof.py" \
  --candidate "$candidate" --candidate-sha "$candidate_sha" \
  --tool-autoload "$tool_autoload" \
  --lean "$lean_release_directory" --archive "$lean_release_archive" \
  --out "$evidence/postgres-reindex-proof"
```

The checker validates three exact theorem types and their empty transitive axiom sets. It runs the Lean, lean4lean, con-leche and con-ron kernels. It rejects weakened and unfinished proofs of both command headers with prescribed signals. The PHP correspondence checks the actual AST bodies of the six rebuild renderers, their coordinate renderers, the identifier representation and the placement prefix. It applies a changed delimiter and checks the expected boundary rejection. Source, build and export, physical package distribution, toolchain and fault evidence carry separate digests.

The theorems cover canonical lexical rendering for complete rebuild values with or without a resolved tablespace under the fixed name interpretation. Consult the native rebuild receipts for observed physical storage changes, transaction outcomes and ordered PostgreSQL reports. Both records remain subject to review and protected acceptance. [Lean proof-validation reference](https://lean-lang.org/doc/reference/latest/ValidatingProofs/)


### Reproduce the SQLite matrix

Use an archived verifier checkout and a new evidence directory for the complete SQLite schedule. The launcher checks that every domain has both a runner and a replay observer. It checks that each source mutation can be applied, executes each domain in its own process, and runs the independent collector after all domain attempts. The launcher writes its full plan before starting and records each child start and exit as it occurs. Its final receipt links the journal, logs and artifacts to the measured candidate and SQLite binary. The manual workflow on main runs the same schedule from its trusted workflow revision.

```sh
candidate=/path/to/candidate
verifier=/path/to/archived-verifier/packages/sql-semantics/assurance
tool_autoload=/path/to/trusted-verifier/packages/sql-semantics-sqlite/vendor/autoload.php
candidate_sha=$(git -C "$candidate" rev-parse HEAD)
evidence=$(mktemp -d)
python3 "$verifier/run_sqlite_matrix.py" --candidate "$candidate" --candidate-sha "$candidate_sha" \
  --tool-autoload "$tool_autoload" --sqlite-library "$third_party/libsqlite3.so" --evidence-root "$evidence"
```

For example, `--diagnostic sqlite-not-null-conflict-v2.json` runs one named contract. Its receipt lists all omitted domains. The bundle checks the exact schedule and absence of skipped executions for a full matrix. The matrix runner retains raw outputs even if a domain exits with an error.

### Reproduce the native release matrix

The native join runner also saves the original request and failure before a reduction. The [native reduction contract](native-counterexample-reduction-v1.json) covers direct projections on the same fixture when dropping an output position leaves the other premises intact. A reduced case must reproduce the same comparison failure family and exact witness twice. The separate receipt includes every attempted probe and links back to the unchanged original.

The initial and relational SQLite schedules retain the full input and measured failure before trying a smaller request. Their [reduction contract](counterexample-reduction-v1.json) bounds probes and requires the smaller direct case to reproduce its failure twice through the same construction and comparison. Reproduced witnesses and failed reductions have separate digest-linked records.

To run the complete native release matrix locally, keep the trusted archive's workflow file next to its `packages/` directory. Install PDO MySQL and PDO PostgreSQL for the observer. The launcher starts a disposable pinned database per release, binds the server to loopback and runs the candidate through the isolated PHP worker. It records per-domain exit outcomes and retains the raw runs for independent replay.

The pinned MySQL 5.6.51 and 5.7.44 images use Linux amd64. On an ARM64 controller with an amd64 executor, preload these exact manifests before starting the matrix. The launcher reads the installed image architecture when it starts each fixture.

```sh
docker pull --platform linux/amd64 mysql@sha256:20575ecebe6216036d25dab5903808211f1e9ba63dc7825ac20cb975e34cfcae
docker pull --platform linux/amd64 mysql@sha256:4bc6bc963e6d8443453676cae56536f4b8156d78bae03c0145cbe47c2aad73bb
```

```sh
python3 "$verifier/run_native_matrix.py" --candidate "$candidate" --candidate-sha "$candidate_sha" \
  --tool-autoload "$tool_autoload" --evidence-root "$evidence"
python3 "$verifier/collect_native_replays.py" --layout release --candidate "$candidate_sha" \
  --candidate-root "$candidate" --evidence-root "$evidence"
```

The container launch, image check, local endpoint discovery and native PDO profile qualification share the fixed 900-second setup deadline. The launcher records the failure phase and cleans up its named fixture after an interrupted launch. The fixture probe ledger retains every startup attempt. The launcher begins the domain budgets after observing the fixed server profile. The [fixture launch recovery policy](native-fixture-port-recovery-v1.json) allows one new launch after a specific RootlessKit loopback port collision and a native check that the failed container is absent. The original failure remains in the receipt. Run `check_fixture_launch_recovery.py` to exercise the recovery and incomplete outcomes.

The native launcher records the source and unique match for every scheduled renderer fault in `native-launch/renderer-fault-sources.json`. It continues running the scheduled domains and records an incomplete matrix if any fault cannot be applied. The individual runtime ledgers still require a healthy control and the exact detection signal.

Use `--profile postgres-16.6` to diagnose a single release in a fresh evidence directory. The bundle lists every omitted profile. The local launcher and the manual workflow produce provisional evidence for the same native replay checks.

### Compare assurance revisions

Select the previous verifier archive independently of the candidate. Pass its assurance directory to the indexer with `--previous-verifier` to include the change record in the review bundle. To compare the verifier revisions before running the database matrix, use the standalone comparator.

```sh
python3 "$verifier/compare_assurance_revisions.py" \
  --previous-verifier "$previous_verifier" --current-verifier "$verifier" \
  --out "$evidence/assurance-changes.json"
```

The report verifies the declared contract digests and records changed observations, limits, fault lists, native schedules, verifier files and available workflow and dependency lock files. It flags mechanically recognizable reductions, including a reduced exploration budget or a removed native domain. The report records the archive fingerprints and any unavailable supporting files. A reviewer assesses the effect of each change and records the independent decision.

## Establish independent acceptance

The proposed [manual evidence workflow](../../../.github/workflows/semantics-ir-assurance.yml) selects its checker from the workflow revision on `main` and reads an explicit candidate SHA. Keep it outside the candidate's pull-request checks and review the workflow, its action pins, locked dependencies, engine build, oracle, mutation fixtures and contract before using the evidence for acceptance. Require repository-owner control of changes to this workflow and its inputs, and an independently maintained decision keyed to the tested candidate SHA. Verify that the decision rejects missing or stale evidence. No repository-level control has been installed or verified in this change.

The repository administrator can prepare the ruleset below after choosing a dedicated GitHub App that can publish commit statuses. Keep the App's credentials with the independent verifier. The App must receive the verifier's decisions over a channel that candidate-controlled jobs cannot write. Publish the `semantics-ir/acceptance` context only after confirming the exact current candidate SHA, reviewed verifier revision, complete scope and matched distribution digests. The manual workflow emits provisional evidence. It has no permission to write acceptance statuses.

```sh
python3 packages/sql-semantics/assurance/branch_gate.py prepare --app-id "$acceptance_app_id" > proposed-ruleset.json
gh api --paginate --slurp repos/k-kinzal/ztd-query-php/rules/branches/main | jq 'add' > effective-rules.json
gh api "repos/k-kinzal/ztd-query-php/rulesets/$acceptance_ruleset_id" > acceptance-ruleset.json
python3 packages/sql-semantics/assurance/branch_gate.py audit \
  --repository k-kinzal/ztd-query-php --app-id "$acceptance_app_id" \
  --rules effective-rules.json --definition acceptance-ruleset.json
```

The proposal starts disabled and can be added alongside the existing merge gate. Activate it after the reviewed acceptance publisher is running and a sandbox pull request demonstrates that missing evidence, old SHAs and the same context from another App are rejected. Run the audit with the effective rules and the attached ruleset definition to verify the exact App, strict checks and the absence of bypass actors. Check the publisher's credential isolation and review history separately. [GitHub ruleset API](https://docs.github.com/en/rest/repos/rules)

The registered evidence runs observe the listed domains. Unimplemented SQL structures, uncovered server projection roles and modes, and effects outside those domains remain open under the full grammar contract.

## Native construction boundary audit

The [native boundary rules](native-boundaries-v31.json) fix selected PHP guard sequences for row slots, projected parameters, joined and local sources, compound roles, INSERT, UPDATE, table declaration and removal and transaction roles. The shape checker resolves class names and compares each method body with its fixed template. It also verifies closed property types, checked list storage and the publication path. The templates and primitive digests form part of the reviewable checker.

Run `check_native_boundaries.py --candidate /path/to/candidate --tool-autoload /path/to/trusted/vendor/autoload.php --out boundary-evidence` from the trusted assurance directory. It saves each applied source mutation, the exact rejection and the candidate distribution. The [fault replay contract](native-boundary-fault-replay-v1.json) requires every mutation anchor to exist before the audit starts. Use `replay_native_boundary_faults.py --candidate /path/to/candidate --evidence boundary-evidence --out boundary-evidence/replay.json` to reconstruct the portable overlays, check the complete receipt set and verify the raw artifacts. The review bundle runs the same replay. The independent engine observations remain the evidence for SQL meaning in each tested domain.

## MySQL and PostgreSQL evidence

Before launching the native matrix, the runner checks the [workflow routing contract](native-workflow-routing-v1.json). The checked comparison job and the independent release plan must agree on every runner, artifact and release, including the older-release recursion control. The launch receipt links to the measured route inventory. The evidence index recomputes it from the fixed verifier. Run `python3 packages/sql-semantics/assurance/check_native_workflow.py` to inspect the routes and `assurance_controls.py` to replay the five injected routing defects.

[Server projection criteria](server-projection-v1.json) define independently checked native projection domains for MySQL and PostgreSQL. The [publication amendment](server-projection-publication-v1.json) fixes the analysis route to `Semantics::analyze()`. The [MySQL label refinement](server-projection-errata.md) records the observed output-name rule. Replay requires every release listed in the baseline profiles. Additional language modes remain separate engine-test obligations.

Use PHP with `pdo_mysql` and `pdo_pgsql`. Start disposable MySQL and PostgreSQL containers with an `ir_fixture` database. The manual workflow records the pinned image digests and settings. In a local run, configure the six variables `ZTD_IR_MYSQL_DSN`, `ZTD_IR_MYSQL_USER`, `ZTD_IR_MYSQL_PASSWORD`, `ZTD_IR_POSTGRES_DSN`, `ZTD_IR_POSTGRES_USER` and `ZTD_IR_POSTGRES_PASSWORD` for those disposable databases. The observer creates fresh test tables and uses read-only transactions for candidate SQL. The separate PHP candidate container has no database connection.

```sh
server_evidence=$(mktemp -d)/server-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_server.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --out "$server_evidence"
```

The finite verifier checks the observed case IDs by expression tree and source policy. The evaluator independently computes integer operations, NULL propagation, overflow, output types and names, duplicate rows, and PostgreSQL inheritance. Each analyzed case compares the original statement and its emitted SQL in the observed server settings.

The [fault replay contract](sqlite-fault-replay-v1.json) matches each recorded mutation to the exact source file and applied diff. The collector runs the independent SQLite comparison again using the healthy and mutated PHP responses. It records source audits for the twelve initial controls and the static restoration control. The initial runner executes those dedicated controls. Run `preflight_sqlite_faults.py --candidate <checkout> --out <directory>` to check overlay anchors before running the database matrix. The review bundle links each replay to its raw receipt, patch and mutated bytes. The initial controls measure worker startup and candidate execution against separate deadlines. Either timeout invalidates the run. [Initial fault budget](initial-fault-budget-v1.json)

The SQLite evidence also writes `scope.json`. It compares the AST inventory with the constructor IDs and named public return roles in the fixed contracts. Each matched constructor links to its source digest and public and magic signatures. An added or missing constructor or publication route fails the check. The same audit follows inherited interfaces to find every completed root and compares grammar artifact digests against [the fixed baseline list](required-grammars.json). The wider model and grammar obligations remain open. The inventory also lists internal analysis types so reviewers can check their visibility and separation from the public IR.

For a single declared release, pass `--engine mysql --release 8.4.7` or `--engine postgres --release 16.6` and connect the observer to that release. The runner checks the exact server version before comparing any case. The [manual release matrix](../../../.github/workflows/semantics-ir-release-matrix.yml) pins images for all nine MySQL releases and both PostgreSQL releases. Its artifacts are keyed by engine and release. Every matrix result remains specific to the native projection domain.

The [native transport contract](server-projection-transport-v1.json) groups up to sixteen direct requests at a time. The database observer prepares and executes each SQL string separately. The controller checks the response cardinality, records each comparison, and replays the same request order. The join, result selection, INSERT, UPDATE, transaction, table-removal and compound runners also use this transport. Their ledgers record each dispatch, construction and individual observation in batch order. The existing timeout and case budgets apply.

To join the independent evidence from all native server releases, run `combine_server_evidence.py --candidate "$candidate_sha" --candidate-root /path/to/candidate --out server-matrix.json` with the paths to the per-release `index.json` files. The join checks the candidate checkout, the distribution, contract and tools. The join reproduces the required requests, recomputes the direct oracle and compares both recorded observations for analyzed statements. A missing or duplicate release, missing original observation or changed ledger fails the join. Collect server observations with the revised runner before replaying them.

## Native DELETE effects

The [native DELETE contract](server-delete-v2.json) covers single-target removal on the same pinned releases and profiles. Its observer runs one checked statement against disposable parent, descendant and control tables. It compares the affected-row count, remaining rows, deletion log and restored state. PostgreSQL observations distinguish ONLY from deletion through an inheritance hierarchy. The oracle models predicates independently of the emitted SQL and preserves typed NULLs and duplicate rows.

The [PostgreSQL RETURNING extension](postgres-delete-returning-v3.json) also compares ordered column names and native types, the returned row bag, and the same physical effects. The prior unsupported-clause case now checks `USING`, and `RETURNING id` is an analyzed positive case. Run `run_server_postgres_delete_returning.py` for 16.6 and 17.2, then `combine_server_postgres_delete_returning_evidence.py` for the complete pinned replay.

The [PostgreSQL UPDATE RETURNING contract](postgres-update-returning-v4.json) compares the simultaneous assignment effect and the named fields of the new row in a single execution. It includes assignments that swap old values, inherited rows, duplicate returned values and empty result metadata. Run `run_server_postgres_update_returning.py` for 16.6 and 17.2 and replay the records with `combine_server_postgres_update_returning_evidence.py`.

The [PostgreSQL INSERT RETURNING contract](postgres-insert-returning-v1.json) compares named fields of each appended row, including unwritten fields with defaults. The finite domain crosses destination and return orders for literal and query inputs. Run `run_server_postgres_insert_returning.py` for 16.6 and 17.2 and replay the records with `combine_server_postgres_insert_returning_evidence.py`.

Run `run_server_delete.py` with the same native fixture environment and `--candidate`, `--tool-autoload`, `--engine`, `--release` and `--out` as the projection runner. The observer requires a disposable `ir_fixture` database. The manual release matrix runs DELETE alongside the projection, join and row selection domains. Combine the per-release DELETE `index.json` paths using `combine_server_delete_evidence.py --candidate "$candidate_sha" --candidate-root /path/to/candidate --out delete-matrix.json`. The collector replays the state comparisons and verifies the recorded fault results for the tested distribution.

## Native joined projections

The [native join contract](server-join-v2.json) checks a binary tree of ordinary table occurrences, Boolean and signed-integer predicates, and WHERE. MySQL and PostgreSQL cases exercise both nesting directions and self joins. PostgreSQL cases distinguish inherited scans from ONLY and compare cross-side integer equality in FULL joins. The reference evaluator computes scoped row products, unmatched-row extension, filtering and typed projection. The [MySQL merged join domain](mysql-merged-join-v3.json) separately checks signed INT NATURAL and USING, their native heading order, qualified fields and the two reference slots of each equality. Run `run_server_mysql_merged_join.py` and replay all releases with `combine_server_mysql_merged_join_evidence.py`. The [PostgreSQL merged join domain](postgres-merged-join-v2.json) checks int4 heading order, USING aliases, RIGHT join origins and inheritance under ONLY using `run_server_postgres_merged_join.py` and `combine_server_postgres_merged_join_evidence.py`. The [PostgreSQL typed-column domain](postgres-typed-columns-v2.json) verifies the exact native int4 and text roles of resolved fields with `run_server_postgres_typed_columns.py` and `combine_server_postgres_typed_columns_evidence.py`. The [PostgreSQL typed SELECT write domain](postgres-text-insert-query-v2.json) compares the producing query, target correspondence, duplicate policy and table effects through `run_server_postgres_text_insert_query.py` and its replay. The [PostgreSQL typed VALUES domain](postgres-text-insert-v2.json) checks the native write layout, exact scalar cells, omission, defaults and physical effects with `run_server_postgres_text_insert.py` and its replay. The [PostgreSQL text update domain](postgres-text-update-v2.json) evaluates typed assignments over old rows and checks the returned values and physical updates with `run_server_postgres_text_update.py` and its replay. The [PostgreSQL typed mutation results domain](postgres-text-returning-v2.json) compares native RETURNING metadata, values and physical effects of the four owning statements with `run_server_postgres_text_returning.py` and an independent replay. The [PostgreSQL derived text domain](postgres-derived-text-v1.json) compares exact native output kinds and positions of independently scoped SELECT inputs with `run_server_postgres_derived_text.py` and its independent replay. The [PostgreSQL text declaration domain](postgres-text-declarations-v2.json) observes native builtin and shadowing composite type identities at ordered declaration positions through `run_server_postgres_text_declarations.py` and its independent replay. The [PostgreSQL text presence domain](postgres-text-null-tests-v1.json) independently checks typed NULL tests in SELECT, WHERE and ON with `run_server_postgres_text_null_tests.py` and `combine_server_postgres_text_null_tests_evidence.py`. The [PostgreSQL text conversion domain](postgres-text-conversion-v1.json) verifies typed NULL and exact OIDs at ordered native positions using `run_server_postgres_text_conversion.py` and `combine_server_postgres_text_conversion_evidence.py`. The [PostgreSQL collated text domain](postgres-collated-text-v2.json) compares explicit builtin collation identity, UTF-8 byte order, NULL placement and duplicate policy with `run_server_postgres_collated_text.py` and `combine_server_postgres_collated_text_evidence.py`. The [PostgreSQL text comparison domain](postgres-text-comparison-v2.json) checks all six byte comparison operators with native text literals, fields and NULL in projection, WHERE, ordinary joins and UPDATE and DELETE filters. Run `run_server_postgres_text_comparison.py` and `combine_server_postgres_text_comparison_evidence.py`. The [PostgreSQL text literal domain](postgres-text-literal-v1.json) verifies exact UTF-8 values and native text metadata with `run_server_postgres_text_literal.py` and `combine_server_postgres_text_literal_evidence.py`. The [PostgreSQL FROM-list domain](postgres-from-list-v2.json) checks ordered comma sources, cross-occurrence filtering and inherited scans with `run_server_postgres_from_list.py` and `combine_server_postgres_from_list_evidence.py`. The [PostgreSQL FULL merge domain](postgres-full-merged-join-v1.json) checks ordered equality and both physical origins using `run_server_postgres_full_merged_join.py` and `combine_server_postgres_full_merged_join_evidence.py`. The [MySQL FROM-list domain](mysql-from-list-v1.json) checks comma precedence and local ON scope using `run_server_mysql_from_list.py` and `combine_server_mysql_from_list_evidence.py`. Observations retain ordered result metadata and a bag of rows with duplicates and NULL.

Run `run_server_join.py` with the native fixture environment and the same arguments as the DELETE runner. The join matrix preserves each release's request transcript and the observed SQL for three output mutations. It permits no case exclusions. Use `combine_server_join_evidence.py --candidate "$candidate_sha" --candidate-root /path/to/candidate --out join-matrix.json` followed by each release's `index.json` to replay and verify the complete release set.

## Native row selection

The [native result contract](server-result-v2.json) covers DISTINCT, output ordinals, native and explicit NULL placement, and literal row bounds. Run `run_server_result.py` with the same fixture arguments as the native join runner. The observer retains the database's actual row sequence and native metadata. The evaluator computes ordered equivalence bands and accepts each valid tie selection with its exact row multiplicities. Controls check duplicate loss, excess repetitions, wrong ordering bands, changed NULL placement, and differences in names and types.

Replay the release matrix with `combine_server_result_evidence.py` and the same release paths and options as the native join collector.

## Native literal INSERT effects

The [native INSERT contract](server-insert-v1.json) covers ordinary writable signed-integer targets and literal integer or NULL values. The observer starts with different target and control data and observes all rows, affected-row counts, defaults on omitted columns, trigger events and restoration after rollback. It only admits the fixed fixture's literal INSERT forms.

Use `run_server_insert.py` with the same native options and fixture connection settings as the DELETE runner. Replay the release set with `combine_server_insert_evidence.py` and the per-release `index.json` paths. PostgreSQL analysis distinguishes ordinary inherited columns, identity columns copied through LIKE, and inherited identities on partitions.

## Native ordered UPDATE effects

The [native UPDATE contract](server-update-v2.json) compares writes to declared writable signed integer slots. MySQL assigns in written order and counts changed rows in the fixture's client profile. PostgreSQL reads the old row for each assignment, counts updated rows and preserves ONLY. The observer records complete table contents, before and after values from the row trigger, and the state after rollback.

Use `run_server_update.py` with the same options and pinned fixture settings as the INSERT runner. Replay the release set with `combine_server_update_evidence.py` and the per-release `index.json` paths.

## Native transaction effects

The [native transaction contract](server-transaction-v1.json) covers plain BEGIN, COMMIT, ROLLBACK and the three savepoint roles. The experiment runs a generated program with a candidate transition between trusted setup and continuation steps. It observes transaction state and typed rows on the writer and an independent reader after every step. Programs include a second live checkpoint, repeated labels and BEGIN on an already active transaction.

Run `run_server_transaction.py` with the same options and pinned fixture settings as the UPDATE runner. Run `combine_server_transaction_evidence.py` with all of the per-release `index.json` paths to replay the release set. The observer uses an InnoDB table for MySQL and fixes the session completion type to NO_CHAIN.

## Native table removal

The [native DROP TABLE contract](server-drop-table-v3.json) covers ordered persistent table targets, IF EXISTS and PostgreSQL dependency policy. The observer records retained typed rows, dependent view survival and changes to an open transaction. Its fixed fixtures distinguish partial deletion on MySQL 5.6 or 5.7 from DROP with a missing target on newer InnoDB releases. PostgreSQL exercises both dependency options and restoration on rollback.

Run `run_server_drop_table.py` with the same native fixture options. Replay the pinned release set with `combine_server_drop_table_evidence.py` and the per-release `index.json` paths.

## Native table creation

The [native CREATE TABLE contract](server-create-table-v2.json) covers ordinary persistent tables with ordered signed integer columns, nullability and literal defaults. The observer inspects column names, positions, types and defaults and attempts a default insert. It measures IF NOT EXISTS with a preexisting target and compares transaction effects and rollback.

Run `run_server_create_table.py` with the same native fixture options. Replay all releases with `combine_server_create_table_evidence.py` and the per-release `index.json` paths.

The [MySQL VARCHAR declaration contract](mysql-create-varchar-v1.json) also observes declared and inherited collation, character capacity, row format, and diagnostics from creating the table and inserting a default row. It compares information-schema bytes and stored bytes separately, including supplementary characters and truncated ASCII padding. Run `run_server_mysql_create_varchar.py` with the MySQL fixture options and replay the nine release indexes with `combine_server_mysql_create_varchar_evidence.py`. The storage observation uses the fixture's pinned InnoDB settings and records its page size and strict mode.

The [MySQL signed declaration contract](mysql-create-signed-v2.json) checks the five native signed widths, including both exact BIGINT endpoints, and observes adjacent invalid values in the server. Run `run_server_mysql_create_signed.py` with the MySQL fixtures and replay the nine indexes with `combine_server_mysql_create_signed_evidence.py`. The native constructor audit emits `mysql-signed-storage.json`. It derives the admitted interval from the audited PHP guard for each possible sign and decimal length. The derivation assumes canonical signed operands and PHP's ASCII byte-comparison rules. The independent database suite checks the declared widths, observed catalog defaults, stored values and transaction effects. Revision 2 adds the empty constructor map required by the inventory and leaves the observed domain and decisions fixed in revision 1.

The [MySQL signed primary key contract](mysql-create-primary-v2.json) checks ordered references, the native PRIMARY index, effective field defaults and duplicate insertion. The 5.6 fixture distinguishes the zero default supplied by a table key from an omitted default on an inline key. The 5.6 and 5.7 fixtures resolve DESC index syntax to ascending storage. Run `run_server_mysql_create_primary.py` and replay all nine release indexes with `combine_server_mysql_create_primary_evidence.py`.

The [PostgreSQL signed default contract](postgres-create-signed-defaults-v1.json) preserves the source type, numeric value and deferred assignment. The observer compares catalog operands, successful default rows, SQLSTATE 22003 on narrowing, explicit replacement rows and rollback. Run `run_server_postgres_create_signed_defaults.py` and `combine_server_postgres_create_signed_defaults_evidence.py` for both pinned releases. The revised native CREATE TABLE contract retains the MySQL SMALLINT construction rejection. It measures the PostgreSQL outside-width case as an admitted declaration whose default fails when evaluated.

The [PostgreSQL Boolean declaration contract](postgres-create-boolean-v2.json) checks typed default rows, nullability and the complete specified input grid of unambiguous tokens, ASCII case, padding and builtin cast syntax. The runner measures malformed Boolean inputs against the native parser and recovers each valid test identity from the recorded SQL. Run `run_server_postgres_create_boolean.py` and `combine_server_postgres_create_boolean_evidence.py` for both pinned releases.

The [PostgreSQL Boolean field contract](postgres-boolean-fields-v1.json) checks typed native reads, qualified and unqualified bindings, NULL extension, filters, DISTINCT and positional ordering. Its observer compares exact type identifiers even for empty results. Run `run_server_postgres_boolean_fields.py` and `combine_server_postgres_boolean_fields_evidence.py` for both pinned releases.

The [PostgreSQL int8 field contract](postgres-int8-fields-v1.json) checks exact native width and coordinates of physical and derived fields. It compares bound result metadata, full signed values, NULL extension, positional order and SQLSTATE 22003 from narrowing. Run the domain with `run_server_postgres_int8_fields.py` and replay with `combine_server_postgres_int8_fields_evidence.py` on both pinned releases.

The [PostgreSQL output order contract](postgres-output-order-names-v1.json) checks name and position binding against the same result layout. The native observer compares complete row order, direction, NULL placement, duplicates and bounds. Run `run_server_postgres_output_order_names.py` and `combine_server_postgres_output_order_names_evidence.py` on both pinned releases.

The [PostgreSQL source order contract](postgres-source-order-v1.json) checks qualified and hidden sort keys over the owned input layout and checks a projected field under DISTINCT by its exact coordinate and type. The native observer calibrates the fixture's byte collation, checks the SQL before and after lowering, and replays fixed analyzed and directly constructed grids. Run `run_server_postgres_source_order.py` and `combine_server_postgres_source_order_evidence.py` on both pinned releases.

The [PostgreSQL signed write contract](postgres-signed-write-widths-v1.json) covers signed storage casts, old-row UPDATE reads and int8 RETURNING across ordinary VALUES, INSERT SELECT, UPDATE and DELETE. The observer compares exact type IDs, every affected row, source preservation, native narrowing errors and rollback. Run `run_server_postgres_signed_write_widths.py` and `combine_server_postgres_signed_write_widths_evidence.py` on both pinned releases.

The [PostgreSQL signed comparison contract](postgres-signed-comparison-widths-v1.json) compares the native int4 and int8 overloads in output values, filters and joins. The oracle checks both input occurrences, result type IDs, bag multiplicity and nullable Boolean outcomes. Run `run_server_postgres_signed_comparison_widths.py` and `combine_server_postgres_signed_comparison_widths_evidence.py` on both pinned releases.

The [PostgreSQL signed range and distinctness contract](postgres-signed-range-widths-v1.json) checks both native signed widths, typed bigint NULL, inclusive endpoints, reversed bounds and unknown results in output, filtering and join conditions. The native observer confirms the builtin-qualified representation under a competing operator search path. Run `run_server_postgres_signed_range_widths.py` and `combine_server_postgres_signed_range_widths_evidence.py` on both pinned releases.

The [PostgreSQL grouped order contract](postgres-grouped-result-order-v1.json) composes checked aggregate fields with ordinal sort keys and row bounds. The observer compares the complete typed fields and permitted selections within each tie band. Run `run_server_postgres_grouped_result_order.py` and `combine_server_postgres_grouped_result_order_evidence.py` on both pinned releases.

The [PostgreSQL grouped HAVING contract](postgres-grouped-having-v1.json) checks signed comparisons of group keys, builtin aggregates and literals. The observer measures global and keyed results, NULL outcomes, row and group filters, and the result window applied after HAVING. Run `run_server_postgres_grouped_having.py` and `combine_server_postgres_grouped_having_evidence.py` on both pinned releases.

The [MySQL external view contract](mysql-external-views-v1.json) checks absolute base-table and view identities, projected VARCHAR types and collation, and base-only write placement. A live MERGE view, a TEMPTABLE view, a nested renamed heading and a same-named decoy produce distinguishable rows. Load and replay the results with `run_server_mysql_external_views.py` and `combine_server_mysql_external_views_evidence.py` across all nine pinned releases.

The [MySQL CREATE VIEW contract](mysql-create-view-v2.json) checks persistent definitions with a closed ordinary SELECT, declared headings, creation and replacement policy, algorithm, security and native metadata. The fixture checks that a view reads changed source rows and observes the 9.1 IF NOT EXISTS behavior. Use `run_server_mysql_create_view.py` and `combine_server_mysql_create_view_evidence.py` on the pinned matrix.

The [MySQL DROP VIEW contract](mysql-drop-view-v2.json) checks absolute view identities, optional absence, retained objects and the implicit commit. Its isolated fixture distinguishes required-absence effects on MySQL 5.6 and 5.7 from the later pinned releases. Run `run_server_mysql_drop_view.py` and `combine_server_mysql_drop_view_evidence.py` on all nine pins.

The [MySQL numeric HAVING contract](mysql-grouped-having-v2.json) checks a separate group predicate with signed keys and literals, exact numeric aggregates, VARCHAR COUNT and resolved output aliases. It checks the legacy implicit key order for its simple grouped data before MySQL 8.0. Run `run_server_mysql_grouped_having.py` and `combine_server_mysql_grouped_having_evidence.py` across all nine pinned releases.

The [MySQL ordinary WITH contract](mysql-with-projection-v1.json) checks ordered lexical definitions, resolved field positions, nested closed scopes, physical names under lexical shadowing and native prepared-statement marker order. Use `run_server_mysql_with_projection.py` and `combine_server_mysql_with_projection_evidence.py` on the seven releases that support WITH. The installed layout probe checks both publication routes on the two older releases.

The [PostgreSQL external source kind contract](postgres-external-source-kinds-v1.json) checks absolute base-table, view and materialized-view references. The fixture makes a live view and a persisted snapshot differ after a base mutation and uses a same-named decoy in another schema. The observer compares typed rows, duplicate counts, NULL extension and ordering. Run `run_server_postgres_external_source_kinds.py` and `combine_server_postgres_external_source_kinds_evidence.py` on both pinned releases.

The [PostgreSQL lexical VALUES contract](postgres-local-values-v2.json) checks a constant table in an independent derived query or a WITH declaration or terminal body. Its observer compares native output kinds, duplicate and NULL rows, local coordinates and an unused typed parameter declaration. Run `run_server_postgres_local_values.py` and `combine_server_postgres_local_values_evidence.py` on both pinned releases.

The [PostgreSQL VALUES set contract](postgres-values-compound-v1.json) checks native set types, leftmost headings and typed multisets when independently resolved VALUES appears in an arm. It covers UNION, INTERSECT and EXCEPT with their respective duplicate policies, including the parameter type of a sibling SELECT. Run `run_server_postgres_values_compound.py` and `combine_server_postgres_values_compound_evidence.py` on both pinned releases.

The [PostgreSQL standalone VALUES contract](postgres-values-v3.json) fixes rectangular row and cell roles, native whole-column type resolution, duplicate and NULL rows and the built-in output names and type IDs. Its direct width probes exercise the last supported result field. Run `run_server_postgres_values.py` and `combine_server_postgres_values_evidence.py` on both pinned releases.

The [PostgreSQL persistent view contract](postgres-create-view-v2.json) compares schema-qualified view creation and replacement with native catalog metadata, live reads after a base update, security option presence, checked writes and rollback. Run `run_server_postgres_create_view.py` and `combine_server_postgres_create_view_evidence.py` on both pinned releases.

The [PostgreSQL materialized view contract](postgres-materialized-view-v1.json) compares persistent heap creation and ordinary refresh with native catalog headings, population flags, persisted rows across base updates, exact unreadable states and rollback. A cross-schema decoy distinguishes resolved targets. Run `run_server_postgres_materialized_view.py` and `combine_server_postgres_materialized_view_evidence.py` on both pinned releases.

The [PostgreSQL create-table-as contract](postgres-create-table-as-v1.json) compares native logged and unlogged table creation from a resolved query. It checks the copied heading, nullability, writable empty tables, source changes, exact collision effects and transaction rollback. Run `run_server_postgres_create_table_as.py` and `combine_server_postgres_create_table_as_evidence.py` on both pinned releases.

The [PostgreSQL persistent rename contract](postgres-table-rename-v2.json) compares table and column name changes by absolute identity. The observer tracks relation identity, attribute positions, original data, descendant policy, dependent view fields and rollback. Run `run_server_postgres_table_rename.py` and `combine_server_postgres_table_rename_evidence.py` on both pinned releases.

The [PostgreSQL index removal contract](postgres-drop-index-v1.json) checks ordered absolute selectors, absent schemas and names, guarded notices, constraints, cascading removal and transaction restrictions. Run `run_server_postgres_drop_index.py` and `combine_server_postgres_drop_index_evidence.py` on both pinned releases.

The [PostgreSQL named B-tree contract](postgres-create-btree-v1.json) checks key and covering occurrences, native order and NULL policies, transaction restrictions, occupied names and nullable uniqueness. The [scalar partial B-tree contract](postgres-partial-btree-v1.json) also checks the exact bound column and filter polarity through subsequent writes. These observers read the catalog, constraints and ordered rows in one snapshot per phase, and record write and rollback outcomes separately. A construction-only witness observes the published typed graph before rendering so a rendering crash cannot count as rejection by the constructor. Run `run_server_postgres_create_btree.py` and `run_server_postgres_partial_btree.py` on each pinned release, then replay each pair with `combine_server_postgres_create_btree_evidence.py` and `combine_server_postgres_partial_btree_evidence.py`, respectively.

The [PostgreSQL composed partial-index contract](postgres-boolean-index-predicate-v1.json) checks the exact resolved tree and all eight assignments of three nullable columns. The observer reads the stored native condition through a disposable nonunique index even when the tested unique build rejects a duplicate. It checks subsequent writes separately. Run `run_server_postgres_boolean_index_predicate.py` on both releases and replay with `combine_server_postgres_boolean_index_predicate_evidence.py`. For an explicitly registered construction-only fault, the replayer requires the original typed rejection, the constructed mutant graph and the exact fault dispatch and source diff. Rendering witnesses continue to require emitted SQL and a native semantic mismatch.

The [PostgreSQL index truth-test contract](postgres-index-truth-tests-v1.json) covers all six native tests of two independently nullable Boolean fields and checks bound column identity under each supported qualification. It compares the stored truth mask, uniqueness and a prospective row with a repeated key and different Boolean values, including after a failed build. Run `run_server_postgres_index_truth_tests.py` for both pinned releases and replay with `combine_server_postgres_index_truth_tests_evidence.py`.

The [PostgreSQL nullable Boolean index contract](postgres-index-boolean-values-v1.json) checks the exact true, false and unknown states of bare fields and their logical parents. It also checks the effects of `ON CONFLICT` for the fixed arbiter matrix, including callers that distinguish a bare field from `IS TRUE` even when both predicates select the same rows. Run `run_server_postgres_index_boolean_values.py` on both releases and replay with `combine_server_postgres_index_boolean_values_evidence.py`.

The [PostgreSQL index rebuild contract](postgres-reindex-index-v1.json) compares the resolved physical index and untouched namesakes in the same session. The observer records raw catalog identities and file nodes, index flags and layouts, rows, subsequent write behavior, and relation identity before and after rollback. Run `run_server_postgres_reindex_index.py` on both releases and replay with `combine_server_postgres_reindex_index_evidence.py`.

The [PostgreSQL constraint-index rebuild contract](postgres-reindex-constraint-index-v1.json) checks the separately bound named key index and whether each preserved foreign key points to the current physical index. It probes primary, unique, NULL equality and deferrable enforcement. Run `run_server_postgres_reindex_constraint_index.py` on both releases and replay with `combine_server_postgres_reindex_constraint_index_evidence.py`.

The [PostgreSQL owner-wide index rebuild contract](postgres-reindex-table-v3.json) checks the bound table or materialized view and each visible and TOAST index while preserving the heap, its typed rows and decoy catalog. It measures mode-specific notices, temporary physical rebuilding, concurrent transaction refusal, and rollback. Run `run_server_postgres_reindex_table.py` for each pinned release and replay with `combine_server_postgres_reindex_table_evidence.py`.

The [PostgreSQL catalog binding contract](postgres-local-catalog-binding-v1.json) verifies that a decoded current catalog qualifier resolves to the same local coordinate and scalar slot as the schema-qualified spelling. The native observer compares typed result headings, ordered rows, write effects and materialized snapshots. It also executes the original mismatched qualifiers and verifies their SQLSTATE and unchanged local state. Run `run_server_postgres_local_catalog.py` on both pinned releases and replay with `combine_server_postgres_local_catalog_evidence.py`.

The [PostgreSQL schema rebuild contract](postgres-reindex-schema-v2.json) observes the complete selected native index set and an independent decoy. A partition attached across schemas keeps its catalog link while selection follows the actual namespace of each physical owner. Run `run_server_postgres_reindex_schema.py` on both pinned releases and replay with `combine_server_postgres_reindex_schema_evidence.py`.

The [PostgreSQL partition table rebuild contract](postgres-reindex-partition-table-v1.json) distinguishes the selected branch or leaf, verifies every descendant and auxiliary index, and checks branch-wide and leaf-only transaction behavior. The observer verifies that parent and index links survive concurrent physical index replacement. Run `run_server_postgres_reindex_partition_table.py` on both pinned releases and replay with `combine_server_postgres_reindex_partition_table_evidence.py`.

The [PostgreSQL named partition index rebuild contract](postgres-reindex-partition-index-v1.json) compares a resolved named branch or leaf index, its exact attachment lineage, and the auxiliary storage of its owning table. The leaf-only and branch-wide cases preserve unrelated index storage and verify the native transaction differences. Run `run_server_postgres_reindex_partition_index.py` on both pinned releases and replay with `combine_server_postgres_reindex_partition_index_evidence.py`.

The [PostgreSQL database rebuild contract](postgres-reindex-database-v2.json) compares current-database and pinned system selectors against the full [stock index census](postgres-stock-reindex-catalog-v2.json). The census selects the exact native build before comparing the stock index inventory. Both known Linux architectures retain the complete inventory. Four information-schema heap, TOAST and index links have build-specific identities. The observer measures independently named native relations, shared and auxiliary catalogs, same-session temporary relations and the exact exclusion-index warning. Run `run_server_postgres_reindex_database.py` on both pinned releases and replay with `combine_server_postgres_reindex_database_evidence.py`.

The [PostgreSQL exclusion-index contract](postgres-reindex-exclusion-index-v2.json) checks the exact constraint and physical index link, rollback and exclusion enforcement with duplicate keys, NULL and a partial predicate. Run `run_server_postgres_reindex_exclusion_index.py` on both pinned releases and replay with `combine_server_postgres_reindex_exclusion_index_evidence.py`.

The [PostgreSQL rebuild reporting contract](postgres-reindex-verbose-v1.json) checks effective `VERBOSE` through each resolved rebuild root and both public construction routes. The native observer records raw diagnostics, compares the report order and target names for each release, and measures changes to physical index identities. The resource-use comparison retains the structure of native CPU and elapsed-time details. The ledger includes the raw durations. Run `run_server_postgres_reindex_verbose.py` on both pinned releases and replay with `combine_server_postgres_reindex_verbose_evidence.py`.

The [PostgreSQL qualified index operation contract](postgres-index-catalog-binding-v1.json) checks explicit current-database qualifiers on index creation, removal and rebuilds. The observer measures physical index selection, ownership, attachment and independently resolved decoy state. Run `run_server_postgres_index_catalog.py` on both pinned releases and replay with `combine_server_postgres_index_catalog_evidence.py`.

The [PostgreSQL namespace removal contract](postgres-drop-schema-v2.json) checks ordered selectors and admitted absence. Its native oracle measures untouched names, table rows, views and foreign keys after `CASCADE`, `RESTRICT` and rollback. Run `run_server_postgres_drop_schema.py` on both pinned releases and replay with `combine_server_postgres_drop_schema_evidence.py`.

The [PostgreSQL namespace creation contract](postgres-create-schema-v3.json) checks the decoded namespace, exact named or session owner, guard, release-specific stock roles and transaction outcomes. Run `run_server_postgres_create_schema.py` on both pinned releases with the verified disposable service ID and then replay with `combine_server_postgres_create_schema_evidence.py`. The oracle gives different identities to the effective role and session authorization.

The [PostgreSQL namespace table contract](postgres-schema-tables-v1.json) checks absolute child coordinates, column metadata, effective ownership, ordered duplicates, deferred defaults and rollback. Run `run_server_postgres_schema_tables.py` with the same verified disposable service inputs and replay with `combine_server_postgres_schema_tables_evidence.py`.

The [PostgreSQL namespace alteration contract](postgres-alter-schema-v1.json) verifies distinct rename and ownership roles. It records namespace and relation identities, ownership and namespace grants, dependent views, unchanged rows and rollback. Run `run_server_postgres_alter_schema.py` against the verified disposable services and replay with `combine_server_postgres_alter_schema_evidence.py`.

The [fixture isolation contract](native-postgres-fixture-isolation-v2.json) renews the trusted service's disposable `ir_fixture` database before the six observers that inspect database-wide state. Their receipts identify the container and record the database identity and native catalog census. Start focused runs with a freshly launched fixture. Collecting a full matrix requires each scheduled renewal receipt.

The [PostgreSQL rebuild placement contract](postgres-reindex-tablespace-v2.json) checks the resolved destination, report and mode together. Its trusted service preparation creates two separate native table spaces. The observer independently checks relation and index placement before and after the command, including the pinned catalog policy. Run `run_server_postgres_reindex_tablespace.py` through the native matrix or replay its two release observations with `combine_server_postgres_reindex_tablespace_evidence.py`.



The [MySQL ordered rename contract](mysql-ordered-table-rename-v2.json) verifies each original table identity through left-to-right name transitions. The native observer checks both schemas, matching column metadata and duplicate and NULL rows, exact errors, atomic replacement of names and implicit commit. Run `run_server_mysql_ordered_table_rename.py` and `combine_server_mysql_ordered_table_rename_evidence.py` on all nine pinned releases.

The [MySQL truncation contract](mysql-truncate-v2.json) checks exact persistent and session-owned targets. Two connections observe the namespace distinction, matching declarations, referential restrictions, generated identities and transaction effects. Run `run_server_mysql_truncate.py` and `combine_server_mysql_truncate_evidence.py` on all nine pinned releases.

The [MySQL session table contract](mysql-session-table-sources-v1.json) checks both relational namespaces on two independent connections. It compares source roles, typed query results, mutation counts, retained permanent rows, rollback and persistent rename through an overlay. Run `run_server_mysql_session_table_sources.py` and `combine_server_mysql_session_table_sources_evidence.py` on all nine pinned releases.

The [MySQL session table removal contract](mysql-session-drop-table-v1.json) compares persistent and session-local identities, explicit TEMPORARY lookup, ordered mixed targets, missing-name notes and rollback. Run `run_server_mysql_session_drop_table.py` and `combine_server_mysql_session_drop_table_evidence.py` on all nine pinned releases.

The [MySQL table declaration namespace contract](mysql-session-create-table-v1.json) compares temporary and persistent InnoDB declarations. The observer checks ordered columns and primary keys, namespace-specific collision, default insertion, transaction effects and the view of a fresh session after the owner disconnects. Run `run_server_mysql_session_create_table.py` and `combine_server_mysql_session_create_table_evidence.py` on all nine pinned releases.

The [MySQL table definition copy contract](mysql-create-like-v1.json) checks both resolved operands of `CREATE TABLE … LIKE` across permanent and session-local namespaces. Observe the empty copy, preserved column and index definitions, allocation and foreign-key behavior, and rollback. Run `run_server_mysql_create_like.py` and `combine_server_mysql_create_like_evidence.py` on all nine pinned releases.

The [MySQL query-backed table contract](mysql-create-table-as-v1.json) checks source-free integer, Boolean and NULL projections. The native observer compares exact inferred storage, ordered names, values, both table namespaces, existence policy, rollback and owner disconnect. Run `run_server_mysql_create_table_as.py` and `combine_server_mysql_create_table_as_evidence.py` for all nine pinned releases. The [MySQL 5.6 source-free ordering rule](mysql-source-free-order-v1.json) retains positional ordering by rendering an independent one-row source. Its source SQL rejection and direct output checks are recorded by the same suite. The [integer width observation](mysql-create-table-as-integer-width-v1.json) records the threshold on both legacy and modern MySQL profiles.

The [resolved MySQL query source contract](mysql-create-table-as-source-v1.json) covers inherited native signed fields, resolved temporary table precedence and two spellings of the same slot. Its observer records both sessions and compares inserted rows with the independent peer-group selection rule when LIMIT cuts a tie. Run `run_server_mysql_create_table_as_source.py` and `combine_server_mysql_create_table_as_source_evidence.py` for the nine releases.

The [MySQL VARCHAR table creation contract](mysql-create-table-as-varchar-v1.json) checks native text boundaries and row effects, including exact inherited storage, padding, positional row selection and the maximum logical row size. Run `run_server_mysql_create_table_as_varchar.py` and `combine_server_mysql_create_table_as_varchar_evidence.py` for the nine releases.

The [MySQL explicit signed creation contract](mysql-create-table-as-declarations-v1.json) combines a checked declaration list with a source-free query. Its native comparison checks both column orders, selected conversions, defaults, collision effects and exact warning order. Run `run_server_mysql_create_table_as_declarations.py` and `combine_server_mysql_create_table_as_declarations_evidence.py` for all pinned MySQL profiles.

The [PostgreSQL truncation contract](postgres-truncate-v1.json) observes duplicate target order, inherited rows, transitive foreign-key effects, the lifetime of owned sequence state and before/after callback order. Run `run_server_postgres_truncate.py` and `combine_server_postgres_truncate_evidence.py` on both pinned releases.

The [PostgreSQL view removal contract](postgres-drop-view-v1.json) covers persistent ordinary and materialized views. It compares exact qualified targets, dependency failures and transitive cascade, live and materialized rows in surviving objects, and native rollback. Run `run_server_postgres_drop_view.py` and `combine_server_postgres_drop_view_evidence.py` on both pinned releases.

The [PostgreSQL ordinary WITH contract](postgres-with-projection-v1.json) checks lexical definition visibility, output positions, materialization policies and the shared native input signature. The observer compares result names, types and row multiplicity, including shadowed and independently nested scopes. Run `run_server_postgres_with_projection.py` and `combine_server_postgres_with_projection_evidence.py` on both pinned releases.

The [PostgreSQL Boolean RETURNING contract](postgres-boolean-returning-v1.json) checks native Boolean and signed fields in four mutation roots. The observer compares affected-row count, exact positional types, returned values and physical rows before execution, after execution and after rollback. Run `run_server_postgres_boolean_returning.py` and `combine_server_postgres_boolean_returning_evidence.py` for both pinned releases.

The [PostgreSQL Boolean write contract](postgres-boolean-writes-v1.json) checks native destinations for VALUES, INSERT SELECT and UPDATE. It compares assigned and untouched data, old-row swaps, native result type identifiers and the DISTINCT boundary for untyped NULL. Run the domain with `run_server_postgres_boolean_writes.py` and replay with `combine_server_postgres_boolean_writes_evidence.py` on both pinned releases.

The [PostgreSQL Boolean write-input contract](postgres-boolean-write-inputs-v1.json) compares the fixed native input tokens and four assignment and cast contexts. The replay checks every raw input spelling against the observed construction, exact materialized Boolean and native SQLSTATE for rejected forms. Run `run_server_postgres_boolean_write_inputs.py` and `combine_server_postgres_boolean_write_inputs_evidence.py` on both pinned releases.

## Native compound queries

The [native compound contract](server-compound-v2.json) compares the weighted result rows and output types of UNION, INTERSECT and EXCEPT. The pinned MySQL 5.x releases use left-associated UNION. Later pinned MySQL releases and PostgreSQL also accept parenthesized branches. The evaluator adds, intersects or subtracts integer multiplicities and checks names in leftmost output order.

Run `run_server_compound.py` with the same native fixture options. Replay all releases with `combine_server_compound_evidence.py` and the per-release `index.json` paths.

## Native projected parameters

The [native parameter contract](server-parameter-v5.json) fixes the binding position and builtin cast for explicitly cast native markers in output columns. The observer binds distinct integers and NULL through MySQL's native prepared protocol or PostgreSQL PREPARE with int4 arguments. The independent comparison checks the exact vector lookup, output order and native types. PostgreSQL uses repeated and reordered positions.

Run `run_server_parameter.py` with the same native fixture options. Replay all releases with `combine_server_parameter_evidence.py` and the per-release `index.json` paths.

## MySQL physical VARCHAR projections

The [VARCHAR domain](mysql-varchar-slots-v2.json) checks the scoped native field type, textual bytes, ordinal projections, repeated labels and PAD SPACE semantics across the pinned MySQL releases. For DISTINCT it requires one of the original rows from each padded equality class. For ORDER BY it also checks the order of the classes.

Run `run_server_mysql_varchar_slots.py` with the native fixture options and replay the per-release reports with `combine_server_mysql_varchar_slots_evidence.py`.

The [VARCHAR presence domain](mysql-varchar-presence-v1.json) independently checks IS NULL and IS NOT NULL over a typed field in SELECT output, WHERE and local ON. It preserves native integer truth results and distinguishes empty values from NULL, including join null extension. Run `run_server_mysql_varchar_presence.py` and replay with `combine_server_mysql_varchar_presence_evidence.py`.

The [VARCHAR mutation domain](mysql-varchar-effects-v1.json) measures UPDATE and DELETE filtered by VARCHAR presence. It compares physical and rolled-back rows, separate control tables, the rows observed by triggers and the changed-row count. Run `run_server_mysql_varchar_effects.py` and replay with `combine_server_mysql_varchar_effects_evidence.py`.

The [VARCHAR comparison domain](mysql-varchar-comparison-v1.json) enumerates seven operators, ordered field, literal and NULL operand roles, and five statement placements. Its independent PAD SPACE rule handles case, byte values, trailing spaces and NULL. SELECT checks ordered column positions, native integer types and row multiplicity. UPDATE and DELETE check the resulting fixture state and trigger events. Run `run_server_mysql_varchar_comparison.py` and replay with `combine_server_mysql_varchar_comparison_evidence.py`.

The [VARCHAR assignment domain](mysql-varchar-assignment-v4.json) checks ordered single-table UPDATE assignments over typed fields, literals and NULL. Its independent model compares final rows, the changed-row count, warning severity, code and field, and the observed trigger records. It checks truncation by Unicode character capacity and the profile’s required-field substitution. Run `run_server_mysql_varchar_assignment.py` and replay with `combine_server_mysql_varchar_assignment_evidence.py`.

The [VARCHAR VALUES domain](mysql-varchar-insert-v2.json) compares the exact typed rows and ordered trigger events of ordinary INSERT. It observes single-row required-NULL errors, multiple-row substitution, and transactional rollback on the registered duplicate keys. Run `run_server_mysql_varchar_insert.py` and replay with `combine_server_mysql_varchar_insert_evidence.py`.

The [VARCHAR query INSERT domain](mysql-varchar-insert-query-v2.json) checks each destination against its source position and independently evaluates the source bag, DISTINCT representatives and native write effects. Its fixture captures exact typed rows, source and control state, trigger effects, diagnostic classes and rollback. Run `run_server_mysql_varchar_insert_query.py` and replay with `combine_server_mysql_varchar_insert_query_evidence.py`.

The [VARCHAR COUNT domain](mysql-varchar-count-v1.json) checks nullable and duplicate text fields, global zeros, keyed grouping, required legacy group ordering and exact output metadata. Its oracle independently partitions rows and counts exact non-NULL values or PAD SPACE equivalence classes. Run `run_server_mysql_varchar_count.py` and replay with `combine_server_mysql_varchar_count_evidence.py`.

The [VARCHAR extrema domain](mysql-varchar-extrema-v2.json) checks native `MIN` and `MAX` by position, including NULL, duplicate labels, empty inputs, result collation and legacy grouping order. The independent model compares exact stored representatives of the extreme PAD SPACE class and preserves row multiplicity. Run `run_server_mysql_varchar_extrema.py` and replay with `combine_server_mysql_varchar_extrema_evidence.py`.

The [VARCHAR inheritance domain](mysql-varchar-inheritance-v2.json) checks a resolved table default and explicit column overrides. It compares the derived physical slot with direct construction and checks native character-set, collation, width and nullability metadata before comparing the results. Run `run_server_mysql_varchar_inheritance.py` and replay with `combine_server_mysql_varchar_inheritance_evidence.py`.

The [explicit MySQL collation domain](mysql-explicit-text-collation-v1.json) compares the native result columns and observes their collation and coercibility by position, including duplicate labels and projections filtered to an empty result. Run `run_server_mysql_explicit_text_collation.py` and replay with `combine_server_mysql_explicit_text_collation_evidence.py`.

The [derived VARCHAR domain](mysql-derived-varchar-v1.json) checks both occurrence and output ordinal after propagating the physical field's native metadata. Its independent join model consumes all permitted child DISTINCT representatives and compares typed rows, NULL extension and native text equality. Run `run_server_mysql_derived_varchar.py` and replay with `combine_server_mysql_derived_varchar_evidence.py`.

The [local VARCHAR domain](mysql-local-varchar-v2.json) follows a resolved position through reorderings and repeated labels in up to sixteen local projections. It compares the wire type and collation identifier even when the query has no rows. Run `run_server_mysql_local_varchar.py` and replay with `combine_server_mysql_local_varchar_evidence.py`.

## Native local projections

The [local projection contract](server-local-projection-v4.json) specifies slot reads from an immediate definition and its composition through nested local scopes. The observer compares explicit output names, typed values and multiplicities on derived inputs and, where available, an ordinary one-use CTE that shadows a physical table. It verifies MySQL's optimizer switch and the result types of materialized signed literals, NULL and table fields. The fault probes change the selected slot, row multiplicity, definition and MySQL integer field width. A separate probe bypasses an inner scope that reverses its output positions.

Run `run_server_local_projection.py` with the native fixture options. Replay all releases with `combine_server_local_projection_evidence.py` and the per-release `index.json` paths.

## Native three-valued predicates

The [truth contract](server-truth-v2.json) enumerates every constant input for the registered logic operators. Two row observations distinguish each of TRUE, FALSE and UNKNOWN. Seeded tests vary nesting, scope, nullable slot occurrences, and join type. The DELETE and UPDATE runners check the same predicates against physical target rows, trigger records and restored state.

Run `run_server_truth.py` with the native fixture options and replay with `combine_server_truth_evidence.py`. Use the DELETE and UPDATE replay commands for the state changes.

## Native recursive series

The [recursive series contract](server-recursive-series-v2.json) fixes the signed one-column anchor, increment, strict comparison, UNION policy and output roles. The oracle computes the expected admitted rows directly from the distance to the bound and the increment. The observer compares native field types and verifies an analyzed recursive CTE that shadows a physical table.

Run `run_server_recursive_series.py` on pinned MySQL releases from 8.0 onward and PostgreSQL. On MySQL 5.6 and 5.7, use `check_recursive_availability.py` to check native syntax rejection, the analyzed and direct construction boundaries, and a positive native projection. Replay the accepted recursive statements with `combine_server_recursive_series_evidence.py`, the supported release `index.json` paths and one `--unavailable` record per MySQL 5.x release.

## Wildcard projection

The [wildcard record](wildcard-projection-v2.json) checks expansion into the existing ordered projection roles. Each finite case compares the written wildcard and explicit emitted projection against independently constructed layouts and typed outcomes. Fixtures distinguish the two occurrences of a self join and record empty results, repeated labels, and the preserved binding slot in SQLite. The verifier counts the written wildcard form and resolved result structure separately.

Run `run_sqlite_wildcard.py` or `run_server_wildcard.py` with the corresponding fixture options. Replay the native receipts using `combine_server_wildcard_evidence.py`. The SQLite evidence collector replays its wildcard receipt with the other required domains.

## Native signed derived join inputs

The [derived join record](server-derived-join-v2.json) checks external and independent sources in the same join tree. Derived references use an outer occurrence and an inner ordinal. The direct finite domain crosses both input positions, four join kinds and both observed output layouts. The seeded cases add nested joins, repeated input names, filters and distinct definitions. The native comparator checks result labels, exact type tags, multiplicities and NULL extension.

Run `run_server_derived_join.py` with the native fixture options. Replay all releases with `combine_server_derived_join_evidence.py` and the per-release `index.json` paths. Its mutation probes change occurrence selection, field position and outer join behavior.

## SQLite statement conflict policies

The [conflict policy contract](sqlite-mutation-conflicts-v2.json) compares inherited behavior and each of the five explicit SQLite conflict algorithms. Every direct case runs in autocommit and inside an explicit transaction, each with recursive triggers on and off. The observer records ordered trigger events, typed returned fields, exact errors, changes and transaction state. Run `run_sqlite_mutation_conflicts.py`. The SQLite collector replays this domain with the other required receipts.


The [targetless DO NOTHING contract](sqlite-upsert-nothing-v1.json) compares the uniqueness action separately from the statement policy. It measures the two input forms, the unchanged blocking row, exact output and transaction effects, and SQL grammar disambiguation for SELECT inputs. Run `run_sqlite_upsert_nothing.py` and preserve its raw SQLite receipt for replay.

## MySQL signed quotient and remainder

The [signed quotient record](mysql-signed-quotient-v1.json) specifies ordered signed `DIV` and infix remainder operands, the signed result, NULL, zero-divisor diagnostics and the signed 64-bit boundaries. The runner compares signed values, ordered field names, native metadata, duplicates and warning counts. Its observer checks that enabling a different SQL mode actually makes a division warning visible before it restores the empty mode.

Run `run_server_mysql_signed_quotient.py` on the nine pinned MySQL releases. Replay their reports with `combine_server_mysql_signed_quotient_evidence.py`. The original boundary failure resulted from evaluating a nested multiplication before DIV. The schedule retains that expression and also constructs the same minimum value by addition so the two behaviors can be compared independently.


The [signed range record](mysql-signed-range-v3.json) checks the ordered subject and inclusive endpoints, both placements, negation and signed precision beyond binary64. Its observer checks the integer result type, NULL, duplicates and warning counts. Run `run_server_mysql_signed_range.py` and replay the nine pinned releases with `combine_server_mysql_signed_range_evidence.py`.

## PostgreSQL int4 operators

The [int4 operator record](postgres-int4-operators-v2.json) checks ordered int4 operands, type and result values, SQLSTATE 22012 for a zero divisor and SQLSTATE 22003 for division overflow. Each evaluation outcome is checked against the independent rules for its exact operands. The fixture sets a distinguishing search path with an identically named division operator and measures both operation identities. Run `run_server_postgres_int4.py` for both pinned PostgreSQL releases. Replay the exact reports with `combine_server_postgres_int4_evidence.py`.

The [searched int4 CASE record](postgres-int4-case-v1.json) exhausts the ordered one- and two-arm finite domain and explores nested, repeated and nullable branches. The fixture compares exact int4 metadata, scoped reads and row-dependent guarded arithmetic. Run `run_server_postgres_int4_case.py` for both pinned PostgreSQL releases. Replay the reports with `combine_server_postgres_int4_case_evidence.py`.


The [int4 range record](postgres-int4-range-v1.json) checks all 2,216 ordered operand and placement identities on each PostgreSQL release. Its evaluator preserves UNKNOWN, measures bool result types and only admits TRUE in a filter. The fixture checks generated SQL under a search path with competing operators. Run `run_server_postgres_int4_range.py` and replay the receipts with `combine_server_postgres_int4_range_evidence.py`.


The [null-safe int4 contract](postgres-int4-distinctness-v1.json) compares exact Boolean output, both polarities, NULL and both reference positions. It uses the same signed input and independent fixture as the range domain. The native comparator detects changes in the NULL rule, referenced operand, comparison polarity and equality identity. Run `run_server_postgres_int4_distinctness.py` and replay its two release records with `combine_server_postgres_int4_distinctness_evidence.py`.

The [Boolean truth test contract](postgres-boolean-tests-v1.json) covers the six tests over TRUE, FALSE and NULL. The exploration also uses composed and nested predicates, typed NULL, scoped references, filters and CASE. Run `run_server_postgres_boolean_tests.py` on both releases and replay them with `combine_server_postgres_boolean_tests_evidence.py`.

The [PostgreSQL built-in cast contract](postgres-builtin-casts-v4.json) checks explicit int4, int8 and Boolean conversions, typed NULL, exact 64-bit endpoints, native narrowing errors, scoped inputs, nested roles, DISTINCT and typed union results. Run `run_server_postgres_builtin_casts.py` on each registered PostgreSQL release. Replay the closed finite identities and native recordings with `combine_server_postgres_builtin_casts_evidence.py`. The source audit checks the [numeric binding inputs](postgres-integer-binding-sources-v1.json) and exercises a substituted token magnitude.

The [PostgreSQL typed-parameter contract](postgres-typed-parameters-v2.json) observes the input parameter OID vector from a native PREPARE without an explicit type list. The observer separately compares output names, types, values and narrowing errors. Repeated uses preserve one input type even when the projected conversions differ. Run `run_server_postgres_typed_parameters.py` on both pinned releases and replay with `combine_server_postgres_typed_parameters_evidence.py`.


The [MySQL signed cast contract](mysql-signed-casts-v2.json) compares binary protocol result type and signedness, names, values, NULL, multiplicity and native expression failures on all registered releases. Run `run_server_mysql_signed_casts.py` with the local disposable MySQL fixture and the mysqlnd-backed mysqli extension. Replay with `combine_server_mysql_signed_casts_evidence.py`.

The unsigned cast fixture observes the same native binary protocol fields, nested signed and unsigned values and mixed UNION results on all registered MySQL releases. Both UNION operand orders preserve the observed 5.x clipping and modern decimal promotion. Its comparator preserves NULL, duplicate rows and exact values above the signed range. Run `run_server_mysql_unsigned_casts.py` with the same fixture. [MySQL unsigned cast contract](mysql-unsigned-casts-v3.json)

## Native grouped projections

The [grouped projection record](server-grouped-v3.json) defines signed input slots, COUNT, SUM, MIN and MAX, argument duplicate policy and ordered grouping fields. Its oracle partitions the fixture by declared key tuples, computes typed aggregate outputs and exercises both keyed and global queries over empty inputs. The comparator preserves exact decimal text and native metadata. On PostgreSQL the observer supplies a competing `count(int4)` and measures the distinct results of qualified and unqualified calls.

Run `run_server_grouped.py` using the native fixture options. Replay every release with `combine_server_grouped_evidence.py` and its `index.json` path. The faulty SQL probes change DISTINCT, NULL treatment, grouping fields, filter placement and PostgreSQL routine identity.

The [grouping admission record](server-projection-admission-v1.json) preserves a construction rejection for a computed grouping key and requires independent comparison of the formerly unsupported named grouping. The projection and grouped suites replay those two cases with the unchanged release set and budgets.

## Native INSERT from SELECT

The [query INSERT contract](server-insert-query-v2.json) connects positional target columns to a checked signed source projection. The observer measures typed target rows, read source and control rows, trigger images, affected rows and rollback. It also checks the PostgreSQL distinction between a NULL assigned to a signed column and an untyped NULL projected by DISTINCT.

Run `run_server_insert_query.py` using the native fixture options. Replay with `combine_server_insert_query_evidence.py` and the per-release `index.json` paths.

## SQLite joins, filters and ordering

The [SQLite relational criteria](sqlite-relational-v4.json) extend the initial SQLite projection domain. The [merged join contract](sqlite-merged-join-v3.json) compares explicit join graphs with native USING and NATURAL on ordinary binary inputs. Its separate fixture checks left collation precedence, numeric affinity, unmatched rows and result names. The [RIGHT merge contract](sqlite-merged-right-join-v2.json) separately verifies the right representative and the distinction between generated wildcard labels and explicit qualified or unqualified result names. The [FULL merge contract](sqlite-merged-full-join-v1.json) checks both unmatched inputs, the ordered COALESCE value and its native type and name in selections and views. The [merged prefix contract](sqlite-merged-prefix-join-v1.json) checks preceding occurrences, keys introduced in later inputs and the NULL extension of earlier LEFT joins. Run these domains with the same fixture arguments using the corresponding `run_sqlite_*.py` scripts. Run the same pinned SQLite 3.47.2 build and isolated PHP image through the separate checker:

```sh
relational_evidence=$(mktemp -d)/sqlite-relational-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_relational.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$relational_evidence"
```

The evaluator treats outer joins as filtered products with unmatched-row extension, uses SQL truth rules for predicates and checks ordering without constraining ties. It reads fixture data with deliberately different and duplicate rows, NULLs and empty inputs. Separate analyzed cases compare the original and emitted statements, including a self join and ordering by a column with a declared collation. The relational mutations change an occurrence alias, remove NULL-extended rows, drop a filter, reverse an ordering key and bypass the snapshot restoration guard. Run the initial F01–F12 exercise on the same distribution.

The [closed value contract](closed-value-graph-v5.json) fixes the static argument for declared fields and retained children. The source audit requires each nonpromoted readonly field to receive one unconditional constructor initialization. The bundle reconciles the audited fields with the independently extracted inventory, verified list copies and pinned release enums. Its machine-readable graph records the argument's premises and the omission, open-edge, unchecked-list, external-definition and initialization controls.

The [scalar dispatch contract](scalar-dispatch-v3.json) checks that each admitted operand type reaches one explicit branch or a typed delegation. The audit compares the renderer's PHP argument union with the checked fields of the listed callers. Removing a branch, adding a fallback, or narrowing both the signature and dispatch while still accepting a wider field fails the corresponding check. This evidence concerns coverage of the dispatch, with SQL semantics checked by the native comparisons.

The source audit also checks the baseline digests of the value primitives used by all three dialects. A change to list copying, constructor checks, name comparison or the snapshot restoration guard is visible to the verifier. Any intentional revision of those primitives belongs in the review of the assurance mechanism.

## SQLite DISTINCT and row bounds

Run the [result selection contract](sqlite-result-v1.json) with the same SQLite library and worker requirements:

```sh
result_evidence=$(mktemp -d)/sqlite-result-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_result.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$result_evidence"
```

The oracle groups entire typed rows using SQLite's BINARY duplicate equivalence, then models each band of tied ordering keys. The limit must select the expected number of members from each band. The checker preserves storage classes and row multiplicities when testing membership. An analyzed probe exercises a declared NOCASE collation. The runner records both native parameter labels and specific binding failures. It injects wrong duplicate handling, swapped count and offset roles, and changed first-label binding order.

## SQLite DELETE effects

Run the [DELETE effect contract](sqlite-delete-v1.json) against the pinned SQLite library:

```sh
delete_evidence=$(mktemp -d)/sqlite-delete-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_delete.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$delete_evidence"
```

The independent evaluator partitions a fixed set of rows by the predicate's truth value. SQLite executes each observation inside a rolled-back savepoint and reports both target tables, the trigger log, change counts and native binding metadata. The main and temp tables share a name and have visibly different contents. Tests cover absence of a predicate, false and NULL conditions, duplicates, aliases and parameter labels. The runner injects changes to the target schema, predicate and column slot.

## SQLite DELETE RETURNING

The [RETURNING record](sqlite-delete-returning-v1.json) checks projection of old-row slots alongside row removal. The runner observes output names, exact typed values, repeated fields, the bag of deleted rows, parameter registration, trigger results and complete rollback. It checks that emitted fields resolve in the native RETURNING scope even when the WHERE clause uses a generated alias. It also runs the prior statement that the basic DELETE suite had recorded outside its domain.

Run `run_sqlite_delete_returning.py` using the SQLite fixture options. The SQLite collector replays the result and effect observations.

## SQLite VALUES

Run the [VALUES contract](sqlite-values-v1.json) against the same pinned engine:

```sh
values_evidence=$(mktemp -d)/sqlite-values-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_values.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$values_evidence"
```

The checker compares ordered column names and an unordered bag of typed rows. It records native labels in lexical order. Positive cases go through direct row constructors and through analysis. Negative controls exercise empty lists, width mismatch and a column hidden inside arithmetic. The renderer faults remove a row and change the positions within a row.

The result, DELETE and VALUES suites share evidence setup, ledger checks and renderer fault execution. Their 240-second observation budgets are specified in their contracts. The renderer controls and mutations use a separate 180-second worker startup window followed by a 45-second probe budget. A startup or probe timeout yields a failure record and no successful index. Runtime fault detection requires the specified failure signal after the candidate executes.

The [renderer fault dispatch contract](renderer-fault-dispatch-v1.json) records the independent comparison input and the exact worker request for each fault in `fault-dispatch.json`. A dispatch failure leaves a `fault-dispatch-failure.json` record with its phase and request. Replay checks the planned requests and the digest on every completed fault receipt alongside the source overlays and comparison witnesses. The assurance controls try a malformed worker response and three altered dispatch artifacts.

## SQLite local row sources

Run the [local source contract](sqlite-local-source-v2.json) with the same engine:

```sh
local_source_evidence=$(mktemp -d)/sqlite-local-source-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_local_source.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$local_source_evidence"
```

The evaluator supplies the definition's independently evaluated rows as the local input to the projection. The observed dimensions include width, height, typed multiplicity, result names, order, binding labels and read-only execution. The direct construction probes vary the operand graphs and exercise filtering, duplicate elimination and bounds. Analyzed statements run with a same-named physical table holding deliberately different rows. The mutations reverse the definition's column order and alter the first native parameter label.

## SQLite INSERT from a checked projection

The [query INSERT record](sqlite-insert-query-v1.json) checks the composed source query, writable target positions and conflict policy. It evaluates the typed input independently, including DISTINCT, bounds and binding positions, and compares affected rows, generated columns, shadowed tables, triggers and rollback.

Run `run_sqlite_insert_query.py` using the SQLite fixture options. The SQLite collector requires its finite cases and replays the statement effects alongside the other domains.

## SQLite default input and implicit write targets

The [INSERT input record](sqlite-insert-input-v2.json) checks a default-only row and an omitted column list. The completed write records its absolute target and effective conflict policy. A supplied VALUES or SELECT input retains its resolved writable positions. The fixture distinguishes a default that collides with an existing key from a default that inserts a new row and computes a generated value.

Run `run_sqlite_insert_input.py` with the SQLite fixture options. The collector replays typed rows, constraint outcomes, trigger effects and rollback.

## SQLite numeric operators

The [numeric operator record](sqlite-numeric-operators-v1.json) checks division, remainder, bitwise operators and shifts. The evaluator applies signed 64-bit and binary64 rules and compares exact storage classes and values. The finite cross product covers each operand order and root. The generated trees also exercise nested operations and table references. Run `run_sqlite_numeric.py` with the SQLite fixture options.

## SQLite prefix expressions and NULL tests

The [unary record](sqlite-unary-v2.json) tests direct prefix expressions, computed negation and NULL-safe comparisons. Analysis of a signed literal retains the native storage class and signed zero. The fixture checks that unary plus can change an INTEGER column comparison by removing affinity and can retain the text column’s NOCASE collation. Run `run_sqlite_unary.py` with the SQLite fixture options.

## SQLite truth tests

The [concatenation record](sqlite-concatenation-v1.json) checks ordered TEXT bytes and NULL in projections and VALUES. The fixture covers mixed storage classes, native REAL formatting and two byte fragments that form a UTF-8 character only after concatenation. Run `run_sqlite_concatenation.py` with the SQLite fixture options.

The [collation record](sqlite-collation-v1.json) checks explicit BINARY, NOCASE and RTRIM on scalar expressions. It compares nested precedence, declared affinity, NUL-containing TEXT, ordered result positions, BETWEEN bounds and singleton and multi-item IN lists. The same builtin enum and independent byte-key rules serve [SQLite grouping](sqlite-grouped-v2.json). Its schedule, fixtures and expected outcomes are preserved after the enum moves to the shared dialect IR namespace. Run `run_sqlite_collation.py` with the SQLite fixture options.

The [folded membership record](sqlite-folded-membership-v1.json) checks constant empty-list outcomes and surviving numbered registrations. Analysis audits the whole syntax graph and binds only the live part. It preserves bindings from the discarded operand in their original lexical order. The comparison pairs the written statements with emitted SQL and distinguishes the first label of a shared slot and gaps before the highest slot. Run `run_sqlite_folded_membership.py` with the SQLite fixture options.

The [membership record](sqlite-membership-v1.json) covers scalar IN and NOT IN with ordered candidate expressions. It compares the numbered binding count and first labels for empty lists, where SQLite folds the result. The observer distinguishes left affinity and collation from a list member's discarded affinity and implicit collation. Run `run_sqlite_membership.py` with the SQLite fixture options.

The [range test record](sqlite-between-v1.json) covers positive and negated scalar BETWEEN in the same projection and VALUES roles. The independent evaluator applies affinity and collation to each comparison before combining the two truth values. Run `run_sqlite_between.py` with the SQLite fixture options. The recorded faults erase a bound, move the bounds or omit negation.

The [truth record](sqlite-truth-v2.json) distinguishes lookup of truth-word columns, integer literal roles and Boolean tests. The observer checks all four Boolean tests, NULL behavior and equivalent group filters on nonnull counts. The collision fixture pairs each result with its source value, which distinguishes the faulty binding even when result counts match. Run `run_sqlite_truth.py` with the SQLite fixture options.

## SQLite INSERT RETURNING

The [INSERT RETURNING record](sqlite-insert-returning-v1.json) checks an ordered list of supplied slots and a separate ordered result over one target. Its fixture computes defaults and generated values, records the inserted row, and then changes its payload with an AFTER trigger. Run `run_sqlite_insert_returning.py` with the SQLite fixture options to compare the immediate returned row, trigger state, stored state and rollback for all three input roles.

## SQLite UPDATE RETURNING

The [UPDATE RETURNING record](sqlite-update-returning-v1.json) observes the new row after assignments and generated columns are computed. The fixture changes the updated row in an AFTER trigger and records the arguments received by that trigger. The oracle compares the returned rows, persisted rows and trigger effects separately, and confirms that rollback restores all three tables. Run `run_sqlite_update_returning.py` with the SQLite fixture options.

## SQLite grouped counts

The [SQLite grouping record](sqlite-grouped-v2.json) fixes builtin text collations, grouping and COUNT argument alternatives. The independent observer compares exact storage classes, result names and one representative row per group. The case generator includes equal numeric values with different storage classes, repeated rows, NULLs, mixed storage classes, trailing spaces and text containing NUL. The [grouped observer policy](sqlite-grouped-observer-v1.json) limits native statements to reads and builtin COUNT.

Run `run_sqlite_grouped.py` with the same SQLite fixture options as the other domains. The SQLite collector replays the transcript under the same restricted observer and verifies the exact finite identities.

The [group filter record](sqlite-group-filter-v1.json) adds a scoped input filter and a comparison evaluated over the formed group. Run `run_sqlite_group_filter.py` with the SQLite fixture options. The oracle checks both stages against a table with mixed storage classes, unequal counts, NULLs and repeated numbered parameters. The group filter can read a count absent from the result.

## SQLite recursive common tables

The [recursive source contract](sqlite-recursive-source-v1.json) covers one named local definition with an independent anchor and one self-fed SELECT arm. The step scope represents a single dequeued row, and the consuming SELECT reads the resulting local table. The reference evaluator implements queue admission and UNION duplicate elimination, including equality of NULL cells. It compares storage classes, output names, row multiplicity, declared ordering, read-only effects and parameter slots against SQLite 3.47.2.

```sh
recursive_evidence=$(mktemp -d)/sqlite-recursive-source-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_recursive_source.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$recursive_evidence"
```

The recursive observer extends authorization for this domain only. Its progress callback stops an observation after the fixed virtual-machine budget. The runner confirms that the ordinary observer rejects recursive statements, the recursive observer interrupts an unbounded fixture, and a subsequent observation succeeds. Interrupted evaluations fail the comparison. The collector reruns these controls and replays the raw request ledger.

## SQLite INSERT effects

Run the [INSERT effect contract](sqlite-insert-v1.json) with the same engine:

```sh
insert_evidence=$(mktemp -d)/sqlite-insert-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_insert.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$insert_evidence"
```

The fixture uses two same-named tables with different data and conflict behavior, one generated column, a default and an ordered insert-trigger log. The evaluator models the fixture's affinities and defaults, records which writes succeed, and checks the full state after the specific constraint error. Each observation runs inside a savepoint. Comparisons include the native parameter labels and check the statement change count independently of trigger effects.

The acceptance workflow calls `combine_sqlite_evidence.py` after the domain runners. It checks the candidate checkout and requires that every domain index name the same candidate, distribution, tools and engine. The collector starts fresh databases from the pinned SQLite library and reconstructs each required request in order. The recorded PHP responses must match those requests. The collector reexecutes the SQL, compares the observations and requires the reproduced event stream and summary to match the original. Candidate PHP execution and isolation are recorded by the original runner.

```sh
python3 packages/sql-semantics/assurance/combine_sqlite_evidence.py \
  --root /path/to/domain-reports --candidate "$candidate_sha" --candidate-root /path/to/candidate \
  --sqlite-library "$third_party/libsqlite3.so" --out /path/to/sqlite-index
```

Use `--allow-local` for diagnostic input. The resulting index records which domains ran without isolation. A bundle records provisional evidence while human approval and repository-level protection are pending. The workflow also runs `assurance_controls.py` to check missing requests and responses, altered observation rows, extra comparisons, unchecked list storage, a changed Composer bootstrap, and altered, missing or untracked runtime files under an unchanged candidate commit. It preserves the controls alongside F01–F12.

The source audit checks a fixed storage catalogue for all array-valued fields of the completed constructors. It checks the element domain and minimum length, requires a single unconditional assignment from the checked copy and detects assignments through local variables and destructuring. The reviewed scalar-copy form covers VALUES rows. Every admitted child class is final under the existing audit. These checks support the recursive storage argument under the pinned list-copy and restoration primitives. They leave SQL semantics and external schema facts as separate obligations. The runtime package audit also verifies the fixed Composer PSR-4 prefixes, runtime requirements and the absence of symlinks in each checked tree.

The addition of the local input widened the first source type in `Select`. F05 now applies its original-SQL property injection at that declaration. Its fixture, expected shape failure and mandatory presence are preserved.

## SQLite UPDATE effects

Run the [UPDATE effect contract](sqlite-update-v1.json) with the same engine:

```sh
update_evidence=$(mktemp -d)/sqlite-update-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_update.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$update_evidence"
```

The reference evaluator computes each effective assignment from the old row. The fixture includes duplicates, NULLs, a generated column and two same-named targets with distinct conflict policies. Comparisons preserve the typed contents of both targets and the OLD and NEW trigger values. Skipped rows and statement rollback are observable. Numbered parameter labels are checked in written assignment order, including a superseded assignment.

## SQLite DROP TABLE effects

Run the [DROP TABLE effect contract](sqlite-drop-table-v2.json) with the same engine:

```sh
drop_table_evidence=$(mktemp -d)/sqlite-drop-table-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_drop_table.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$drop_table_evidence"
```

The fixture enables foreign keys before construction and compares object identity, removal of owned indexes and triggers, cascade effects and immediate restrictions. Its trigger log detects any invocation of the removed table's trigger. The outer runner compares the complete user catalog in both schemas and checks the prepare-time error for a missing unguarded target. The structural explorer generates distinct decoded names, including names containing quotes and non-ASCII characters.

## SQLite CREATE TABLE effects

Run the [CREATE TABLE effect contract](sqlite-create-table-v3.json) with the same engine:

```sh
create_table_evidence=$(mktemp -d)/sqlite-create-table-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_create_table.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$create_table_evidence"
```

The observer compares ordered native column metadata, implicit rowids and data writes through the created table. It checks typed affinity conversions, NOT NULL violations and the existing rows preserved by IF NOT EXISTS. Both main and temp catalogs contain a same-named sentinel table. The structural explorer varies type, column count, target identifiers, nullability and collision handling.

The [SQLite STRICT table contract](sqlite-strict-tables-v2.json) compares the catalog mode and declared names and measures each write's typed rows, change count and exact constraint errors. Its fixed storage values distinguish ordinary ANY from STRICT ANY, BLOB from untyped and INT from INTEGER. Run `run_sqlite_strict_tables.py` with the same fixture options. The original and emitted declarations are compared independently for analyzed cases.

The [SQLite primary key contract](sqlite-primary-keys-v2.json) follows resolved owned-column slots through an inline or table constraint. Its fixtures compare primary key index fields, collations and directions, rowid availability, generated keys, duplicate and NULL writes, and rollback. Run `run_sqlite_primary_keys.py` with the SQLite fixture options to record both transaction states.

The [SQLite automatic rowid contract](sqlite-auto-rowid-v1.json) preserves the distinction between the ordinary allocator and AUTOINCREMENT. It compares alias values and the owning schema's sqlite_sequence after deletions, commits and rollbacks. The fixed histories also verify SQLITE_FULL after exhausting the signed range. Run `run_sqlite_auto_rowid.py` with the same SQLite fixture options.

The [SQLite population contract](sqlite-create-as-v5.json) checks the catalog and contents of CREATE TABLE AS. It compares declaration names, affinity, read-back storage classes, rowid order, parameters and the guarded destination. Run `run_sqlite_create_as.py` with the SQLite fixture options. The contract preserves the independent counterexamples for compound type selection and population affinity.

The [SQLite unique-key contract](sqlite-unique-keys-v1.json) covers ordered inline and table keys, default and explicit collations and conflict policies, equivalent indexes, and coexistence with a primary key. The fixture compares native index order, covering slots, nullable writes, colliding rows and rollback. Run `run_sqlite_unique_keys.py` with the pinned SQLite library and the ordinary SQLite fixture arguments.

The named CHECK fixture independently models slot comparisons, NULL propagation and ordered CHECK failures. It measures ordinary INSERT and conflict overrides, column rename and drop, and coexistence with NOT NULL and UNIQUE. The journal preserves the full ALTER diagnostic and compares the resolved column dependency. Run `run_sqlite_named_checks.py` with the same arguments. [SQLite named CHECK contract](sqlite-named-checks-v4.json)

The folded CHECK suite compares native preparation slot count and first marker spelling, then repeats the declaration and writes with absent, NULL, integer and text bindings. A discarded operand contributes its markers in occurrence order. The observed schema condition remains independent of the supplied values. Run `run_sqlite_folded_checks.py` with the standard SQLite fixture options. [SQLite folded CHECK contract](sqlite-folded-checks-v1.json)

The [implicit comparison CHECK contract](sqlite-implicit-checks-v1.json) observes the failure labels of unnamed comparisons before and after native column rename. Its witness varies declaration slot, referenced slot, comparison aliases, quoting, whitespace and zero prefixes. The native keyword catalogue checks the allowed bare identifier spellings. Run `run_sqlite_implicit_checks.py` with the standard SQLite fixture options.

The [SQLite joined local-source contract](sqlite-joined-local-source-v1.json) uses one immutable VALUES definition in either input position and in several occurrences of an explicit join. The independent observer checks the shared value identity, scoped operands, typed rows and parameter registration. It uses the same-named base table as a distinct source for comparison. Run `run_sqlite_joined_local_source.py` with the standard fixture arguments.

The [SQLite generated-column contract](sqlite-generated-columns-v2.json) checks resolved dependencies across VIRTUAL and STORED columns, forward references and multiple parents. An independent evaluator predicts typed rows from ordinary inserts and updates, and the native fixture measures storage metadata, writable columns, constraint effects, marker registrations, rename and dependent-drop rejection. The fixture reads durable schema and rows after recovering a fixed inner savepoint. SQLite 3.47.2 can temporarily omit generated columns in a connection-local projection immediately after a failed DROP with a double-quoted reference. The raw diagnostic and the recovered state remain observable evidence. Run `run_sqlite_generated_columns.py` with the same SQLite fixture options.

The [SQLite CREATE VIEW contract](sqlite-create-view-v2.json) checks view declarations with a projection or VALUES body. The native observation covers both schema catalogs, ordered column metadata, typed results, guarded declarations, and later reads after base-table changes. Run `run_sqlite_create_view.py` with the same fixture arguments. The [grouped-view domain](sqlite-create-view-grouped-v1.json) adds a separate finite product and native observations for aggregate counts, partitioning, HAVING, and the global empty group. Run `run_sqlite_create_view_grouped.py` to replay those observations. The [compound-view domain](sqlite-create-view-compound-v1.json) checks all four set operators with deterministic declaration affinity, including duplicate and NULL handling, later inputs, the exact outer limit and the SQL size of the full declaration. Run `run_sqlite_create_view_compound.py` for this domain. The [local-view domain](sqlite-create-view-local-v1.json) exercises values and recursive definitions, mixed joins, shared identities, the earlier unsupported case as a positive witness, and refusals for markers inside a definition. Run `run_sqlite_create_view_local.py` for this domain. The [resolved view input domain](sqlite-resolved-view-input-v2.json) checks reading an external view with its own absolute source identity, preserving complete wildcard expansion, independent repeated references and the base-table mutation boundary. Run `run_sqlite_resolved_view_input.py` for this domain.

The [SQLite DROP VIEW contract](sqlite-drop-view-v1.json) verifies the selected view's schema under temp/main collisions. It compares owned-trigger removal, catalog membership, dependent reads and unchanged underlying data. The finite identities use both the actual IR and the verified catalog before removal. Run `run_sqlite_drop_view.py` with the same fixture arguments.

The rename fixture checks qualified table identity, both column rewrite modes, stored rows, schema dependencies and rollback. It obtains native keyword spellings from the SQLite C API and measures all legal and rejected bare destinations. The view and trigger cases retain native refusal outcomes and distinguish a changed index role under temp-table shadowing. Run `run_sqlite_rename.py` with the same SQLite fixture options. [SQLite rename contract](sqlite-rename-v4.json)

The ordinary column addition fixture measures changes to the resolved table while a same-named table in the other schema stays observable. It compares the prior heading, byte-preserving default reads and inserts, NOT NULL conflict behavior, collation, native diagnostics, and rollback. The fixed finite domain crosses schema, table population, declared type and STRICT mode, literal default, and NULL policy. The separate analyzed cases include native counterexamples for folding the exponent marker of a REAL default in a TEXT column. Run `run_sqlite_add_column.py` with the same SQLite fixture options. [SQLite column addition contract](sqlite-add-column-v3.json)

The generated column addition fixture distinguishes VIRTUAL and STORED storage, populated and empty targets, prior generated inputs, and WITHOUT ROWID tables. It observes the generated value after inserting and updating dependencies, read and write restrictions, atomic refusal, and numbered parameter registrations in folded expressions. Run `run_sqlite_add_generated_column.py` with the same SQLite fixture options. [SQLite generated column addition contract](sqlite-add-generated-column-v1.json)

The computed default fixture compares declaration, old-row fallback and insertion across CREATE TABLE and both empty and populated ALTER targets. It observes computed NULL handling, folded marker registration, native error phase, and the nested unary minus cases that binary subtraction changes. Run `run_sqlite_computed_defaults.py` with the same SQLite fixture options. [SQLite computed column default contract](sqlite-computed-column-default-v3.json)

The appended CHECK fixture compares ordinary, VIRTUAL and STORED additions with ordered named constraints. It measures the new and prior slots, old-row refusal, the first failing label during later writes, folded registrations and rollback. Run `run_sqlite_appended_checks.py` with the same SQLite fixture options. [SQLite appended CHECK contract](sqlite-appended-column-checks-v2.json)

The implicit appended CHECK fixture uses checked comparisons and observes failures before and after renaming their referenced columns. It also exercises SQLite's rule that an explicit constraint name remains in effect for later checks in the same inline definition. Run `run_sqlite_appended_implicit_checks.py` with the same SQLite fixture options. [SQLite implicit appended CHECK contract](sqlite-appended-implicit-checks-v1.json)

The column removal fixture binds the declared old slot and checks local keys, conditions and generated dependencies before publication. It independently compares the compacted catalog, ordered typed rows, rowid aliases, integrity, later writes and rollback. It also observes native index rewriting under temp-table shadowing. Run `run_sqlite_drop_column.py` with the same SQLite fixture options. [SQLite column removal contract](sqlite-drop-column-v3.json)

The index fixture compares a scoped ordinary or unique index and its optional partial expression. It observes ordered keys, collation, key type, NULL and duplicates, forced index reads, catalog state, numbered parameters and rollback. Run `run_sqlite_create_index.py` with the same SQLite fixture options. [SQLite index contract](sqlite-create-index-v5.json)

The index expression spelling fixture compares native CAST names and binary IFNULL against independently authored SQLite SQL. It observes literal conversion, exact partial index matching, expression key search and typed results. Both the original and rendered CREATE INDEX run on the same pinned engine. Run `run_sqlite_index_expression_spelling.py` with the SQLite fixture options. [SQLite index expression spelling contract](sqlite-index-expression-spelling-v1.json)

The index removal fixture compares the selected object and owner, catalog and statistics removal, original and subsequent typed table state, constrained writes, rollback, and the schemas verified by an IF EXISTS no-op. Run `run_sqlite_drop_index.py` with the SQLite fixture options. [SQLite index removal contract](sqlite-drop-index-v3.json)

The SQLite trigger mutation fixture checks ordered INSERT, UPDATE and DELETE actions, local and row-image field roles, collation-sensitive comparison orientation, and conflict handling. Fixture triggers observe whether the outer write took place before or after the checked trigger program. Run `run_sqlite_trigger_mutations.py` with the SQLite fixture options. [SQLite trigger mutation contract](sqlite-trigger-mutations-v2.json)

The SQLite trigger batch fixture distinguishes a single ordered VALUES insertion from separate operations. It checks exact change deltas, typed row state, ordered effects, native constraint failures and caller rollback under inner and outer conflict policies. Run `run_sqlite_trigger_insert_batches.py` with the SQLite fixture options. [SQLite trigger batch contract](sqlite-trigger-insert-batches-v1.json)

The SQLite trigger interruption fixture checks source-free SELECT RAISE actions with NULL guards and checked message values. It compares precise diagnostics, earlier and later trigger effects, the write that activated the trigger and rollback of a separate transaction marker. Run `run_sqlite_trigger_raise.py` with the SQLite fixture options. [SQLite trigger interruption contract](sqlite-trigger-raise-v1.json)

The SQLite trigger removal fixture compares installation identity, schema verification, unaffected sibling definitions, rollback and later firing. For every required missing name in the finite matrix, it compares a native preparation diagnostic with the typed construction refusal. Run `run_sqlite_drop_triggers.py` with the SQLite fixture options. [SQLite trigger removal contract](sqlite-drop-triggers-v4.json)

The constraint index fixture compares the native original and generated absence selectors. It records protected PRIMARY KEY and UNIQUE indexes, including WITHOUT ROWID storage, along with separate direct checks for each watched absence. Run `run_sqlite_constraint_index_names.py` using the SQLite fixture options. [SQLite constraint index contract](sqlite-constraint-index-names-v2.json)

The REINDEX fixture compares the selected index coordinates and actual traversal order before and after rebuilding. Distinct outdated orders expose incorrect selections, and a unique-key fixture records a native failure and atomic rollback. Run `run_sqlite_reindex.py` with the SQLite fixture options. [SQLite REINDEX contract](sqlite-reindex-v2.json)

The statistics fixture compares names, scope and exact STAT1 rows after collecting table or index statistics. It checks NULL, collation, partial membership, stale state, WITHOUT ROWID duplicates and rollback. Run `run_sqlite_analyze_statistics.py` with the SQLite fixture options. [SQLite ANALYZE contract](sqlite-analyze-statistics-v1.json)

## SQLite literal defaults

The [default record](sqlite-defaults-v4.json) extends the ordinary declaration with a checked literal operand. The runner records both raw catalog values and declared default presence and compares omitted, overridden and explicit NULL writes against an independent affinity model. It checks both schema locations, the effects of IF NOT EXISTS, constraint violations, and restoration of the fixture.

Run `run_sqlite_defaults.py` using the SQLite fixture options. The SQLite collector requires and replays the default receipt.

The [column policy record](sqlite-not-null-conflict-v2.json) checks INSERT and UPDATE behavior after the declaration is installed. It crosses all five algorithms, explicit statement overrides, absence of the constraint, three fixed defaults and two transaction states. The explorer varies column count, storage type and the column receiving NULL. Run `run_sqlite_not_null_conflict.py` with the same options and retain the catalog, conflict write and transaction records.

The [SQLite first non-NULL contract](sqlite-coalesce-v1.json) checks COALESCE and binary IFNULL, including ordered arguments, all-NULL results and deferred evaluation errors. It compares implicit and explicit collation, marker registration, filters and schema expressions. Run `run_sqlite_coalesce.py` with the pinned native fixture and standard options.

## SQLite literals and scalar composition

Run the [scalar contract](sqlite-scalars-v2.json) with the pinned SQLite library:

```sh
scalar_evidence=$(mktemp -d)/scalar-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_scalars.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$scalar_evidence"
```

The four new literal classes are under the SQLite IR namespace Literal. The runner checks literal storage classes, negative zero, ordered result labels and parameter slots. It covers the finite product of literal pairs and the recorded operators in SELECT and VALUES, and explores nested operands from the fixed seeds. Six persistent-state observations compare the three write forms through direct construction and analysis against a table with duplicates and mixed storage classes. Each write is rolled back to the original fixture. The mutations detect loss of the REAL or hexadecimal sign and a TEXT value emitted as BLOB.

The second scalar revision keeps the finite product and adds exact TEXT bytes containing NUL. Its affinity control distinguishes the unary-plus representation from a plain TEXT cast in a numeric comparison. The former NUL rejection becomes a positive direct construction. A separate ill-formed UTF-8 input checks the retained encoding boundary.

## SQLite built-in patterns

The [pattern contract](sqlite-pattern-v1.json) distinguishes ASCII-insensitive LIKE, case-sensitive GLOB, their negation and the optional LIKE escape. The [observer policy](sqlite-pattern-observer-v1.json) permits those two built-in functions for these comparisons. The runner measures the native build settings, all finite identities, nested and escaped operands, nullable results and the specified invalid-escape evaluation error. Run `run_sqlite_pattern.py` with the SQLite fixture options. The SQLite collector requires and replays its receipt.

## SQLite conditional expressions

The [CASE record](sqlite-case-v1.json) links the ordered branches, optional base, and fallback to their construction conditions and observation rules. The runner compares the direct and analyzed forms against the independent lazy evaluator and SQLite 3.47.2, including both simple and searched CASE, typed results, parameter registration, and effective column affinity and collation. It detects incorrect precedence, selection of a WHEN operand as a result, and omitted base or fallback operands.

Run `run_sqlite_case.py` with the SQLite fixture options. The SQLite collector requires and replays the CASE receipt.

## SQLite scalar casts

The [cast record](sqlite-cast-v1.json) fixes the conversion role from the declared type affinity. Its native comparisons preserve the stored result type, exact numeric and byte values, NULL, names and parameter slots. The [SQLite TEXT observation record](sqlite-text-observation-v1.json) retains bytes that fall outside UTF-8 and distinguishes them from BLOBs with the same content.

Run `run_sqlite_cast.py` using the SQLite fixture options. Its observed identities, typed comparisons and renderer faults are required by the collector.

## SQLite compound queries

Run the [compound contract](sqlite-compound-v1.json) with the pinned SQLite library:

```sh
compound_evidence=$(mktemp -d)/compound-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_compound.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$compound_evidence"
```

The runner checks SELECT and VALUES operands, all four operations, and mixed chains. The independent evaluator reduces typed rows in lexical order and checks ordinal sorting, row bounds, parameter labels and the names of the first arm. The exact 500-arm boundary and placement errors exercise the composition checks. Additional analyzed inputs compare collated and numerically equivalent rows with the original native execution. Faults change duplicate handling, operand order, and the outer ordering clause.

## SQLite transaction state

Run the [transaction contract](sqlite-transaction-v1.json) with the pinned SQLite library.

```sh
transaction_evidence=$(mktemp -d)/transaction-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_transaction.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$transaction_evidence"
```

The reference state machine tracks fixture rows, the outer transaction and the ordered savepoint stack. The native observer reads autocommit, rows, metadata and state-dependent errors after every operation in the fixture. A separate two-connection fixture observes the three BEGIN modes under DELETE journaling. A savepoint lookup is a named action on the connection's state at execution, so no declaration context or savepoint object is retained.

The [SQLite PRAGMA inventory](sqlite-pragma-namespace-v1.json) records the 78 stock and conditional names independently of the PHP class and test generators. Regenerate the native declaration table and the active engine inventory with `python3 sqlite_pragma_inventory.py --sqlite-source <sqlite3.c> --sqlite-library <library> --out <new-json-file>`. The `declaredColumns` entry lists the explicit column labels or the default label for a pragma that emits a column. Build conditions and the request family determine which labels appear in an actual result.

The [SQLite flag PRAGMA contract](sqlite-flag-pragmas-v1.json) covers the 18 connection flags present in both builds. Run `run_sqlite_flag_pragmas.py` with the SQLite fixture options to compare direct and analyzed requests, state changes at prepare time, exact result headers and SQLite types, transaction-sensitive foreign key behavior and schema reload. The engine inventory records all unexercised names, including active pragmas outside the flag family.

The [SQLite table metadata contract](sqlite-table-metadata-v2.json) covers ordered column, index and foreign-key introspection, typed schema-list rows, catalog aliases and native scope. Run `run_sqlite_table_metadata.py` with the SQLite fixture options. The native comparison records how a temporary view can shadow SQLite's internal metadata initialization. The widened observation distinguishes the cold and warmed main-view states and retains the original counterexample digest.

The [SQLite connection inventory contract](sqlite-connection-inventory-v1.json) covers all six builtin registry reads. Run `run_sqlite_inventory.py` with the SQLite fixture options. The comparison uses the native database and compile-option APIs, the separate active-name inventory, and the [native registry snapshot](sqlite-native-registry-v1.json). Regenerate the snapshot from the fixed amalgamation with `python3 sqlite_native_registry.py --sqlite-source <sqlite3.c> --out <new-json-file>`. It derives function and module data from the registrations in the engine. The comparison preserves capabilities, names, overloads, exact filesystem filenames and SQLite's activation of temp.

The [SQLite index metadata contract](sqlite-index-metadata-v1.json) verifies `index_info` and `index_xinfo`, including exact index identity, physical primary storage, qualified scope and empty results. Run `run_sqlite_index_metadata.py` with the SQLite fixture options. The oracle compares native index positions, flags, collation and expression or rowid sentinels, as well as the database slot measured from the native program.

The [SQLite foreign-key audit contract](sqlite-foreign-key-audit-v1.json) covers schema-wide and relation-specific native checks. Run `run_sqlite_fk_audit.py` with the SQLite fixture options. The oracle matches the parent key by collation and SQLite affinity, checks NULL and duplicate violation rows, and compares the selected database slot and unchanged fixture population. A generated identifier containing a quote exposed an error in the expected native diagnostic. The original counterexample SHA-256 is `f143cb3c52a18b6116199033c849761e87cd0ec3b0e034e6cef0efa988836de2`. The ordered coverage includes a separate authored mismatch with quotes in both identifiers.

The [SQLite integrity audit contract](sqlite-integrity-audit-v2.json) covers both native algorithms, report budgets and relation lookup. The verifier runs `run_sqlite_integrity_audit.py` with the pinned SQLite library. It compares typed reports, attachment access, temporary opening and the native program budget. Its fixture distinguishes full and quick checks using an index that was built before its collation changed.

The [SQLite header pragma contract](sqlite-header-pragmas-v1.json) compares reads and writes using `run_sqlite_header_pragmas.py` with the pinned SQLite library. The verifier inspects serialized page-one bytes in all opened databases and compares read and write transactions, the exact cookie operands, rollback, and a file-backed sequence of local and external commits. It compares the separately constructed value for every observed finite source coordinate.

The [SQLite VACUUM contract](sqlite-vacuum-v1.json) uses `run_sqlite_vacuum.py` with the pinned library. It compares the actual page count and header cookies, fixture rows, copied file and runtime errors. The observer admits only the known filename in its disposable working directory and the native rebuild callbacks. The fixed schedule checks temporary statements that register numbered aliases while skipping the filename expression.

The [SQLite connection command contract](sqlite-connection-commands-v1.json) uses `run_sqlite_connection_commands.py` with the pinned library. It reconstructs the bounded native argument program before executing the request against disposable paths. It compares attachment order, file populations, parameter roles, authorization arguments, and the effect of a transaction on the B-tree selected for detachment.

The [SQLite pager pragma contract](sqlite-page-pragmas-v1.json) uses `run_sqlite_page_pragmas.py` with the pinned library. It checks preparation and stepping separately, reads the physical file header, and observes a later VACUUM and an insert refused by the page limit. SQLite cannot build the cold temporary pager during EXPLAIN. The verifier opens it in the separate program fixture and still measures the cold connection in each actual prepare and step test.
