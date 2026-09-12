# Benchmarks

The suite follows php-ai-toolkit's
[`setup-toolkit-phpbench`](https://github.com/k-kinzal/php-ai-toolkit/tree/main/skills/setup-toolkit-phpbench)
skill. `phpbench.json.dist` is the shared configuration derived from its template.
The existing `phpbench/phpbench:^1.4` requirement resolves to 1.4.3 with the
package's PHP 8.1 platform constraint; newer PHPBench releases require PHP 8.2.

## Running locally

From `packages/sql-faker`, install the committed dependencies with `composer install`.
The lock supports development on PHP 8.1–8.3, including its ParaTest dependency.
Disable Xdebug when measuring and avoid running other CPU-intensive work alongside the suite.

```bash
XDEBUG_MODE=off composer bench:quick
XDEBUG_MODE=off composer bench
XDEBUG_MODE=off composer bench -- --group=generation
XDEBUG_MODE=off composer bench -- --group=bootstrap
```

`composer bench` uses 10 iterations, two warmup revolutions, and a 5% retry
threshold. `bench:quick` uses three iterations, one warmup revolution, and a 10%
retry threshold. `bench:full` remains an alias for `bench`. Revolution counts
belong to each subject. All commands use a 512 MB memory limit with OPcache
disabled. Benchmarks run separately from tests and lint.

For a local comparison, keep the benchmark code, configuration, PHP version,
and dependencies unchanged between measurements:

```bash
XDEBUG_MODE=off composer bench -- --tag=baseline
# Apply the production-code change, then measure it on the same machine.
XDEBUG_MODE=off composer bench -- --ref=baseline --tag=candidate
```

Add `--assert='mode(variant.time.avg) <= mode(baseline.time.avg) +/- 10%'` to
the candidate command to enforce the toolkit's relative regression limit locally.
Review `rstdev` and repeat noisy measurements before interpreting a difference.
`build/phpbench/` (including tagged XML storage) and a local `phpbench.json`
override are ignored by Git. CI explicitly uses the committed `.dist` configuration.

## Measured workloads

All six subjects use committed grammar resources and explicit release names:
MySQL `mysql-8.4.7`, PostgreSQL `pg-17.2`, and SQLite `sqlite-3.47.2`.
Changing the default database release therefore does not silently change the workload.

| Group | Operation | Measurement boundary | State and workload |
| --- | --- | --- | --- |
| `bootstrap` | Construct each SQL provider | Faker construction and seeding, grammar file loading, and provider construction | A new Faker/provider for each of 20 revolutions; seeds 1001, 1002, and 1003 respectively. The OS file cache may already be warm. |
| `generation` | Call each provider's `selectStatement(maxDepth: 6)` | SQL generation only; provider construction is outside timing | Each iteration constructs only the measured dialect's provider and seeds its Faker with 2001, 2002, or 2003. Warmup initializes lazy grammar analysis; 250 measured calls reuse the provider and advance the seeded random stream. |

Faker uses a shared random generator. Separate setup hooks prevent another
dialect's setup from overwriting the measured dialect's seed. Each isolated
iteration starts the same stream; quick and full runs consume different warmup
prefixes and should not be compared numerically with each other.
Existing provider unit tests cover statement generation and seed reproducibility.

## Pull-request comparisons

`.github/workflows/bench.yml` runs for changes to this package or the workflow.
It uses PHP 8.3 on Ubuntu 24.04, installs each revision's committed lock
independently, and checks all installed platform requirements. PHP 8.3 is the
newest runtime supported by every development dependency in the current lock.

For comparable revisions, the job measures the PR's merge-target SHA and GitHub's
candidate merge commit sequentially on one runner. It transfers the baseline's
XML storage to the candidate and reports the aggregate difference. The initial
setup PR records only the candidate because the merge target has no shared
PHPBench configuration. Benchmark/configuration or PHPBench version changes
are likewise reported as non-comparable instead of producing misleading differences.

`PERFORMANCE_GATE` is `false`: provider bootstrap includes filesystem I/O, so
hosted-runner timing is informational under the toolkit policy. Execution,
dependency, and configuration failures still fail the job. The 10% regression
limit is retained for future gate use; enabling it requires a stable in-process
suite or a controlled runner for I/O workloads. A gated harness/toolchain change
must be reviewed as calibration before re-enabling the gate.

The job summary contains the report. The `sql-faker-phpbench-pr-*` Artifact
retains raw XML, full console output, tagged storage, revision SHAs, and
environment/tool versions for 14 days, including on failed runs. It requires
only `contents: read` and does not post PR comments or use secrets.
