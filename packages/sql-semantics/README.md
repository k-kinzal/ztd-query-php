# SQL Semantics

[![Packagist Downloads](https://img.shields.io/packagist/dt/k-kinzal/sql-semantics.svg?label=Packagist)](https://packagist.org/packages/k-kinzal/sql-semantics)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics analyzes SQL of one database release into an immutable, typed semantic model. An analyzed statement is an `Operation`: the concrete structure of what the statement requests (its operation, operands and input relations), the facts derived for it against an explicit declaration context (name resolution, output fields, types, NULL facts and diagnostics), and SQL rendered from that structure. Before an operation is returned, the rendered SQL is parsed again and checked against the structure, and the structure is checked against the analyzed input.

The model is read-only. There is no API that edits an operation; another statement is analyzed from new SQL or built from explicit inputs, and it stands on its own. Analysis reads the grammar of the selected release and needs no database connection; it does not execute or simulate statements.

This package is the shared runtime. Install the package of your database: [MySQL](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics-mysql/README.md), [PostgreSQL](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics-postgres/README.md) or [SQLite](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics-sqlite/README.md).

## Requirements

- PHP 8.1+
- No PHP extension, FFI or external tool. The package is pure PHP and reads the grammar artifacts of [SQL Parser](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-parser/README.md).
- One of the database packages listed below

## Support Syntax

The following grammar releases are available. Pass the database dialect and, optionally, its version tag to `Semantics`; omitting the tag selects the default. A statement is read with the grammar of the selected release only, and each release is pinned by the digests of its grammar and keyword artifacts.

### MySQL

| Version | Version tag | Default |
|---------|-------------|---------|
| 5.6.51 | `mysql-5.6.51` | |
| 5.7.44 | `mysql-5.7.44` | |
| 8.0.44 | `mysql-8.0.44` | |
| 8.1.0 | `mysql-8.1.0` | |
| 8.2.0 | `mysql-8.2.0` | |
| 8.3.0 | `mysql-8.3.0` | |
| 8.4.7 | `mysql-8.4.7` | Yes |
| 9.0.1 | `mysql-9.0.1` | |
| 9.1.0 | `mysql-9.1.0` | |

### PostgreSQL

| Version | Version tag | Default |
|---------|-------------|---------|
| 16.6 | `pg-16.6` | |
| 17.2 | `pg-17.2` | Yes |

### SQLite

| Version | Version tag | Default |
|---------|-------------|---------|
| 3.47.2 | `sqlite-3.47.2` | Yes |

## Installation

Install the package of your database; it installs this runtime.

MySQL:

```bash
composer require k-kinzal/sql-semantics-mysql
```

PostgreSQL:

```bash
composer require k-kinzal/sql-semantics-postgres
```

SQLite:

```bash
composer require k-kinzal/sql-semantics-sqlite
```

Each package provides its dialect: `SqlSemantics\Platform\MySql\Dialect::MySql`, `SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql` or `SqlSemantics\Platform\Sqlite\Dialect::Sqlite`. The examples below use SQLite; the API is the same for every database.

## Usage

### Analyzing a statement

`Semantics` fixes a language profile: the database, the grammar release, the session mode, the parameter style and the search path. `analyze()` turns one input into an `Operation`.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;

$semantics = new Semantics(Dialect::Sqlite);
$query = $semantics->analyze('select   id, name  from users -- active users');

$query->statement instanceof Select; // => true
$query->toString(); // => 'SELECT id, name FROM users'
$semantics->profile()->grammar->value; // => 'sqlite-3.47.2'
```

`$query->statement` is the concrete statement structure of the database package; `toString()` is SQL rendered from that structure, never the analyzed text. See [Rendering](docs/rendering.md).

### Reading fields, types and resolutions

The facts of an operation depend on the declarations it is analyzed against. A CREATE statement provides declarations; pass the operation (or its `Table` declarations) as the context of the next analysis.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

$semantics = new Semantics(Dialect::Sqlite);
$users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT)');
$query = $semantics->analyze('SELECT u.id, upper(name) AS label, email FROM users AS u', [$users]);

count($query->fields()); // => 3
$query->field('label')->type instanceof Known; // => true
$query->field('label')->type->descriptor->name(); // => 'TEXT'
$query->field('label')->nullability; // => Nullability::NotNull
$query->field('email')->nullability; // => Nullability::Nullable
$query->field('email')->resolution instanceof ResolvedColumn; // => true
$query->field(0)->name?->value; // => 'id'
```

`field()` is a convenience for a name that denotes exactly one field, and throws otherwise. `lookupField()` tells the cases apart: one field, no field, several fields with the name, or a lookup that missing inputs leave undecided, such as missing declarations.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Shape\AbsentField;
use SqlSemantics\Statement\Shape\AmbiguousFields;
use SqlSemantics\Statement\Shape\DependentField;
use SqlSemantics\Statement\Shape\UniqueField;

$semantics = new Semantics(Dialect::Sqlite);

$semantics->analyze('SELECT 1 AS a')->lookupField('a') instanceof UniqueField; // => true
$semantics->analyze('SELECT 1 AS a')->lookupField('b') instanceof AbsentField; // => true
$semantics->analyze('SELECT 1 AS a, 2 AS a')->lookupField('a') instanceof AmbiguousFields; // => true
$semantics->analyze('SELECT * FROM users')->lookupField('id') instanceof DependentField; // => true
$semantics->analyze('SELECT 1 AS a, 2 AS a')->field('a'); // throws InvalidConstruction
```

Semantic problems of grammatical SQL, such as a missing column, are facts of the operation, not exceptions:

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Type\Invalid;

$semantics = new Semantics(Dialect::Sqlite);
$users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$query = $semantics->analyze('SELECT nickname FROM users', [$users]);

$query->facts->diagnostics[0]->message(); // => 'Column nickname does not exist.'
$query->field('nickname')->resolution instanceof MissingColumn; // => true
$query->field('nickname')->type instanceof Invalid; // => true
$query->toString(); // => 'SELECT nickname FROM users'
```

The [model](docs/model.md) describes operations, fields, shapes, resolutions, types, diagnostics and missing inputs.

### Declaration identity

A resolved reference reaches the declaration object that was passed in the context. Declarations are never copied, so identity comparison works.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$semantics = new Semantics(Dialect::Sqlite);
$users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$table = $users->declarations()[0];
$query = $semantics->analyze('SELECT name FROM users', [$users]);

$query->field('name')->column() === $table->columns[1]; // => true
$query->facts->relation($query->inputRelation())->table->table === $table; // => true
```

Declarations can also be constructed from your own catalog information; see [Contexts](docs/contexts.md#declarations).

### Open and complete contexts

The context states what is known about the database, and the facts say what follows from it.

| Context | How to pass it | An undeclared name |
|---------|----------------|--------------------|
| Open | `analyze($sql)` or `analyze($sql, null)` | may exist: facts that need it depend on its declaration |
| Complete | `analyze($sql, [...])`, also `[]` | does not exist: a diagnostic |
| Partial | `analyze($sql, $semantics->context([...], false))` | may exist, even where it would hide a declared one |

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Type\Dependent;

$semantics = new Semantics(Dialect::Sqlite);

$open = $semantics->analyze('SELECT name FROM users');
$open->field('name')->type instanceof Dependent; // => true
$open->field('name')->type->missing[0]->describe(); // => 'the declaration of relation users'
$open->field('name')->resolution instanceof ConditionalColumn; // => true

$complete = $semantics->analyze('SELECT name FROM users', []);
$complete->facts->diagnostics[0]->message(); // => 'Relation users does not exist.'
```

A fact that depends on missing information carries the missing input; it is never used for a rule that is not implemented. See [Contexts](docs/contexts.md) for search paths, name comparison, conflicts and completeness.

### Scripts

An input with several statements is one operation whose statement is a `Script`. `analyzeAll()` analyzes each statement into its own operation, and `split()` finds the statement boundaries as the database does. Every statement sees the same explicit context: a CREATE earlier in a script declares nothing for the statements after it.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Script;

$semantics = new Semantics(Dialect::Sqlite);
$sql = 'CREATE TABLE t (a INTEGER); SELECT a FROM t';

$semantics->analyze($sql, [])->statement instanceof Script; // => true
count($semantics->analyzeAll($sql, [])); // => 2
$semantics->analyzeAll($sql, [])[1]->facts->diagnostics[0]->message(); // => 'Relation t does not exist.'
$semantics->split("SELECT 1; SELECT ';'"); // => ['SELECT 1;', " SELECT ';'"]
```

To analyze a statement against the table a script creates, analyze the CREATE first and pass its operation as the context.

### Building a new root

`new Operation($context, $statement)` derives, renders and checks a new root from a context and an explicit statement structure. Structure values hold decoded names and exact literals and no bindings, so they are the inputs of a construction; the database package documents the constructor of each class.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;

$semantics = new Semantics(Dialect::Sqlite);
$users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$query = $semantics->analyze('SELECT id FROM users', [$users]);

$other = new Operation($query->context, new Select(
    [new ResultColumn(new ColumnUse(new Name('name')), new Name('label'))],
    new TableInput($query->singleNamedInput()->name()),
    new Binary(BinaryOperator::Greater, new ColumnUse(new Name('id')), new IntegerLiteral('10')),
));

$other->toString(); // => 'SELECT name AS label FROM users WHERE id > 10'
$other->field('label')->column() === $users->declarations()[0]->columns[1]; // => true
```

The new operation resolves its names at their new positions against the context it is given. It has no guaranteed relation to the operation its inputs were read from: no clause, alias or binding is inherited. A node may occur at one position of a statement only, a constructor refuses operands that its rendering would associate differently, and a context of another language profile is refused; each is an `InvalidConstruction`.

`singleNamedInput()` answers the input only when it is exactly one named relation; for joins, derived tables and other inputs, read `inputRelation()`.

### Spelling names for new SQL

You can also read names from a model, assemble SQL text, and analyze it. `SqlNames::table()` spells a decoded, optionally qualified relation name for a relation name position of a profile, quoting only as the database requires.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Facade\SqlNames;
use SqlSemantics\Platform\Sqlite\Dialect;

$semantics = new Semantics(Dialect::Sqlite);
$name = $semantics->analyze('SELECT a FROM "order items"')->singleNamedInput()->name();
$table = SqlNames::table($name, $semantics->profile());

$table; // => '`order items`'
$semantics->analyze("SELECT count(*) FROM {$table}")->singleNamedInput()->name()->name->value; // => 'order items'
```

`SqlNames` is not a general escaping function: it does not spell values, expressions or statements, and SQL assembled with it is unchecked until you analyze it.

## What it does not do

The model has no editing API. Nothing adds or removes a field, replaces a WHERE clause, renames an alias, rewrites with a visitor, moves a bound part of one operation into another, or re-resolves an existing operation against a different context, not even through methods that return a new value. To obtain a different statement:

- analyze new SQL text, using names read from the model and `SqlNames` where needed, or
- build a new root with `new Operation(...)` from explicit structure values.

Both paths check the new root on its own. Neither establishes how the new statement relates to an earlier one; that comparison is yours to make. To analyze the same SQL against other declarations, analyze it again with the other context.

The package also does not execute SQL, connect to a database, infer session settings from the host, or apply ALTER, DROP or writes to the declarations of a context.

## Failures

| Situation | Result |
|-----------|--------|
| SQL outside the selected grammar | `AnalysisException` |
| Missing or ambiguous names, arity mismatches and other semantic problems | facts of the returned operation: resolutions and `facts->diagnostics` |
| Declarations, signatures, parameter values or session state the context does not hold | `Dependent` facts that name the missing input |
| A rule the library has not implemented | `ImplementationGap`, a defect of the library |
| A constructor input outside its documented domain, a node shared between two positions, or a context of another profile | `InvalidConstruction` |
| A failed correspondence or integrity check | `InvariantViolation`, a defect of the library, or a constructed [layout](docs/rendering.md#spelled-regions) that does not fit its expression; no operation is returned |
| A configured work limit | `ResourceLimitExceeded` (reserved: no limit is configured in this release) |

All exceptions are in `SqlSemantics\Diagnostic`. `Semantics` refuses a release, mode or search path that does not belong to the database with an exception from its constructor. An `ImplementationGap` or an `InvariantViolation` is a bug; please report it with the SQL and the release.

## Documentation

- [Model](docs/model.md) - Operations, statement structure and facts, fields and lookups, shapes and open stars, resolutions, types and nullability, diagnostics, and missing inputs
- [Contexts](docs/contexts.md) - Analysis contexts: declarations, relation kinds and generated columns, identity and conflicts, completeness, search paths, and name comparison
- [Rendering](docs/rendering.md) - How SQL is rendered from the model, the checks before publication, what the rendered SQL does not preserve, and the spelling layouts of result columns
- [Guarantees](docs/guarantees.md) - What the package guarantees, the trust boundary, rule records, what tests and fuzzing establish, and known limitations

## License

MIT License. See [LICENSE](LICENSE) for details.
