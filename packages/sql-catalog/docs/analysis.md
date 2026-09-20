# How the analysis works

The analyzer answers one question: what SQL can this source send to a database?

It is not a PHP interpreter that happens to notice queries along the way. It
starts at the calls that receive SQL and works back through the dependencies
that decide what those calls are given.

## Starting at the call, not at the program

```
find the calls that receive SQL
    ↓
work back through the definitions, branches and callers that decide their argument
    ↓
recover the statements each call can carry
    ↓
report the recovered statements, and what is left open, and why
```

Two consequences follow from taking the call as the root.

**Finding a call does not depend on resolving it.** `$enabled && $pdo->query('SELECT 1')`
issues a query whatever `$enabled` is. The condition decides whether the query
runs, not what it says, and the two are kept apart: `SinkFinder` collects the
calls written the way a database call is written, independently of anything the
evaluation manages to work out. A call the walk never reached is reported with
its statement left open, never dropped, and so is a call it reached without
being able to tell what the call was made on. A call it *could* tell apart — one
made on a class that is simply not a database handle — is not reported, because
that is an answer rather than a gap.

**Bodies that cannot reach such a call are not walked.** `SinkFinder::reaching()`
marks the bodies that write a database call, then spreads that backwards over
the call graph until it settles. Everything else is skipped. On WordPress this
is the difference between minutes and seconds, and it is the same idea as taking
the call for the root: a function that cannot reach one has nothing to say about
SQL.

## Keeping the values of one path together

```php
if ($admin) {
    $table = 'admins';
    $column = 'admin_id';
} else {
    $table = 'users';
    $column = 'user_id';
}

$pdo->query("SELECT $column FROM $table");
```

Two statements, not four. Treating `$table` and `$column` as independent sets of
values and pairing them would invent `SELECT user_id FROM admins`, which the code
cannot produce.

A branch therefore **forks** the analysis rather than merging what its arms leave
behind. `PathSet` holds the paths reaching a point, each with its own bindings,
and the call is read once per path. The values one arm decided stay together
because they were never taken apart.

Paths are bounded by `PathSet::MAX_PATHS`. Past the bound they are joined into
one, which loses the correspondence — and the statements produced from a joined
set are reported with `correlated: false` rather than passed off as certainty.

The same flag covers the case the fork cannot reach:

```php
$pdo->query('SELECT ' . pick($a) . ' FROM ' . pick($b));
```

Two calls returning two alternatives each are paired into four statements. The
analyzer has no way to know which pairings the program reaches, so it says so.

## What "determined" means

A statement is determined when the search that produced it closed: every
dependency that could change the SQL was followed, and each was resolved.

Finding one concrete answer is not that. When a helper is reached from two
callers and only one of them resolves, **both readings are kept**:

```php
function run(PDO $pdo, string $sql): void { $pdo->query($sql); }

function a(PDO $pdo): void { run($pdo, 'SELECT 1'); }
function b(PDO $pdo, string $outside): void { run($pdo, $outside); }
```

```
SELECT 1
{$}
```

Deleting the second because the first exists would claim the call is pinned down
when it is not.

## Saying why something is open

`resolution` distinguishes four ways of not being determined:

| Resolution | Meaning | Search closed |
|------------|---------|---------------|
| `resolved` | The text is fully determined. | yes |
| `external-input` | The values were followed to runtime input. The trail ended; the string simply is not fixed. | yes |
| `incomplete-model` | A dependency the analyzer does not model was reached. | no |
| `incomplete` | A cycle or an analysis budget stopped the search. | no |
| `not-analyzed` | The call was found but never examined, so nothing was read from it. | no |

`searchClosed` is the part that matters for trusting a call site: when it is
false, the statements listed may not be all of them, and the `analysis-incomplete`
or `call-not-analyzed` finding says so. Stopping early is never reported as
having found nothing.

Injection risk is judged separately, from where the values came from, not from
whether the text resolved. A statement can be fully determined and still splice
in a request parameter; a statement can be open for reasons that have nothing to
do with input.

## The values themselves

Every expression evaluates to a `Domain`: a bounded set of alternatives.
Alternatives are resolved scalars, strings with gaps, arrays, objects, or values
known only by type. Concatenation pairs the sides; union merges them. Past
`Domain::MAX_TERMS` the alternatives are generalized into one shape that covers
all of them.

A gap carries where the value came from:

| Origin | Introduced by |
|--------|---------------|
| `external` | A superglobal, `getenv()`, `filter_input()` and the like |
| `parameter` | A parameter of the body being walked |
| `property` | A property read with no settled default |
| `call` | A call with no body to follow |
| `budget` | A cycle, the depth limit, or an exhausted budget |
| `loop` | A value a loop kept changing |
| `branch` | Alternatives merged away to stay within the bound |
| `unresolved` | Anything else |

A cast to `int`, `float` or `bool` deliberately ends the trail: `(int) $_GET['id']`
is `call`, not `external`, because the cast neutralises the value as a source of
injected SQL. A cast to `string` does not.

## What resolves

- String and number literals, interpolated strings and heredocs.
- Global constants, class constants, `::class`, and enum cases.
- `->value` and `->name` on an enum. A value typed as a backed enum resolves to
  the union of that enum's cases, which turns a parameter typed `Status` into the
  handful of strings a column can hold.
- Properties declared with a default that nothing in the class assigns again.
- `sprintf`, `vsprintf`, `implode`, `str_repeat`, `str_replace`, the trim and
  case functions, and the casts.
- Calls into the analyzed source, up to `EvaluationBudget::$maxDepth` frames.
  Readings are remembered by callee and argument (`CallResults`), so a body
  reached from many callers with the same values is walked once.
- Calls on an abstract class or interface, resolved across the implementations
  the source declares.
- Every branch of `if`, `switch`, `match` and the ternaries, kept apart.
- Loops: the body is walked twice per path and widened, so `$sql .= ' AND …'`
  becomes the statement with no iterations plus a shape covering every number of
  them.
- `$params[] = $value` and `$params[':id'] = $value`.
- A call that interpolates a statement and hands it back, such as
  `wpdb::prepare()`, declared by an extension as a composing call.
- A handle reached through a global. `global $wpdb;` says nothing about what the
  name holds, and in WordPress nearly every statement is written after that line.
  An `@global` or `@var` tag documenting the declaration is read first; failing
  that, an extension says what the name stands for. Without this the calls are
  not recognised at all, which is the difference between 100 statements and 820
  on WordPress.

## What does not

Each of these produces a gap with a stated reason, never a wrong answer:

- SQL assembled at runtime from data the source does not contain: a query
  builder, an ORM's generated SQL, a statement read from a file or a database.
- A property assigned outside its declaration.
- The inside of a class an extension already models. An extension that names a
  class's calls is the model of that class, so `$wpdb->insert(...)` is not walked
  into from each of its callers: doing so re-derives the statements `wpdb` issues
  at the sites they are already read from, once per caller. The class's own
  source is still read like any other, and a call it makes on itself is followed
  normally.
- Dispatch through a value whose class cannot be named.
- Recursion and calls past the depth budget.
- Correspondence between values that separate calls decide from a shared
  condition — reported as `correlated: false`.

## Binding values to placeholders

A `prepare()` returns a handle identifying the call site it came from. The
`execute()` or `bindValue()` that follows finds the statements filed under that
handle. The handle travels through variables and chained calls alike.

Placeholders are read with a dialect-neutral lexer that knows string bodies,
comments, `?`, `:name`, `$1` and Postgres casts, so a question mark inside a
quoted string is not mistaken for a parameter.

When the same call is read more than once, the bound values take the **union** of
what each reading found. A public method callable with any integer is reported as
`int`, not as whichever literal one caller happened to pass.

## Bounds

`EvaluationBudget` bounds the work: expressions per body, frames of call
following, and passes over a loop body. `PathSet::MAX_PATHS` bounds the paths and
`Domain::MAX_TERMS` the alternatives. Every one of these degrades the result into
a stated gap rather than into silence.

The budget is refilled for each body. Spending one budget across a whole file
would let its first bodies use it up and report the rest of the file as
unreached, which says more about the order the file is written in than about the
program.

## Known limits

These are measured, not suspected. Each shows up in the output rather than
silently changing an answer.

**Reachability is matched by name.** `SinkFinder::reaching()` decides which
bodies to walk by spreading backwards over calls matched on their written name,
to a fixpoint. On a codebase that uses names like `query` or `prepare` for things
other than databases — WordPress being the extreme case — this keeps far more
bodies than it needs to: about 63% of them. The result is wasted time, never a
missed statement. Bounding the spread to `EvaluationBudget::$maxDepth` hops would
match what the walk can actually use a caller for.

**Correspondence is not established across separate calls.** Two calls that
decide their values from the same condition are paired into every combination,
because nothing relates one call's branch to the other's. Those statements are
marked `correlated: false`, so the report says the combinations may be wider than
the code allows rather than presenting them as fact.

**Large trees take minutes.** Parsing and indexing dominate for a few thousand
files, and the walk scales with how many bodies reachability keeps. The 93
WordPress files that name `$wpdb` take about four minutes and yield 820
statements. Pointing the command at the directories that talk to a database,
rather than at a whole repository, is the practical answer until reachability is
tightened.

**A receiver that cannot be named leaves the call unread.** A call written the
way a database call is written, on something the walk could not type, is
reported with a `sink` of `unmatched` and a `not-analyzed` resolution rather
than a statement. On WordPress this is a few dozen calls out of several hundred,
and each one says so in the catalog instead of being absent from it.
