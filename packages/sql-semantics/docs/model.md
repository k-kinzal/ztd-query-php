# Model

`Semantics::analyze()` returns an `SqlSemantics\Statement\Operation`. This page describes what an operation holds and how to read it. Every value described here is immutable: it has only readonly properties, and cloning, dynamic properties and serialization are refused by the `SqlSemantics\Statement\Snapshot` trait, which the [value audit](rendering.md#checks-before-publication) requires of every published class.

## Operations

An operation is one analyzed root:

| Member | Content |
|--------|---------|
| `$operation->context` | The `AnalysisContext` the facts were derived against: the language profile and the declarations. See [Contexts](contexts.md). |
| `$operation->statement` | The statement structure: a concrete class of the database package, or a `Script` of several statements. |
| `$operation->facts` | Every fact derived for the structure against the context. |
| `$operation->sources` | Recorded byte ranges of particular occurrences in the original parser input. Coverage is partial; `sources->of($node)` returns null when that rule does not record a location. |
| `toString()` | SQL rendered from the structure and checked against it. See [Rendering](rendering.md). |
| `profile()` | The language profile: grammar release, lexical settings, parameter style. |
| `declarations()` | The `Table` declarations the statement provides, for example those of a CREATE TABLE or CREATE VIEW. |
| `shape()`, `fields()`, `field()`, `lookupField()` | The rows the statement returns, when it returns rows. |
| `inputRelation()`, `singleNamedInput()` | The input relation of a statement that reads rows. |

The constructor `new Operation($context, $statement)` is the only way to obtain an operation, and `analyze()` uses it too. All facts are established in the constructor; nothing is filled in later. An optional third argument supplies a `SourceMap`. Direct construction defaults to an empty map; source locations are never inherited implicitly from another operation.

Input locations are separate from both the semantic structure and its rendering. An `Origin` holds the exact node occurrence, a byte `offset`, and a byte `length`, measured from the first through the last token. Comments and whitespace between those tokens remain within the range; empty synthetic tokens do not. Offsets refer to the input passed to the parser, including when `analyze()` receives an already parsed tree. Keep that input if you need to show the original text in a diagnostic. The MySQL lowering records statement, query, expression and data type ranges, including partition functions and bounds; rules without tracking may still return no origin.

A `SourceMap` also holds `notices`: spelling-dependent warnings attached to a node or decoded `Name` by identity. Each `SourceNotice` records the warning and the byte boundary after that spelling. For example, MySQL deprecates an unquoted `FULL` identifier but accepts the same decoded name in backticks. These notices enter `facts->warnings`; they do not change lookup or canonical SQL. When notices are present, located warnings are merged by their recorded boundaries; warnings without a location follow in derivation order. A map supplied to direct construction must describe that statement and its original analysis profile. Reusing a structure without the map does not retain input spelling warnings.

## Statement structure and facts

An operation separates two kinds of information.

The **structure** describes what the SQL requests: the operation, its operands and its input relations, as concrete classes of the database package (for example `SqlSemantics\Platform\Sqlite\Statement\Query\Select`, `...\Mutation\InsertRows`, `...\Schema\CreateTable`). It holds decoded names (`Name`, `QualifiedName`), exact literal values (digits as strings, decoded text, hexadecimal digits; never a PHP float), enum cases for closed choices, and lists in written order. It holds no parser node, no token and no binding: which declaration a name denotes is not part of the structure. It holds no source text either, except the checked spelling of a result column that the database names after its text (a `Layout`, see [Rendering](rendering.md#spelled-regions)). A structure value may occur at one position of a statement only.

The **facts** are everything that depends on the context: name resolutions, row shapes, output fields, types, NULL facts and diagnostics. They are kept in `$operation->facts`, keyed by the identity of the structure node they are about.

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

$where = $query->statement->where;
$where instanceof Binary; // => true
$where->operator; // => BinaryOperator::Greater
$where->right->digits; // => '10'
$query->facts->scalar($where->left)->resolution instanceof ResolvedColumn; // => true

$sum = $semantics->analyze('SELECT 1 + NULL');
$sum->facts->scalar($sum->field(0)->expression->right)->type instanceof NullOnly; // => true
```

`$facts->scalar($node)` answers the `ScalarFact` of an expression (`type`, `nullability`, and `resolution` for a name use), `$facts->relation($node)` the `RelationFact` of a relation occurrence (`shape`, and `table` for a named relation), and `$facts->query($node)` the `QueryFact` of a query, including subqueries.

`QueryFact::$aggregates` lists the aggregate occurrences assigned to that query block by platforms that resolve ownership (currently MySQL). An occurrence can be written inside a nested query while aggregating the rows of an enclosing query. Window functions are not in this list.

`$facts->diagnostics` lists the semantic problems, `$facts->declarations` the provided declarations, and `$facts->output` the `QueryFact` of the rows the statement returns, or null. Asking for a node that is not part of the operation throws `InvalidConstruction`; a node of one operation has no facts in another.

A scalar fact can also publish a `replacement`: a bound expression evaluated in place of that occurrence. It is a strict descendant in the same operation, with its own facts and original name bindings. Consumers may compile it directly; the original statement and its rendered SQL stay intact. For example, MySQL resolves `(SELECT 1 LIMIT 0)` to its selected expression and returns `1`. A scalar subquery that reads table rows still needs query execution and can return NULL or raise a multiple-row error. Which forms are reduced depends on the dialect and release.

The structure classes and their properties are documented in each database package. The core defines the roles they play:

| Interface | Role |
|-----------|------|
| `Statement` | A statement root. |
| `Query` | Something that produces rows: a SELECT, VALUES, a set operation, a WITH query. |
| `Relation` | A relation in an input: a named table, a join, a derived table, a table function. |
| `NamedRelation` | A relation that is one named table, view or common table; `name()` and `alias()`. |
| `Selection` | A statement or query that reads from an input relation; `input()`. |
| `Scalar` | An expression. |
| `Node` | Any structure value. |

A `Script` holds the statements of an input with several statements, in order. Its diagnostics and declarations are those of all its members; it returns no rows itself.

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

`Operation::field($key)` takes a position or a name. By name, it answers the field only when exactly one field has that name, and throws `InvalidConstruction` otherwise; it never picks the first of several. `Operation::lookupField($name)` answers one of:

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
$open->facts->output->projection[0] instanceof OpenStar; // => true

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
$use = $sorted->facts->scalar($sorted->statement->orderBy[0]->expression)->resolution;
$use instanceof AliasTarget; // => true
$use->field === $sorted->field('label'); // => true

$correlated = $semantics->analyze('SELECT name, (SELECT count(*) FROM orders WHERE orders.user_id = users.id) AS n FROM users', [$users, $orders]);
$outer = $correlated->facts->scalar($correlated->statement->columns[1]->expression->query->where->right)->resolution;
$outer->depth; // => 1
$outer->relation === $correlated->inputRelation(); // => true

$both = $semantics->analyze('SELECT id FROM users, orders', [$users, $orders]);
$both->field('id')->resolution instanceof AmbiguousColumn; // => true
$both->facts->diagnostics[0]->message(); // => 'Column id is ambiguous.'
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

$insert->facts->diagnostics[0] instanceof ArityMismatch; // => true
$insert->facts->diagnostics[0]->message(); // => '2 values for 1 columns.'
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
