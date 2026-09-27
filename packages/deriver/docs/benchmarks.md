# Benchmark corpus

Run the reproducible synthetic corpus after installing package dependencies:

```sh
composer bench
# Or select one size:
php bench/Bench.php 10000
```

The runner starts a separate PHP process for each size, disables Xdebug instrumentation, and gives each worker a 2 GiB host memory limit. It emits JSON on standard output. Application snippets are supplied to the public analysis API as source data; the benchmark never executes them.

## What is measured

The scale corpus contains exactly 1,000, 10,000, or 100,000 callable declarations in one source file. The selected query calls two functions and must return the known value `1`. A further 32 independent queries validate their expected constants and provide p50/p95 timings. The corpus records:

- Cold and warm source capture/indexing time.
- The first query, repeated result-cache lookup, and 32-query batch times.
- Lowered/evaluated graph counts and logical transfers.
- Peak PHP allocation for the complete worker, including indexing, caches, warm snapshots, and scenarios.
- Closure, precision, correlation, frontier counts, and normal/exceptional outcomes.

Additional independent fixtures cover a chain of 100 helpers, 200 calls from the same helper call site, six independent Boolean branches, a mutable builder with cloning, and interface dispatch with two implementations. The repeated-helper fixture permits 256 precise loop visits. The branch fixture retains the default partition limit and deliberately exercises a residual boundary. Its reduced precision is reported in the JSON.

Cold and warm batches exclude opening the session; opening times are separate fields. The warm batch uses source/IR caches from the same `Analyzer`, while each query still receives a fresh logical budget. Timing and allocator peaks depend on host load and runtime settings. These synthetic projects provide regression fixtures, not an estimate of coverage or speed for an arbitrary application.

## Recorded baseline

[Full measurements](measurements/2026-09-26.json), recorded on 2026-09-26 with PHP 8.5.8, the PHP 8.3 analysis target, Darwin arm64, an Apple M3, and 24 GiB of physical memory. Standard models were enabled; no custom models were registered.

| Callables | Cold open | Warm open | Query p95 | Worker peak memory |
| ---: | ---: | ---: | ---: | ---: |
| 1,000 | 0.025 s | 0.003 s | 0.083 ms | 28.0 MiB |
| 10,000 | 0.214 s | 0.203 s | 0.126 ms | 110.0 MiB |
| 100,000 | 3.092 s | 2.577 s | 0.132 ms | 999.5 MiB |

The selected query evaluated two callable graphs at every scale and returned the expected result without a frontier. The repeated-helper scenario recorded 199 summary-cache hits. The baseline is one complete run; repeat measurements under comparable machine load when investigating a performance change.
