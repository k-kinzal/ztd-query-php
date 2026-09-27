# Differential and mutation fuzzing

The analysis API never runs project input. These commands execute only the bounded test grammar defined in `tests/Fake/Oracle/StatePrograms.php`.

```sh
# Use PHP 8.3 as the oracle, independently of the analyzer's host PHP.
export DERIVER_PHP83_BINARY=/path/to/php8.3
composer test:differential
composer fuzz:semantic

# Reproduce 1,000 seeds starting at seed 32, with inputs -1, 0, and 1.
php fuzz/semantic.php 1000 32

# Mutate PHP source bytes and check parser/solver invariants.
composer fuzz:seed
php fuzz/run.php 10000
```

The structured generator composes assignments, shared objects, shallow clones, reference cells inside copied arrays, nested assignment patterns, reference destructuring, overlapping foreach destinations, closures, helper calls, bounded loops, guarded exceptions, and finally blocks. The oracle independently records the return or throwable class and the externally observable heap, including object identity and cycles. The comparison reads Deriver's public storage records without using its internal transfer rules.

On a mismatch, the runner removes statements while the mismatch remains, producing a fixture for which no single remaining statement can be removed without losing the counterexample. It writes that fixture under `build/semantic-failures/` and prints the seed and concrete input. The fixed setup keeps every removable statement valid. This shrinker does not claim a globally smallest PHP program.

Raw source mutation checks API termination, budgets, result serialization, and invariant failures. It does not treat arbitrary mutated source as safe to execute. Source admission-limit rejection is an expected outcome; other uncaught analyzer failures fail the run. Neither finite generator proves soundness for arbitrary PHP.
