# SQL Semantics for MySQL

[![Packagist Downloads](https://img.shields.io/packagist/dt/k-kinzal/sql-semantics-mysql.svg?label=Packagist)](https://packagist.org/packages/k-kinzal/sql-semantics-mysql)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--semantics--mysql-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-semantics-mysql/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Semantics for MySQL adds MySQL to [SQL Semantics](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics/README.md): the statement structure classes of the official MySQL grammars, the MySQL rules for resolving names and deriving types, NULL facts and diagnostics, and the MySQL spelling of rendered SQL. Installing it also installs the shared runtime. `Dialect::MySql` selects MySQL in the runtime's `Semantics`, and `Mode` describes the `sql_mode` settings that change how SQL text is read. No database connection is needed.

## Requirements

- PHP 8.1+
- No PHP extension

## Support Syntax

The following grammar releases are supported. Pass the version tag, or the version number alone, as the second argument of `Semantics`; omitting it selects the default. A statement is read with the grammar of the selected release only.

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
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

$semantics = new Semantics(Dialect::MySql);
$users = $semantics->analyze('CREATE TABLE users (id INT NOT NULL PRIMARY KEY, name VARCHAR(40))');
$query = $semantics->analyze('select id, upper(name) as label from users where id > 10', [$users]);

$query->toString(); // => 'SELECT id, upper(`name`) AS label FROM users WHERE id > 10'
$query->field('label')->type instanceof Known; // => true
$query->field('label')->type->descriptor->name(); // => 'VARCHAR'
$query->field('id')->nullability; // => Nullability::NotNull
$semantics->analyze('SELECT missing FROM users', [$users])->facts->diagnostics[0]->message(); // => 'Column missing does not exist.'
```

See the [SQL Semantics documentation](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics/README.md) for operations, facts, contexts, rendering and guarantees.

### Releases

Each release is read with its own grammar and keywords, and version comments are read as that release reads them:

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

(new Semantics(Dialect::MySql, '8.0.44'))->profile()->grammar->value; // => 'mysql-8.0.44'
(new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT /*!80000 1 + */ 2')->toString(); // => 'SELECT 1 + 2'
(new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT /*!80000 1 + */ 2')->toString(); // => 'SELECT 2'
```

### Session modes

Five `sql_mode` settings change tokenization: `ANSI_QUOTES`, `PIPES_AS_CONCAT`, `HIGH_NOT_PRECEDENCE`, `NO_BACKSLASH_ESCAPES` and `IGNORE_SPACE`. Pass a `Mode` as the third argument of `Semantics`. `Mode::fromString()` reads a value as `SELECT @@SESSION.sql_mode` reports it: a combination mode such as `ANSI` turns on the settings it includes, and every other mode name is accepted and not recorded, because it changes no token.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;

Mode::fromString('STRICT_TRANS_TABLES,ansi_quotes')->toString(); // => 'ANSI_QUOTES'
Mode::fromString('ANSI')->toString(); // => 'ANSI_QUOTES,PIPES_AS_CONCAT,IGNORE_SPACE'

$default = new Semantics(Dialect::MySql);
$ansi = new Semantics(Dialect::MySql, null, Mode::fromString('ANSI_QUOTES,PIPES_AS_CONCAT'));

$default->analyze('SELECT "name", a || b FROM users')->toString(); // => "SELECT 'name', a OR b FROM users"
$ansi->analyze('SELECT "name", a || b FROM users')->toString(); // => 'SELECT `name`, a || b FROM users'
```

The mode is part of the language profile. A declaration analyzed under one mode cannot be used in a context of another mode. Rendered SQL is meant to be read under the mode it was rendered for.

### Current database

MySQL searches an unqualified table name in the current database only. Name it with a `SearchPath` of exactly one schema; unqualified declarations then belong to that database, and qualified names in it resolve to them. Without a search path, the current database is unnamed: a name qualified with a database does not match an unqualified declaration, and facts that would show the database name depend on it as session state.

```php
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

$semantics = new Semantics(Dialect::MySql, null, null, ParameterStyle::Native, new SearchPath('shop'));
$users = $semantics->analyze('CREATE TABLE users (id INT)');
$query = $semantics->analyze('SELECT id FROM shop.users', [$users]);

$query->facts->relation($query->inputRelation())->table instanceof DeclaredTable; // => true
(new Semantics(Dialect::MySql))->analyze('SHOW TABLES')->shape()->missing[0]->describe(); // => 'the session state: the current database'
new Semantics(Dialect::MySql, null, null, ParameterStyle::Native, new SearchPath('shop', 'other')); // throws InvalidArgumentException
```

### Parameters

The native parameter style reads `?` markers. `ParameterStyle::Named` adds `:name` markers, as PDO accepts them; without it, `:name` is a syntax error. A parameter's type depends on the value bound to it.

```php
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

$named = new Semantics(Dialect::MySql, null, null, ParameterStyle::Named);

$named->analyze('SELECT :id, ?')->field(0)->type->missing[0]->describe(); // => 'the value bound to parameter :id'
(new Semantics(Dialect::MySql))->analyze('SELECT :id'); // throws AnalysisException
```

### Names

- Table and database names are compared exactly. Column names and output field names are compared without regard to ASCII letter case.
- Names are rendered bare when they are no keyword, function name or introducer of the release, and in backticks otherwise. Backticks are identifiers under every mode.
- User variables (`@total`) and system variables are session state; their facts depend on it.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\MissingTable;

$semantics = new Semantics(Dialect::MySql);
$users = $semantics->analyze('CREATE TABLE users (id INT)');

$semantics->analyze('SELECT ID FROM users', [$users])->field(0)->resolution instanceof ResolvedColumn; // => true
$upper = $semantics->analyze('SELECT id FROM USERS', [$users]);
$upper->facts->relation($upper->inputRelation())->table instanceof MissingTable; // => true
$semantics->analyze('SELECT @total')->field(0)->type->missing[0]->describe(); // => 'the session state: user variable @total'
```

## Limitations

- Optimizer hints (`/*+ ... */` after `SELECT`, `INSERT`, `REPLACE`, `UPDATE` or `DELETE`) are not analyzed. The server reads them with a grammar of their own, and the parser this package uses delivers them as a comment, so from 5.7 on a statement with a hint is refused with `ImplementationGap` instead of being read without it. In MySQL 5.6 such a comment is an ordinary comment.
- Version comments (`/*!80000 ... */`) are read as the selected release reads them: the body is part of the statement when the release is at least the written version, and a comment otherwise. Rendered SQL writes that reading without the comment markers, so it is SQL for the selected release.
- Table and database names are compared exactly, as a server with `lower_case_table_names=0` (the default on Unix) compares them. A server running with 1 or 2 compares them without regard to letter case; that setting is not part of the analysis.
- Column names are compared without regard to ASCII letter case. The server also folds letters outside ASCII; two column names that differ only in the case of such a letter are one name to the server and two names here.
- An unaliased select item that is not a column reference has no fixed name, because the server names it after the text of the expression as written, which the model does not keep. Such a field has a null name, and a lookup of a column of a derived table that could only match it depends on `ItemSpelling`.
- When no current database is given, results that would show its name, such as the column name of `SHOW TABLES`, depend on it.

See [Guarantees](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics/docs/guarantees.md) for the limits that apply to every database.

## License

MIT License. See [LICENSE](LICENSE) for details.
