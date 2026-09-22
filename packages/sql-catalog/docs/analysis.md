# How the analysis works

The analyzer answers one question: what SQL can this source send to a database?

It is not a PHP interpreter that happens to notice queries along the way. It
starts at each call that receives SQL, asks what that call's argument depends
on, and works back from there. The statement written at the call is the root;
everything else is only read because the statement needs it.

## The procedure

```
for every call written the way a database call is written
    work out what the call is made on            → is it a database call at all?
    walk back from the call to the start of its body,
        keeping only the assignments the SQL argument depends on
    bind what the walk still needs at the start of the body
        a parameter        → what each caller passes, asked the same way at the caller
        $this->property    → what the class can leave the property holding
        a file-scope name  → what an extension says it holds
    run the kept assignments forward, once per way in, and read the argument
    one statement per value the argument can have
```

The walk goes backwards because that is the direction the question points in.
Starting from the program's entry and running everything forward reads every
body, most of which have nothing to do with SQL, and a forward run that stops
halfway leaves the statement with nothing at all. Starting from the call reads
only what the statement depends on, and a walk that stops halfway still has the
statement it started from: what it did not get to is left as a gap, and the rest
of the text stands.

## Finding the call

`SinkFinder::findAll()` collects every call whose name is one an enabled
extension recognises — `query`, `prepare`, `get_results` and so on — in every
file, wherever it is written. Finding a call does not depend on resolving
anything: `$enabled && $pdo->query('SELECT 1')` issues a query whatever
`$enabled` is.

For a method call, what the call is made on is worked out first, the same way
the SQL argument is (below). That decides whether it is a database call:

- a receiver whose class the extensions name is a database call, and its SQL
  argument is worked out next;
- a receiver whose class is known and is not a database class is not a database
  call, and nothing is reported — that is an answer, not a gap;
- a receiver whose class could not be worked out might be one, and is reported
  with a `sink` of `unmatched` and a `not-analyzed` resolution.

The budget is refilled for every call, so a large file does not starve the calls
written at its end.

## Walking back

`BackwardSlicer` starts from the names the SQL argument reads and goes back
statement by statement:

```php
function find(PDO $pdo, string $table, bool $desc) {
    $order = $desc ? 'DESC' : 'ASC';     // kept: $sql reads $order
    $log = new Logger();                 // skipped: nothing the SQL reads
    $sql = "SELECT * FROM $table ORDER BY id $order";   // kept
    return $pdo->query($sql);
}
```

- An assignment to a name the path needs is kept as a step, and the names its
  right-hand side reads are needed instead. `$sql .= '…'` and `$a[] = …` keep the
  name needed, so the walk goes on to find what they added to.
- A statement that assigns nothing the path needs is stepped over whole.
- A branch that assigns something the path needs becomes one step holding each
  arm as an alternative run. An arm that cannot fall through — it returns,
  throws, breaks or continues — is left out, since it cannot be the one taken on
  the way to the call.
- A loop becomes one run per number of passes: none, one, and up to
  `EvaluationBudget::$maxLoopPasses`. A `foreach` over an array written out in
  full runs exactly as many times as it has elements. A loop that could still go
  round again at the limit marks the path as cut short.
- A closure's body is walked like any other; at its start, what it captured is
  looked for where the closure is written, and its parameters are left open.

Where the walk reaches the start of the body, what is left is a set of paths,
each with the steps that lead from there to the call and the names it still
needs. In the example above that is `$table` and `$desc`, both parameters.

## Binding what is still needed

`EntryBinder` gives each name a path still needs at the start of its body its
possible values, and each combination it finds is one *way in*.

**Parameters** are looked for at every call of the body (`Callers`,
`CallerIndex`). At each call the argument passed is asked about exactly the way
the SQL argument was asked about at the database call: walk back from the call,
bind what is still needed at *that* body's start, run forward. So the question
climbs from the database call to the callers that decide the statement, as far as
`EvaluationBudget::$maxDepth` frames. A body that nothing calls leaves the
parameter open, typed by its declaration: that is the statement's input, not a
failure to find it.

Which calls reach a method follows PHP's own dispatch:

- a call on an instance is looked up from the instance's class upwards, so it
  reaches the method when the instance can be of the method's class, of a
  subclass that inherits it without overriding it, or of a parent class whose
  instance may really be the subclass that declares it;
- `parent::m()` looks from the parent of the class it is written in, and
  `self::m()` and `Name::m()` from that class — none of them reach an override
  in a subclass; `static::m()` does;
- a constructor is reached by `new` of its class, by `new` of a subclass that
  does not declare its own, and by `parent::__construct()` and the like.

A call on something whose class could not be worked out is not taken as a
caller: `$datetime->format()` is not a call of a `format()` method in the
analyzed source just because no other class there declares one — `DateTime`
does, and so may classes in files that were not analyzed. The ways in found are
marked as cut short instead, so the statement says there may be a caller it
could not account for.

The climb does not leave a class an extension models. `wpdb::query()` reading
its `$query` parameter is not traced back to every caller of `wpdb::query()`:
those callers are themselves database calls, and their statements are read where
they are written.

**Properties of `$this`** are bound by `PropertyWrites` to what the class can
leave them holding: what every method that assigns the property leaves at its
returns, the promoted constructor parameter it is filled from, and its declared
default. A property no method writes keeps its default.

**Names at file scope** — `$wpdb` in a WordPress template — are bound by what
an extension says the name stands for, or by an `@global`/`@var` tag on the
declaration.

**Anything else** is left open, with the reason it is open: a parameter nothing
calls with a value, a callee with no body, the budget running out.

## Running forward

`SliceExecutor` runs each way in along its path: only the kept assignments, in
the order they run. When an assignment leaves a variable with several possible
values, the run splits there, one run per value, so every later read of the
variable sees the same one:

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

Two statements, not four. The arms are alternative runs, and `$table` and
`$column` are read within one run, so the values one arm decided are never taken
apart. The same holds for a value used twice:

```php
$dir = $asc ? 'ASC' : 'DESC';
$pdo->query("SELECT * FROM t ORDER BY a $dir, b $dir");
```

gives `… a ASC, b ASC` and `… a DESC, b DESC`, never the mixed pairs.

How many runs are kept apart is what the budget can pay for along the path: a
short path keeps up to `SliceExecutor::MAX_RUNS`, a body of several hundred
assignments under a hundred conditionals keeps a few. Beyond that the rest are
joined value by value. A joined run still holds every value; what it gives up is
knowing which go together, and the statements read from it say so with
`correlated: false`.

## Calls along the way

A call into the analyzed source is read by `CalleeReturns` the same way: walk
back from each `return` of the callee to its start, bind its parameters to the
arguments at hand, run forward, and take what the `return` gives. Readings are
remembered per callee and arguments (`CallResults`), so a helper called from
many places with the same values is read once. A call on an interface or an
abstract class is read across the implementations the source declares.
Recursion and calls past `EvaluationBudget::$maxDepth` stop with a `budget` gap.

A call an extension declares as composing, such as `wpdb::prepare()`, and a
builtin such as `sprintf()` are not walked into: their model says what text they
hand back.

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

`resolution` says how far the analyzer got with a statement:

| Resolution | Meaning |
|------------|---------|
| `resolved` | The text is fully determined. |
| `external-input` | The values were followed to runtime input. The trail ended; the string simply is not fixed. |
| `incomplete-model` | A dependency the analyzer does not model was reached. |
| `incomplete` | A cycle or the budget stopped the search. |
| `not-analyzed` | The call was found but nothing was read from it. |

`searchClosed` is the part that matters for trusting a call site. It is true
when the resolution is `resolved` or `external-input` **and** no bound cut the
search short: not the number of loop passes, not the number of callers asked,
not the number of ways in kept. When it is false the statements listed may not
be all of them, and the `analysis-incomplete` or `call-not-analyzed` finding
says why. Stopping early is never reported as having found nothing.

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
| `parameter` | A parameter nothing in the analyzed source calls with a value |
| `property` | A property the class never settles, such as one assigned from outside |
| `call` | A call with no body to follow |
| `budget` | A cycle, the depth limit, or the budget running out |
| `loop` | What a loop iterates over, when it is not written out |
| `branch` | Alternatives merged away to stay within the bound |
| `unreached` | A call nothing was read from |
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
- Properties: a declared default, a promoted constructor parameter, and what
  the class's own methods assign.
- `sprintf`, `vsprintf`, `implode`, `str_repeat`, `str_replace`, the trim and
  case functions, and the casts.
- Parameters, through the callers the analyzed source contains, up to
  `EvaluationBudget::$maxDepth` frames.
- Calls into the analyzed source, including calls on an abstract class or an
  interface.
- Every arm of `if`, `switch`, `try`, `match` and the ternaries, kept apart.
- Loops, as one statement per number of passes up to the limit.
- `$params[] = $value` and `$params[':id'] = $value`.
- A call that interpolates a statement and hands it back, such as
  `wpdb::prepare()`, declared by an extension as a composing call.
- A handle reached through a global. `global $wpdb;` says nothing about what the
  name holds; an `@global` or `@var` tag documenting the declaration is read
  first, and failing that an extension says what the name stands for.

## What does not

Each of these produces a gap with a stated reason, never a wrong answer:

- SQL assembled at runtime from data the source does not contain: a query
  builder, an ORM's generated SQL, a statement read from a file or a database.
- A value a hook or a callback decides, such as WordPress's `apply_filters()`.
- A global a called function assigns. `unset($wpdb); require_wp_db();` leaves
  `$wpdb` unknown to the walk, because the call is not read for what it does to
  globals.
- Dispatch through a value whose class cannot be named.
- Recursion and calls past the depth budget.

## Binding values to placeholders

A `prepare()` returns a handle identifying the call site it came from. The
`execute()` or `bindValue()` that follows is read like any other call — what its
receiver and arguments can be, worked out from the call backwards — and its
values are attached to the statements filed under the handle.

Placeholders are read with a dialect-neutral lexer that knows string bodies,
comments, `?`, `:name`, `$1` and Postgres casts, so a question mark inside a
quoted string is not mistaken for a parameter.

When the same statement is bound more than once, the values take the **union** of
what each binding found. A public method callable with any integer is reported as
`int`, not as whichever literal one caller happened to pass.

## Bounds

`EvaluationBudget` bounds the work spent on one database call:

| Bound | Default | What happens past it |
|-------|---------|----------------------|
| `maxSteps` | 20000 | The search goes nowhere new: no further callers, callees or property writers are asked. |
| `maxSteps × READING_ALLOWANCE` | 80000 | The run that reads the statement stops too. Every name a step it did not get to would have written is left open as `budget`, so stopping never reports a value a later assignment would have replaced. |
| `maxDepth` | 4 | Callers and callees beyond this many frames are not followed. |
| `maxLoopPasses` | 2 | Longer runs of a loop are not listed, and the statement is marked as cut short. |

Alongside it, `Deriver::MAX_CALLERS` bounds the callers asked per body,
`Deriver::MAX_SOLUTIONS` the ways in kept per point, `BackwardSlicer::MAX_PATHS`
the paths kept apart through a body, `SliceExecutor::MAX_RUNS` the runs, and
`Domain::MAX_TERMS` the alternatives per value. Each of them degrades the result
into a stated gap, a `correlated: false`, or a `searchClosed: false` — never into
silence.

## Measured on WordPress

The 93 WordPress files that name `$wpdb` take about 20 seconds and yield 774
statements. 27 of them are a gap from end to end, and each has a reason in the
code rather than in the analyzer:

| Count | Why the whole statement is open |
|-------|---------------------------------|
| 10 | The statement is a parameter of an entry point nothing in WordPress calls with a value: `wpdb`'s own `query()` family, and the `$create_ddl` and `$drop_ddl` of the `maybe_create_table()` family of install helpers. |
| 7 | The statement is what an `apply_filters()` hook returns. |
| 5 | The call is named like a database call but made on something else, or on `$wpdb` after `require_wp_db()` reassigns it. |
| 4 | The budget ran out in `WP_Query::get_posts()` and `get_bookmarks()`, which assemble their statement over several hundred lines of conditionals. |
| 1 | `dbDelta()` runs each statement of an array it builds from what its caller passes. |

Most of the remaining gaps sit inside otherwise-resolved statements and are
`$wpdb->posts`-style table names, which WordPress fixes at runtime from its
configured prefix.
