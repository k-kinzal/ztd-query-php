# SQL Semantics

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics is the semantic phase of a database front end for MySQL, PostgreSQL, and SQLite. It parses SQL with [sql-parser](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/sql-parser), builds a schema from CREATE TABLE statements, and binds a SELECT against it: names are resolved to table uses and columns, expressions get dialect types, and every value carries conservative NULL facts and the relation occurrences it comes from. The result is an immutable bound statement that keeps the original syntax tree. No database connection is needed, and SQL outside the supported surface is rejected with `SemanticException` rather than silently ignored.

## Requirements

- PHP 8.1+ with the zlib extension

## Support Syntax

The following grammar versions are supported. Pass the dialect and, optionally, the version tag to `SchemaBuilder`; omitting the version tag uses the default for that database. MySQL 5.6 and 5.7 grammars are not supported.

### MySQL

| Version | Version tag | Default |
|---------|-------------|---------|
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
use SqlSemantics\SchemaBuilder;

$schema = (new SchemaBuilder(Dialect::PostgreSql))->build(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY,
    parent_id INTEGER,
    score INTEGER NOT NULL
);
SQL);

$statement = (new Binder($schema))->bind(<<<'SQL'
SELECT
    child.id,
    parent.score AS parent_score,
    COALESCE(parent.score, 0) AS effective_score
FROM users AS child
LEFT JOIN users AS parent ON child.parent_id = parent.id;
SQL);

$statement->outputs[1]->expression->type->name;                // 'integer'
$statement->outputs[1]->expression->nullability->value;        // 'maybe-null', because of the LEFT JOIN
$statement->outputs[2]->expression->nullability->value;        // 'not-null'
$statement->outputs[2]->expression->lineage()[0]->relationId;  // 'r1', the parent occurrence of users
```

## License

MIT License. See [LICENSE](LICENSE) for details.
