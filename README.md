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
| [sql-formatter](packages/sql-formatter/) | SQL formatting with Compact, Expanded, Tabular, and River layouts, preserving concrete syntax |

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
of a database front end. It accepts schema DDL and SQL statements and returns
bound statements with types, conservative NULL facts, value provenance, and
relational structure for fixture generation and SQL metadata consumers. See its
[schema API](packages/sql-semantics/docs/schema.md),
[binder API](packages/sql-semantics/docs/binder.md), and
[limitations](packages/sql-semantics/docs/limitation.md).

## Generated documentation

Generate the API documentation for all packages from the repository root:

```bash
composer install
composer docgen
```

The site is written to `build/docs/`. Use `composer docgen:serve` to preview it
locally, or `composer docgen:diff` to compare the working tree with `origin/main`.

DocGen is provided by `k-kinzal/php-ai-toolkit`. The committed lock files select
its doc-ui renderer, which bundles the exact
[document-design v1.0.0 stylesheet](https://k-kinzal.github.io/document-design/v1.0.0/document-design.css)
as `assets/document-design-v1.0.0.css`, with its license and SHA-256 notice.
Generated HTML uses doc-ui's `.doc` layout and components. The design stays
fixed at v1.0.0 and works offline; no floating CDN version is loaded.

## License

MIT License. See [LICENSE](LICENSE) for details.

### Requirements

[`k-kinzal/requirements`](packages/requirements/README.md) links source quotations, EARS specifications and executable tests. It reports source coverage, unsupported behavior with reasons, independent specifications and differential CI gates.
