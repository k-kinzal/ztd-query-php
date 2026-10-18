# Configurable evaluators

Register `MicroEvaluator` instances through `DeriverConfig::evaluators`. A matching evaluator receives an operation and lazy inputs. Returning a `Term` replaces that operation; returning `null` declines it and tries the next evaluator. Higher priorities run first, followed by the built-in evaluator. Duplicate identities or identical selectors at the same priority are rejected.

The same selection mechanism handles ordinary expression operations, branch decisions, and resolved functions and methods. An evaluator does not have to traverse the implementation it replaces. Evidence records the accepted evaluator's identity, version, and requested inputs. Change the version when changing its semantics.

## Replacing a function or method

```php
use Deriver\Deriver;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\EvaluationOperation;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\MicroEvaluator;

$subject = new MicroEvaluator(
    'app.subject',
    '1',
    EvaluationOperation::Function,
    'subject',
    static fn () => Term::constant(42),
);
$deriver = Deriver::prepare(['src/subject.php'], new DeriverConfig(evaluators: [$subject]));
$result = $deriver->derive('subject#return');
```

This replaces both calls to `subject()` and direct `subject#return` queries. The body and unused arguments are not derived. The return query still resolves and compiles the selected declaration to establish its identity and formal inputs. To replace a method, use its resolved declaration name, such as `App\Repository::find`; the rule applies to instance calls, static calls, and direct method return queries. Function and method names are matched without case sensitivity or a leading backslash.

`$evaluation->input(0)` requests one argument. At call sites, string keys refer to explicitly named arguments; at direct return queries they refer to formal parameter names. Direct queries can derive that parameter from supplied inputs or discovered callers. A constant replacement need not request any inputs.

Optional `path`, `start`, and `end` selectors match the operation's source path and exact byte offsets. For a call this is the call expression; for a direct return query it is the declaration. Branch sites use the condition expression's source location. The operation name for a `Branch` evaluator is the empty string.

## Optional branch pruning

Source derivation keeps both branches by default. Enable boolean candidate pruning explicitly:

```php
use Deriver\Deriver\BranchEvaluators;

$config = new DeriverConfig(evaluators: [
    ...BranchEvaluators::truth(),
    $subject,
]);
```

This registers a normal, replaceable `Branch` evaluator. It requests `$evaluation->truth(0)`. The same branch operation is used by `if` and `elseif`, ternary expressions, the conditions underlying `switch` and `match`, and `for`, `foreach`, `while`, and `do` loops. A `Conditional` evaluator can still replace a complete ternary expression, including its result operands.

The boolean demand applies PHP boolean conversion to candidates. A completed false-only result excludes the true arm; a completed true-only result excludes the false arm. Both possibilities keep both arms. Unknown or interrupted dependencies keep any branch they could permit and retain their dependency evidence. Finding one concrete boolean alongside an unresolved dependency is insufficient to prune the other arm.

Boolean demand has its own cache and propagates through conditional alternatives, returns, local assignments, and lazy arguments. Once both outcomes have witnesses, remaining alternatives are not opened. Nested alternatives receive the outcomes still needed by their parent. Other operations retain their ordinary evaluator semantics and may need operand values; custom evaluators control their own demands. A full value request remains independent: using a value as a condition never truncates a later query for that value.

Declared `bool`, literal boolean types, captured enums, and unions made entirely from finite types (including nullable forms) also bound ordinary return requests. Derivation stops when every member has a candidate witness. A type annotation alone does not create candidates: a `bool` function returning only `false` has only that candidate. Declaration conversions apply before checking coverage, so weak boolean conversion and strict type failures retain their usual behavior. Coverage retains witnesses for values, not an exhaustive list of paths producing the same value; already observed failures remain available.

Loop derivation first provides structural candidates from entry and body updates. Pruning only removes candidates. The configured branch evaluator can establish that the body is skipped or that the loop ends after its first update. A zero-pass constraint excludes the body; a one-pass constraint permits pruning expressions inside the body using entry bindings. Loop bounds and selecting conditions are shared across requested storage locations.

This constraint check examines entry and at most one update. It never repeats the body according to an iteration count. When more passes remain possible, body candidates and necessary recurrence dependencies remain; the final iteration is not computed. For example, `$v = 0; for ($i = 0; $i < 1; $i++) { $v = $i === 0 ? 11 : 27; }` provides `0`, `11`, and `27` by default, and `11` with truth pruning. Changing the bound to `2` or `10000` retains `11` and `27` with the same candidate expansion cost.

Known empty and singleton iterables support the same constraints for `foreach`. A larger nonempty iterable permits both an element and eventual exhaustion. A `do` loop always keeps its first body independently of its condition.

To replace the branch policy itself, register another `MicroEvaluator` with `EvaluationOperation::Branch`, name `''`, and a higher priority, or supply that evaluator instead of `BranchEvaluators::truth()`. Its callback may request `truth(0)`, request the full `input(0)`, or return its own candidates without opening the condition. An empty `Choice` represents no available branch. Returning `null` restores the next matching evaluator or the default candidate behavior.
