# SQL Parser

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--parser-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-parser/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Parser is a set of LALR(1) parsers for MySQL, PostgreSQL, and SQLite written in PHP. Each parser is generated from the official grammar of a server release: `sql_yacc.yy` for MySQL, `gram.y` for PostgreSQL, and `parse.y` for SQLite. The lexers are ports of the servers' own scanners, so text is tokenized the way the server tokenizes it, and the syntax tree names the nonterminals of the upstream grammar. Grammar releases are selected at parse time.

The package has no runtime dependency beyond PHP and `ext-zlib`.

## Requirements

- PHP 8.1 or higher
- `ext-zlib`

## Supported grammars

Pass the release tag to select a grammar; omitting it uses the default for that dialect. The releases and their tags are the ones [sql-faker](../sql-faker/) generates SQL for.

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
```

`PostgreSqlParser` and `SqliteParser` work the same way. Every token of the text is a leaf of the tree, and every node is named after the rule of the upstream grammar that built it, so the tree of a MySQL statement is shaped exactly as `sql_yacc.yy` shapes it. A rejected statement raises `SyntaxException` with the offending token, its position, and the terminals the parser would have accepted; text no token starts with raises `LexicalException`. Both extend `SourceException`.

```php
use SqlParser\MySql\MySqlParser;
use SqlParser\MySql\SqlMode;

$parser = new MySqlParser(mode: new SqlMode(ansiQuotes: true));
$parser->tokenize('SELECT "x"')[1]->name;       // 'IDENT_QUOTED'
```

MySQL's `sql_mode` flags that change tokenization, `ANSI_QUOTES`, `PIPES_AS_CONCAT`, `HIGH_NOT_PRECEDENCE`, `NO_BACKSLASH_ESCAPES` and `IGNORE_SPACE`, are passed as `SqlMode`.

See [docs/usage.md](docs/usage.md) for the tree API and [docs/architecture.md](docs/architecture.md) for how the tables are built.

## How the parsers are built

`bin/build-mysql.php`, `bin/build-pg.php` and `bin/build-sqlite.php` download the grammar and keyword sources of each release, read them with the package's own Bison and Lemon readers, build the LALR(1) automaton, resolve conflicts with the same precedence rules Bison and Lemon apply, and write the tables under `resources/`. A build refuses to write a table whose conflicts differ from what the grammar declares with `%expect`, which is how the generator is checked against Bison on every release.

```bash
composer build-mysql -- --all
composer build-pg
composer build-sqlite
```

## Fuzzing

`composer fuzz:mysql`, `composer fuzz:pg` and `composer fuzz:sqlite` generate statements with sql-faker and require every one of them to parse. See [fuzz/README.md](fuzz/README.md).

## License

MIT License. See [LICENSE](LICENSE) for details.
