# SQL Semantics for SQLite

[![Packagist Downloads](https://img.shields.io/packagist/dt/k-kinzal/sql-semantics-sqlite.svg?label=Packagist)](https://packagist.org/packages/k-kinzal/sql-semantics-sqlite)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics--sqlite-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics-sqlite/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics for SQLite adds SQLite to [SQL Semantics](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics/README.md): the statement structure classes of the official SQLite grammar, the SQLite rules for resolving names and deriving types, affinities, NULL facts and diagnostics, and the SQLite spelling of rendered SQL. Installing it also installs the shared runtime, and `Dialect::Sqlite` selects SQLite in the runtime's `Semantics`. No database connection is needed.

## Requirements

- PHP 8.1+
- No PHP extension; the `sqlite3` and `pdo_sqlite` extensions are not used

## Support Syntax

The following grammar release is supported. Pass the version tag as the second argument of `Semantics`; omitting it selects the default.

| Version | Version tag | Default |
|---------|-------------|---------|
| 3.47.2 | `sqlite-3.47.2` | Yes |

## Installation

```bash
composer require k-kinzal/sql-semantics-sqlite
```

## Usage

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Statement\Type\Nullability;

$semantics = new Semantics(Dialect::Sqlite);
$users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT)');
$insert = $semantics->analyze("insert or replace into users (id, name) values (1, 'Alice') returning id", [$users]);

$insert->statement instanceof InsertRows; // => true
$insert->toString(); // => "INSERT OR REPLACE INTO users (id, name) VALUES (1, 'Alice') RETURNING id"
$insert->field('id')->nullability; // => Nullability::NotNull
$semantics->analyze('INSERT INTO users (id) VALUES (1, 2)', [$users])->facts->diagnostics[0]->message(); // => '2 values for 1 columns.'
```

INSERT with VALUES, with a query and with DEFAULT VALUES are three classes: `InsertRows`, `InsertSelect` and `InsertDefaults`.

See the [SQL Semantics documentation](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics/README.md) for operations, facts, contexts, rendering and guarantees.

### Types and affinity

A declared column type keeps its text as SQLite reports it, and the affinity SQLite derives from it (`ColumnDomain`). A computed value has the storage class of its result (`Storage`). A `TypeName`, as in a CAST, keeps its words and how they are quoted, because SQLite records quoted type words with their quotes.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

$semantics = new Semantics(Dialect::Sqlite);
$table = $semantics->analyze('CREATE TABLE t (a VARCHAR(10), b integer, c)')->declarations()[0];

$table->columns[0]->type->affinity; // => Affinity::Text
$table->columns[1]->type->name(); // => 'INTEGER'
$table->columns[2]->type->affinity; // => Affinity::Blob
(new ColumnDomain('UNSIGNED BIG INT'))->affinity; // => Affinity::Integer
$semantics->analyze('SELECT 1.5')->field(0)->type->descriptor; // => Storage::Real
```

### Names

- Relation, schema and column names, and output field names, are compared without regard to ASCII letter case.
- A name written bare, in brackets or in backticks is a column use. A word in double quotes is a different request: SQLite reads it as an identifier when a column has that name and as a string otherwise, and the model keeps that difference. The bare words `TRUE` and `FALSE` are boolean words unless a column has that name.
- Rendered SQL writes a name bare when it is made of ASCII letters, digits and underscores, is no keyword and is not `TRUE` or `FALSE`, and in backticks otherwise. Backticks never fall back to a string.
- A table created by CREATE TABLE has the implicit columns SQLite gives it, such as `rowid`, `oid` and `_rowid_` (unless it is declared WITHOUT ROWID). A declaration you construct yourself has only the columns you give it.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

$semantics = new Semantics(Dialect::Sqlite);
$users = $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');

$semantics->analyze('SELECT NAME FROM USERS', [$users])->field(0)->column() === $users->declarations()[0]->columns[1]; // => true
$semantics->analyze('SELECT "name" FROM users', [$users])->field(0)->type->descriptor->name(); // => 'TEXT'
$semantics->analyze('SELECT "nickname" FROM users', [$users])->field(0)->type->descriptor; // => Storage::Text
$semantics->analyze('SELECT rowid FROM users', [$users])->field(0)->column() === $users->declarations()[0]->columns[0]; // => true
$semantics->analyze('SELECT [order], `group` FROM t')->toString(); // => 'SELECT `order`, `group` FROM t'
```

In the example, `id` is an `INTEGER PRIMARY KEY`, so `rowid` is another name for it. `"nickname"` names no column, so it is the string `'nickname'`.

### Search path

SQLite searches an unqualified name in `temp`, then `main`, then the attached schemas in order. A `SearchPath` lists `main` and the attached schemas; it must start with `main`. An unqualified declaration belongs to `main`, and `CREATE TEMP TABLE` declares in `temp`.

```php
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$semantics = new Semantics(Dialect::Sqlite, null, null, ParameterStyle::Native, new SearchPath('main', 'archive'));

array_map(static fn ($schema) => $schema->value, $semantics->context()->searchPath); // => ['temp', 'main', 'archive']
$semantics->analyze('CREATE TEMP TABLE scratch (a)')->declarations()[0]->name->schema?->value; // => 'temp'
```

Because `temp` is searched first, an unqualified name in a partial context (`$semantics->context([...], false)`) resolves conditionally even when it is declared in `main`: an undeclared temporary table could hide it. Qualify the name, as in `main.users`, or use a complete context.

### Parameters

SQLite reads every parameter form natively: `?`, `?NNN`, `:name`, `@name` and `$name`. The parameter style of the profile does not change how SQLite text is read; it is still part of the profile, so declarations analyzed under one style cannot be used under the other. A parameter's type depends on the value bound to it.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

$query = (new Semantics(Dialect::Sqlite))->analyze('SELECT ?, ?2, :name, @name, $name');

$query->toString(); // => 'SELECT ?, ?2, :name, @name, $name'
$query->field(2)->type->missing[0]->describe(); // => 'the value bound to parameter :name'
```

### Unaliased result columns

SQLite names an unaliased result column after the text of its expression as it was written, from its first token to the start of the next token, without the whitespace at the end, unless the expression is a column reference. A comment written after the expression is therefore part of the name. That text is part of the meaning, so the model keeps it as the layout of the result column, renders the expression in that spelling, and derives the name from it the way SQLite does:

- the rows a statement returns take the name of the column a column reference denotes, and otherwise the text;
- a subquery in FROM and a common table name their columns before resolution: a single word, possibly qualified, in parentheses or under COLLATE, keeps the word as written, and any other expression takes the text;
- a view and a table created from a query name their columns after resolution, looking through COLLATE, `likely()`, `unlikely()` and `likelihood()` as well;
- in these last two cases a name TRUE or FALSE becomes `columnN`, and a repeated name gets a `:1` to `:4` suffix; a fifth repeat gets a random suffix, so its name stays open (`RandomColumnName`);
- a name that depends on a missing declaration, such as a double-quoted word that is a column of an undeclared table or else a string, is left open, and the output slot names the missing inputs (`OutputSlot::$unnamed`), so looking a name up in such a result is a `DependentField`. Behind a star of an undeclared table, the names of a subquery or common table are open in the same way.

The layout is kept only where it differs from the canonical spelling, and it keeps the trivia after the expression only when that holds a comment. Whether AS introduces an alias is kept as well, because the text of an enclosing expression includes it. A result column built with `new` and without a layout is rendered in the canonical spelling and named after that text, which is the text the database reads.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\DependentField;

$semantics = new Semantics(Dialect::Sqlite);

$semantics->analyze('SELECT 1+1, 2 AS two')->field(0)->name?->value; // => '1+1'
$semantics->analyze('SELECT 1+1, 2 AS two')->toString(); // => 'SELECT 1+1, 2 AS two'
$semantics->analyze('SELECT 1+1 /* sum */, 2')->field(0)->name?->value; // => '1+1 /* sum */'
$semantics->analyze('SELECT 1+1 -- sum')->toString(); // => 'SELECT 1+1 -- sum'
$semantics->analyze('SELECT "a" FROM t')->fields()?->lookup('a') instanceof DependentField; // => true
$semantics->analyze('SELECT "1+1" FROM (SELECT 1+1)', [])->field(0)->resolution instanceof ResolvedColumn; // => true
```

### Views

`CREATE VIEW` declares a view (`RelationKind::View`); every other declaration is a table. Where a name resolves to one declaration, a request SQLite carries out only for the other kind is a `WrongRelationKind` diagnostic: `DROP TABLE`, every `ALTER TABLE` form, `CREATE INDEX`, a `BEFORE` or `AFTER` trigger and an upsert on a view, and `DROP VIEW` and an `INSTEAD OF` trigger on a table. A view has no `rowid`.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Declaration\RelationKind;

$semantics = new Semantics(Dialect::Sqlite);
$view = $semantics->analyze('CREATE VIEW v AS SELECT 1 AS one');

$view->declarations()[0]->kind; // => RelationKind::View
$semantics->analyze('DROP TABLE v', [$view])->facts->diagnostics[0]->message(); // => 'Relation v is a view: DROP TABLE removes only a table.'
```

## Limitations

- INSERT, UPDATE and DELETE on a view succeed exactly when an `INSTEAD OF` trigger handles them. Contexts do not hold triggers, so such a write is not reported.
- Whether a relation is a virtual table is not part of a declaration, so what SQLite refuses only for virtual tables, such as indexing one, is not reported.
- A search path must start with `main`; `temp` is always searched first.
- The parameter style has no effect on reading, but two profiles that differ only in it are not compatible.
- Version 1 contexts declare relations only. Application-defined functions, such as the `regexp()` that `REGEXP` calls, are missing inputs.

See [Guarantees](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics/docs/guarantees.md) for the limits that apply to every database.

## License

MIT License. See [LICENSE](LICENSE) for details.
