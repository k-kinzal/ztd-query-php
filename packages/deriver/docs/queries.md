# Queries

A query selects the value to derive. Pass one query per command, enclosed in shell quotes.

## Selectors

| Query | Selected value |
|-------|----------------|
| `App\answer#return` | Return value of the function `App\answer`. |
| `App\Repository::find#return` | Return value of the method `App\Repository::find`. |
| `App\Repository::find#parameter(id)` | Value supplied to the formal parameter `$id`. |
| `src/Repository.php:25:5#argument(0)` | First argument of the call beginning at line 25, column 5. |
| `src/Repository.php:25:5#argument(id)` | Explicitly named argument `id:` of that call. |

```console
vendor/bin/deliver 'App\Repository::find#parameter(id)' --path=src
vendor/bin/deliver 'src/Repository.php:25:5#argument(0)' --path=src
```

Use the fully qualified function or class name. Parameter names omit `$`; argument positions start at `0`. A named argument selector requires the name to be present at the call site, as in `find(id: 42)`.

## Call paths

Prefix a selector with a caller path to derive its value in selected invocation contexts:

| Query | Selected context |
|-------|------------------|
| `bar -> foo#return` | Calls to `foo` directly from `bar`. |
| `bar -> ... -> foo#return` | Calls to `foo` with `bar` as an ancestor, including direct calls. |
| `entry -> bar -> foo#return` | Consecutive calls from `entry` to `bar` to `foo`. |
| `entry -> ... -> bar -> foo#return` | Calls directly from `bar`, below `entry` at any depth. |
| `bar -> foo#parameter(x)` | Values passed to `$x` by the selected calls. |
| `bar -> src/Run.php:25:5#argument(0)` | The first argument of the call at that position, whose direct caller is `bar`. |

`->` means one call edge. `...` means zero or more intermediate callers and must appear between named targets. Spaces around arrows are optional; formatting uses ` -> `. Use fully qualified function or method names, with `Class::method` for both instance and static methods. Names are matched without case sensitivity or a leading namespace backslash. Put the selector only on the final target.

```console
vendor/bin/deliver 'App\Controller::run -> ... -> App\Service::foo#return' --path=src
```

The leftmost caller does not have to be an application entry: its own callers remain unrestricted. Every named step must belong to the same ordered invocation chain. Separate calls inside one caller keep their arguments together; arguments from different call sites are not combined into new tuples. Helpers used inside the selected target remain ordinary dependencies and need not appear in the path.

For a source-position argument selector, the position denotes the final call, and its containing callable is its direct caller. Thus `entry -> bar -> src/Run.php:25:5#argument(0)` selects that call inside `bar` when `entry` calls `bar` directly. Source coordinates still follow the rules below.

Paths restrict caller discovery before deriving actual arguments. They do not execute the leftmost caller or enable predicate evaluation. With `BranchEvaluators::truth()` configured, the selected inputs can prune branches inside the target and exclude calls whose selecting conditions are false. Without it, structural branch candidates remain available.

A known path with no matching calls returns no candidates. An unknown named caller is an invalid query. Unresolved dispatch or a bounded recursive ancestry remains a partial dependency; it is never treated as an unrestricted invocation. Query identities and candidate evidence retain the path and matched call sites.

## Source locations

Lines and byte columns start at `1`. The position must identify the start of the call expression, such as the `$` in `$repository->find(42)`, rather than the argument itself.

Paths in queries are relative to the command's working directory, even when a configuration file is used. The file must be included by the [file selection](cli.md#file-selection); putting its path in a query does not select it automatically. Quote the whole query in the shell, with no additional quotes around its path.

Only the selectors listed above are supported; each can have a caller path prefix. A query may produce several candidates or retain unresolved inputs; see [reports](reports.md).

## Candidate dependencies

Deriver follows the values and operations needed to construct each candidate. A branch predicate does not become a required input merely because it selects a value at runtime. Both `return $flag ? 1 : 2` and `return false ? 1 : 2` yield the candidates `1` and `2`, without deriving the condition. Recognizing `false` as a standard constant is independent of using it to remove a branch.

For `$a = $x ? 1 : 2; $b = $x ? 10 : 20; return $a + $b;`, the return query yields four candidates: `11`, `12`, `21`, and `22`. The query does not need `$x` or its callers. These candidates do not assert that all four values can occur in a PHP execution. Each candidate keeps the evidence for its selected values and operations, with branch source information that does not require resolving the predicate.

The same rule applies to `if`, `switch`, `match`, `for`, `foreach`, `while`, and `do` statements. A fixed return inside a loop does not require deriving its condition, iterator, or iteration count. An iterator is needed when a candidate uses its keys or values; known array entries provide those alternatives. A loop that assigns a fixed value contributes that value alongside the entry value where the structure permits an entry path. An update that depends on the previous iteration remains a recurrence containing its initial value, update expression, and loop input, with reason `LOOP_DEPENDENCY`. Cycles between dependent storage locations may retain a partial update with a `CYCLE` reference. Deriver does not execute the loop to choose its final value.

Dependencies used as values are still derived. For example, the condition of `$x ?: 2` is also one of the result operands, and an assignment inside a condition must be followed when its assigned value is requested. Likewise, an argument used only by a predicate is not expanded solely to validate its declared type before a source call.

Expression evaluation and candidate filtering can be provided by an application evaluator or performed by the consumer after derivation. Register `BranchEvaluators::truth()` to request boolean candidates through the common evaluator dispatcher and prune excluded branches. Evaluators can also replace an entire conditional, function, or method without deriving its body. See [configurable evaluators](evaluators.md). Explicit evaluator plans retain their own built-in semantics unless a configured evaluator replaces them. Default source candidate discovery does not require that evaluation.
