# SQL Semantics for MySQL

[![Packagist Downloads](https://img.shields.io/packagist/dt/k-kinzal/sql-semantics-mysql.svg?label=Packagist)](https://packagist.org/packages/k-kinzal/sql-semantics-mysql)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics--mysql-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics-mysql/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics for MySQL adds MySQL to [SQL Semantics](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/sql-semantics): the typed statement models of the official MySQL grammars, the MySQL rules for resolving names and deriving types and NULL facts, and the MySQL spelling of rendered SQL. Installing it also installs the shared SQL Semantics runtime; `Dialect::MySql` selects MySQL in the runtime's `Semantics`, and `Mode::fromString()` reads a session's `sql_mode` for it. No database connection is needed.

## Requirements

- PHP 8.1+ with the zlib extension

## Support Syntax

The following grammar versions are supported. Pass the version tag as the second argument of `Semantics`; omitting it uses the default. A statement is read with the grammar of the selected release only.

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

## Installation

```bash
composer require k-kinzal/sql-semantics-mysql
```

## Usage

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

$semantics = new Semantics(Dialect::MySql);
$users = $semantics->analyze('CREATE TABLE users (id INT NOT NULL PRIMARY KEY, name VARCHAR(40))');
$query = $semantics->analyze('select id, upper(name) as label from users where id > 10', [$users]);

$query->toString();                         // "SELECT id, upper(`name`) AS label FROM users WHERE id > 10"
$query->field('label')->type;               // Known VARCHAR
$query->field('id')->nullability;           // Nullability::NotNull

$semantics->analyze('SELECT missing FROM users', [$users])->facts->diagnostics[0]->message(); // "Column missing does not exist."
```

Read SQL as the session reads it:

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;

$semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44', Mode::fromString('ANSI_QUOTES,NO_BACKSLASH_ESCAPES'));
$semantics->analyze('SELECT "name" FROM users')->toString(); // "SELECT `name` FROM users": "name" is an identifier under ANSI_QUOTES
```

See the [SQL Semantics documentation](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/sql-semantics) for operations, facts, declarations and contexts.

## Limitations

- Optimizer hints (`/*+ … */` after `SELECT`, `INSERT`, `REPLACE`, `UPDATE` or `DELETE`) are not analyzed. The server reads them with a grammar of their own, and the parser this package uses delivers them as a comment, so a statement with a hint is refused with `ImplementationGap` instead of being read without it. On MySQL 5.6 such a comment is an ordinary comment.
- Version comments (`/*!80000 … */`) are read as the selected release reads them: the body is part of the statement when the release is at least the written version, and a comment otherwise. Rendered SQL writes that reading without the comment markers, so it is SQL for the selected release.
- Table and database names are compared exactly, as a server with `lower_case_table_names=0` (the default on Unix) compares them. A server running with 1 or 2 compares them without regard to letter case; that setting is not part of the analysis.
- Column names are compared without regard to ASCII letter case. The server also folds letters outside ASCII; two column names that differ only in the case of such a letter are one name to the server and two names here.
- An unqualified table name belongs to the current database. When no current database is given, the analysis treats it as unknown: results that would show its name, such as the column name of `SHOW TABLES`, depend on it.

## License

MIT License. See [LICENSE](LICENSE) for details.
