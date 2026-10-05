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

$default->analyze('SELECT a FROM users WHERE "name" = a || b')->toString(); // => "SELECT a FROM users WHERE 'name' = a OR b"
$ansi->analyze('SELECT a FROM users WHERE "name" = a || b')->toString(); // => 'SELECT a FROM users WHERE `name` = a || b'
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

### Result column names

MySQL names a select list item without alias after the text of its expression as the statement writes it, so `SELECT 1+1` and `SELECT 1 + 1` return columns named `1+1` and `1 + 1`. An item that names itself keeps its own name: a column reference its column name, a string the value of its first quoted part, `NULL` the name `NULL`, a number its text, `?` the name `?`, and in MySQL 5.6 and 5.7 `TRUE` and `FALSE` those words; parentheses and a unary plus do not change it. The server removes leading spaces and control characters from the name, and keeps at most 255 bytes of it.

Such an item keeps the spelling of its expression as a layout (`SelectExpression::$layout`), and `toString()` writes the expression as it was written, comments included, so the rendered SQL returns the same column names. The optional words that change that text are part of the model: `AS` before an alias, `OUTER` and `INNER` of a join, the empty parentheses of `CURDATE()` and the other niladic functions, `ROW`, the `OF` of `MEMBER OF`, `INT` after `SIGNED`, `CHARACTER SET` for `CHARSET`, and the 5.6 and 5.7 leading dot of `.t`. Version comment markers are left out, as the server leaves them out of the name. An item built without a layout is named after, and written as, the canonical rendering of its expression. A name with characters outside ASCII, or a text longer than 255 bytes, depends on the character set conversion of the server (`NameConversion`).

Derived tables, common tables, `CREATE TABLE ... SELECT` and views take these names. A view renames a generated name that is no valid column name to `Name_exp_N` and one that repeats an earlier name with the prefix `Name_exp_` (`My_exp_` in 5.6 and 5.7), as the server does; `CREATE TABLE ... SELECT` reports an invalid one (`IncorrectColumnName`). A name in `ORDER BY`, `GROUP BY` and `HAVING` finds an item by the name it was given after its text, as it finds an alias.

```php
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

$semantics = new Semantics(Dialect::MySql);

$query = $semantics->analyze('SELECT 1+1, CURRENT_DATE, a total, NOW( ) FROM users', []);
[$query->field(0)->name?->value, $query->field(3)->name?->value, $query->toString()]; // => ['1+1', 'NOW( )', 'SELECT 1+1, CURRENT_DATE, a total, NOW( ) FROM users']
$semantics->analyze('SELECT `1+1` FROM (SELECT 1+1) AS d', [])->field(0)->resolution instanceof ResolvedColumn; // => true
```

## Limitations

- Optimizer hints (`/*+ ... */` after `SELECT`, `INSERT`, `REPLACE`, `UPDATE` or `DELETE`) are not analyzed. The server reads them with a grammar of their own, and the parser this package uses delivers them as a comment, so from 5.7 on a statement with a hint is refused with `ImplementationGap` instead of being read without it. In MySQL 5.6 such a comment is an ordinary comment.
- Version comments (`/*!80000 ... */`) are read as the selected release reads them: the body is part of the statement when the release is at least the written version, and a comment otherwise. Rendered SQL writes that reading without the comment markers, so it is SQL for the selected release.
- Table and database names are compared exactly, as a server with `lower_case_table_names=0` (the default on Unix) compares them. A server running with 1 or 2 compares them without regard to letter case; that setting is not part of the analysis.
- Column names are compared without regard to ASCII letter case. The server also folds letters outside ASCII; two column names that differ only in the case of such a letter are one name to the server and two names here.
- The name of an unaliased select item that holds characters outside ASCII, or whose text is longer than 255 bytes, depends on the conversion the server applies from `character_set_client`, or from the character set of an introducer, to the system character set. Such a field has a null name, a lookup of a column of a derived table that could only match it depends on `NameConversion`, and a name in `ORDER BY`, `GROUP BY` or `HAVING` is not taken to refer to it. A name of ASCII characters is known for every client character set that encodes ASCII as ASCII, which all of them but `swe7` do.
- The name `NAME_CONST` gives a column is derived for a string, decimal or integer number, hexadecimal or bit value and boolean name argument; other arguments are refused with `ImplementationGap`.
- `SHOW CREATE TABLE` returns the columns of `SHOW CREATE VIEW` for a view. A declaration of the context does not tell a table from a view, so its row shape depends on that (`TableOrView`).
- When no current database is given, results that would show its name, such as the column name of `SHOW TABLES`, depend on it.

See [Guarantees](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/sql-semantics/docs/guarantees.md) for the limits that apply to every database.

## License

MIT License. See [LICENSE](LICENSE) for details.
