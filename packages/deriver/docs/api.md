# Queries and results

## Capture a project

`ProjectInput` contains immutable `SourceFile` objects. `ProjectInput::fromFiles()` reads an explicit local file list. `ProjectInput::fromDirectory()` captures PHP files, excluding `vendor`, `.git`, and `build` by default. Neither loader executes PHP or follows symlink directories. Use `new SourceFile($path, $bytes, declarationsOnly: true)` for an external signature file whose callable implementations are unavailable. The snapshot records that distinction and keeps body effects unresolved.

Open a new session after changing source, models, environment inputs, or world assumptions. A snapshot records source hashes, target semantics, and registered extension versions. References belong to the snapshot that produced them; another session with a different snapshot cannot use them.

`ProjectSnapshot::models` prefixes contract IDs with `model:`, `intrinsic:`, `domain:`, `slot:`, or `provider:`. Its values are opaque contract fingerprints or registered versions. Compare each complete value when checking whether a contract changed.

`Configuration` selects the target, standard models, additional call models, intrinsics, domains, providers, environment, dependency versions, and abstract state slot contracts. The target is PHP 8.3 with 64-bit integers. An unsupported target is rejected.

## Query types

| Query | Observation |
| --- | --- |
| `ReturnQuery` | Return values and exceptional completions of a callable |
| `ValueQuery` | An evaluated expression register, optionally projected |
| `StateQuery` | A local variable's state at a program point, optionally projected |
| `TupleQuery` | Several evaluated registers under the same path guard |

`QueryScope::symbolic()` represents arbitrary valid parameters of the queried callable. `QueryScope::fromEntrypoints()` starts from explicit invocations, including supplied arguments and optional receivers. Omitted arguments and unknown arguments have different meanings.

`callsTo()` resolves namespace-qualified names and imported function aliases when finding named call sites, and returns `Observation` objects. `beforeInvocation()` observes after arguments have been evaluated. `afterInvocation()` observes normal completion of the invocation. A reference includes the source range, owning callable, and register or instruction identity. Named function and method owners are matched case-insensitively, with an optional leading backslash. A tuple can combine references using equivalent owner spellings. Synthetic script and closure identities retain their case-sensitive source identity.

`Projection::stateSlot($id, $path)` selects a registered abstract receiver slot, independently of PHP property names. Use it with `StateQuery` or `ValueQuery`; `$path` optionally selects a value inside the slot.

`deriveMany()` shares captured declarations and compiled graphs between requests. Each query has its own logical budget and result assessment. `explain()` retrieves the dependency graph, assumptions, and frontiers of a result created by the session.

## Interpret the result

A normal outcome contains named values, a path guard, observed local state, and evidence references. An exceptional outcome contains the exception and the state reached before it was thrown. Keep each outcome together when consuming correlated values.

`Term::native()` is available only for wholly concrete scalars and arrays. Check `isConcrete()` first. An object identity, symbolic parameter, external input, or partial array is represented by its term graph.

The assessment keeps separate axes:

- **Closure**: whether relevant unresolved dependencies remain.
- **Precision**: whether values are concrete, symbolic, or abstracted.
- **Correlation**: whether relationships between alternatives were retained.
- **Coverage**: whether the supported transfer rules provide an over-approximation.
- **Enumeration**: whether the represented possibilities are explicitly enumerated.

An empty normal-outcome list can accompany exceptions or proven unreachability. It is not a substitute for checking frontiers. Project diagnostics describe captured source problems independently from frontiers that affect the query.

## Work limits

`Budget` bounds logical instruction transfers, live partitions, precise loop iterations, recursive specializations, and graph nodes. Defaults are 100,000 transfers, 32 partitions, 16 precise loop visits, 64 active recursive specializations, and 20,000 graph nodes.

A budget boundary retains residual values and effects. It may widen state and relax correlations. It must not report the first explored alternatives as the complete result. Logical limits are part of query identity; a warm result does not acquire a larger semantic budget.

## Source admission

`Configuration::sourceLimits` accepts `SourceLimits`. Defaults admit at most 10,000 files, 64 MiB of captured source, 4 MiB per file, 250,000 syntax nodes across the project, and 128 levels of syntax nesting. File sizes are checked before parsing. Raw syntax is measured iteratively before name resolution, cloning, and lowering. Cached trees must satisfy the current admission limits too.

Opening a project that exceeds these limits throws `InvalidInputException` with a `SOURCE_LIMIT` reason. No partial declaration world is returned. Increase a limit explicitly when admitting a larger generated project. These limits apply after source bytes have been supplied; callers remain responsible for bounding their own file capture and trusted provider implementations.

```php
$configuration = new \Deriver\Api\Project\Configuration(
    sourceLimits: new \Deriver\Api\Execution\SourceLimits(nodes: 500000),
);
```

## Runtime interruptions

`Configuration::resources` accepts `ResourceLimits`. Its default memory allowance is 256 MiB of additional used PHP memory per query. Source capture and eager declaration indexing happen before this allowance starts. Deriver also checks the host PHP allocator limit and reserves 256 KiB, where available, for constructing an interruption report. Checks occur between semantic operations; these limits do not preempt an individual parser or value operation.

The default host stack allowance is 2,048 PHP frames. Active Xdebug nesting limits reduce this allowance while retaining termination headroom. This runtime protection is separate from the two-site semantic context suffix; long call chains remain analyzable when runtime resources permit.

Set `seconds` to a positive duration to enable a monotonic wall-clock limit. Supply a `CancellationToken` for caller-controlled cancellation, including cancellation from an application signal handler. The analyzer checks the token without invoking application callbacks.

```php
$token = new \Deriver\Api\Execution\CancellationToken();
$configuration = new \Deriver\Api\Project\Configuration(
    resources: new \Deriver\Api\Execution\ResourceLimits(
        memoryBytes: 64 * 1024 * 1024,
        seconds: 10.0,
        cancellation: $token,
    ),
);
// Calling $token->cancel() requests termination of the active or next query.
```

Interruptions produce `CANCELLED`, `MEMORY_LIMIT`, `TIME_LIMIT`, or `STACK_LIMIT` frontiers and retain residual normal and exceptional behavior. Their positions depend on runtime conditions. Interrupted results are not cached. Cancellation is permanent for a token; use a new token for a later operation.

## Reuse across snapshots

Reuse an `Analyzer` instance when opening successive source snapshots. It caches resolved syntax by source bytes and target grammar, and source IR by file hash, lexical declaration, target, and lowerer version. Source references are rebound to the receiving snapshot. The caches are in memory and bounded to 128 source trees and 512 graph templates. Each cache also has a 32 MiB retention allowance based on conservative source and structure cost estimates; this estimate is separate from PHP allocator usage. Large entries are used without retaining them. Eviction changes performance, not source semantics.

Semantic query results remain local to their immutable session. Adding a source declaration, implementation class, model, domain, or environment input creates a new snapshot and recomputes semantic results, including earlier missing-model and open-dispatch results. An old session continues to represent its original captured world.

Results and explanations remain available for the session's lifetime. Release completed sessions when processing an unbounded stream of unrelated queries. Completed call outcomes are memoized only after proving isolation; general stateful calls reuse compiled IR and evaluate effects against their current storage.

Within a query, proven isolated source functions can reuse correlated return/exception summaries. Their keys include bound inputs, constraint partitions, and the last two call sites. Shared reference cells, mutable objects, and external-state effects use the ordinary state evaluator. Pending recursive summaries are solved in dependency components; budget exhaustion keeps explicit residual behavior.

## Models and unknown calls

Source bodies are preferred unless a model explicitly requests source replacement. Unknown calls retain normal and exceptional alternatives and invalidate reachable mutable state, reference arguments, globals, and statics. Open-world method dispatch keeps known implementations and an unknown-implementation remainder. Set `closedWorld` only when the supplied declarations cover the relevant implementations.

## Exceptional paths and storage

For `ValueQuery`, `StateQuery`, and `TupleQuery`, normal outcomes describe executions that reach the selected observation. Uncaught exceptions escaping an entry before that observation appear in `exceptionalOutcomes`, including their state and evidence. An exception alone does not make the observation reachable. Exceptions handled inside the entry follow the ordinary catch/finally flow. Failures after an already reached observation are not reported as failures to reach it.

Every normal and exceptional alternative exposes `storage`, an immutable `StorageSnapshot`. The existing `state` map contains convenient materialized local values. `storage.bindings` maps local names to location terms, and `storage.cells` contains the raw reachable storage graph, including returned objects, reference cells, globals, static storage, and registered model slots. This retains aliases and cycles without recursively expanding them.

A location term's literal identifies a cell; its operands hold ordered path keys. A `cell` term directly refers to another cell. An `object` term with literal `id` refers to `storage.cells['object:' . id]`; its `class` attribute carries the runtime class. Registered receiver slots use `model:id`. Private properties use `DeclaringClass::name` storage keys. These identities belong to one alternative and must not be compared across independent outcomes or snapshots.

Opaque cells remain opaque, and uninitialized properties remain distinct from null. Storage contains captured reachable roots, not a list of every variable an external process could create. When the outcome budget merges alternatives, cell values widen and the result records relaxed correlation.
