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

## Package development

Every package uses `src/`, `tests/`, `fuzz/`, and `bench/`, with the same Composer
commands and root configuration files. Test containers and PHPStan stubs live in
`tests/Container/` and `tests/Stub/`. SQL Faker additionally keeps its grammar
inputs in `resources/`, compiler entry points in `bin/`, and design notes in `docs/`.
Generated files are ignored; package lock files are committed. Development files
and lock files are excluded from release archives.

Shared dependency constraints and locked versions must agree across all packages.
After updating a shared dependency, update the other package lock files and run:

```sh
python3 .github/bin/package_policy.py
```

The root `deptrac.yaml` checks package boundaries: core defines shared contracts,
MySQL/PostgreSQL/SQLite implement them, and PDO/MySQLi adapt native connections.
Each package's `deptrac.yaml` checks its internal layers. Database-specific stubs,
fuzz targets, container setup, and mutation timeouts follow that package's needs.

Common commands, run from any package directory:

```sh
composer install
composer lint
composer test
composer test:unit
composer test:coverage
composer doctest
composer bench:quick
composer docgen
```

Packages with database integration tests also provide `composer test:integration`.
The docs workflows use unit-test coverage consistently for every package; database
integration tests and doctests remain in each package's CI. Both main and PR docs
build one site containing all packages, their dependency graph, and test links.
After installing dependencies and running `composer test:coverage` in every
package, build the complete site from the repository root with:

```sh
python3 .github/bin/docs.py
# Compare against main:
python3 .github/bin/docs.py --diff=origin/main
```
