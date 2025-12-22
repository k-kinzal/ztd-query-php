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
| [sql-faker](packages/sql-faker/) | Faker provider for generating syntactically valid SQL |
| [sql-fixture](packages/sql-fixture/) | Faker provider for generating test fixture data from schemas |

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

## License

MIT License. See [LICENSE](LICENSE) for details.
