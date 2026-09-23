# ZTD Query PHP

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)

A Zero Table Dependency testing library for PHP 8.1+ that enables SQL unit testing without modifying physical databases.

ZTD Query PHP wraps PDO/MySQLi to intercept and transform SQL queries using CTE (Common Table Expression) shadowing. This allows you to test SQL queries against fixture data using the real MySQL engine, without migrations, data seeding, or cleanup.

## Packages

| Package | Description |
|---------|-------------|
| [ztd-query-core](packages/ztd-query-core/) | Core library: interfaces, session management, query routing |
| [ztd-query-mysql](packages/ztd-query-mysql/) | MySQL platform: SQL parsing, classification, rewriting, schema reflection |
| [ztd-query-pdo-adapter](packages/ztd-query-pdo-adapter/) | PDO adapter: drop-in `ZtdPdo` / `ZtdPdoStatement` |
| [ztd-query-mysqli-adapter](packages/ztd-query-mysqli-adapter/) | MySQLi adapter: drop-in `ZtdMysqli` / `ZtdMysqliStatement` |
| [sql-faker](packages/sql-faker/) | Faker provider for generating syntactically valid SQL · [Documentation](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-faker/) |
| [sql-fixture](packages/sql-fixture/) | Faker provider for generating test fixture data from schemas |
| [sql-catalog](packages/sql-catalog/) | Catalogs the SQL an application issues, by static analysis |
| [container](packages/container/) | Shared MySQL and PostgreSQL containers for integration and fuzz tests |
| [lemon-parser](packages/lemon-parser/) | Parser for Lemon grammar files, producing a lossless syntax tree · [Documentation](https://k-kinzal.github.io/ztd-query-php/k-kinzal/lemon-parser/) |
| [bison-parser](packages/bison-parser/) | Parser for GNU Bison grammar files, producing a lossless syntax tree · [Documentation](https://k-kinzal.github.io/ztd-query-php/k-kinzal/bison-parser/) |
| [sql-parser](packages/sql-parser/) | LALR(1) SQL parsers for MySQL, PostgreSQL and SQLite built from the official grammars |

## Quick Start

```bash
composer require --dev k-kinzal/ztd-query-pdo-adapter
```

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;

$pdo = new ZtdPdo('mysql:host=localhost;dbname=test', 'user', 'password');

$pdo->exec('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
$pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice')");

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([1]);
$result = $stmt->fetchAll();
// [['id' => 1, 'name' => 'Alice']]
```

See [packages/ztd-query-core/README.md](packages/ztd-query-core/README.md) for full documentation.

## SQL semantics

[`packages/sql-semantics`](packages/sql-semantics/) implements the semantic phase
of a database front end. It accepts schema DDL and SELECT strings and returns
bound statements with types, conservative NULL facts, value provenance, and
relational structure for fixture generation and SQL metadata consumers. See its
[semantic design](packages/sql-semantics/docs/design.md) and
[supported surface](packages/sql-semantics/docs/support.md).

## Grammar coverage seeds

[seeds/](seeds/) holds PHP-Fuzzer inputs with which sql-faker takes every production of its default MySQL, PostgreSQL and SQLite grammars. [seeds/README.md](seeds/README.md) explains how to replay them through the fuzz targets of this repository or fuzz from them.

## License

MIT License. See [LICENSE](LICENSE) for details.

### Requirements

[`k-kinzal/requirements`](packages/requirements/README.md) links source quotations, EARS specifications and executable tests. It reports source coverage, unsupported behavior with reasons, independent specifications and differential CI gates.
