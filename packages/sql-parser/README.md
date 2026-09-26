# SQL Parser

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--parser-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-parser/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Parser is a set of LALR(1) parsers for MySQL, PostgreSQL, and SQLite written in PHP. Each parser is generated from the official grammar of a server version (`sql_yacc.yy` for MySQL, `gram.y` for PostgreSQL, and `parse.y` for SQLite), and its lexer is a port of the server's own scanner, so text is tokenized and parsed the way the server does it. The syntax tree names the nonterminals of the upstream grammar, and parsing is lossless: a tree writes back the text it was parsed from, byte for byte, including comments and whitespace.

## Requirements

- PHP 8.1+ with the zlib extension

## Support Syntax

The following grammar versions are bundled. Pass the version tag to the parser; omitting it uses the default for that database.

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
composer require k-kinzal/sql-parser
```

## Usage

```php
use SqlParser\MySql\MySqlParser;

$parser = new MySqlParser('mysql-8.4.7');
$sql = 'SELECT id, name FROM users u WHERE u.id = ? ORDER BY id LIMIT 10';
$tree = $parser->parse($sql);

$tree->name;                                     // 'start_entry', the grammar's start symbol
$tree->find('where_clause')[0]->text($sql);      // 'WHERE u.id = ?'
$tree->find('table_reference')[0]->text($sql);   // 'users u'
$tree->toString();                               // the statement again, byte for byte
```

## License

MIT License. See [LICENSE](LICENSE) for details.
