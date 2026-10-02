# Read-only SQL semantic model

**SQLSEM-DESIGN-001, version 1.0 — 2026-10-02.** Implementation contract, not a
claim of completed implementation or formal proof. Baseline: PR #571 at
`db448cfa920c8b4cccc84fb09bff90bc44b0a5fb`. This contract supersedes the editing
and assertion-only contracts in the initial semantic-model migration.

## Boundary

SQL Semantics analyzes SQL into concrete, deeply immutable semantic operations,
and reconstructs SQL from their meaning. The runtime is pure PHP, compatible
with PHP 8.1, without additional native extensions, FFI, external provers, or a
required database connection for analysis, construction, or reconstruction.

There is no editing API, including persistent updates returning different objects:
withFields, withWhere, addField, withColumn, copyWith, rebuild, toBuilder, rewriting
visitors, and patches are excluded. Moving them elsewhere or renaming them does
not change this boundary. Read-only traversal is permitted. Collections retain
order and duplicates and expose inspection, iteration, counting, and lookup only.

To request different SQL, explicitly construct a new root from typed new inputs,
or read semantic values, encode names for their positions, and analyze new SQL.
A new SELECT explicitly specifies its inputs and projection; it does not inherit
WHERE, DISTINCT, ordering, or other clauses. The library establishes the new
request's meaning, not whether it implements a user's intended change.

There are no edit histories, frame conditions, preservation claims between roots,
automatic rebinding, partial-graph transplantation, invalidation, or incremental
repair. Name resolution, declaration identity, complete semantic structuring,
derived facts, and reconstruction remain required. Rendering must avoid name
capture, including capture caused by generated aliases within a single SQL input.

## Guarantees

Fix a language profile D and immutable declaration context C. For finite inputs
in D's grammar, given sufficient resources:

| ID | Obligation |
| --- | --- |
| G1 | Concrete structuring of every reachable grammatical form and permitted composition. |
| G2 | Correspondence of all meaningful input operations, operands, and relations to the actual published model. |
| G3 | Sound references, types, NULL, comparisons, and conversions, with each rule's required minimum precision. |
| G4 | Deep immutability and consistent ownership, identities, and references. |
| G5 | Constructive SQL output with actual output/model correspondence established before publication. |
| G6 | Checked new roots from every documented constructor input domain. |
| G7 | Original declaration identity and a closed distinction between missing information and missing implementation. |

Internal consistency is not database success. Missing columns, ambiguous names,
contradictory requests, and width mismatches remain typed semantic diagnostics on
concrete operations, never exclusions from SQL coverage. Constructors may reject
impossible input shapes, incompatible profiles, and foreign bound components.
They cannot use this to conceal a missing SQL rule.

Preserved observations: requested operations, input/output positions and names,
duplicates, bindings, declarations, types, NULL, comparison/conversion semantics,
important evaluation structure, and DML/DDL/transaction requests. Whitespace,
ordinary comments, unspecified row order, exact server diagnostics, and plans are
excluded. Hints, executable comments, and meaning-bearing type-name text are not
ordinary trivia. Equivalence between independently constructed roots is excluded.

## Profile and context

A profile fixes dialect, version, exact grammar artifact, semantic-rule version,
lexical/session modes, parameters, and character/identifier interpretation.
Mutable globals, locale, time, or connection state cannot silently supply meaning.
Undetermined lexical configuration is a configuration error, not an unknown fact.

AnalysisContext contains that profile and a declaration snapshot, including
namespace completeness, member completeness, and missing external inputs. Every
boundary checks profile compatibility. Identically named foreign types are not
interchangeable. `analyze($sql)` is open; `analyze($sql, [])` is explicitly empty
and complete. Declaration arrays and reusable explicit contexts are accepted.

Deduplicate identical declaration objects; retain distinct conflicts. CREATE
supplies declarations. ALTER, DROP, writes, and transactions are requests, never
updates to context. Each script statement sees the same explicit snapshot.
Reanalysis under another context creates an independent result. Borrowing an
explicitly permitted closed root requires the identical snapshot; no equivalent
wrapper inference or declaration-ID remapping is performed.

## Semantic model and construction

Published operations contain concrete operations, operands, relations, completed
resolutions, and derived facts. They contain no parser nodes, tokens, grammatical
production copies, raw SQL, mutable builders/resolvers, or deferred resolution.

Declarations, relation occurrences, and output slots differ. Self-join occurrences
can share a declaration but remain distinct. Resolved direct columns reach the
original supplied table/column objects. Derived outputs can depend on many or no
declarations. FROM is a relation structure, not a flattened scope table list.
Ordered row shape and clause visibility follow that structure: outer-join NULL
extension affects occurrence outputs, ON differs from joined output, and USING
and lateral visibility have their own rules.

SELECT, VALUES, set operations, WITH, and recursion are concrete row-producing
types. InsertRows, InsertSelect, and InsertDefaults remain distinct. Expressions
preserve evaluation structure: simple CASE has one base, searched CASE has ordered
tests, scalar subqueries differ from EXISTS. Types, references, NULL, comparison,
and conversion facts derive from operands and environments, not independent inputs.

Field lookup distinguishes unique, absent, ambiguous, and declaration-dependent
results. `field()` requires uniqueness. An unresolved star is a typed expansion
request with a pending row shape, not UnknownField or a fabricated concrete list.

Names, literals, settings, and canonical immutable declarations can be reused.
New occurrences require new inputs. Bound fields, expressions, and correlated
queries are not generic construction inputs or universally safe SQL fragments.
Explicit borrowing slots may accept a closed complete query or INSERT target
under the identical context and compatible profile, without changing its internal
environment. Correlation and duplicate occurrence reuse are checked.

Analysis parses temporary production views, constructs candidates, derives
scopes/references/shapes/facts, checks actual input/model correspondence, checks
internal consistency, and establishes actual output correspondence before return.
No external callback sees a partial candidate. Public new accepts semantic requests
such as ColumnUse, NamedInput, and explicit projection definitions and applies the
same rules. It cannot accept checked flags, arbitrary snapshots, independently
chosen resolutions, or arbitrary derived output names. An internal mutable builder
may exist for one construction only and cannot remain reachable from a result.

## Rule contracts and correspondence

Each rule states arbitrary eligible child contracts, composition conditions,
environment derivation, facts, minimum precision in complete/incomplete contexts,
input correspondence, output, and termination. Consumed flags, token counts, and
populated ledger rows do not demonstrate correspondence. WHERE must correspond to
the actual stored predicate; likewise projection order, JOIN kind, and INSERT form.
Rules are closed typed PHP implementations, without arbitrary callbacks, eval,
raw fallback, generic ignore, or a required general proof DSL/equivalence engine.

Resolution accounts for position, name kind, qualification, search order,
competitors, and completeness. A known outer candidate cannot win while a nearer
namespace is incomplete. Distinguish resolved, absent, ambiguous, declaration
conflicts, and conditional search. Missing facts name actual absent inputs;
unsupported nodes, rule IDs, timeouts, and precision cutoffs are not uncertainty.
Broad type domains still have to meet each rule's required minimum precision.

## Reconstruction and PHP boundaries

Output expresses operations/operands without optimization, reordering, alias
substitution, FROM restructuring, or dropped clauses. Numbers never pass through
PHP float. Profile/position codecs preserve decoding and binding, including quoted
case folding and SQLite double-quoted-string compatibility. Output names follow
explicit aliases or a fixed profile naming contract. Spelling-dependent names use
typed output-name constraints, never retained expression SQL. Generated aliases
require binding/evaluation preservation. Name encoding is not a general sanitizer.

Before publication: construct typed temporary output, write SQL, parse it with the
same profile, and check actual output structure against the model. Do not recursively
call public analyze. Fingerprints/formatter stability are supplementary, not this
check. toString deterministically writes immutable semantic values, never stored
source SQL. Missing output paths are implementation gaps, including at construction.

Use final classes with typed readonly properties, and closed immutable reachable
values. Reject resources, closures, mutable/unregistered implementations, referenced
array members, and uninitialized values. Preserve canonical declaration identity;
adapting mutable declarations creates new immutable identities. Checks execute with
assertions disabled. Close dynamic properties, clone, and standard deserialization
bypasses. No restoration API exists. An interface, namespace, or @internal tag is
not a validation boundary. This is not a sandbox against hostile Reflection,
bound closures, or autoload replacement in the same process.

| Outcome | Contract |
| --- | --- |
| AnalysisException | Input outside the selected grammar. |
| Typed semantic diagnostic/resolution | Grammatical request with semantic problems; still structured and rendered. |
| Typed information dependency | Actual missing declarations/signatures. |
| ImplementationGap | Missing lowering, meaning, or output rule; fuzz failure and release blocker. |
| InvalidConstruction | Input outside the constructor domain, including foreign bound components. |
| InvariantViolation | Internally inconsistent candidate or input/output correspondence. |
| ResourceLimitExceeded | Operational failure, never success or unsupported syntax. |

Unexpected PHP errors remain failures. EditConflict and RebindConflict are removed.

## Completeness and evidence

The surface is every SQL form SQL Faker can generate from every shipped grammar,
including combinations and semantic contradictions. Enumerate every production
reachable from the original roots. Each needs a semantic rule or justified
structural role (list, empty, forwarding), plus output/composition obligations.
No generic success/default, raw fallback, ignored clause, narrowed generator/root,
allowed-exception expansion, or test relaxation can satisfy a missing obligation.
Private generated production views are allowed; public grammar copies are not.

Syntax recursion decreases input or uses explicit work stacks. Scope search visits
finitely many environments. Recursive CTEs can use typed IDs and a completed
immutable arena; termination/soundness need separate rules. Work limits are not
missing declaration information. One-analysis indexes/memoization are allowed;
edit epochs, histories, undo, and invalidation graphs are excluded. Measure costs
against input/model size and rule search, without promising universal linearity.

The trust boundary includes PHP, parser/profile alignment, semantic primitives,
construction, correspondence checkers, codecs, and output checking. Typed rules and
runtime checks are not machine proof of database equivalence. Per-rule states are
Specified, Implemented, and ContractReviewed, never automatically Proved by tests.
Record rule ID, profile/grammar artifact, input slots, semantic constructor, child
contracts, environments, facts, minimum precision, diagnostics, input correspondence,
output, termination, primitive assumptions, sources, and implementation/review status.
Do not record editing frames or invalidation obligations.

Broad fuzz, independent DB observations, constructor misuse tests with assertions
disabled, and deliberately malformed-candidate tests detect rule/integration errors.
DB observations remain optional development/CI evidence, not runtime dependencies.
Findings must revisit rules/assumptions, not become SQL-string-specific exceptions.

## Migration and acceptance

M0 fixes this contract, removed API inventory, and constructor domains. M1 closes
immutable publication, declaration identity, resolutions, and checked constructors.
M2 connects input, initial construction, and actual output correspondence. M3
migrates useful semantic rules and consumers to new inputs or reanalysis. M4
completes all productions, rendering, composition/termination, and verification.

Editing responsibilities are removed as breaking changes without wrappers. Useful
semantic types and declaration identities survive. Graph audits check immutability
but do not establish semantic completeness. Acceptance requires all G1–G7, no
editing or unchecked entry points, actual input/output correspondence, reviewed
contracts and composition/termination, independent conformance evidence, and green
PR CI. Counts, corpus success, fingerprints, and ledger completion alone do not
suffice. Report remaining trust assumptions separately from implementation evidence.

## Sources

- [PHP assert](https://www.php.net/manual/en/function.assert.php) and [properties](https://www.php.net/manual/en/language.oop5.properties.php).
- [SQLite output names](https://sqlite.org/c3ref/column_name.html), [expressions](https://sqlite.org/lang_expr.html), [SELECT](https://sqlite.org/lang_select.html), and [quirks](https://sqlite.org/quirks.html).
- [PostgreSQL 17 table expressions](https://www.postgresql.org/docs/17/queries-table-expressions.html) and [lexical structure](https://www.postgresql.org/docs/17/sql-syntax-lexical.html).
- [Scope Graphs](https://doi.org/10.1145/2775051.2676994): methodology; SQL-specific rules remain separate obligations.
- [CompCert](https://compcert.org/man/manual001.html): distinguish this contract-based implementation from a verified compiler.
