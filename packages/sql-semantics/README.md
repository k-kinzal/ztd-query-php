# SQL Semantics

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics is the semantic phase of a database front end for MySQL, PostgreSQL, and SQLite. It turns any statement of the shipped grammars into an immutable, typed statement model that writes the SQL back, and it binds SELECT statements against a schema built from CREATE TABLE statements, resolving names, types, conservative NULL facts, and the relation occurrences each value comes from. No database connection is needed. This package is the shared runtime; install it through the package of your database.

## Requirements

- PHP 8.1+ with the zlib extension

## Support Syntax

The following grammar versions are supported. Pass the dialect of your database package and, optionally, the version tag to `Semantics` or `SchemaBuilder`; omitting the version tag uses the default for that database. Schema binding with `SchemaBuilder` and `Binder` requires MySQL 8.0 or later.

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

Each package provides its dialect: `SqlSemantics\Platform\MySql\Dialect::MySql`, `SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql`, or `SqlSemantics\Platform\Sqlite\Dialect::Sqlite`.

## Usage

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;

$statement = (new Semantics(Dialect::PostgreSql))->analyze(<<<'SQL'
WITH changed AS (
    UPDATE accounts SET balance = balance + 10 WHERE id = 7 RETURNING id, balance
)
SELECT id, balance FROM changed;
SQL);

$statement->command;    // the typed model of the statement
$statement->toString(); // 'WITH changed AS( UPDATE accounts SET balance = balance + 10 WHERE id = 7 RETURNING id , balance ) SELECT id , balance FROM changed ;'
```

See [statement models](docs/statements.md) for building statements without SQL, and [schema binding](docs/binding.md) for names, types, and NULL facts.

## License

MIT License. See [LICENSE](LICENSE) for details.
