# How the analysis works

The analyzer answers one question: what SQL can this source send to a database?
It answers it without running anything, which means it has to reconstruct each
statement from the pieces the code assembles it out of.

## The pipeline

```
sources ──▶ parse ──▶ index ──▶ walk each body ──▶ record statements ──▶ catalog
```

1. **Parse.** Every file is read into a statement tree with names resolved to
   their fully qualified form (`SqlCatalog\Php\SourceParser`). A file that does
   not parse is reported as a problem and the run continues.
2. **Index.** Every class, enum, function, constant, property and method in the
   whole source tree is collected first (`ProgramIndexBuilder`). This is what
   lets a statement built from a constant in one file resolve while walking
   another.
3. **Walk.** Each function body is walked on its own, with its parameters
   standing for whatever a caller may pass (`Interpreter`, `BodyWalker`).
4. **Record.** A call that matches an extension's database call is recorded with
   the statement text as far as it resolved (`CallEvaluator`, `StatementRecorder`).
5. **Report.** Records become catalog entries: statement kind, tables,
   placeholders with what is bound to them, and findings (`EntryFactory`).

## What a value is

Every expression evaluates to a `Domain`: a bounded set of alternatives the
expression can take. An alternative is a resolved scalar, a string with gaps in
it, an array, an object, or a value known only by its static type.

Two operations matter:

- **Concatenation** takes the cartesian product. `'ORDER BY ' . ($asc ? 'a' : 'b')`
  is two statements, not one statement with a gap.
- **Union** merges alternatives at a branch join.

A domain holds at most `Domain::MAX_TERMS` alternatives. Past that it is
generalized into one shape that covers all of them, with the part the
alternatives disagree on replaced by a gap. The approximation only ever widens.

## What a gap is

A gap is a value the analyzer could not pin down, and it carries where it came
from:

| Origin | Introduced by |
|--------|---------------|
| `external` | A superglobal, `getenv()`, `filter_input()` and the like |
| `parameter` | A parameter of the body being walked |
| `property` | A property read with no settled default |
| `call` | A call the analyzer did not follow |
| `loop` | A value a loop kept changing |
| `branch` | Alternatives merged away to stay within the bound |
| `unresolved` | Anything else |

The origin is what separates a query built from request input from one that is
merely dynamic. A cast to `int`, `float` or `bool` deliberately ends the trail:
`(int) $_GET['id']` is reported as `call`, not `external`, because the cast
neutralises the value as a source of injected SQL. A cast to `string` does not.

## What resolves

- String and number literals, interpolated strings and heredocs.
- Global constants, class constants, `::class`, and enum cases.
- `->value` and `->name` on an enum. A value typed as a backed enum resolves to
  the union of that enum's cases, which is what turns a parameter typed `Status`
  into the handful of strings a column can hold.
- Properties declared with a default that nothing in the class assigns to again.
- `sprintf`, `vsprintf`, `implode`, `str_repeat`, `str_replace`, the trim and
  case functions, and the casts.
- Calls into the analyzed source, followed up to `EvaluationBudget::$maxDepth`
  frames deep. A call on an abstract class or an interface is resolved across the
  implementations the source declares.
- `if`, `switch`, `match` and the ternaries: every branch is kept, not chosen
  between.
- Loops: the body is walked twice and whatever kept changing is widened into one
  shape. The familiar `$sql .= ' AND …'` becomes two alternatives — the statement
  with no iterations, and the shape covering every number of them.
- `$params[] = $value` and `$params[':id'] = $value`, so a bound value survives
  being collected into an array before it is passed.

## What does not

- Anything assembled at runtime from data the source does not contain: a query
  builder, an ORM's generated SQL, a statement read from a file or a database.
- A property assigned outside its declaration.
- Virtual dispatch through a value whose class the analyzer cannot name.
- Recursion, and calls past the depth budget.

Each of these produces a gap rather than a wrong answer.

## Binding values to placeholders

A `prepare()` returns a handle that identifies the call site it came from. The
`execute()` or `bindValue()` that follows finds the statements filed under that
handle and attaches what it binds. The handle travels through variables and
chained calls, so both of these are read the same way:

```php
$statement = $pdo->prepare($sql);
$statement->execute($values);

$pdo->prepare($sql)->execute($values);
```

Placeholders are read out of the statement text with a dialect-neutral lexer that
knows string bodies, comments, `?`, `:name`, `$1` and Postgres casts, so a
question mark inside a quoted string is not mistaken for a parameter.

## Reading the same call twice

A method is walked on its own, with its parameters open, and again through every
caller the analyzer follows. Both readings are kept and merged: the bound values
take the union of what each reading found, so a public method callable with any
integer is reported as `int` rather than as whichever literal a caller happened
to pass. Where a resolved reading covers an unresolved one at the same call site,
the unresolved one is dropped — it is the same statement seen with less
information, and reporting both would overstate what the code does.

## Bounds

`EvaluationBudget` bounds the work: how many expressions one file may cost, how
many frames deep calls are followed, and how many times a loop body is re-walked.
An exhausted budget degrades the result into gaps rather than hanging the run.
