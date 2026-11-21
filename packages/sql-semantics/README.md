# SQL Semantics

[Complete statement IR](docs/semantics-ir.md) describes the dialect-owned complete IR API and its current scope.

[![Packagist Downloads](https://img.shields.io/packagist/dt/k-kinzal/sql-semantics.svg?label=Packagist)](https://packagist.org/packages/k-kinzal/sql-semantics)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics resolves SQL to a complete, immutable `SemanticsIR` where the dialect implements that statement's full contract. `inspect()` supplies a diagnostic `Operation`: the concrete structure of what the statement requests (its operation, operands and input relations), the facts derived for it against an explicit declaration context (name resolution, output fields, types and NULL facts), and SQL rendered from that structure. Before an operation is returned, the rendered SQL is parsed again and checked against the structure, and the structure is checked against the analyzed input.

The model is read-only. There is no API that edits an operation; another statement is analyzed from new SQL or built from explicit inputs, and it stands on its own. Analysis reads the grammar of the selected release and needs no database connection; it does not execute or simulate statements.

This package defines the interfaces for SQL analysis. Each database package supplies the analyzer, declaration context, query model and analysis exceptions. Install the package of your database: [MySQL](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics-mysql/README.md), [PostgreSQL](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics-postgres/README.md) or [SQLite](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics-sqlite/README.md).

## Requirements

- PHP 8.1+
- The database implementations are pure PHP and read the grammar artifacts of [SQL Parser](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-parser/README.md).
- One of the database packages listed below

## Support Syntax

The following grammar releases are available. Pass a version tag to your database package’s `Semantics` constructor; omitting the tag selects the default. A statement is read with the grammar of the selected release only, and each release is pinned by the digests of its grammar and keyword artifacts.

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

Install the package of your database. It installs the common interfaces. Libraries that accept SQL Semantics interfaces can require k-kinzal/sql-semantics directly.

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

Create a `Semantics` instance from the namespace of your database package. The examples below use SQLite. The `SqlSemantics\Analyzer` interface declares `analyze(string $sql, ?AnalysisContext $context = null): SemanticsIR`. Its database-specific implementations also accept declaration lists as a convenience.

## Usage

### Analyzing and inspecting a statement

`Semantics` fixes a language profile: the database, the grammar release, the session mode, the parameter style and the search path. `analyze()` turns one input into a `SqlSemantics\Query` implemented by the database package’s `Operation`.

```php
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;

$semantics = new Semantics();
$ir = $semantics->analyze('SELECT 42 AS answer');
$ir->toString(); // => 'SELECT 42 AS "answer"'

$query = $semantics->inspect('select   id, name  from users -- active users');

$query->statement instanceof Select; // => true
$query->toString(); // => 'SELECT id, name FROM users'
$semantics->profile()->grammar->value; // => 'sqlite-3.47.2'
```

`$query->statement` is the concrete statement structure of the database package; `toString()` is SQL rendered from that structure, never the analyzed text. See [Rendering](docs/rendering.md).

### Reading fields, types and resolutions

The facts of an operation depend on the declarations it is analyzed against. A CREATE statement provides declarations; pass the operation (or its `Table` declarations) as the context of the next analysis.

```php
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;

$semantics = new Semantics();
$users = $semantics->inspect('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT)');
$query = $semantics->inspect('SELECT u.id, upper(name) AS label, email FROM users AS u', [$users]);

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
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\AbsentField;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\AmbiguousFields;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\DependentField;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\UniqueField;

$semantics = new Semantics();

$semantics->inspect('SELECT 1 AS a')->lookupField('a') instanceof UniqueField; // => true
$semantics->inspect('SELECT 1 AS a')->lookupField('b') instanceof AbsentField; // => true
$semantics->inspect('SELECT 1 AS a, 2 AS a')->lookupField('a') instanceof AmbiguousFields; // => true
$semantics->inspect('SELECT * FROM users')->lookupField('id') instanceof DependentField; // => true
$semantics->inspect('SELECT 1 AS a, 2 AS a')->field('a'); // throws InvalidConstruction
```

Analysis throws a database-specific exception when it establishes a semantic problem. Catch the common `SqlSemantics\AnalysisException` interface for syntax, semantics, or incompatible-context errors.

```php
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\Sqlite\Semantics;

$semantics = new Semantics();
$users = $semantics->inspect('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

try {
    $semantics->inspect('SELECT nickname FROM users', [$users]);
} catch (AnalysisException $error) {
    $error->getMessage(); // => 'Column nickname does not exist.'
}
```

The [model](docs/model.md) describes operations, fields, shapes, resolutions, types, diagnostics and missing inputs.

### Declaration identity

A resolved reference reaches the declaration object that was passed in the context. Declarations are never copied, so identity comparison works.

```php
use SqlSemantics\Platform\Sqlite\Semantics;

$semantics = new Semantics();
$users = $semantics->inspect('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$table = $users->declarations()[0];
$query = $semantics->inspect('SELECT name FROM users', [$users]);

$query->field('name')->column() === $table->columns[1]; // => true
$query->facts->relation($query->inputRelation())->table->table === $table; // => true
```

Declarations can also be constructed from your own catalog information; see [Contexts](docs/contexts.md#declarations).

### Open and complete contexts

The context states what is known about the database, and the facts say what follows from it.

| Context | How to pass it | An undeclared name |
|---------|----------------|--------------------|
| Open | `analyze($sql)` or `analyze($sql, null)` | may exist: facts that need it depend on its declaration |
| Complete | `analyze($sql, [...])`, also `[]` | throws `SemanticException` |
| Partial | `analyze($sql, $semantics->context([...], false))` | may exist, even where it would hide a declared one |

```php
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Dependent;

$semantics = new Semantics();

$open = $semantics->inspect('SELECT name FROM users');
$open->field('name')->type instanceof Dependent; // => true
$open->field('name')->type->missing[0]->describe(); // => 'the declaration of relation users'
$open->field('name')->resolution instanceof ConditionalColumn; // => true

$semantics->inspect('SELECT name FROM users', []); // throws SemanticException
```

A fact that depends on missing information carries the missing input; it is never used for a rule that is not implemented. See [Contexts](docs/contexts.md) for search paths, name comparison, conflicts and completeness.

### Scripts

`inspect()` can inspect a script as one `Operation` whose statement is a `Script`. `analyzeAll()` publishes a list of complete IR values by applying `analyze()` to each statement. `split()` finds statement boundaries and can be combined with `inspect()` for a list of diagnostic operations. All statements use the same declaration context.

```php
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Model\Statement\Script;

$semantics = new Semantics();
$sql = 'CREATE TABLE t (a INTEGER); SELECT a FROM t';

$semantics->inspect($sql)->statement instanceof Script; // => true
count($semantics->analyzeAll('SELECT 1; SELECT 2')); // => 2
$semantics->split("SELECT 1; SELECT ';'"); // => ['SELECT 1;', " SELECT ';'"]
$semantics->analyzeAll('SELECT 1; SELECT a FROM t', []); // throws SemanticException
```

Inspect the CREATE statement first to use its declaration as context for subsequent analysis.

### Building a new root

`new Operation($context, $statement)` derives, renders and checks a new root from a context and an explicit statement structure. Structure values hold decoded names and exact literals and no bindings, so they are the inputs of a construction; the database package documents the constructor of each class.

```php
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Operation;

$semantics = new Semantics();
$users = $semantics->inspect('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$query = $semantics->inspect('SELECT id FROM users', [$users]);

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
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\SqlNames;

$semantics = new Semantics();
$name = $semantics->inspect('SELECT a FROM "order items"')->singleNamedInput()->name();
$table = SqlNames::table($name, $semantics->profile());

$table; // => '`order items`'
$semantics->inspect("SELECT count(*) FROM {$table}")->singleNamedInput()->name()->name->value; // => 'order items'
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
| Missing or ambiguous names, arity mismatches and other semantic problems | `SemanticException` with structured diagnostics |
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
