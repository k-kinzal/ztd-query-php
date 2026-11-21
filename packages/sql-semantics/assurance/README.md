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

## Read the evidence

- `index.json` identifies the candidate commit, contract, tool files, SQLite build, supported scope, executed checks and fault records.
- `inventory.json` identifies named types, visibility, properties, traits and constructor or factory signatures across all packages. The baseline inventory procedure and gaps are in [baseline.md](baseline.md).
- `events.jsonl` contains input, constructed shape, emitted SQL, storage-class-tagged results, binding metadata and reported rejections. Finite completion is checked against independently computed symbolic IDs.
- `counterexample.json` is written on failure inside the main run. The original input and observed failure remain available even if a smaller reproducing input cannot be found. A shrink is accepted only when it constructs and still fails the independent comparison.
- `faults/` contains F01–F12 patches, original and mutated digests, fixture inputs, the healthy control and the observed rejection signals. The fault results establish detection of these particular faults.

The independent evaluator reads the direct construction inputs and implements selection, projection, literal values, parameter slots and SQLite arithmetic for the fixed test data. It shares the structure vocabulary and fixture restrictions with the case generator. PHP supplies the observed structure and SQL only. SQLite supplies ordered names, native parameter slot labels, runtime values and storage types through its C API. The comparator preserves repeats and compares rows as a multiset under the contract's lack of guaranteed ordering. The fixed table contains distinct column values, repeated rows, NULL, values of each SQLite storage class, and an alternate table in the temp schema. Analyzed-SQL examples separately compare the original and emitted SQL on the same engine.

The checked domain supports a composition argument. Every leaf has one of the closed roles. Each arithmetic node has two ordered children of those roles. The constructor visits every occurrence, checks the source slot range, and bounds expansion. All lists and children have immutable value semantics. The renderer emits the two operands with the recorded operator inside parentheses, or an explicit leaf representation. A schema-qualified source and an explicit output alias preserve the external identity and result names. This is an argument for human review. The finite and seeded SQLite comparisons are empirical evidence. The PHP code shape audit checks the recorded property types, method names, traits, dependencies and known dynamic constructs. It does not prove arbitrary method bodies or establish the correctness of parser lowering and declaration resolution.

## Establish independent acceptance

The proposed [manual evidence workflow](../../../.github/workflows/semantics-ir-assurance.yml) selects its checker from the workflow revision on `main` and reads an explicit candidate SHA. Keep it outside the candidate's pull-request checks and review the workflow, its action pins, locked dependencies, engine build, oracle, mutation fixtures and contract before using the evidence for acceptance. Require repository-owner control of changes to this workflow and its inputs, and an independently maintained decision keyed to the tested candidate SHA. Verify that the decision rejects missing or stale evidence. No repository-level control has been installed or verified in this change.

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

To join the independent evidence from all native server releases, run `combine_server_evidence.py --candidate "$candidate_sha" --out server-matrix.json` with the paths to the per-release `index.json` files. All inputs must identify the same candidate, distribution, contract and tools. The join fails when a required release is missing, appears twice or has incomplete evidence.

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
