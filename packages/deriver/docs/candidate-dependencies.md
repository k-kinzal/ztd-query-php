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

Property origins with known calls select models in those callers' argument contexts. Without known calls, inputs remain symbolic. An explicit model decline permits source behavior; an unsupported decision leaves an `UNSUPPORTED_MODEL_CASE` frontier. Check `statistics->expandedBodies` to verify that a replaced source symbol was never expanded.

## What verification establishes

The differential suite generates combinations of declared receiver type, alias and method spelling; mutation form and storage entry; and recursive argument spelling, argument naming and recursion length. Generated fixtures run in an independent PHP 8.3 process. Closed dependencies must agree exactly with PHP and have no frontiers or exceptional outcomes. Property-origin tests additionally require the runtime mutation result to appear among the source candidates, which can include earlier writes.

Separate contract tests check forbidden source expansion, unnecessary argument evaluation, receiver correlations, and the distinction between cycle and budget frontiers. The generated matrix runs in the existing differential CI job. Adding a syntax variant to a matrix dimension applies the existing cross-feature checks to that variant.

These checks cover the generated combinations, not every legal PHP program. Missing source, unresolved inputs, recursive property histories and analysis limits can still leave residual expressions. Inspect frontiers and exceptional outcomes before treating a result as exhaustive. Candidate coverage does not prove runtime reachability or arbitrary external object histories.
