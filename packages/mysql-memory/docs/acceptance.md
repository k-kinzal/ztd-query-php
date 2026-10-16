# Acceptance contract

MySQL Memory is a consumer that tests the adequacy of SQL Semantics. The
structure contract concerns public statement and expression values **before**
`Operation` construction. It does not require SQL Semantics to certify execution
eligibility or to own database execution.

The work is complete only when all of these gates pass on the same revision:

| Gate | Required evidence |
| --- | --- |
| Source independence | The semantic value schema excludes retained source SQL and parser ASTs; dependency checks prevent planning and execution from accessing the parser or input text. Direct construction executes without parsing input text. Observable request labels are runtime context, kept outside semantic values and compared exactly. |
| Constructible structure | Type-driven generation visits every public MySQL statement and scalar root. Every accepted construction has a coherent language interpretation and all information required by its consumer. Intrinsically contradictory operands must be excluded by types or constructor invariants. Exhaustion, unsupported generator metadata, profile failures, internal exceptions and missing implementation remain failures. |
| SQL outcomes | Every generated structure is compared with MySQL, including successful results, result metadata, SQL errors and warnings. Catching a SQL error is not sufficient. A native success cannot confirm an emulator's unsupported-feature error. Profile failures and internal exceptions cannot become SQL outcomes. |
| State transitions | When an error is explained as state-dependent, preserve the same structure and demonstrate the relevant transition against MySQL. Include populated fixtures and histories so that an empty database does not hide all downstream behavior. |
| Differential behavior | Replay all SQL Faker supplied high-coverage seeds and run an unseeded differential campaign for at least 900 seconds without an unexplained difference or crash. Grammar coverage belongs exclusively to SQL Faker. |

Values determined by SQL session history must agree. Values specific to an
instance, operating system or physical storage require an explicit comparison
contract supported by observations from independent MySQL instances. Memory
still returns its own meaningful value, or zero/empty for an unavailable physical
quantity. A `volatile` classification alone does not satisfy this gate.

These are sampled properties, not a proof over all possible SQL or PHP values.
The evidence must include the revision, seed or duration, input counts and all
unresolved failures. Tests of selected repaired domains do not replace the
whole-model construction campaign. A matching SQL error does not establish state
dependence by itself. For example, `SIGNAL SQLSTATE '45000'` intentionally reports
an error even when the structure is valid. Requiring every valid structure to
return successful rows or a completion would exclude SQL's condition language.
The construction property requires a defined SQL outcome and native agreement,
not a success-only execution certificate supplied by SQL Semantics. Native
agreement is necessary but does not establish the construction property by
itself. Each observed rejection still needs an explanation: an intentional SQL
condition, a state constraint with a transition witness, or an intrinsic
structural defect that must be repaired. A matching error must not be used to
close that last category. The `compare` command's exit status tests the SQL
outcome gate; it is not a certificate for all the gates above.

Run the package static checks and tests, then the campaigns described in
[direct semantic construction](semantic-structures.md) and the
[fuzzing instructions](../README.md). The strict exit status of the construction
campaign's native comparison must remain strict. Local SQL errors without native
evidence remain unresolved. Do not obtain a
passing run by removing a root, discarding a constructed input, suppressing an
exception or excluding a SQL Faker seed.

PDO connectivity and the parser → semantics → plan/iterator execution path must
continue to pass their integration tests. Implementation and fixes are derived
from public SQL behavior and black-box observations, without translating MySQL
implementation code.
