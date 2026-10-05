# Candidate expansion measurements

`Contract.php` measures candidate derivation with controlled source fixtures. `contract.py` runs each observation in a fresh process, interleaving a pinned baseline and the current implementation. It records capture, call discovery, first derivation, serialization, warm lookup and batch durations separately, together with CPU time, peak memory, compiled graphs, expansion statistics, result quality and evidence hashes.

The fixtures cover unrelated branches before a literal, local read or returned helper value (0, 8, 16, 20 and 100 branches); arrays of 128, 1,024, 4,096 and 8,192 entries; receiver/SQL tuples; repeated observations; correlated tuple entries; a loop that cannot change the selected SQL; independent array choices; two receiver alternatives; and two outer callers sharing an inner call site. Each record includes the generated source hash, dependency lock hash, target profile and budgets. The source bytes are reproducible from the worker's shape and size parameters. First-derivation timing combines selected IR/dependency construction, expansion, evidence construction and enumeration; those internal subphases are not timed separately. Baseline tuple quality is recorded per outcome field, while current tuple quality uses the candidate array value.

## Reproduce

Run from `packages/deriver`, with dependencies installed:

```sh
# Prepare an isolated copy of the baseline source.
mkdir -p /tmp/deriver-baseline
git -C ../.. archive fed0725f6220c06d8eebc5d349d61c5c2a588178 packages/deriver/src | tar -x -C /tmp/deriver-baseline

python3 bench/contract.py \
  --baseline-source /tmp/deriver-baseline/packages/deriver/src \
  --output bench/results/local.jsonl \
  --repetitions 3 --cpu-seconds 5 --timeout 120
```

The controller uses Python's POSIX resource limits. The worker uses the installed dependency lock for both implementations. Baseline and current workers share the same common logical budgets (`transfers=1000000`, `nodes=200000`, `partitions=32`, depth 64); the current implementation also retains its default candidate and evidence bounds. These raised work budgets keep the measurement focused on graph growth.

A worker interrupted by the controller has **no returned result**. `measurement-cpu-limit` and `measurement-timeout` are measurement outcomes, never successful partial analyses. An `error` record includes the worker's diagnostic and must not be counted as a completed query. PHP's memory budget is one GiB per worker. No latency guarantee follows from these generated fixtures or from a cooperative analyzer budget.

## Recorded comparison

The checked-in JSONL records were captured on an Apple Silicon macOS host with PHP 8.5.8, Xdebug disabled and CLI OPcache disabled. Both analyzers target PHP 8.3 on 64-bit integers. Three isolated repetitions are recorded for each shape/size and implementation. The host also had other workloads, so CPU time, logical work, quality and memory should be considered alongside wall-clock timings.

The baseline is `fed0725f6220c06d8eebc5d349d61c5c2a588178`. Current records include a SHA-256 over source paths and bytes, independent of the result artifact itself. Warm equality compares the entire exported result, including evidence; it does not merely compare scalar values.

[Raw measurements](results/candidate-contract-2026-10-06.jsonl) contain 192 process records: 32 shapes/sizes, two implementations and three repetitions. The final current-source records were rerun after the last correctness fix and combined with the unchanged pinned-baseline measurements. They are not all an interleaved run.

| Fixture | Size | Baseline median CPU seconds | Current median CPU seconds | Current result |
| --- | ---: | ---: | ---: | --- |
| literal | 100 | 0.0704 | 0.0749 | One concrete SQL string; no reference expansion |
| local | 8 | 0.1456 | 0.0455 | One concrete SQL string; one reference expansion |
| local | 16 | CPU cap; no result | 0.0452 | One concrete SQL string; one reference expansion |
| local | 100 | CPU cap; no result | 0.0756 | One concrete SQL string; one reference expansion |
| return | 100 | CPU cap; no result | 0.0593 | One concrete SQL string |
| receiver | 20 | CPU cap; no result | 0.0474 | One receiver/SQL tuple |
| receiver-choice | 2 | 0.0479 | 0.0482 | Two correlated receiver/SQL tuples |
| callers | 2 | 0.0390 | 0.0460 | 35 and 65 with distinct caller evidence |
| product | 4 | 0.0487 | 0.0577 | 16 concrete arrays, each with head/tail |
| product | 8 | 0.8677 | 0.0497 | One partial array retaining all 10 entries |
| product | 20 | CPU cap; no result | 0.0710 | One partial array retaining all 22 entries |

The baseline local-read fixture with eight irrelevant branches returns a non-concrete choice; the current fixture returns the single concrete SQL string. CPU-capped baseline rows have no candidate output. Objects retain allocation/type information as partial values, so a receiver/SQL tuple is not classified as a concrete native PHP object.

| Array entries | Baseline CPU seconds | Current CPU seconds | Baseline peak MiB | Current peak MiB |
| ---: | ---: | ---: | ---: | ---: |
| 128 | 0.0518 | 0.0441 | 12 | 12 |
| 1,024 | 0.0869 | 0.0947 | 18 | 20 |
| 4,096 | 0.2131 | 0.2528 | 42 | 46 |
| 8,192 | 0.4015 | 0.4906 | 74 | 80 |

Every measured array retains its full entry count and value hash. The richer candidate evidence adds time and memory in these array fixtures; this comparison does not claim a speedup for every input. All completed workers have identical cold/warm exports. The reference, graph and node counters and per-phase wall times are available in the raw records.

Current source fingerprint: `f3f20f7011f071f5d900a9203297ec2276b25e91d26ee606e8563c133d5391aa`.

This is a generated-fixture comparison. WordPress and MagixCache application sources were not supplied for this run, so their application capture, cold, warm and batch performance remains unmeasured. The candidate contract tests use the nested caller/TTL integration example independently of those application repositories.

`Bench.php` remains available for larger declaration inventories and mixed query scenarios. It reports concrete and partial candidate counts and rejects incorrect concrete values in the fixtures with known answers.
