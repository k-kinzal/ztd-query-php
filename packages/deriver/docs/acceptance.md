# Acceptance and verification

The AC identifiers below preserve the requirements from the Deriver design. Each row links to executable regression evidence. They cover the stated fixtures and contracts; passing them is not a proof over every PHP program. [Capabilities](capabilities.md) records the implemented surface and explicit boundaries.

| Requirement | Required behavior | Regression evidence |
| --- | --- | --- |
| AC-01 | Overwritten values do not survive as false candidates | [Concrete semantics](../tests/Semantic/ConcreteSemanticsTest.php), `overwrite` |
| AC-02 | Symbolic concatenation is a closed, exact expression | [Acceptance contract](../tests/Semantic/AcceptanceContractTest.php), `testSymbolicConcatenationIsAClosedExpression` |
| AC-03 | Branch tuples and independently projected fields keep their correspondence | [Acceptance contract](../tests/Semantic/AcceptanceContractTest.php), `testSeparateProjectionsRetainTheSameBranchIdentities`; [state joining](../tests/Unit/Internal/Solver/Control/StateJoinTest.php) |
| AC-04 | Contradictory supported integer guards exclude an impossible branch | [Constraints](../tests/Unit/Internal/Constraint/ConstraintsTest.php) |
| AC-05 | Separate calls do not mix their argument contexts | [Concrete semantics](../tests/Semantic/ConcreteSemanticsTest.php), `callContexts` |
| AC-06 | Shared objects, shallow clone, and clone hooks preserve identities | [Concrete semantics](../tests/Semantic/ConcreteSemanticsTest.php), `clone`, `shallowClone`, `cloneHook`; [state oracle](../tests/Differential/StateAgreementTest.php) |
| AC-07 | Value capture and reference capture observe different times | [Concrete semantics](../tests/Semantic/ConcreteSemanticsTest.php), `capture`; [callable corpus](../tests/Fake/Programs/CallablePrograms.php) |
| AC-08 | Reference writes matter even when the return is unused | [Concrete semantics](../tests/Semantic/ConcreteSemanticsTest.php), `reference`; [location contracts](../tests/ModelContract/LocationContractTest.php) |
| AC-09 | Finally resumes or overrides an already evaluated completion | [Concrete semantics](../tests/Semantic/ConcreteSemanticsTest.php), `finally`, `finallyOverride`, `breakFinally`; [generated corpus](../tests/Fake/GeneratedPrograms.php) |
| AC-10 | Known array iteration remains precise through helper returns | [Acceptance contract](../tests/Semantic/AcceptanceContractTest.php), `testKnownIterationUsesTheArrayReturnedByASourceHelper`; [iteration corpus](../tests/Fake/Programs/IterationPrograms.php) |
| AC-11 | General loops widen; forced budget exhaustion retains a residual | [Loop convergence](../tests/Unit/Internal/Solver/Control/LoopConvergenceTest.php), [budget](../tests/Unit/Api/Query/BudgetTest.php), [residual paths](../tests/Unit/Internal/Solver/Control/ResidualPathsTest.php) |
| AC-12 | Concrete recursion evaluates; unresolved cycles are never empty success | [Concrete semantics](../tests/Semantic/ConcreteSemanticsTest.php), `recursion`; [summary evaluation](../tests/Unit/Internal/Solver/Summary/EvaluationTest.php), [components](../tests/Unit/Internal/Solver/Demand/ComponentsTest.php) |
| AC-13 | Unknown effects invalidate reachable state and preserve unrelated locals | [Havoc](../tests/Unit/Internal/Solver/HavocTest.php), [state slot contracts](../tests/ModelContract/StateSlotContractTest.php), [snapshot invalidation](../tests/Unit/AnalyzerTest.php) |
| AC-14 | Policy choice, composition, and override remain distinct | [Policy contracts](../tests/ModelContract/PolicyContractTest.php), [domain laws](../tests/Unit/Model/Contract/DomainLawsTest.php) |
| AC-15 | Named, variadic, and unpacked arguments follow the selected signature | [Argument corpus](../tests/Fake/Programs/ArgumentPrograms.php), [location contracts](../tests/ModelContract/LocationContractTest.php) |
| AC-16 | Reference foreach retains its last cell until unset | [Concrete semantics](../tests/Semantic/ConcreteSemanticsTest.php), `byrefForeach`; [iteration corpus](../tests/Fake/Programs/IterationPrograms.php) |
| AC-17 | Array copies preserve embedded reference cells | [Concrete semantics](../tests/Semantic/ConcreteSemanticsTest.php), `arrayReferenceCopy`, `arrayValueCopy`; [state oracle](../tests/Differential/StateAgreementTest.php) |
| AC-18 | Distinct allocations keep same-named properties separate | [Concrete semantics](../tests/Semantic/ConcreteSemanticsTest.php), `propertyAllocations`; [state slot contracts](../tests/ModelContract/StateSlotContractTest.php) |
| AC-19 | Interface dispatch retains known implementations and an open remainder | [Acceptance contract](../tests/Semantic/AcceptanceContractTest.php), `testOpenDispatchRetainsBothKnownImplementationsAndTheRemainder`; [provider contracts](../tests/ModelContract/ExtensionContractTest.php) |
| AC-20 | Defaults apply to omitted call arguments, not all external inputs | [Query execution](../tests/Unit/Internal/Api/QueryExecutionTest.php), [declaration contracts](../tests/ModelContract/DeclarationContractTest.php) |
| AC-21 | Defining or storing a callback does not execute it | [Concrete semantics](../tests/Semantic/ConcreteSemanticsTest.php), `deferredClosure`; [callable corpus](../tests/Fake/Programs/CallablePrograms.php) |
| AC-22 | Fresh random evaluations differ from two uses of one saved value | [External transfer](../tests/Unit/Internal/Solver/Transfer/ExternalTransferTest.php) |
| AC-23 | Catch observes only effects completed before throw | [Concrete semantics](../tests/Semantic/ConcreteSemanticsTest.php), `catch`; [observation semantics](../tests/Semantic/ObservationSemanticsTest.php), [state oracle](../tests/Differential/StateAgreementTest.php) |
| AC-24 | Short circuit and unselected branches do not contribute effects | [Concrete semantics](../tests/Semantic/ConcreteSemanticsTest.php), `shortCircuit`, `nullsafe`, `coalesce` |
| AC-25 | Query frontiers remain separate from unrelated project syntax errors | [Session contracts](../tests/Integration/SessionContractTest.php), [target syntax](../tests/Unit/Internal/Frontend/Php/Validation/TargetSyntaxTest.php) |
| AC-26 | Model conflicts and invalid domain/plan contracts cannot succeed silently | [Precedence](../tests/Unit/Internal/Model/ModelPrecedenceTest.php), [model boundary](../tests/Unit/Internal/Model/ModelBoundaryTest.php), [domain laws](../tests/Unit/Model/Contract/DomainLawsTest.php), [extension contracts](../tests/ModelContract/ExtensionContractTest.php) |
| AC-27 | Query and file order do not change completed semantics | [Session contracts](../tests/Integration/SessionContractTest.php), [batch queries](../tests/Unit/Internal/Api/SessionTest.php), [model precedence](../tests/Unit/Internal/Model/ModelPrecedenceTest.php) |
| AC-28 | Source, model, dependency, slot policy, and world changes invalidate reuse | [Session contracts](../tests/Integration/SessionContractTest.php), [session identity](../tests/Unit/Internal/Api/SessionTest.php), [state slots](../tests/ModelContract/StateSlotContractTest.php), [declaration modes](../tests/ModelContract/DeclarationContractTest.php) |
| AC-29 | Source capture never executes application top-level code | [Session contracts](../tests/Integration/SessionContractTest.php), `testSourceCaptureDoesNotExecuteTopLevelCode` |
| AC-30 | PHP 8.3 operations and signatures are independent of the host | [Differential suites](../tests/Differential/), [native overloads](../tests/Integration/NativeOverloadContractTest.php); CI runs behavioral suites on PHP 8.1–8.5 |
| AC-31 | Candidate limits include late alternatives rather than retaining a prefix | [Observation limits](../tests/Unit/Internal/Solver/Control/ObservationLimitTest.php), [state joining](../tests/Unit/Internal/Solver/Control/StateJoinTest.php) |
| AC-32 | Invalid requests, interrupted work, and proven unreachability differ | [Session contracts](../tests/Integration/SessionContractTest.php), [cancellation](../tests/Unit/Internal/Api/SessionTest.php), [resource limits](../tests/Unit/Internal/Solver/Control/ResourcesTest.php) |

## Independent runtime comparison

The differential suite sends only trusted test fixtures to a separate PHP 8.3 process. The analyzer consumes the same source as data. Scalar/array fixtures compare returns, exception classes, and selected diagnostics. The structured state corpus additionally compares reachable object properties, sharing, cycles, clones, reference elements, globals, and state after exceptional completion using the public storage report.

`composer fuzz:semantic` generates seeded programs, enumerates three concrete inputs, and minimizes a failing program by deleting statements. [Fuzz documentation](../fuzz/README.md) describes reproduction and counterexample artifacts. The grammar and finite input set bound the evidence. The separate byte-input fuzzer checks parser/solver robustness without executing its input.

## Reproducing the checks

```sh
composer install
composer lint
composer test
composer test:differential
composer fuzz:smoke
composer fuzz:semantic
composer bench
```

Set `DERIVER_PHP83_BINARY` to an isolated PHP 8.3 executable when the host differs. Static checks use the repository's php-ai-toolkit policies, strict PHPStan, PHP compatibility, size/tree guards, and Deptrac. Coverage metadata and runtime issues are strict. CI separates host compatibility, target semantics, and daily mutation/fuzz campaigns. Mutation alerts use the same 80% thresholds and reporting policy as sibling packages.

A known wrong constant or silently omitted runtime outcome blocks release. A failing supported case must be corrected or converted to an explicit boundary with an updated capability entry and regression test. Runtime differential evidence and benchmarks are finite measurements; they do not establish a whole-language soundness proof or an application-wide performance guarantee.
