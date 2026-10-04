# Candidate analysis validation

These results were collected on 2026-10-04. They distinguish candidate-contract acceptance, regression coverage, measured workloads and application probes. They do not establish complete PHP or framework coverage.

## Automated checks

The default public API is exercised by `CandidateContractTest`, `CandidateIntegrityTest` and `CandidateDependenciesTest`. The acceptance identifiers map to the requested behavior as follows:

| Cases | Checked behavior |
| --- | --- |
| C01, C16 | An unrelated body, exception or unavailable ancestor does not affect a constant observation; body and input expansion counts are zero |
| C02–C05 | Identifiable formal inputs, reverse caller lookup, arithmetic residual alternatives and partial explicit bindings |
| C06 | Property initializer and ordinary-method write origins |
| C07–C09 | Models replace available source and demand only required inputs; additional tests check demand-based matching and explicit decline |
| C10–C12 | Reference depth, retained operands and receiver types; an additional interruption test enforces a time boundary |
| C13–C15 | Closed numeric expressions, 8,192-element arrays with normal, negative, numeric-string and computed keys, and partial arrays |
| G01–G05 | Reaching definitions, distinct declarations, inheritance, call contexts and correlated branch pairs |
| G06–G09 | Recursive residuals and finite recursion, reference writes, reassignment types and unsupported-operation structure |
| G10–G12 | Shared work across observations, unresolved siblings, depth/partition isolation, changed source snapshots and model versions |
| G13–G14 | Bounded retention, explicit release and immutable caller-owned results |

Additional cases cover lexical captures, model state slots, aliases, array updates/unpacking, signature-only calls, declaration coercions and catches, replacement of reference-writing source bodies, conditional receiver classes, globals, finite loops and post-condition loops. The SQL corpus probe exposed a missing first `do ... while` body; regression tests now check both a mandatory first iteration and successive condition versions, including an earlier symbolic dependency.

Validation on PHP 8.5.8 with Xdebug disabled:

| Check | Result |
| --- | --- |
| Unit, integration, semantic, model-contract and doctest suites | 5,226 tests, no failures |
| Differential suite against an independent PHP 8.3.33 process | 1,661 tests / 7,618 assertions, no failures or skips |
| New candidate differential fixtures, included above | 166 closed finite programs, all agree with PHP |
| Existing execution semantic fuzzing | 100 seeds / 300 runtime comparisons, zero mismatches |
| Mutation fuzz smoke | 100 runs, no crash |
| Composer lint | Autoload, formatting, PHPStan, PHP compatibility, size/tree guards and dependency checks pass |

The execution fuzz suite retains its explicit execution contract. It is not presented as fuzz coverage of every candidate feature. The existing execution tests and changed expectations are explained in [migration](migration.md). Deptrac's dependencies emit PHP 8.5 deprecation notices; its report contains zero violations, uncovered dependencies, warnings or errors.

Run from the package directory after installing its development dependencies:

```sh
XDEBUG_MODE=off composer lint
XDEBUG_MODE=off vendor/bin/paratest --processes=4 --testsuite unit,integration,semantic,model-contract,doctest
DERIVER_PHP83_BINARY=/path/to/php83 XDEBUG_MODE=off vendor/bin/phpunit --testsuite differential
DERIVER_PHP83_BINARY=/path/to/php83 XDEBUG_MODE=off php fuzz/semantic.php 100
XDEBUG_MODE=off php fuzz/run.php 100
```

The reference process used here was `php:8.3-cli` in an isolated Docker container, invoked through a wrapper accepting PHP's normal command arguments. Application files are executed only by these independent test oracles, never by the analyzer. The large-array acceptance method runs in an isolated PHPUnit process to separate test-discovery memory from the workload; it does not raise analyzer budgets or the host memory limit.

## Comparable engine measurements

Baseline: monorepo commit `38d662c9626830673efe226e776cd9f48ea3ccd9`, the branch base. This is distinct from the earlier diagnostic report's `5996928` revision. Both engines received identical source bytes and default budgets/resources on the same host, PHP 8.5.8, CLI OPcache disabled, Xdebug disabled and `memory_limit=128M`. Each case ran in three fresh processes. The numbers below are medians, not latency guarantees; other host work can affect short wall-clock measurements.

Array cases contain 8,192 entries. `shared` has one partial observation followed by 20 observations of the same known dependency. Query times exclude source capture, selector preparation and native-result enumeration/hashing. Lazy lowering requested during derivation is included in query time.

| Scenario | Baseline query ms | Candidate query ms | Baseline peak MiB | Candidate peak MiB | Result |
| --- | ---: | ---: | ---: | ---: | --- |
| Normal array | 54.13 | 39.86 | 58 | 58 | Same complete ordered array |
| `-1` key | 75.36 | 42.43 | 58 | 58 | Same complete ordered array |
| `"-1"` key | 151.65 | 46.70 | 58 | 58 | Same complete ordered array |
| `-(1+0)` key | 715.64 | 83.19 | 128 | 66 | Baseline memory boundary; candidate complete and equal to both negative-key forms |
| Unrelated heavy call | 14.10 | 2.18 | 12 | 10 | Same constant; candidate has no widening and zero body expansions |
| Fixed override with source available | 9.35 | 5.34 | 12 | 12 | Baseline unresolved; candidate `15`, zero body expansions |
| Shared observations | 70.26 | 11.85 | 16 | 12 | Candidate retains first partial expression and all 20 concrete `8` values; one helper body expansion, 20 dependency hits |

Candidate phase timings outside derivation are reported separately:

| Scenario | Capture/parse/declarations ms | Selector/lowering ms | Native enumeration/hash ms | Warm identical query ms |
| --- | ---: | ---: | ---: | ---: |
| Normal array | 88.79 | 0.13 | 2.62 | 0.047 |
| `-1` key | 104.32 | 0.15 | 2.78 | 0.037 |
| `"-1"` key | 102.10 | 0.14 | 2.61 | 0.029 |
| `-(1+0)` key | 99.13 | 0.16 | 3.25 | 0.039 |
| Unrelated heavy call | 15.22 | 3.55 | 0.008 | 0.020 |
| Fixed override | 16.33 | 3.64 | 0.008 | 0.021 |
| Shared observations | 20.40 | 5.92 | 0.071 | 0.018 |

The warm query can return the same immutable result and its original statistics. It is distinct from the shared-dependency hits across different observations. Baseline candidate counters are unavailable; their zero compatibility defaults do not mean the baseline performed no work. [Recorded samples](../bench/results/candidates-2026-10-04.json) include both engines' phase times, source/result hashes, expansion counters and retention measurements.

For the computed-key array, three-process size probes at 1,024 / 2,048 / 4,096 / 8,192 entries peaked at 18 / 24 / 38 / 66 MiB. These measurements include parsing and indexes. The implementation retains one construction buffer instead of every intermediate array; this does not promise linear behavior for arbitrary branching programs.

Every candidate scenario's last result became unreachable after dropping the caller reference and calling `release()`, checked with `WeakReference`. Representative used memory before/after release was 61,807,696 / 61,801,608 bytes for the computed-key array and 10,377,216 / 10,109,240 bytes for shared observations. Source indexes remain live in the session, so release is not expected to return process memory to its startup level. G13–G14 additionally exercise retention capacities and externally held graphs.

Reproduce each scenario in fresh processes, repeating at least three times:

```sh
XDEBUG_MODE=off php -d opcache.enable_cli=0 bench/Candidates.php general-key 8192
XDEBUG_MODE=off php -d opcache.enable_cli=0 bench/Candidates.php shared 20
DERIVER_BENCH_AUTOLOAD=/path/to/baseline/vendor/autoload.php XDEBUG_MODE=off php -d opcache.enable_cli=0 bench/Candidates.php general-key 8192
```

Other scenario names are `array`, `negative-key`, `negative-string-key`, `heavy` and `model`. Use an independently installed baseline package, or an autoloader that overrides its entire `Deriver` class map; changing only a PSR-4 prefix does not override an optimized Composer class map. The native-result hash covers keys, order and values. Native enumeration/hashing includes serialization of those values, not full JSON-report rendering.

## Dependency and evaluation profile

Dependency discovery, condition reduction and choice expansion interleave. To separate their costs without adding timers to every production operation, `bench/profile-phases.py` groups exclusive function costs from an optional Xdebug Cachegrind profile. Shared runtime/builtin costs stay in a separate bucket instead of being attributed to guessed phases.

One profiled computed-key run at 8,192 entries reported: source/declarations 627.17 ms; indexing/lowering 239.95 ms; dependency expansion 59.58 ms; partial evaluation 44.33 ms; choice enumeration/result preparation 27.78 ms; metadata serialization 0.50 ms; cache/retention 43.40 ms; shared evaluation utilities 0.02 ms; runtime/autoload/other 113.87 ms. No application body was expanded. These are instrumented exclusive costs over the whole benchmark, including the warm query and release, and must not be compared as latency against the unprofiled table.

```sh
XDEBUG_MODE=profile php -d xdebug.output_dir=/tmp -d xdebug.profiler_output_name=deriver-phases.%p bench/Candidates.php general-key 8192
python3 bench/profile-phases.py /tmp/deriver-phases.PROCESS_ID.gz
```

The categorization rules are visible in the script. It accepts uncompressed profiles too. Capture, lowering and dependency counts remain different concepts: compiling an unused declaration does not count as expanding its body for a value query.

## Available application probes

The captured SQL Catalog worktree at `ef62350c2b9c0e551e35c045aa350d9287e19b07` supplied `evaluation.json` and `derivation.json`: 305 source sets and 497 selected SQL-call arguments. The candidate API produced 243 observations containing a concrete normal outcome, 240 with symbolic normal outcomes and 14 with exceptional outcomes only. None returned no outcomes, and no analyzer call threw. Each query had a 0.2-second cooperative limit. Time-boundary counts can vary by host load. Unsupported operations, missing sources/properties and recursive dependencies remain in the reports.

This was a direct candidate-API probe, not the existing SQL Catalog adapter pipeline and not an equivalence assertion against every catalog golden result. Reproduce it with the captured fixture files:

```sh
XDEBUG_MODE=off php bench/Corpus.php /path/to/Characterization/evaluation.json /path/to/Characterization/derivation.json
```

The available Magix reader worktree at `dbbccc49ca8ce1c702f30d24990af9888fa956dd` was compared with its captured baseline literal reader. The unmodified consumer rejected candidate coverage and differed for 2,018 of 3,072 expressions. A temporary copy changing only its expected coverage from `over-approximation` to `source-candidates` produced zero differences across all 3,072 expressions. Its five additional shape probes agreed for string-key, positive-key, negative-key and numeric-string-key arrays of 8,192 entries, plus 1,024 nested values. The application checkout was not modified.

Those shape probes confirmed values, not reader performance parity. The new reader's source capture and derivation took approximately 149–333 ms per shape, versus 0.49–4.88 ms for the specialized baseline literal reader in that single probe. They are different workloads and not a general engine speed comparison. The same reader harness can be rerun by substituting the candidate package autoloader and applying the documented coverage migration before comparing native results.

The full Magix application/CLI suite, SQL Catalog extension/adapter suites, testcontainers workloads and WordPress applications were not rerun as integrated consumers of this new contract. The captured extension, project and documentation fixture groups were also not included in the 305-set probe. These remaining integration checks must not be inferred from the minimal examples or reader comparisons.
