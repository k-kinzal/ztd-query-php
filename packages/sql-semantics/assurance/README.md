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

The initial runner does not establish complete coverage of the existing grammar productions, full acceptance of every supported SQL input, or a machine-checked proof. The legacy models, other SQLite constructs, MySQL and PostgreSQL, uncovered modes, and comparison of statement effects outside SELECT all remain open under the wider contract.
