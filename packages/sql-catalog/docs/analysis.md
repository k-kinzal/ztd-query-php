# Analysis

sql-catalog reads PHP source without running it. It finds every call that sends SQL to a database, then follows the SQL argument back through assignments, branches, loops, properties, constants and function calls until it reaches values the source fixes, runtime input, or something it cannot follow. Each value the argument can take becomes one catalog entry.

## Example

```php
<?php

namespace App;

use PDO;

enum Status: string
{
    case Active = 'active';
    case Banned = 'banned';
}

final class UserRepository
{
    private const TABLE = 'users';

    private string $order = 'name';

    public function __construct(private PDO $pdo)
    {
    }

    public function findByStatus(Status $status): array
    {
        $statement = $this->pdo->prepare('SELECT id FROM ' . self::TABLE . ' WHERE status = :status ORDER BY ' . $this->order);
        $statement->execute([':status' => $status->value]);

        return $statement->fetchAll();
    }
}
```

```
src/UserRepository.php:25  SELECT  93af735295ad
  in App\UserRepository::findByStatus via pdo.prepare
  SELECT id FROM users WHERE status = :status ORDER BY name
  resolved
  :status = 'active'|'banned'
```

The table comes from the class constant, the sort column from the property default, and the bound value from the enum the parameter is typed as.

## Statements

A call site produces one entry per SQL text it can send. Each arm of an `if`, `switch`, `match`, ternary or `try`, and each number of loop passes up to the limit, is a separate alternative:

```php
$order = $asc ? 'ASC' : 'DESC';
$pdo->query("SELECT id FROM posts ORDER BY created_at $order");
```

```
SELECT id FROM posts ORDER BY created_at ASC
SELECT id FROM posts ORDER BY created_at DESC
```

Values decided together stay together. A branch that sets both a table and a column produces two statements, not the four combinations of the values. When the analyzer has to combine parts that vary independently, the entry has `correlated: false`, and some of its combinations may never occur.

Conditions are never evaluated. Every branch is taken, including branches behind `isset()`, `false` or incompatible guards, so an entry means the SQL can be built, not that it runs.

A value the analyzer cannot determine is written as `{$}` in the SQL, and the rest of the statement stays resolved:

```
SELECT id FROM users WHERE name = '{$}'
```

## Resolution

Every entry states how far the analysis got:

| `resolution` | Meaning | `searchClosed` |
|--------------|---------|----------------|
| `resolved` | The text is fully determined. | yes, unless a limit cut the search short |
| `external-input` | A value comes from runtime input, so the text is not fixed. | yes, unless a limit cut the search short |
| `incomplete-model` | A dependency the analyzer does not model was reached, such as a function without a body. | no |
| `incomplete` | A cycle or an analysis limit stopped the search. | no |
| `not-analyzed` | The call was found, but nothing was read from it. | no |

Three fields answer different questions:

| Field | True means |
|-------|------------|
| `exact` | The SQL text of this entry has no `{$}`. |
| `searchClosed` | Every dependency at this call site was followed. When false, the call site may send statements that are not listed. |
| `correlated` | The alternatives kept their pairing. |

An exact entry is still not closed when another entry at the same call site has an unresolved dependency, or when a limit on loop passes or callers stopped the search. The `analysis-incomplete` or `call-not-analyzed` finding says which.

## Where unknown values come from

Each `{$}` and each unresolved bound value records its origin:

| Origin | Introduced by |
|--------|---------------|
| `external` | A superglobal, `getenv()`, `filter_input()` and similar input functions. |
| `parameter` | A parameter that nothing in the analyzed source calls with a value. |
| `property` | A property the class never settles, such as one assigned from outside. |
| `call` | A call without a body to follow, or a function modelled only by its type. |
| `budget` | A cycle, the depth limit, or the step limit. |
| `loop` | What a loop iterates over, when it is not written out. |
| `branch` | Alternatives merged to stay within the limits. |
| `unreached` | A call nothing was read from. |
| `unresolved` | Anything else. |

A cast to `int`, `float` or `bool` ends the trail: `(int) $_GET['id']` is `call`, not `external`, because the value can no longer inject SQL. A cast to `string` does not.

## Findings

| Rule | Severity | Reported when |
|------|----------|---------------|
| `external-input` | high | A value spliced into the statement text comes from external input. |
| `dynamic-sql` | medium | A value is spliced into the statement text instead of being bound. |
| `placeholder-count-mismatch` | medium | The statement binds a different number of values than it has placeholders. |
| `unresolved-sql` | low | The statement text could not be reconstructed at all. |
| `analysis-incomplete` | low | A cycle or an analysis limit stopped the search. |
| `call-not-analyzed` | low | A call that carries a statement was found but not examined. |

The severity of an entry is the highest severity of its findings, or `info` when it has none. `--severity` and `--fail-on` compare against it.

## Bound values

Values passed to the call that executes or binds a prepared statement, such as `PDOStatement::execute()`, `PDOStatement::bindValue()` or `mysqli_stmt::bind_param()`, are attached to the statement prepared at the same call site. Placeholders are `?`, `:name` and `$1`; a question mark inside a string literal or a comment is not a placeholder.

When a statement is reached along several paths, the values of all of them are merged. A method that callers invoke with arbitrary integers is reported as `int`, not as the literal one caller passes. `exhaustive: false` means only the type is known.

## What resolves

- String and number literals, interpolated strings, heredocs and nowdocs.
- Global constants, class constants, `::class` and enum cases, including `->value` and `->name`. A parameter typed as a backed enum resolves to the values of its cases.
- An element of an array written out in full, even when the key is not known. `self::TABLES[$kind]` with `$kind` from a request gives one statement per value in `TABLES`. An element the array does not hold, or an alternative that is not an array, stays a gap.
- Properties of `$this`: the declared default, a promoted constructor parameter, and what the class's own methods assign. A static property that nothing in the class assigns reads as its declared default.
- Parameters, through every caller in the analyzed source, following PHP's method dispatch.
- Calls into the analyzed source, including calls on interfaces and abstract classes, across their implementations.
- The functions that have a [function model](configuration.md#function-models).
- `$params[] = $value` and `$params[':id'] = $value`.
- A database handle held in a global, when a `@global` or `@var` tag documents it, or an [extension](extensions.md) names the global.

## What does not

These produce a gap with an origin, never a wrong value:

- SQL read from a file, a database or configuration at runtime.
- Values decided by hooks and callbacks, such as WordPress's `apply_filters()`.
- Globals assigned by a called function.
- Calls on a value whose class cannot be determined. These are reported with the sink `unmatched`.
- Code reached through `include`, `eval` or dynamic variables.
- Recursion and calls deeper than the depth limit.
- Framework query builders beyond what an extension models. See [Laravel](extensions/laravel.md).

## Limits

Each database call is analyzed within these limits. They can be changed with `EvaluationBudget` in the [PHP API](api.md#options).

| Limit | Default | When reached |
|-------|---------|--------------|
| `maxSteps` | 20000 | No further callers, callees or property writers are followed. |
| `maxDepth` | 4 | Callers and callees beyond this many frames are not followed. |
| `maxLoopPasses` | 2 | Loops are listed for zero, one and two passes, and the search is marked as cut short. |

Reaching a limit leaves a `budget` gap, `correlated: false` or `searchClosed: false`. It never removes a statement silently.

## What the catalog does not prove

- That an entry runs. Conditions are not evaluated, and reachability is not assessed.
- That the list is complete when `searchClosed` is false.
- That the SQL is valid for the database. The text is what the source builds.
- What code outside the analyzed paths does. Analyze every path that calls into the code you catalog.
