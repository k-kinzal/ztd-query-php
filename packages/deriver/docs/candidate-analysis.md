# How candidate analysis works

An observation identifies one or more registers or storage reads in the captured source. Analysis asks for those definitions directly. It does not create an entry execution state, run a machine to the observation, or reconstruct a restricted expression after failure.

```text
captured declarations and definition indexes
                   |
selected expression / tuple / storage read
                   |
reaching definitions, caller actuals, property origins
                   |
model selection before body or unused input expansion
                   |
shared immutable expressions and guarded alternatives
                   |
partial evaluation, residual references, explanation
```

## Definitions and conditions

The parser and lowered control-flow graph supply a searchable index. Reading or compiling a declaration is distinct from requesting its values. A local read follows its reaching writes, including writes through a statically bound reference argument. Earlier overwritten values are excluded. Conditions that select a demanded value are evaluated; unrelated calls do not become evaluation roots.

Choices carry decision identities. Repeated reads selected by the same branch or caller are joined only when those decisions agree. A tuple therefore keeps `users/name` and `orders/total` together rather than inventing `users/total`. Enumeration overflow retains the operand choices, named tuple fields and an `ENUMERATION_LIMIT` boundary.

Argument bindings are lazy and positional or named. Caller indexes resolve declaration identity, including inherited methods. Lexical captures can be resolved at closure creation without requiring an invocation history. Property origins use declaration and receiver information rather than a search for matching property-name text. A known allocation can request a constructor's relevant property write.

The default source scope is not a claim about every external execution. Explicit entry bindings narrow corresponding declarations; they are included in dependency-cache identity. A source-origin search can still encounter callers outside those entries. Use the separate execution contract when the question requires restricting results to paths actually reached from selected entries.

## Partial expressions

The same `Term` representation carries constants, arrays, operations, references, choices, recursion and deferred work. Unknown numeric inputs remain operands of arithmetic. Unknown array entries retain known neighbors. Unavailable calls retain their arguments. Unsupported demanded operations retain children and source positions with a missing-capability reason.

Natural boundaries such as `EXTERNAL_INPUT` and `MISSING_SOURCE` mean that captured origins have ended. `DEPTH_LIMIT`, resource limits and `CYCLE` mean expansion stopped or closed a recursive dependency. These facts are distinct from the number of candidates. Supplying an actual input, adding source or a model, or increasing a relevant bound can resolve the corresponding dependency.

Depth is consumed when a reference crosses to its origin, not for every arithmetic or concatenation node. For `$table = $name; $sql = 'SELECT ' . $table`, observing `$sql` yields a reference at depth zero, a concatenation containing `$table` at depth one, and a concatenation containing `$name` at depth two. Each branch has its own remaining depth.

Finite recursive inputs and simple loop-carried definitions are simplified by requesting successive definition versions. Unbounded or unresolved recurrence keeps a recursive reference and known surrounding structure. This mechanism does not advance unrelated program state.

## Models and state

A registered model is selected before source-body derivation. A constant plan can return immediately. A plan reading one parameter or receiver slot requests just that dependency. `DemandModel` additionally declares the parameters needed to decide whether a plan applies.

Model state is indexed by slot and receiver. A demanded slot follows preceding matching model writes and its registered allocation default. External receiver state remains a `state-read` dependency. Model plans use the same values, conditions, references and source evaluation as ordinary candidates.

## Sharing and resource ownership

A session owns one source/model snapshot and its indexes. Dependency keys additionally include call/receiver bindings, observed definition identity, scope, remaining depth and enumeration/recurrence bounds. Different snapshots or model registrations use distinct session caches. Dependencies containing incomplete expansion, recursive closure or query-local allocation/capture state are not retained as reusable answers.

Flat array construction evaluates key/value expressions into one buffer. A general expression such as `-(1 + 0)` is simplified before PHP key normalization. Ordered symbolic updates are retained when a key or unpack is unknown. The implementation does not retain arrays of lengths one through N while building a flat N-element literal.

The cache capacity counts retained evaluation entries, not bytes or total transitive `Term` nodes. Large values, source indexes and caller-owned results have separate costs. Recent result ownership is additionally restricted by the existing small-result policy. `release()` drops session-owned evaluation and result references without mutating graphs already returned to users.

Statistics report requested reference expansions, source body expansions, model applications, shared dependency hits and retained cache entries. `constructedNodes` counts recorded dependency-definition visits, including reuse evidence; it is not a heap allocation profiler. `expandedBodies` and `expandedReferences` support checking that unrelated work was never requested.

## Scope of the implementation

The source target remains PHP 8.3. Candidate derivation covers the acceptance matrix and the additional language cases reported in validation. It is not a claim that every execution-only feature already has an equally precise backward dependency representation. Complex dynamic aliasing, arbitrary framework execution history, generator protocols and complex exception/finally or iteration state can remain residual dependencies. They do not trigger an implicit fallback to execution analysis. Parser errors and invalid model contracts remain API diagnostics/errors rather than guessed candidates.
