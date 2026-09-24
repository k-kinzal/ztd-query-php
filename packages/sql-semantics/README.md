# SQL Semantics

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics converts SQL strings into structured declarations and bound statements for MySQL, PostgreSQL, and SQLite. It uses [sql-parser](../sql-parser/) to preserve the original SQL and exposes table, column, and index definitions, referential actions, declaration options, types, nullability, query inputs, write destinations, conditions, and configuration effects. No database connection is required. See the [schema API](docs/schema.md), [binder API](docs/binder.md), [statement forms](docs/statement-forms.md), and [statements and serialization](docs/statements.md).

## Requirements

- PHP 8.1 or higher
- [k-kinzal/sql-parser](../sql-parser/)

## Support Syntax

The following grammar versions are available through sql-parser, matching sql-faker. Pass `grammarVersion` to `SchemaBuilder` to select a grammar; omitting it uses the default for that database. The database release is the only language support boundary. Every SQL construct in that release is in scope; missing semantic behavior is a defect.

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
| 17.2 | `pg-17.2` | Yes |

### SQLite

| Version | Version tag | Default |
|---------|-------------|---------|
| 3.47.2 | `sqlite-3.47.2` | Yes |

## Installation

```bash
composer require k-kinzal/sql-semantics
```

## Usage

```php
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;

$schema = (new SchemaBuilder(Dialect::PostgreSql))->build(
    'CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER DEFAULT 0)',
);
$schema->tables[0]->columns[0]->type->name; // integer

$binder = new Binder($schema);
$statement = $binder->bind('SELECT id, score FROM users WHERE score > 0');
if ($statement instanceof BoundSelect) {
    $statement->outputs[0]->expression->lineage()[0]->column->name; // id
    $statement->outputs[1]->expression->type->name;                // integer
    $statement->where?->inputs()[0]->lineage()[0]->column->name;    // score
}
$statement->toString(); // SQL generated from the semantic operands
```

`SchemaBuilder::build()` reads table and index definitions. Register function signatures through `Schema::withFunctions()` or the builder's `functions` argument to supply application-specific argument types, return types, and NULL behavior. `Binder::bind()` reads one statement against that schema; `bindAll()` reads a sequence of statements. Pass `strict: false` to collect semantic diagnostics on each returned statement. See [schema.md](docs/schema.md) and [binder.md](docs/binder.md) for responsibilities and result fields, and [statement-forms.md](docs/statement-forms.md) for the Statement class and structure of every SQL form. Statements expose immutable transformations that validate the complete result and refresh dependent facts. `Serializer` defines SQL output; `SimpleSerializer` and `BoundStatement::toString()` provide the default compact layout. `StatementFactory` also constructs validated statements from structure without original SQL.

Development checks are `composer lint`, `composer test`, and `composer bench:quick`. Run `XDEBUG_MODE=off composer fuzz:smoke` for a bounded run of each dialect. The [fuzz instructions](fuzz/README.md) describe the unrestricted grammar property and all-release runs.

## License

MIT License. See [LICENSE](LICENSE) for details.
