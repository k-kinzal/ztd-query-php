# SQL alternatives without evaluating conditions

Status: proposed design. This document specifies future behavior; it does not
claim that the implementation already satisfies it. It supersedes the approach
introduced by [PR #407](https://github.com/k-kinzal/ztd-query-php/pull/407), which
selects ternary branches using known boolean conditions. That PR is already
merged. Implementing this proposal requires a follow-up change.

## Decision

Keep every structural branch, track variable presence separately from its value,
and merge alternatives when they produce the same SQL bytes at the same call
site. Never evaluate a condition to choose, remove, or narrow a branch.

This policy applies even to literal conditions and familiar guards such as
`isset`, `empty`, null comparisons, and boolean combinations. There is no option
that enables predicate evaluation, no special rewrite of an `isset` ternary to
`??`, and no later phase that silently removes candidates using predicates.

The analysis answers **which SQL strings are admitted by the source when branch
choices are left open**. It does not prove that each candidate is executable in
a real run. This distinction is part of the public contract, not a limitation
hidden behind an implementation detail.

The priorities, in order, are:

1. Preserve every possible SQL value at each discovered database call, within
   the modeled language. An unmodeled relevant operation must leave an explicit
   unknown alternative and an open search.
2. Preserve associations between values written together and repeated reads of
   the same binding while the budget permits.
3. Eliminate duplicate SQL text without losing uncertainty or source evidence.
4. Keep work bounded. Generalize values and disclose unfinished work when needed.

## The motivating example

```php
function findUsers(PDO $pdo, bool $active): void {
    if ($active) {
        $where = ' WHERE active = 1';
    }
    $pdo->prepare('SELECT * FROM users' . (isset($where) ? $where : ''));
}
```

The first `if` supplies two environments. The ternary supplies both operand
expressions in each environment. Neither condition is tested.

| Environment before the ternary | Read `$where` | Read `''` |
|---|---|---|
| Assignment arm | `' WHERE active = 1'` | `''` |
| Empty arm; ordinary local remains absent | Absent read, materialized as null | `''` |

String conversion maps null to `''`. Exact byte equality then leaves:

```text
SELECT * FROM users WHERE active = 1
SELECT * FROM users
```

There is no unresolved third statement because absence was established, not
because the ternary condition was evaluated. These are complete candidates
under the stated abstraction; their runtime reachability is not assessed.

A real read of an undefined PHP variable yields null and raises a warning;
null converts to an empty string. These are separate facts about presence,
value, and diagnostics, and the model keeps them separate.
[PHP variables](https://www.php.net/manual/en/language.variables.basics.php),
[PHP string conversion](https://www.php.net/manual/en/language.types.string.php#language.types.string.casting).

The absent read in the table belongs to an alternative admitted by the
abstraction. It must **not** produce an unconditional warning claiming that the
original, guarded program reads an undefined variable at runtime.

## What this policy deliberately retains

Some additional SQL candidates cannot be removed without testing conditions.
For example:

```php
function findUsers(PDO $pdo, bool $active): void {
    if ($active) {
        $where = 'active = 1';
    }
    $pdo->prepare('SELECT * FROM users' . (isset($where) ? ' WHERE ' . $where : ''));
}
```

This admits three distinct strings: the filtered query, the unfiltered query,
and `SELECT * FROM users WHERE `. The third string is an accepted consequence
of the policy. It is not merged with the other two, guessed away, or removed
because a SQL parser rejects it. SQL validation may annotate a candidate but
must not change the candidate set.

Similarly, two separate `if ($flag)` statements have independent branch choices.
Their predicates being textually equal does not establish a relationship. A
single `if` that writes both a table and a column still keeps those writes
paired. A variable assigned once and read twice still keeps the same value.

For this variant, three candidates are required:

```php
if ($flag) {
    $table = 'admins';
    $tail = ' WHERE admin = 1';
} else {
    $table = 'users';
}
$pdo->prepare('SELECT * FROM ' . $table . (isset($tail) ? $tail : ''));
```

Assuming a fresh local scope, they are `SELECT * FROM admins WHERE admin = 1`,
`SELECT * FROM admins`, and `SELECT * FROM users`. The two-candidate expectation
added in #407 relies on condition evaluation and must change.

## Variable presence and values

A variable binding has a presence state and a value domain. Absence is not a
new scalar value and is not another spelling of `OpaqueTerm`.

| Binding | Meaning |
|---|---|
| `Absent(evidence)` | This local binding does not exist in this environment. |
| `Present(values)` | The binding exists; its value domain may contain null, literals, or unknown values. |
| `MaybePresent(values, evidence)` | The binding may be absent; when present, its value is in `values`. |

`Present(null)` and `Absent` remain distinct until a read materializes their
values. `Present(unknown)` is never converted to absence or an empty string.
An environment map lacking a key is not proof of absence: lookup uses the
scope's default binding and any intervening effects.

The initial states are:

| Name at entry | Initial binding |
|---|---|
| Ordinary local in a fresh function or method activation | `Absent`, after excluding the categories below |
| Parameter | `Present` with its argument, default, or declared input domain |
| Closure capture | Derived from the captured environment; open if unavailable |
| Ordinary uncaptured closure local | `Absent` in that closure's fresh activation |
| `$this` | Receiver binding when available; otherwise open |
| Superglobal, imported global, static storage | Dedicated binding/model; never ordinary-local absence |
| Name at file scope | `MaybePresent(unknown)` unless established by a reaching definition or an extension contract |
| Property or array element | Its own reference model; never inferred absent from a missing local-map key |

A newly entered function provides the absence evidence. The absence remains
valid only while every relevant operation on the path preserves it. A scan
ending before scope entry or an unmodeled effect yields unknown, not absence.

File scope remains open by default. Scanning a file does not prove that it is a
process entry point or that no including file supplied its variables. No
closed-file-scope assumption or new configuration switch is introduced in this
change. An explicit assignment or `unset` can still establish a later state.
[PHP variable scope](https://www.php.net/manual/en/language.variables.scope.php).

### Transfer and join rules

- Assignment to a known, independent local replaces its binding with `Present`.
- `unset($x)` establishes `Absent` for the local binding. It does not overwrite
  every value that an alias previously referred to.
- Reading `Absent` produces literal null plus an `absent-local-read` derivation
  note, with its source position and absence evidence.
- Reading `MaybePresent(V)` produces the alternatives null and `V`. The open
  evidence of `V` survives conversion and union.
- Joining `Absent` with `Present(V)` produces `MaybePresent(V)`; joining value
  domains takes their union. Joining two absent states preserves their evidence.
- An unknown default must participate when a name is missing on one side of a
  join. The binding from the other side must not silently win.

Presence joins are commutative, associative, and idempotent. Mutable properties,
references, and array elements need their own update rules; a local strong
update is not used where aliasing makes replacement uncertain.

Derivation notes describe the analysis, not proven runtime diagnostics. A note
from a speculative branch remains possible. It does not create a separate SQL
entry, a text hole, or an unconditional user-facing undefined-variable finding.

## Effects must be inspected even when conditions are not

Ignoring a condition's truth value does not allow ignoring assignments, calls,
or changes to variable bindings inside it. The analysis keeps expression value
flow and effect flow separate:

- Guard-only values do not need to be resolved or traced through callers merely
  to select a branch. Pure predicate reads introduce no SQL-value dependency.
- Assignments and relevant effects inside guards are retained in evaluation
  order. A call in a guard is inspected for effects, not called to decide truth.
- `SinkFinder` continues to discover database calls anywhere in an expression,
  including guards and operands whose result is unused.
- An expression whose value is also used as data, such as the left side of
  `$fragment ?: ''`, keeps that value dependency. It is evaluated once and both
  result alternatives are retained without truthiness filtering.
- If `isset(...)` itself contributes text, its result is the boolean domain
  `{false, true}`. Its operands' values do not refine that domain. Effects of
  evaluating operand addresses are still modeled.

Each arm starts from a copy of the state after the mandatory guard effects.
It produces an outcome consisting of **value, resulting environment, and
analysis evidence**. Outcomes stay paired through subsequent operations.
Evaluating both arms against one mutable environment in sequence is forbidden.

For example, `$c ? ($part = 'a') : ($part = 'b')` followed by reading `$part`
must retain both states. Running the assignments sequentially would retain
only `'b'` and lose a query despite keeping both expression values.

Use structural choices for ternaries, `if`/`elseif`, `match`, `switch`, `??`, and
short-circuit boolean operators. The left operand of `&&`, `||`, and `??` is
processed once; their right operand has a skipped and an evaluated alternative.
Later `elseif` tests, `match` conditions, and later `isset` operands also have
optional evaluation paths. Do not flatten conditional effects into mandatory
assignments. Where ordering or an effect is not modeled, report it as open.

Structural control flow still applies: preserve `switch` fall-through,
`finally`, and unconditional return/throw/break/continue edges at the correct
scope. A later sink is not reached along an arm that structurally terminates.
Calls inside that arm are still discovered. No predicate is used to prove an
arm dead. Loop bounds remain explicit; inspecting a fully known `foreach`
collection is value traversal, not evaluation of a loop condition.

Branch identity comes from the syntax occurrence, call context, and loop
iteration, not from the predicate's text or value. Reusing a binding reuses its
choice; executing another branch site makes a new choice.

### Writes the slicer must not skip

The current slicer's `ModifiedNames::touches()` gate recognizes named writes.
That is insufficient for establishing absence or retaining earlier literals.
An effect summary must be consulted **before** the skip decision. It describes
known writes, possible writes/unsets, affected scope, and escaped aliases.

| Operation not fully modeled | Required effect on relevant bindings |
|---|---|
| `include`, `require`, their once variants, or `eval` | May change caller-scope bindings; invalidate affected existing bindings and the default for untracked names. |
| `extract`, a variable-variable write/unset, or an unknown symbol-table write | May change names in the relevant scope; preserve unknown effects even if no concrete target name is available. |
| Call that may receive a local by reference | Invalidate that local and its affected aliases unless a complete effect model supplies the result. |
| Call affecting imported globals, mutable properties, or escaped references | Invalidate those locations unless its effects are modeled. |
| Unsupported control/effect syntax or an exhausted effect scan | Leave the possibly affected dependencies open. |

An ordinary named function cannot directly create arbitrary independent caller
locals. Do not invalidate all locals for every unresolved call. Use its known
signature/effect summary, assignable arguments when reference behavior is
unknown, and escaped state. Unknown callbacks and unresolved aliases require a
conservative affected set. If that set cannot be bounded more narrowly, widen
the relevant scope and record why.

Includes inherit the caller's scope, and `extract` imports entries into the
current symbol table. These effects cannot be treated as irrelevant expressions.
[PHP include](https://www.php.net/manual/en/function.include.php),
[PHP extract](https://www.php.net/manual/en/function.extract.php).

A wildcard invalidation must widen both already tracked names and subsequently
read names; changing only the default while retaining old literal overrides is
incorrect. It may also invalidate a PDO receiver, leaving an unmatched/open
call rather than a confidently identified database call.

A later definite overwrite of an independent local can settle that local
again. An earlier unresolved operation must not permanently taint unrelated
values or values fully replaced before use. Unsupported aliases remain open
until independence is established. This is an ordered transfer, not a blanket
file-level flag that disables all analysis.

The regression from #407 is a required counterexample: if an include may define
`$where`, the later guard cannot make it an absent local. An unknown value or
unknown receiver must remain visible and the search must stay open.

## SQL conversion and deduplication

Materialize reads as values first. Convert them when the PHP operation demands
a string, including concatenation, interpolation, and explicit string casts.
Do not turn absence into an empty string at every use: arithmetic, reference
access, and array operations have different semantics.

For SQL text, literal null, literal false, and `''` all convert to the empty
string. An unknown domain remains a hole even if it might contain null. Values
of different PHP types retain their identities until conversion. The existing
`LiteralTerm::toText()` already implements the relevant scalar conversion.
[PHP string conversion](https://www.php.net/manual/en/language.types.string.php#language.types.string.casting).

A direct database argument is converted according to the sink's argument model;
this proposal does not assert that passing an undefined variable directly to
`PDO::prepare()` is equivalent to a valid string argument. The motivating case
performs concatenation before passing its argument.

At the same call site:

- Equal exact SQL bytes are one text alternative. Merge their bindings,
  derivation notes, sources, and completeness evidence.
- Unknown patterns remain alongside exact strings, even when a hole could
  match an exact alternative. Finding one exact reading never erases another
  reading that is still open.
- Partial patterns merge only when their structural keys, including hole
  semantics, agree. The display marker `{$}` is not an identity key.
- Formatting, case folding, whitespace normalization, SQL validity, and guessed
  predicate relationships are not deduplication criteria.

Before the final SQL projection, equal text alone is not enough to merge
execution states: subsequent reads may observe different side effects. Earlier
deduplication requires equivalent value and environment states and merges all
metadata conservatively.

Value identity and evidence accumulation are separate operations. Merging uses
OR for incomplete/combined flags and set union for reasons and source notes.
`QueryRecord::absorb()` must include these flags; keeping the first record's
readonly `combined` value is insufficient. Environment and memoization keys
must distinguish presence states and effective scope/alias contexts. Metadata
must never disappear because two values share a signature.

## Completeness and reporting

The report needs three independent answers:

| Question | Contract |
|---|---|
| Is the text exact? | `exact` means that this candidate's SQL bytes have no holes. |
| Did the search finish? | `searchClosed` means all relevant alternatives at this call site were covered under the declared abstraction. |
| Is each candidate executable? | Reachability is not assessed. Neither exact text nor a closed search proves feasibility. |

`correlated` means that associations between the values were retained. It does
not mean that the predicates permitting their path are satisfiable. Conditions
being ignored does not by itself mark a search incomplete, and preserving
correlation does not certify reachability.

Compute `searchClosed` for the call site before report filtering: all relevant
readings must be closed, with no missed caller, unfinished loop search, budget
stop, or unresolved model effect. Copy that result to each candidate row. A
resolved fallback beside an unresolved fragment must not make the site appear
fully determined. Known runtime input can close a dependency search while
still producing a text hole, as it does today.

`resolution` keeps the existing categories but must consider surviving model
and budget evidence as well as text holes. When reasons coexist, not-analyzed
and budget/model limitations must not be hidden by an external-input origin.
Use the precedence `not-analyzed`, `incomplete` (budget), `incomplete-model`,
`external-input`, then `resolved`. Keep the reason set even when one category
is chosen for display. In particular,
`Resolution::of(TextPattern)` alone is insufficient once string conversion can
remove a value distinction without removing relevant uncertainty.

Show the SQL candidates grouped by call site, with a visible count of unresolved
alternatives. Exact duplicates occupy one row. Details expose derivation notes
and open reasons. The normal view and JSON export retain unresolved alternatives;
there is no implicit "prefer the resolved reading" filter.

The current format documentation describes `correlated` as reachability and
`searchClosed` at entry level. Those promises cannot be silently reinterpreted.
The implementation must introduce format version 2, update the schema and all
reporters together, and document the change. The minimum new fields are:

- Report-level `analysis.conditions: "not-evaluated"`.
- Candidate `siteId`, identifying the syntactic call independently of SQL text
  and distinguishing calls on the same line.
- Candidate `analysisReasons`, structured reasons with a kind and source
  location, and an impact of `note`, `model`, or `budget`. Absence notes are
  possible derivations with `note` impact, not model-failure reasons. The
  not-analyzed case retains its existing resolution and call-not-analyzed finding.

Version 2 also adopts the meanings of `searchClosed` and `correlated` above.
Placeholder domains with `exhaustive: true` enumerate the complete abstract
value set, without claiming that each listed value reaches the call at runtime.
The report says "SQL candidates", never "all statements that will execute".
No compatibility mode may re-enable predicate evaluation. A version 1 reader
must reject the new version rather than infer the old reachability guarantee.

## Budgets and incomplete language models

Retain the current bounded domains, alternative runs, caller limits, and loop
limits. Share unchanged prefixes and memoize effect summaries by syntax/context.
Avoid repeatedly scanning whole bodies for each potentially absent variable.

When runs are joined, presence joins participate and lost associations set
`correlated: false`. When work is skipped, retain an unknown summary for the
unexamined portion and its reason. Neither stopping nor a missing cache entry
may manufacture `Absent` or silently discard an alternative. Do not turn an
empty internal alternative list into "no SQL" unless structural termination is
established; otherwise it represents incomplete work.

The coverage promise concerns discovered calls in the analyzed source and
supported effect models. It is not a claim to enumerate database calls hidden
inside unavailable included code or dynamically generated code. Such source
boundaries must be reported as analysis problems/open effects where relevant.

A custom error handler may stop execution or affect escaped state when an
absent read occurs. The abstract continuation can remain as an extra candidate;
unmodeled relevant effects remain open. Absence notes never assert that the
candidate reaches the database despite such a handler.

## Implementation boundaries

Keep the existing backward derivation and bounded forward replay. This design
does not require a predicate solver, concrete execution of application code, or
a replacement whole-program control-flow engine.

| Component | Required responsibility/change |
|---|---|
| `Evaluation/Environment` | Store presence-aware bindings, scope defaults, alias/effect state, and correct joins. Distinguish explicit `unset` from forgetting an analysis cache entry. |
| `Evaluation/Domain`, scalar terms, text projection | Preserve unknown values and metadata; normalize only actual string conversion; retain typed value identity beforehand. |
| `Derivation/EntryBinder`, closure entry, `CalleeReturns` | Apply the same entry rules in every route into a body, including helper-return evaluation and exhausted arrivals. |
| `ModifiedNames`, `FreeNames`, `BackwardSlicer`, `AssignmentSteps` | Consult effects before skipping nodes; separate guard-only reads from SQL value reads; retain conditional effects as choices rather than flattening them. |
| `SliceStep`, `SliceExecutor`, `ExpressionEvaluator` | Carry bounded value/environment/evidence outcomes through expression-level choices. Preserve structural order and never branch on a condition value. |
| `Solution`, `QueryRecord`, `EntryFactory` | Propagate completeness and source evidence; merge it independently of SQL identity; compute site-wide closure before filtering. |
| `CatalogEntry`, `Resolution`, schema, reporters | Publish exactness, closure, correlation, and the no-predicate policy without implying feasibility. |

Presence belongs at bindings, so adding `UndefinedTerm::toPattern() => ''` alone
is not an adequate implementation. Likewise, changing the ternary evaluator
alone cannot fix side-effect ordering or the absence proof.

## Acceptance cases

These are normative target expectations, not assertions that current tests pass.
Use an ordinary fresh function scope unless another scope is stated. Every
case must also run through method, closure, and helper-return entry where
applicable.

| Case | Required result |
|---|---|
| Original optional-fragment example | Exactly the two SQL strings shown above; no opaque third alternative; conditions not evaluated. |
| Fragment initialized to null, `''`, or false | String conversion merges equal text; no condition-based removal. |
| Explicit `unset($fragment)` followed by the original ternary | One SQL text, with possible absence evidence retained separately. |
| Undefined ordinary local used in concatenation without a guard | Empty contribution with absence evidence; no fabricated unknown SQL fragment. |
| File-scope optional assignment without a known incoming binding | Preserve the incoming unknown alternative; do not assume an empty global scope. |
| File-scope explicit `unset` before optional assignment | The original two strings if no subsequent unknown effect can change the binding. |
| Unknown helper return or runtime input assigned to the fragment | Keep fallback and unknown-pattern alternatives; preserve their distinct completion reasons. |
| `include`/`extract`/`eval` before the fragment read | Retain an unknown value or receiver and an open search; never only a closed fallback. |
| Unknown effect after a known literal assignment | Invalidate the old value as necessary; do not continue reporting it as exhaustive. |
| Unknown effect followed by a definite independent overwrite | Recover that local's exact value; any other affected dependency, including the receiver, is assessed separately. |
| By-reference mutation, captured reference, imported global, or static local | Apply its binding/effect model; never default it to ordinary-local absence. |
| One branch sets both table and column | Preserve pairs; no independent cross-product while within budget. |
| Two separate branches with identical predicate text | Retain independent choices; no inferred predicate relationship. |
| The table/tail example above | Three strings, including `SELECT * FROM admins`. |
| `true ? 'SELECT 1' : 'SELECT 2'` | Both strings; a literal condition is no exception. |
| `isset($x) ? ' WHERE ' . $x : ''` with an absent alternative | Preserve the extra `' WHERE '` string; no SQL-validity pruning. |
| Assignments in ternary arms or short-circuit operands | Preserve each resulting environment and continuation; never sequence mutually alternative writes. |
| A query inside a guard | Discover it regardless of the guard or its enclosing boolean operator. |
| A known exact reading plus an unresolved reading at one site | Keep both; `searchClosed` is false for the whole site. |
| Two identical SQL readings with different completion/correlation evidence | One row, with the conservative union of metadata; merge order has no effect. |
| Budget ends before entry/effect analysis finishes | Unknown with budget evidence; never absence or a closed search. |

In addition to these examples, verify the following properties with generated
small programs and explicit models of the supported effects:

1. Replacing a pure guard with another pure guard changes neither SQL candidates
   nor their environments. Exclude expressions whose result is also SQL data.
2. Each concrete SQL value observed for generated inputs is admitted by at least
   one candidate pattern. Runtime sampling is a falsifier, not a feasibility
   proof or a replacement for the transfer-rule contracts.
3. Presence joins and evidence merges are associative, commutative, and
   idempotent. Value union has these laws before bounded widening; widening may
   lose precision but must preserve coverage and deterministic output.
4. Replacing a modeled effect with an unknown effect cannot improve completeness
   or exclude one of the previously admitted values.
5. Reducing a budget cannot manufacture a closed answer or definite absence.
6. Removing an unrelated pure statement changes neither candidates nor metadata;
   overwriting a local before use removes dead value dependencies correctly.
7. Candidate generation and serialization are deterministic, including after
   deduplication, metadata merging, and report filtering.

Measure duplicate removal, unresolved alternatives, lost correlation, and work
per sink separately. A lower candidate count alone is not a quality metric.

## Delivery sequence

1. Specify and test presence transfers and effect summaries, including the
   include regression. Add outcome metadata and merge invariants alongside them.
2. Replace condition-selected ternaries with unconditional structural choices;
   integrate presence/effect handling through all body-entry and expression
   paths. Reclassify the tests in #407 that assert predicate-dependent pruning.
3. Apply string projection and evidence-preserving SQL deduplication. Test
   against both the original two-candidate case and the intentional extra cases.
4. Ship format version 2, updated report wording, schema validation, property
   tests, fuzz targets, and documentation together with the new semantics.

Implementation commits may be developed in that order, but the release gate is
all four steps. An intermediate change that only treats unfound definitions as
null, or silently changes version 1's reachability promises, is not acceptable.
This design PR changes documentation only and does not revert or replace #407's
runtime behavior by itself.
