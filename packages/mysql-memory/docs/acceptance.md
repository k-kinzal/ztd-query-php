# Acceptance contract

MySQL Memory tests the adequacy of SQL Semantics by consuming its public statement
and expression structures. The construction property concerns these values
**before** `Operation` construction. SQL Semantics owns their types and invariants;
it does not certify execution eligibility or own database execution.

Completion requires these three checks and a green pull request on the same revision:

1. **Generate semantic values from their public types.** The PBT generator discovers
   statement and scalar roots from the reviewed semantic type inventories and samples
   constructor arguments from PHP types and PHPDoc collection types. Constructor
   invariants constrain construction. An accepted value is never discarded because
   execution fails. Exhaustion and unsupported generator metadata fail the check.
2. **Execute those values directly in MemoryDB.** Memory consumes the constructed
   objects without parsing their rendered SQL. Missing information, contradictory
   structure, missing implementation, internal exceptions and unexplained SQL errors
   fail the check. Explicitly identified database or session state conditions are
   recorded separately; an arbitrary SQL error is not evidence of structural validity.
   Every public root participates in `GeneratedStructuresTest` in the unit suite; `fuzz/structures.php`
   also supports longer, replayable random campaigns.
3. **Compare generated SQL with MySQL.** SQL Faker supplies the SQL and owns grammar
   coverage. Replay every supplied high-coverage seed against MemoryDB and
   Testcontainers MySQL, then run without a specified seed for at least 900 seconds.
   Compare results, metadata, errors and warnings under the documented comparison
   contracts. An unexplained difference, infrastructure failure or crash fails the run.

The supplied-seed check includes all nine shipped MySQL corpora: 5.6.51, 5.7.44,
8.0.44, 8.1.0, 8.2.0, 8.3.0, 8.4.7, 9.0.1 and 9.1.0. An earlier revision's run is
useful evidence but does not replace verification of the final revision. MemoryDB
does not impose its own grammar-coverage metric.

Dependency checks and the retained-value schema keep source SQL and parser ASTs
outside the semantic result and its planning/execution consumers. Runtime request
labels remain outside semantic values. PDO connectivity and the parser → semantics
→ plan/iterator path continue to have integration tests.

Native comparisons and state-transition histories for directly generated structures
remain useful diagnostic tools. They are not additional completion requirements for
checks 1 and 2. In particular, each generated root does not need a separate native
success witness or an exhaustive set of database histories. Version-specific behavior
belongs to the implementation and differential checks.

Values determined by SQL session history must agree. Instance, operating-system and
physical-storage values use explicit comparison contracts supported by observations
from independent MySQL instances. Memory returns its own meaningful value, or an
empty/zero value for an unavailable physical quantity; a `volatile` label alone does
not establish agreement.

These are sampled properties, not proofs over every SQL or PHP value. Keep the
revision, seeds or duration, input counts and unresolved failures with the evidence.
Do not remove roots or SQL Faker seeds, filter constructed values by execution
success, or suppress unknown errors to obtain a passing run. Implementation is based
on public SQL behavior and black-box observations, without translating MySQL code.
