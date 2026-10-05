# Candidate dependency guarantees

Deriver follows the dependencies of the value you select. PHP syntax can express the same dependency in several ways, and those forms must agree about the selected receiver, the value stored at a write, and the implementation supplied by a model.

## Receiver candidates

A declared parameter type constrains an input; it does not fix the implementation of an instance method. In this example, deriving `target` returns `users`:

```php
class Repository
{
    public function table() { return 'base'; }
}

class UserRepository extends Repository
{
    public function table() { return 'users'; }
}

function table(Repository $repository)
{
    return $repository->table();
}

function target()
{
    return table(new UserRepository);
}
```

Known receivers flow through named arguments, local aliases and inherited methods. Each candidate receiver stays bound to its selected method, including reads through `$this`. PHP's lexical private-method rules still apply. The same receiver selection is used when a method's reference-argument write or model-state write is requested.

These rules follow PHP's [inheritance](https://www.php.net/manual/en/language.oop5.inheritance.php) and [visibility](https://www.php.net/manual/en/language.oop5.visibility.php) semantics.

## Stored values and expression results

Property origins include mutations rather than only the right-hand operands of ordinary assignments. For `$this->table .= '_archive'`, the prior storage definition and the suffix are both dependencies. For `$this->count++`, the property origin is the updated count; selecting the expression itself yields the old count. Local reads, constructor properties and property-origin queries use the same storage evaluation.

For example, a property declared as `public $table = 'ledger'`, with a method containing `$this->table = 'ledger'; $this->table .= '_archive';`, contributes both `ledger` and `ledger_archive`. No proof that the method was invoked is required. A particular allocation can instead resolve the final constructor definition, starting from the property's declaration initializer.

Compound assignment and prefix/postfix results follow PHP's [expression semantics](https://www.php.net/manual/en/language.expressions.php). A mutation that depends on an unresolved earlier value retains that dependency. Repeated mutation origins can produce a recursive residual instead of a finite closed set.

## Recursion and limits

Reusing a parameter name does not establish a cycle. Both of these recursive steps can be derived with sufficient budget:

```php
return 1 + countDown($n - 1);
```

```php
$n -= 1;
return 1 + countDown($n);
```

Early cycle detection requires proof of unchanged pass-through bindings: the same callable, corresponding inputs, and storage without intervening mutations or relevant effects. Otherwise derivation continues under the normal budgets. Exhausting `Budget::$recursion` reports `RECURSION_LIMIT`; it does not claim that a finite call was cyclic. Depth and other resource limits can be reached first.

## Replacement applies to every output

A selected model supplies the implementation used for returns, reference effects, constructor property initialization, property origins and model-state writes. Inspecting an effect cannot reopen the replaced source body. Model selection precedes inspection of the implementation's writes, so an empty plan suppresses source writes as well as source return expressions.

Property origins with known calls select models in those callers' argument contexts. Without known calls, inputs remain symbolic. An explicit model decline permits source behavior; an unsupported decision leaves an `UNSUPPORTED_MODEL_CASE` residual. Check `statistics->expandedBodies` to verify that a replaced source symbol was never expanded.

## What verification establishes

The differential suite generates combinations of declared receiver type, alias and method spelling; mutation form and storage entry; and recursive argument spelling, argument naming and recursion length. Generated fixtures run in an independent PHP 8.3 process. Closed dependencies must agree exactly with PHP and contain no residual values. Property-origin tests additionally require the runtime mutation result to appear among the source candidates, which can include earlier writes.

Separate contract tests check forbidden source expansion, unnecessary argument evaluation, receiver correlations, and the distinction between cycle and budget frontiers. The generated matrix runs in the existing differential CI job. Adding a syntax variant to a matrix dimension applies the existing cross-feature checks to that variant.

These checks cover the generated combinations, not every legal PHP program. Missing source, unresolved inputs, recursive property histories and analysis limits can still leave residual expressions. Inspect partial candidates and their evidence before treating a result as exhaustive. Candidate coverage does not prove runtime reachability or arbitrary external object histories.


## Origins and operations

Each row describes a dependency rule used by the same backward expansion. Rules can be combined; they do not select separate whole-program execution modes.

| Selected source form | Dependencies opened | Information retained when unresolved |
| --- | --- | --- |
| Literal, constant, exact expression range | The selected definition and its source location | Constant name or original expression |
| Local variable or alias | Reaching writes to the selected storage at the observation point | Storage identity and missing or overlapping writes |
| Formal parameter | Actual arguments at captured callers, with named/unpacked/default binding | Unbound formal and caller context; an uncalled formal does not acquire its default |
| Assignment, compound assignment, increment | Prior storage when needed and the assigned operands | Known operands and the update operation; expression and stored results remain distinct |
| Unary, binary and cast expression | Required operands, after replacement-rule selection | Operator and known operand values |
| Conditional, short-circuit expression | Value-selecting condition and alternatives | Shared selector identity and compatible alternatives |
| Array construction, unpack, indexed read/write | Ordered entries, keys and the selected nested storage | Known entries, unresolved keys/unpacks and enumeration remainder |
| Function, method, callable or callback | Selected implementation, actual/formal binding and demanded outputs | Call expression, receiver, lexical/called class and missing implementation |
| Closure capture | Creation-time value or the captured storage at invocation | Capture mode and unknown reference history |
| Property, constructor, alias or clone | Allocation identity, initializer and relevant writes before observation | Receiver and storage history; a clone retains its copy source |
| Global or static local | Captured script definitions, relevant calls and supplied initial storage | Missing external input or unknown invocation history |
| Class/interface constant, enum | Captured declaration and lexical/called class | Constant or enum identity and missing declaration |
| Loop-dependent value | Initial definition and relevant updates; finite known iterations | Initial value, recurrence and stopping reason; invariant values bypass irrelevant updates |
| Return, explicit throw, catch, finally | Value supply and completion dependencies at the original evaluation point | Non-value dependency or unknown completion; throw-only bodies do not supply null |
| Function/expression override | Only the inputs demanded by the selected versioned rule | Rule identity, requested inputs and any residual returned by the rule |

The [semantic fixtures](../tests/Semantic/CandidateExpansionTest.php), [dependency combinations](../tests/Differential/DependencyCompositionTest.php), [runtime comparisons](../tests/Differential/CandidateContractTest.php) and [evidence tests](../tests/Integration/CandidateEvidenceTest.php) check these rules at different boundaries. They establish the covered forms and combinations, not complete coverage of the PHP grammar. Conditional declarations with unresolved receiver or storage histories can still leave partial values.

## Expansion and evidence

References expand into definitions and their operands. PHP syntax handlers provide local rules for this same mechanism: adding an operator does not introduce a new execution strategy. Calls bind actuals to formals before opening a body; callbacks use the same binding and dispatch rules. Explicit expression/function replacements are selected before demanding operands.

A candidate's evidence is a content-addressed DAG. Conjunction nodes retain dependencies used together; choice nodes retain alternative derivations. Context projection preserves caller and binding relationships separately from the computed value, including when a dependency is reused from cache. Equal values may have multiple evidence alternatives. Exported evidence is owned by the result and survives session release.

Unknown input, unknown receiver dispatch, an unavailable invocation history, and a resource stop have distinct residual reasons. A partial expression keeps known prefixes, fields and operands. A throw-only definition has no ordinary PHP value: it produces a `never` partial rather than a fabricated `null` value. Candidate sets describe source origins under captured assumptions; they do not assert that every origin is reachable in one execution.

Contract tests cover arbitrary expression targets, formal versus actual arguments, lazy replacements, nested callers, equal-value origins, source coordinates, cache/release stability and replay mutations. Semantic fixtures cover PHP forms separately from those API guarantees. The bounded benchmark records capture, cold, warm and batch measurements against a pinned baseline; measurements of generated fixtures do not establish performance for every application.
