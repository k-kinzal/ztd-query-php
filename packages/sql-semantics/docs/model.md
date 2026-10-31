# Model

`Semantics::analyze()` declares the `SqlSemantics\Statement\AnalyzedStatement` interface and returns the concrete SQL statement. MySQL queries return `QueryRoot`, whose `query` is the query body; a MySQL INSERT of written rows returns `Dml\Insert\InsertRows`. PostgreSQL and SQLite return their own concrete statement classes. A dialect accepting several statements in one input returns `Script`.

## Analyzed statements

| Member | Content |
|--------|---------|
| Concrete constructor operands | The SQL form, its expressions, relation uses, clauses and written alternatives. |
| `toString()` | SQL reconstructed from those values. See [Rendering](rendering.md). |
| `profile()` | Grammar release, lexical settings and parameter style. |
| `declarations()` | Tables or views introduced by a definition. |
| `output()`, `shape()`, `fields()`, `field()`, `lookupField()` | Result columns, including incomplete shapes and ambiguous names. |
| `inputRelation()`, `singleNamedInput()` | The input relation of a selection. |
| `diagnostics()`, `warnings()`, `notices()` | Statement conditions and warning ownership. |
| `source()` | An optional byte offset and length belonging to this occurrence. |

The result retains neither its `AnalysisContext` nor an index of every occurrence. To construct a statement from typed operands, call `Semantics::fromStatement($context, $statement)`. Construction makes fresh SQL occurrences, resolves them against that context and returns the concrete result. No SQL renderer or parser participates. Declaration objects retain their identity. Construction supplies each private binding slot once; the public SQL operands are readonly. Cloning, dynamic properties and serialization are refused.

Source ranges refer to the input passed to the parser. They contain offsets and lengths, not input bytes; keep the original input separately if a diagnostic must display it. A nested expression has its own `source()`, independently of its enclosing statement. Directly constructed values may have no location. A `SourceMap` can supply locations and spelling notices during construction, but is not retained by the result. Copying an occurrence preserves its own range unless an explicit replacement changes it.

## Local semantic information

Each concrete occurrence owns the information for its SQL role. `scalarInformation()` returns an expression's type, nullability and name resolution. `relationInformation()` returns the shape and table binding of a relation or table use. `queryInformation()` returns a query body's projection, aggregate ownership and conditions. These values need no enclosing statement index. A fresh occurrence must be resolved before those accessors are used.

The operands retain decoded identifiers, exact literal values, enum alternatives and ordered clauses. They contain no parser nodes. A result-column spelling layout is part of that column's name semantics; it does not replace the expression structure. See [Rendering](rendering.md#spelled-regions).

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\NullOnly;

$semantics = new Semantics(Dialect::Sqlite);
$users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$query = $semantics->analyze('SELECT name FROM users WHERE id > 10', [$users]);

$where = $query->where;
$where instanceof Binary; // => true
$where->operator; // => BinaryOperator::Greater
$where->right->digits; // => '10'
$where->left->scalarInformation()->resolution instanceof ResolvedColumn; // => true

$sum = $semantics->analyze('SELECT 1 + NULL');
$sum->field(0)->expression->right->scalarInformation()->type instanceof NullOnly; // => true
```

`QueryFact::$aggregates` lists aggregate occurrences assigned to that query block by platforms that resolve ownership (currently MySQL). An occurrence can be written inside a nested query while aggregating rows of an enclosing query. Window functions are not in this list.

The root's `output()` is its result `QueryFact`, or null when it produces no row set. Its conditions are available through `diagnostics()` and `warnings()`. Each `Notice` pairs a warning with its optional semantic subject and enclosing stored instruction. These references remain usable without source SQL. MySQL's `statementInformation()` additionally exposes client character-set and connection-collation settings, and offers warning-owner lookups.

## Fields and lookups

When the statement returns rows and every output position is known, `fields()` answers a `Fields` collection; otherwise it answers null. The collection keeps order and duplicate names, and offers counting, iteration, `at($position)` and `lookup($name)`. It has no method that adds, removes, reorders or replaces a field.

A `Field` has:

| Property or method | Content |
|--------------------|---------|
| `position` | The zero-based output position. |
| `name` | The output `Name`, or null when the position has no name or its name depends on missing inputs (see `slot->unnamed`). |
| `type`, `nullability` | The type fact and the NULL fact of the position. |
| `expression` | The expression that computes the field; null when no single expression does, as for a set operation. |
| `resolution` | The resolution, when the expression is a direct name use. |
| `slot` | The `OutputSlot` of the position. |
| `column()` | The declared `Column` the field directly returns, when it is one. |

`AnalyzedStatement::field($key)` takes a position or a name. By name, it answers the field only when exactly one field has that name, and throws `InvalidConstruction` otherwise; it never picks the first of several. `AnalyzedStatement::lookupField($name)` answers one of:

| Result | Meaning |
|--------|---------|
| `UniqueField` | Exactly one field has the name: `->field`. |
| `AbsentField` | The shape is complete and no field has the name. |
| `AmbiguousFields` | Several fields have the name: `->fields`, in output order. |
| `DependentField` | The shape is open, or the name of a field depends on missing inputs, so further fields may have the name: `->candidates` (the known fields with the name) and `->missing`. |

A field whose name is undecided could have any name, so while one field of the result is in that state, every lookup by name is a `DependentField`, and `field($name)` throws:

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Shape\DependentField;

$semantics = new Semantics(Dialect::Sqlite);
$query = $semantics->analyze('SELECT "a", 1 AS b FROM t');

$query->field(0)->name; // => null
$query->field(0)->slot->unnamed[0]->describe(); // => 'the declaration of relation t'
$query->lookupField('b') instanceof DependentField; // => true
$query->lookupField('b')->candidates[0] === $query->field(1); // => true
$query->field('b'); // throws InvalidConstruction
```

In SQLite, a double-quoted word is a column when a column has that name and a string otherwise, and the two are named differently; here `t` is not declared, so the name is undecided. MySQL leaves a name undecided where it depends on session state or a character set conversion, as described in its package.

Names are compared as the database compares output names: without regard to ASCII case for MySQL and SQLite, exactly for PostgreSQL (whose unquoted names are already folded to lower case). A field and its expression are reading results. They are not inputs for another query, and an expression of one operation can be resolved differently at another position.

## Row shapes, output slots and open stars

A `RowShape` lists the `OutputSlot`s of a query or a relation occurrence in order. `shape()` answers the shape of the returned rows; `$facts->relation($occurrence)->shape` answers the shape an input contributes.

An `OutputSlot` is an output position, not a declaration. A slot of a named table refers to the declared column (`column`); a slot that a join or a query re-exposes refers to the slot it comes from (`origin`) and carries its own type and NULL fact. `declaration()` follows `origin` back to the declared column. A slot without a `name` lists in `unnamed` the missing inputs its name depends on; the list is empty when the position has no name at all. When no slot of a relation has a column name that is looked up and one of its slots is unnamed in this way, the name resolves to a `ConditionalColumn`, not to a `MissingColumn`. An outer join therefore makes the re-exposed slot nullable without changing the declaration:

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Type\Nullability;

$semantics = new Semantics(Dialect::Sqlite);
$users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
$orders = $semantics->analyze('CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL)');
$query = $semantics->analyze('SELECT u.name, o.user_id FROM users AS u LEFT JOIN orders AS o ON o.user_id = u.id', [$users, $orders]);
$userId = $orders->declarations()[0]->columns[1];

$query->field('name')->nullability; // => Nullability::NotNull
$query->field('user_id')->nullability; // => Nullability::Nullable
$query->field('user_id')->column() === $userId; // => true
$userId->nullability; // => Nullability::NotNull
```

A shape is **open** when missing declarations prevent listing every position. The known slots are still exact, and `missing` names the inputs that would complete the shape. A star that cannot be expanded stays an `OpenStar` in `$facts->output->projection`, carrying its missing inputs; no placeholder field is invented for it, and `fields()` answers null.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Shape\OpenStar;

$semantics = new Semantics(Dialect::Sqlite);
$open = $semantics->analyze('SELECT * FROM users');

$open->fields(); // => null
$open->shape()->complete(); // => false
$open->shape()->missing[0]->describe(); // => 'the declaration of relation users'
$open->output()->projection[0] instanceof OpenStar; // => true

$users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
count($semantics->analyze('SELECT * FROM users', [$users])->fields()); // => 2
```

## Resolutions

A name used as a value resolves to one of the following (namespace `SqlSemantics\Statement\Reference\Column`). Finding a candidate and resolving uniquely are different outcomes.

| Resolution | Meaning |
|------------|---------|
| `ResolvedColumn` | Exactly one slot: `relation` (the occurrence it was found in), `slot` (the slot visible at the use position), `depth` (how many enclosing queries lie between the use and the occurrence; 0 for the same query), `declaration()`. `resultReference` marks a reference to an enclosing query result rather than its input rows. |
| `AliasTarget` | An output field named by its alias, for example in ORDER BY: `field`. `depth` counts enclosing query scopes, with zero for the same block. |
| `MissingColumn` | No visible relation has the name, established by complete declarations. A diagnostic. |
| `AmbiguousColumn` | Several slots have the name at the same precedence: `candidates`. A diagnostic. |
| `ConditionalColumn` | The outcome depends on missing declarations: `candidates` (known slots), `relations` (incompletely known occurrences that can own the name), `missing`. |

A known candidate in a farther scope is not chosen while a nearer scope is incompletely known. A database package can add resolutions of its own, for example `SqlSemantics\Platform\MySql\Statement\Name\AmbiguousAlias` or `SqlSemantics\Platform\MySql\Statement\Variable\UserVariableBinding`; test with `instanceof` and treat other classes as database-specific.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;

$semantics = new Semantics(Dialect::Sqlite);
$users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$orders = $semantics->analyze('CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER)');

$sorted = $semantics->analyze('SELECT name AS label FROM users ORDER BY label', [$users]);
$use = $sorted->orderBy[0]->expression->scalarInformation()->resolution;
$use instanceof AliasTarget; // => true
$use->field === $sorted->field('label'); // => true

$correlated = $semantics->analyze('SELECT name, (SELECT count(*) FROM orders WHERE orders.user_id = users.id) AS n FROM users', [$users, $orders]);
$outer = $correlated->columns[1]->expression->query->where->right->scalarInformation()->resolution;
$outer->depth; // => 1
$outer->relation === $correlated->inputRelation(); // => true

$both = $semantics->analyze('SELECT id FROM users, orders', [$users, $orders]);
$both->field('id')->resolution instanceof AmbiguousColumn; // => true
$both->diagnostics()[0]->message(); // => 'Column id is ambiguous.'
```

A relation name resolves to one of the following (namespace `SqlSemantics\Statement\Reference\Table`), read from `$facts->relation($occurrence)->table`:

| Resolution | Meaning |
|------------|---------|
| `DeclaredTable` | Exactly one declaration of the context: `table`, the very object that was passed. |
| `CommonTable` | A common table expression of the same statement: `definition`. |
| `MissingTable` | A complete context does not declare it. A diagnostic. |
| `ConflictingTables` | Several distinct declarations claim the name: `candidates`. A diagnostic. |
| `UndeclaredTable` | An open context has no declaration for it; it may exist: `missing`. |
| `ConditionalTable` | A declaration was found in a later schema of the search path while an earlier schema is not completely known: `candidates`, `missing`. |

## Types and nullability

A type fact (`SqlSemantics\Statement\Type\TypeFact`) is one of:

| Type fact | Meaning |
|-----------|---------|
| `Known` | The rules of the database determine the type: `descriptor`. |
| `Choice` | One of several known types, decided by values at run time: `alternatives`. |
| `NullOnly` | The type of a bare NULL, which has no other type. |
| `Dependent` | The type cannot be decided because inputs are missing from the context: `missing`. |
| `Invalid` | The request itself is semantically wrong, so there is no type: `cause`, a diagnostic. |

`Dependent` always names the missing inputs, and it is never used because a rule is not implemented: that is an `ImplementationGap`.

A `TypeDescriptor` is a data type of one database; `name()` names it as the database reports it. Each database package has its own closed set of descriptor classes, for example SQLite's `Storage` (the storage class of a computed value) and `ColumnDomain` (a declared column type with its affinity), PostgreSQL's `Builtin` and `NamedOnPath`, and MySQL's `Integral`, `Character` or `Temporal`.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Nullability;

$semantics = new Semantics(Dialect::Sqlite);

$semantics->analyze('SELECT 1')->field(0)->type->descriptor; // => Storage::Integer
$choice = $semantics->analyze("SELECT CASE WHEN 1 THEN 1 ELSE 'a' END")->field(0)->type;
$choice instanceof Choice; // => true
array_map(static fn ($type) => $type->name(), $choice->alternatives); // => ['INTEGER', 'TEXT']
$semantics->analyze('SELECT NULL')->field(0)->nullability; // => Nullability::Nullable
```

`Nullability` is `NotNull`, `Nullable` or `Dependent`. `Dependent` means the answer needs information the context does not hold; treat such a value as possibly NULL.

## Diagnostics

A semantic problem of grammatical SQL, such as a missing table, a missing or ambiguous column, a wrong number of values, a statement on a relation of the wrong [kind](contexts.md#relation-kinds), or a write into a [generated column](contexts.md#generated-columns), is a value implementing `SqlSemantics\Statement\Fact\Diagnostic`. Such SQL is still structured and rendered; the database would reject it, or warn, when it runs. `$facts->diagnostics` lists them in derivation order. `message()` describes the problem for a person; the concrete class is the machine-readable kind. Some resolutions are diagnostics themselves (`MissingColumn`, `AmbiguousColumn`, `MissingTable`, `ConflictingTables`); the database packages define the others, mostly in `Problem` namespaces.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;

$semantics = new Semantics(Dialect::Sqlite);
$users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$insert = $semantics->analyze('INSERT INTO users (id) VALUES (1, 2)', [$users]);

$insert->diagnostics()[0] instanceof ArityMismatch; // => true
$insert->diagnostics()[0]->message(); // => '2 values for 1 columns.'
```

A diagnostic is not an analysis failure: the operation is complete, and `toString()` renders the statement as requested.

## Missing inputs

A `MissingInput` names information that a fact depends on and that the context does not hold. `describe()` explains it for a person.

| Missing input | Information |
|---------------|-------------|
| `UndeclaredRelation` | The declaration of a relation an open or partial context does not have. |
| `IncompleteMembers` | The complete column list of a declaration marked incomplete. |
| `UndeclaredRoutine` | The signature of a function or procedure that the language profile does not fix. |
| `UndeclaredDomain` | The definition of a named data type. |
| `UnboundParameter` | The value bound to a statement parameter, which also fixes its type. |
| `SessionState` | Connection state, such as a user variable or the current database. |

They are in `SqlSemantics\Statement\Reference\Missing`. Database packages add their own, for example SQLite's `RandomColumnName`, the random suffix SQLite gives a column of a subquery whose name repeats five earlier names, and MySQL's `NameConversion`, the character set conversion the server applies to some strings when it names a column after them.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Type\Dependent;

$semantics = new Semantics(Dialect::Sqlite);
$parameters = $semantics->analyze('SELECT ?, :name');

$parameters->field(0)->type instanceof Dependent; // => true
$parameters->field(0)->type->missing[0]->describe(); // => 'the value bound to parameter ?'
$parameters->field(1)->type->missing[0]->describe(); // => 'the value bound to parameter :name'
```

A version 1 context declares relations only. Routines, data types and session variables cannot be declared yet; a fact that needs them names them as missing inputs instead of guessing.

For MySQL input-name collisions, `NonUniqueTable::relation` identifies the later
relation occurrence when the diagnostic comes from a bound query namespace.
The decoded alias alone cannot identify an occurrence: callers may share the
same immutable `Name` value between different tables. Consumers can order the
condition with nested input preparation using that relation identity, without
resolving aliases again or consulting source text. Administration diagnostics
that do not belong to a query relation can leave this optional reference empty.
