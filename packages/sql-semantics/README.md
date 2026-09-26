# SQL Semantics

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics is the semantic phase of a database front end for MySQL, PostgreSQL, and SQLite. It reads SQL as the server reads it: with the grammar of one release, under the session settings that change tokenization, and with the parameter markers the statement was written for. Every statement of the shipped grammars becomes an immutable, typed statement model that writes the SQL back, can be walked and rewritten without naming its classes, and can be composed from PHP values under stable names. Table declarations become a state the statements are read against. No database connection is needed. This package is the shared runtime; install it through the package of your database.

## Requirements

- PHP 8.1+ with the zlib extension

## Support Syntax

The following grammar versions are supported. Pass the dialect of your database package and, optionally, the version tag to `Semantics` or `Schema`; omitting the version tag uses the default for that database. Common table expressions in `Builder` need MySQL 8.0 or later.

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

A MySQL session's `sql_mode` changes how text is read, and a statement written for PDO carries markers the server itself rejects. Both are part of the language:

```php
use SqlSemantics\Core\Parameters;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;

$semantics = new Semantics(Dialect::MySql, 'mysql-8.4.7', Mode::fromString('ANSI_QUOTES,NO_BACKSLASH_ESCAPES'), Parameters::Pdo);

$semantics->analyze('SELECT "name" FROM users WHERE id = :id')->toString(); // "name" is an identifier, :id a parameter
$semantics->split("SELECT 1; CREATE PROCEDURE p() BEGIN SELECT 1; SELECT 2; END; SELECT 3");
// ['SELECT 1;', ' CREATE PROCEDURE p() BEGIN SELECT 1; SELECT 2; END;', ' SELECT 3']
```

Walk any statement for the values of a role, rewrite it from the leaves up, and compose new values without naming a generated class:

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Model\Sqlite\Role\NmForm;
use SqlSemantics\Statement\Model\Sqlite\Value\NmWithIdj_a2015ecf as Name;
use SqlSemantics\Statement\Traversal;
use SqlSemantics\Statement\Writer;

$semantics = new Semantics(Dialect::Sqlite);
$statement = $semantics->analyze('SELECT id FROM users WHERE active = 1');

Traversal::find($statement->command, NmForm::class);                // every name in the statement
$rewritten = Traversal::rewrite($statement->command, static fn (Element $value): Element => $value instanceof Name && $value->name === 'users' ? $value->withName('members') : $value);
Writer::render($rewritten);                                         // 'SELECT id FROM members WHERE active = 1'

$builder = $semantics->builder();
$rows = $builder->unionAll($semantics->analyze("SELECT 1 AS id, 'a' AS name")->command, $semantics->analyze("SELECT 2, 'b'")->command);
Writer::render($builder->with([$builder->cte('users', $rows)], $statement->command));
// "WITH users AS( SELECT 1 AS id , 'a' AS name UNION ALL SELECT 2 , 'b' ) SELECT id FROM users WHERE active = 1"
Writer::render($builder->compare($builder->column('select'), '=', $builder->string("it's")));
// "\"select\" = 'it''s'"
```

See [statement models](docs/statements.md) for the models, traversal, comments, and statement boundaries; [composition](docs/composition.md) for building values from PHP data; and [schema state](docs/schema.md) for reading declarations.

## License

MIT License. See [LICENSE](LICENSE) for details.
