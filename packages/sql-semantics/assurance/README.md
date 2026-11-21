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

The host owns the case generation, comparator, SQLite database, and evidence path. The candidate worker has read-only mounts, no network, no added capabilities, and memory and process limits. The static inventory reads PHP as data. It does not use the candidate's Composer autoloader. `--local` disables worker isolation for debugging. The evidence records the actual isolation mode.

Use `--verify-distribution "$evidence/distribution.json"` with the same arguments to compare a checkout with the captured files. The manifest covers source, resources and locked dependencies of the four Semantics packages and SQL Parser. It does not pin the contents of a downstream Composer installation. Use the library and tool digests recorded in the evidence when comparing runs.

## Publication contract

The [direct publication record](publication-v2.json) supersedes the opt-in entry points in the original criteria. The shared analyzer and each database façade publish complete IR from `analyze()`. The source audit checks the façade's call to the checked lowering path and the `analyzeAll()` loop. The workers exercise the façade directly. The earlier criteria retain their semantic domains, observations, budgets and mandatory fault cases.

## Read the evidence

- `index.json` identifies the candidate commit, contract, tool files, SQLite build, supported scope, executed checks and fault records.
- `inventory.json` identifies named types, visibility, properties, traits and constructor or factory signatures across all packages. The baseline inventory procedure and gaps are in [baseline.md](baseline.md).
- `grammar-scope.json` enumerates the versioned grammar rules independently of the PHP model and generators. The original recorded counts cover nonempty alternatives. The new index gives both counts and preserves the empty alternatives. The links to partial domain records identify related checks. Every syntax alternative still needs semantic classification.
- `events.jsonl` contains input, constructed shape, emitted SQL, storage-class-tagged results, binding metadata and reported rejections. Finite completion is checked against independently computed symbolic IDs.
- `counterexample.json` is written on failure inside the main run. The original input and observed failure remain available even if a smaller reproducing input cannot be found. A shrink is accepted only when it constructs and still fails the independent comparison.
- `faults/` contains F01–F12 patches, original and mutated digests, fixture inputs, the healthy control and the observed rejection signals. The fault results establish detection of these particular faults.

The independent evaluator reads the direct construction inputs and implements selection, projection, literal values, parameter slots and SQLite arithmetic for the fixed test data. It shares the structure vocabulary and fixture restrictions with the case generator. PHP supplies the observed structure and SQL only. SQLite supplies ordered names, native parameter slot labels, runtime values and storage types through its C API. The comparator preserves repeats and compares rows as a multiset under the contract's lack of guaranteed ordering. The fixed table contains distinct column values, repeated rows, NULL, values of each SQLite storage class, and an alternate table in the temp schema. Analyzed-SQL examples separately compare the original and emitted SQL on the same engine.

The checked domain supports a composition argument. Every leaf has one of the closed roles. Each arithmetic node has two ordered children of those roles. The constructor visits every occurrence, checks the source slot range, and bounds expansion. All lists and children have immutable value semantics. The renderer emits the two operands with the recorded operator inside parentheses, or an explicit leaf representation. A schema-qualified source and an explicit output alias preserve the external identity and result names. This is an argument for human review. The finite and seeded SQLite comparisons are empirical evidence. The PHP code shape audit checks the recorded property types, method names, traits, dependencies and known dynamic constructs. It does not prove arbitrary method bodies or establish the correctness of parser lowering and declaration resolution.

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

The initial runner does not establish complete coverage of the existing grammar productions, full acceptance of every supported SQL input, or a machine-checked proof. The legacy models, other SQLite constructs, uncovered server projection roles and modes, and comparison of statement effects outside SELECT all remain open under the wider contract.

## MySQL and PostgreSQL evidence

[Server projection criteria](server-projection-v1.json) add independently checked native domains for MySQL and PostgreSQL. [The MySQL label refinement](server-projection-errata.md) records an observed server rule about output names. The mandatory fixture releases are MySQL 9.1.0 and PostgreSQL 17.2. Other grammar releases and language modes remain distinct engine-test obligations.

Use PHP with `pdo_mysql` and `pdo_pgsql`. Start disposable MySQL and PostgreSQL containers with an `ir_fixture` database. The manual workflow records the pinned image digests and settings. In a local run, configure the six variables `ZTD_IR_MYSQL_DSN`, `ZTD_IR_MYSQL_USER`, `ZTD_IR_MYSQL_PASSWORD`, `ZTD_IR_POSTGRES_DSN`, `ZTD_IR_POSTGRES_USER` and `ZTD_IR_POSTGRES_PASSWORD` for those disposable databases. The observer creates fresh test tables and uses read-only transactions for candidate SQL. The separate PHP candidate container has no database connection.

```sh
server_evidence=$(mktemp -d)/server-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_server.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --out "$server_evidence"
```

The finite verifier checks the observed case IDs by expression tree and source policy. The evaluator independently computes integer operations, NULL propagation, overflow, output types and names, duplicate rows, and PostgreSQL inheritance. Each analyzed case compares the original statement and its emitted SQL in the observed server settings.

The SQLite evidence also writes `scope.json`. It compares the AST inventory with the constructor IDs and named public return roles in the fixed contracts. Each matched constructor links to its source digest and public and magic signatures. An added or missing constructor or publication route fails the check. The same audit follows inherited interfaces to find every completed root and compares grammar artifact digests against [the fixed baseline list](required-grammars.json). The wider model and grammar obligations remain open. Legacy public types are listed for further classification by semantic role.

For a single declared release, pass `--engine mysql --release 8.4.7` or `--engine postgres --release 16.6` and connect the observer to that release. The runner checks the exact server version before comparing any case. The [manual release matrix](../../../.github/workflows/semantics-ir-release-matrix.yml) pins images for all nine MySQL releases and both PostgreSQL releases. Its artifacts are keyed by engine and release. Every matrix result remains specific to the native projection domain.

To join the independent evidence from all native server releases, run `combine_server_evidence.py --candidate "$candidate_sha" --candidate-root /path/to/candidate --out server-matrix.json` with the paths to the per-release `index.json` files. The join checks the candidate checkout, the distribution, contract and tools. The join reproduces the required requests, recomputes the direct oracle and compares both recorded observations for analyzed statements. A missing or duplicate release, missing original observation or changed ledger fails the join. Collect server observations with the revised runner before replaying them.

## SQLite joins, filters and ordering

The [SQLite relational criteria](sqlite-relational-v1.json) extend the initial SQLite projection domain. Run the same pinned SQLite 3.47.2 build and isolated PHP image through the separate checker:

```sh
relational_evidence=$(mktemp -d)/sqlite-relational-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_relational.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$relational_evidence"
```

The evaluator treats outer joins as filtered products with unmatched-row extension, uses SQL truth rules for predicates and checks ordering without constraining ties. It reads fixture data with deliberately different and duplicate rows, NULLs and empty inputs. Separate analyzed cases compare the original and emitted statements, including a self join and ordering by a column with a declared collation. The relational mutations change an occurrence alias, remove NULL-extended rows, drop a filter, reverse an ordering key and bypass the snapshot restoration guard. Run the initial F01–F12 exercise on the same distribution.

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

## SQLite local row sources

Run the [local source contract](sqlite-local-source-v1.json) with the same engine:

```sh
local_source_evidence=$(mktemp -d)/sqlite-local-source-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_local_source.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$local_source_evidence"
```

The evaluator supplies the definition's independently evaluated rows as the local input to the projection. The observed dimensions include width, height, typed multiplicity, result names, order, binding labels and read-only execution. The direct construction probes vary the operand graphs and exercise filtering, duplicate elimination and bounds. Analyzed statements run with a same-named physical table holding deliberately different rows. The mutations reverse the definition's column order and alter the first native parameter label.

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

Use `--allow-local` for diagnostic input. The resulting index records which domains ran without isolation. A bundle records provisional evidence while human approval and repository-level protection are pending. The workflow also runs `assurance_controls.py` to check missing requests and responses, altered observation rows, extra comparisons, unchecked list storage and a changed Composer bootstrap. It preserves the controls alongside F01–F12.

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

Run the [DROP TABLE effect contract](sqlite-drop-table-v1.json) with the same engine:

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

Run the [CREATE TABLE effect contract](sqlite-create-table-v1.json) with the same engine:

```sh
create_table_evidence=$(mktemp -d)/sqlite-create-table-evidence
PYTHONDONTWRITEBYTECODE=1 python3 packages/sql-semantics/assurance/run_sqlite_create_table.py \
  --candidate /path/to/candidate \
  --tool-autoload packages/sql-semantics-sqlite/vendor/autoload.php \
  --sqlite-library "$third_party/libsqlite3.so" \
  --out "$create_table_evidence"
```

The observer compares ordered native column metadata, implicit rowids and data writes through the created table. It checks typed affinity conversions, NOT NULL violations and the existing rows preserved by IF NOT EXISTS. Both main and temp catalogs contain a same-named sentinel table. The structural explorer varies type, column count, target identifiers, nullability and collision handling.
