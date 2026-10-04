# Migrating to source candidates

`Analyzer::open()` now defaults to the candidate contract. Existing selector and query constructors are retained. The meaning of the result changes from entry execution to source-origin candidates.

| Previous expectation | Candidate contract |
| --- | --- |
| Start at a symbolic function entry | Start at the selected expression |
| Keep formal inputs symbolic regardless of callers | Expand matching captured callers |
| External object properties remain arbitrary state | Collect declared initializers and corresponding source writes |
| Source wins unless `replaceSource` is set | An applied model replaces source; explicit decline permits source |
| Execute required prefixes before observing | Follow only demanded value/write dependencies |
| Recover partial strings after interruption | Keep all partial expression kinds on the ordinary path |
| Batch shares one forward execution and budget | Queries share dependency evaluations with independent budgets |
| Reachability and runtime outcomes describe execution | `reachability = not-assessed`, `coverage = source-candidates` |

To retain the earlier contract explicitly:

```php
$configuration = (new \Deriver\Project\Configuration(models: $models))->forExecution();
$session = (new \Deriver\Analyzer())->open($input, $configuration);
```

The contracts never switch automatically. An unresolved candidate stays in the candidate result even when the execution contract happens to support that program more precisely.

## Consume the graph

Do not filter general observations through `definite()`. Iterate `normalOutcomes` and keep each `Term`; inspect `candidateGraph` when choices exceed eager enumeration limits. An external input is a normal residual operand, while an expansion boundary records why further origins were not requested.

JSON schema version 1 has additive optional `contract` and `candidateGraph` fields and candidate statistics. Candidate reports use the expanded reachability/coverage enums. Consumers that validate against the previous pinned schema must update it. Execution reports retain their previous contract fields; the new statistics have zero defaults in that contract.

Consumers that explicitly require `coverage === 'over-approximation'` also need to handle `source-candidates`. This affected the Magix reader comparison: changing that contract check in a temporary copy restored the expected literal results. Updating the check means accepting the captured-source scope; it does not certify arbitrary external execution histories.

`StateQuery` values retain the `state` slot. Value, return and tuple queries retain their respective names. Graph alternatives retain conditions even when identical public outcome values are deduplicated.

## Model selection

`replaceSource` remains meaningful for explicit execution analysis. In candidate analysis, every selected handled model replaces the body. A partial handled result does not append the source's candidates. `CallModel::describe()` receives formal parameter handles; do not assume every input was already evaluated. Use `DemandModel::demand()` for parameter values needed by matching. Parameter expressions in the chosen plan remain lazy independently of matching.

## Lifetimes

Keep a `DerivationResult` while using its `ResultRef` for `explain()`. Configure `retainedResults` and `candidateCacheEntries` for the session workload, and call `release()` when session-owned evaluation results are no longer useful. Released caller-owned results remain immutable and explainable. Source indexing is not released until the session itself is released.

## Why regression tests select a contract

The existing `Tests\Fake\Analysis` fixture now explicitly selects execution analysis. Its tests continue checking PHP operators, evaluation order, aliasing, heap state, error handling and ordered effects against the same established contract. They were not deleted or changed to accept arbitrary partial values.

New `CandidateContractTest`, `CandidateIntegrityTest` and `CandidateDependenciesTest` use the default public API and test the requested candidate behavior. Additional component tests exercise the dependency evaluator, and candidate differential tests compare closed finite expressions with PHP.

The signature-only declaration test now expects a `call-write` residual retaining the previous input and unavailable declaration, instead of an opaque value and an assumed exceptional execution. The external-default test explicitly selects execution analysis because it asks whether a preceding invocation throws before a later catch return. Compiler strictness and trait-visibility regressions continue to run through the default candidate API. These changes distinguish analysis contracts while preserving their respective language checks.
