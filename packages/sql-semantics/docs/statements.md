# Statement models

`Semantics::analyze($sql, $tables)` parses SQL, structures its operations, and
resolves references in one pass through the semantic phase. It returns a concrete
`Semantic\Statement\Select`, `InsertRows` (INSERT VALUES), `InsertSelect`, or
`Delete` (single-table deletion).
The result does not retain parser nodes, tokens, grammar-production values, or the
original SQL. There is no generic `command` wrapper or syntax fallback.

## Reading and changing a SELECT

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Semantic\Name;
use SqlSemantics\Semantic\Projection\Field;

$original = (new Semantics(Dialect::Sqlite))->analyze('SELECT foo FROM bar');
$field = $original->field('foo');
$field->type->name; // 'unknown': no declaration was supplied
$field->expression->binding->relations[0] === $original->tables[0]; // true

$additional = new Field($original->scope->column(new Name('label')));
$updated = $original->withFields($original->fields()->addField($additional));

$original->toString(); // 'SELECT foo FROM bar'
$updated->toString();  // 'SELECT foo, label FROM bar'
```

The collection and the statement are immutable. An update returns a new value.
Fields resolve through the owning scope; with a catalog, the additional reference
points to that occurrence's column declaration or records a missing column.
Constructors and updates use `assert()` to express their invariants. Enable PHP
assertions when checking those contracts.

`fields()->items` preserves projection order, stars, and duplicate names.
`fields()->outputs()` expands stars when their declarations are available.
`field($name)` requires one unambiguous result name; it does not silently choose
one of several matching positions. A star without declarations has an undetermined
width, so expanding it requires a catalog.

Sort keys referencing output aliases or positions keep references to the actual
output fields. Replacing a projection cannot leave an ordering attached to a
removed field or a different position.

## Changing an INSERT source form

```php
use SqlSemantics\Semantic\Statement\InsertRows;
use SqlSemantics\Semantic\Statement\InsertSelect;
use SqlSemantics\Semantic\Statement\Select;

$semantics = new Semantics(Dialect::Sqlite);
$values = $semantics->analyze('INSERT INTO bar (foo) VALUES (1), (2)');
$source = $semantics->analyze('SELECT foo FROM other');
assert($values instanceof InsertRows && $source instanceof Select);

$fromQuery = new InsertSelect($values->target, $source);
$fromQuery->toString(); // 'INSERT INTO bar (foo) SELECT foo FROM other'
$values->toString();   // 'INSERT INTO bar (foo) VALUES (1), (2)'
```

The destination contains ordered column references. Explicit row widths must
agree, and a known destination width must agree with the source. An absent catalog
can prevent determining the width of an implicit destination or a source star;
that missing information is preserved rather than invented.

## Current implementation boundary

The semantic implementation is incomplete. Removing generated grammar classes
removes the previous claim that accepting syntax establishes semantic coverage.
This is a breaking replacement, not a compatibility layer.

Currently implemented operations cover single-scope SELECTs over named tables,
aliases and supported qualifications, ordinary joins with ON, projection and stars,
DISTINCT, WHERE, ordering, and basic LIMIT/OFFSET; column references, integer and
ordinary string literals without backslash escapes, parameters, the modeled arithmetic/comparison/boolean
operators, NULL tests, COALESCE and NULLIF; basic INSERT VALUES/INSERT SELECT;
and single-table DELETE with a predicate. Basic SELECT, INSERT and DELETE forms
are checked against the MySQL 5.6 and 5.7 grammar shapes as well.

CTEs, nested queries, set operations, grouping, windows, additional expression
forms, INSERT conflict handling/RETURNING, UPDATE, multi-table DELETE, DDL, transactions,
stored programs, and administrative statements still require semantic models and
lowering rules. MySQL 5.6 ORDER BY and other legacy grammar variants still require additional
lowering rules.
They fail explicitly. Broad grammar fuzzing is retained and reports those gaps;
passing focused tests does not imply complete SQL coverage.

SQL reconstruction preserves the implemented meaning, without promising the
original formatting or comments. Formatter-normalized input comparison and model
reanalysis are separate verification checks. See the [semantic model
contract](semantic-model.md) for why neither alone proves SQL meaning.
