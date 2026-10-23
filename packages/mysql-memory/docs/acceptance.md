# Checking semantic structures and SQL execution

MySQL Memory follows the same unit testing, static analysis and package conventions
as the repository's SQL libraries. Its additional properties have three parts:

1. Generate public SQL Semantics statement and scalar values from their constructor
   types and invariants, before constructing an `Operation`.
2. Execute those original values directly in MemoryDB with PHPUnit. Missing
   information, contradictory structure, missing implementation, internal exceptions
   and unexplained SQL errors are counterexamples. Explicit database and session
   state conditions and interpreted data domains remain separate from structural failures.
3. Generate SQL with SQL Faker and use PHP-Fuzzer to compare MemoryDB with
   Testcontainers MySQL. The same target must replay SQL Faker's supplied grammar
   coverage seeds and run for at least fifteen minutes from an empty corpus.

These are sampled properties used to find counterexamples, not proofs over every
SQL statement or PHP value. SQL Faker owns grammar coverage and its seed selection.
MySQL Memory adds no grammar coverage threshold or separate differential test gate.
Native audits of generated structures and database-history campaigns are not part
of this workflow.

`composer test:property` runs the construction and direct-execution property as part
of the ordinary unit suite. Its seed and number of samples per root can be selected
with `MYSQL_MEMORY_PBT_SEED` and `MYSQL_MEMORY_PBT_SAMPLES`; see
[semantic structures](semantic-structures.md).

`fuzz/fuzz_mysql.php` is the PHP-Fuzzer entry point for both supplied seeds and new
inputs. Select a supported release with `MYSQL_VERSION`. Replay that release's
normal, SQL-mode and diagnostic seed profiles with the matching generation settings.
For exploration, use an empty corpus and `MYSQL_MEMORY_FUZZ_SECONDS=900`. The timer
starts with the first input, after server initialization, and finishes only after
an input has completed. See the [README](../README.md#how-correctness-is-checked)
for the commands.

Differences in results, metadata, errors, warnings or fixture contents fail the
property. Unstable reference observations, infrastructure failures and crashes also
fail; they are not matching executions. Check PHP-Fuzzer's output for both crashes
and timeouts because its process may exit successfully after reporting a finding.
The workflow retains counterexample files and logs.

Keep the revision, seed or duration, and unresolved counterexamples with each run.
A previous revision's passing run does not validate changed code. Never remove
roots or supplied seeds, filter constructed values by execution success, or suppress
unknown errors to obtain a pass. Counterexamples must lead to a correction of the
semantic model, the consumer, or an incorrectly specified property.
