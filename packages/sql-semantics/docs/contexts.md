# Contexts

Facts such as resolutions, shapes and types depend on what is known about the database. That knowledge is passed explicitly, as an immutable `SqlSemantics\Contract\AnalysisContext`. Nothing is read from a connection, the host locale, the clock or a mutable setting, and no statement changes a context.

## What a context holds

| Property | Content |
|----------|---------|
| `profile` | The language profile the declarations belong to. |
| `tables` | The distinct `Table` declarations, in the order given. |
| `complete` | Whether the declarations enumerate every relation of the database. |
| `searchPath` | The schemas searched for an unqualified relation name, in precedence order. |
| `declarationSchema` | The schema an unqualified declaration belongs to. |
| `relationNames`, `columnNames` | How relation and schema names, and column names, are compared (`Comparison::Sensitive` or `Comparison::AsciiInsensitive`). |

Create contexts with `Semantics::context()`, which applies the search path and the name rules of the database, or let `analyze()` and `analyzeAll()` create one from their second argument:

| Argument | Context |
|----------|---------|
| `null` (the default) | Open: no declarations, and relations that are not declared may exist. |
| A list, also `[]` | Complete: the list enumerates every relation. |
| `$semantics->context($list, false)` | Partial: the declarations are known, and other relations may exist. |
| An `AnalysisContext` | Used as it is. Passing the same context object to several analyses keeps its identity. |

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$semantics = new Semantics(Dialect::Sqlite);
$users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$context = $semantics->context([$users]);

$semantics->context()->complete; // => false
$semantics->context([])->complete; // => true
$semantics->context([$users], false)->complete; // => false
$semantics->analyze('SELECT name FROM users', $context)->context === $context; // => true
count($context->tables); // => 1
```

`AnalysisContext` also has a public constructor. Prefer `Semantics::context()`: a context built by hand takes its search path and name comparison from the arguments you give, not from the database.

## Declarations

A context declares relations, such as tables and views, with their columns. There are two ways to obtain declarations.

**From CREATE statements.** An operation provides the declarations its statement creates, for example those of CREATE TABLE, CREATE TABLE AS, CREATE VIEW or CREATE VIRTUAL TABLE; `declarations()` lists them. Passing the operation in a context list contributes those declarations. Analyzing the CREATE statement in a context matters where the declaration depends on other declarations, as for a view over a table or a column of a user type. The declarations of the database packages also carry columns the database adds implicitly, such as the SQLite `rowid`.

**From your own catalog.** `Table`, `Column` and `ImplicitColumn` (namespace `SqlSemantics\Statement\Declaration`) have public constructors. A `Column` needs a type descriptor of the database package, for example SQLite's `ColumnDomain`, a NULL fact (nullable by default) and whether it is generated (not by default). A `Table` takes its relation kind (a base table by default). A table you construct has exactly the columns you give it; implicit columns such as `rowid` exist only if you list them.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Nullability;

$semantics = new Semantics(Dialect::Sqlite);
$users = new Table(
    new QualifiedName(new Name('users')),
    $semantics->profile(),
    [
        new Column(new Name('id'), new ColumnDomain('INTEGER'), Nullability::NotNull),
        new Column(new Name('name'), new ColumnDomain('TEXT')),
    ],
);
$query = $semantics->analyze('SELECT name FROM users', [$users]);

$query->field('name')->column() === $users->columns[1]; // => true
$query->field('name')->type->descriptor->name(); // => 'TEXT'
$semantics->analyze('SELECT rowid FROM users', [$users])->facts->diagnostics[0]->message(); // => 'Column rowid does not exist.'
```

A declaration belongs to one language profile; a declaration of another profile is refused with `InvalidConstruction`.

A table whose column list is not known completely is constructed with `complete: false`. Its listed columns resolve; another name is conditional, and a star over it stays open, naming the `IncompleteMembers` it depends on:

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

$semantics = new Semantics(Dialect::Sqlite);
$events = new Table(new QualifiedName(new Name('events')), $semantics->profile(), [new Column(new Name('id'), new ColumnDomain('INTEGER'))], complete: false);

$semantics->analyze('SELECT id FROM events', [$events])->field('id')->resolution instanceof ResolvedColumn; // => true
$semantics->analyze('SELECT kind FROM events', [$events])->field('kind')->resolution instanceof ConditionalColumn; // => true
$semantics->analyze('SELECT * FROM events', [$events])->shape()->missing[0]->describe(); // => 'the complete column list of relation events'
```

### Relation kinds

A `Table` states its `kind`, a `RelationKind`: `BaseTable`, `View`, `MaterializedView`, `ForeignTable` or `Sequence`. A CREATE statement declares the kind it creates; SQLite and MySQL have base tables and views, and PostgreSQL has all five kinds. Some statements behave differently for each kind, and the database refuses some of them for a kind, as DROP TABLE refuses a view. Where a relation name resolves to exactly one declaration, such a refusal is a diagnostic; for a name the context does not declare, nothing is reported about its kind. Each database package lists the statements it checks.

### Generated columns

A `Column` states whether it is `generated`: its value is computed from other columns of its row, and the statement cannot write it. CREATE TABLE declares the generated columns its definition has, and each database package reports the writes into them that its database refuses, such as an UPDATE that assigns one. Reading a generated column is like reading any other column.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

$semantics = new Semantics(Dialect::Sqlite);
$view = new Table(new QualifiedName(new Name('active_users')), $semantics->profile(), [new Column(new Name('id'), new ColumnDomain('INTEGER'))], kind: RelationKind::View);
$orders = new Table(new QualifiedName(new Name('orders')), $semantics->profile(), [
    new Column(new Name('price'), new ColumnDomain('INTEGER')),
    new Column(new Name('total'), new ColumnDomain('INTEGER'), generated: true),
]);

$semantics->analyze('DROP TABLE active_users', [$view])->facts->diagnostics[0]->message(); // => 'Relation active_users is a view: DROP TABLE removes only a table.'
$semantics->analyze('UPDATE orders SET total = 2', [$orders])->facts->diagnostics[0]->message(); // => 'cannot UPDATE generated column "total"'
$semantics->analyze('SELECT total FROM orders', [$orders])->field('total')->column()->generated; // => true
$semantics->analyze('CREATE TABLE t (a INTEGER, b AS (a + 1))')->declarations()[0]->columns[1]->generated; // => true
```

A declaration keeps neither the query of a view nor the expression of a generated column. Checks that need them are not made; the database packages list them among their limitations.

## Identity and conflicts

The declaration object is the identity of a declaration. A resolved reference reaches the very `Table` and `Column` objects of the context; they are never copied. The same object given twice, directly or through the operation that provides it, is kept once. Two different objects with the same name are two declarations, and the context keeps the conflict: the name resolves to `ConflictingTables`, a diagnostic, and no declaration is chosen. There is no "last declaration wins".

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Reference\Table\ConflictingTables;

$semantics = new Semantics(Dialect::Sqlite);
$first = $semantics->analyze('CREATE TABLE t (a INTEGER)');
$second = $semantics->analyze('CREATE TABLE t (b TEXT)');

count($semantics->context([$first, $first, $first->declarations()[0]])->tables); // => 1
$query = $semantics->analyze('SELECT 1 FROM t', [$first, $second]);
$query->facts->relation($query->inputRelation())->table instanceof ConflictingTables; // => true
$query->facts->diagnostics[0]->message(); // => 'Relation t has conflicting declarations.'
```

## Completeness

Completeness concerns the list of relations; each `Table` states separately whether its column list is complete.

| Relation name | Complete context | Open or partial context |
|---------------|------------------|-------------------------|
| Declared once | `DeclaredTable` | `DeclaredTable`, unless an earlier searched schema could hold the name: then `ConditionalTable` |
| Declared by several objects | `ConflictingTables`, a diagnostic | `ConflictingTables`, or `ConditionalTable` as above |
| Not declared | `MissingTable`, a diagnostic | `UndeclaredTable`; facts that need it are `Dependent` |

An open context has no declarations, so every relation name in it is undeclared. In a partial context, the search order matters. When an unqualified name is declared in a later schema of the search path, an undeclared relation of an earlier schema would be found first, so the result is a `ConditionalTable` with the declaration as its candidate. SQLite searches `temp` before `main`, and PostgreSQL searches `pg_temp` and `pg_catalog` before the path, so in a partial context of these databases qualify the names whose resolution you rely on, or use a complete context:

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

$semantics = new Semantics(Dialect::Sqlite);
$users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY)');
$partial = $semantics->context([$users], false);

$unqualified = $semantics->analyze('SELECT id FROM users', $partial);
$unqualified->facts->relation($unqualified->inputRelation())->table instanceof ConditionalTable; // => true
$qualified = $semantics->analyze('SELECT id FROM main.users', $partial);
$qualified->facts->relation($qualified->inputRelation())->table instanceof DeclaredTable; // => true
```

A complete context enumerates relations only. Version 1 contexts cannot declare routines, data types or session state; facts that need them are `Dependent` and name the missing input, also in a complete context.

## Search paths

The fifth argument of `Semantics` is a `SqlSemantics\Contract\SearchPath`: the schemas an unqualified relation name is searched in, in precedence order. A path the database cannot search is refused by the `Semantics` constructor with an `InvalidArgumentException`.

| Database | Default | Searched | Unqualified declarations belong to |
|----------|---------|----------|------------------------------------|
| MySQL | the current database, unnamed | exactly one schema: the current database | the current database |
| PostgreSQL | `public` | `pg_temp`, then `pg_catalog`, then the path; each of the two only if the path does not list it | the first schema of the path other than `pg_temp` and `pg_catalog` |
| SQLite | `main` | `temp`, then the path, which starts with `main` and continues with attached schemas | `main` |

```php
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$semantics = new Semantics(Dialect::Sqlite, null, null, ParameterStyle::Native, new SearchPath('main', 'archive'));

array_map(static fn ($schema) => $schema->value, $semantics->context()->searchPath); // => ['temp', 'main', 'archive']
new Semantics(Dialect::Sqlite, null, null, ParameterStyle::Native, new SearchPath('archive')); // throws InvalidArgumentException
```

A qualified name is looked up in the schema it names. A declaration without a schema belongs to `declarationSchema`.

## Name comparison

Names in the model are decoded: quotes and escapes are removed, and the case folding of the database is applied. Decoded names are then compared as the database compares them.

| Database | Relation and schema names | Column names | Output field names |
|----------|---------------------------|--------------|--------------------|
| MySQL | exact (as with `lower_case_table_names=0`) | ASCII case-insensitive | ASCII case-insensitive |
| PostgreSQL | exact; unquoted names are folded to lower case when decoded | exact, after the same folding | exact |
| SQLite | ASCII case-insensitive | ASCII case-insensitive | ASCII case-insensitive |

The ASCII comparison folds only the letters A to Z, independently of the host locale. The database packages describe where the server differs, for example letters outside ASCII.

## Language profiles

A context belongs to one language profile, and `Semantics` accepts only a context, a declaration or a declaring operation of a compatible profile; anything else is refused with `InvalidConstruction`. Two profiles are compatible when they have the same grammar release, the same lexical settings (the MySQL modes that change tokenization), the same parameter style, and the same rule revision. A declaration analyzed under one MySQL mode, for example, cannot be used under another mode; analyze its CREATE statement again under the other profile.

## No simulation

A context is a snapshot, not a database. Only statements that create relations contribute declarations. ALTER, DROP, writes and transaction statements are analyzed as requests, and contribute nothing:

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$semantics = new Semantics(Dialect::Sqlite);
$create = $semantics->analyze('CREATE TABLE t (a INTEGER)');
$alter = $semantics->analyze('ALTER TABLE t ADD COLUMN d INTEGER', [$create]);

$alter->declarations(); // => []
$semantics->analyze('SELECT d FROM t', [$create, $alter])->facts->diagnostics[0]->message(); // => 'Column d does not exist.'
```

In the same way, `analyze()` of a script and `analyzeAll()` pass the same context to every statement; a CREATE earlier in the script is not applied for the statements after it. Context order is not execution order.

To describe the database after a change, provide the declarations of the new state, for example by analyzing the CREATE statement the changed table would have. To analyze a statement against different declarations, analyze its SQL again with the other context. An existing operation is never re-resolved; its facts stay those of its own context. Because a statement structure holds no bindings, `new Operation($otherContext, $operation->statement)` is also possible: it is a new root whose facts are derived from scratch, with the checks of a construction but without the checks against an input text.
