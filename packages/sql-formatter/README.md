# SQL Formatter

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--formatter-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-formatter/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Formatter formats MySQL, PostgreSQL, and SQLite statements with the **Compact**, **Expanded**, **Tabular**, or **River** layout. It reads each statement with the lossless parser of [sql-parser](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/sql-parser), which is built from the official grammar of the selected database version, Expanded, Tabular, and River change only layout whitespace and keep keywords, identifiers, literals, and comments as written; Compact writes a canonical single form of the statement, without comments or optional whitespace. Every output is parsed again to confirm that its syntax is unchanged. No schema or database connection is needed.

## Requirements

- PHP 8.1+ with the zlib extension
- MySQL 5.6–9.1, PostgreSQL 16–17, or SQLite 3.x

## Support Syntax

The following grammar versions are supported. Pass the version tag to the parser given to the formatter; omitting it uses the default for that database.

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
composer require k-kinzal/sql-formatter
```

## Usage

```php
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Style;
use SqlFormatter\Facade\Formatter;
use SqlParser\MySql\MySqlParser;

$formatter = new Formatter(
    new MySqlParser('mysql-8.4.7'),
    new FormatOptions(style: Style::Expanded, indentWidth: 4),
);

echo $formatter->format('SELECT id,name FROM users WHERE active=1 AND age>=18 ORDER BY name;');
```

```sql
SELECT
    id,
    name
FROM
    users
WHERE
    active = 1
    AND age >= 18
ORDER BY
    name;
```

## License

MIT License. See [LICENSE](LICENSE) for details.
