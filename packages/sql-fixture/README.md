# SQL Fixture

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--fixture-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-fixture/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Fixture is a [FakerPHP](https://fakerphp.org/) provider that generates fixture rows from table definitions for MySQL, PostgreSQL, and SQLite. It reads the schema from CREATE TABLE statements (`FixtureProvider`), a live PDO connection (`DatabaseFixtureProvider`), or a directory of DDL files (`FileFixtureProvider`), and generates values that match each column's type, length, and constraints. Generated rows can be returned as arrays or hydrated into PHP objects, and related rows can be generated together from a fixture plan.

## Requirements

- PHP 8.1+
- [fakerphp/faker](https://github.com/FakerPHP/Faker) ^1.23

## Support Syntax

The following database versions are tested. Pass the dialect to `FixtureProvider` or `FileFixtureProvider` to select how CREATE TABLE statements are read; omitting it uses the default. `DatabaseFixtureProvider` selects the dialect from the PDO driver.

### MySQL

| Version | Dialect | Default |
|---------|---------|---------|
| 8.4.7 | `mysql` | Yes |

### PostgreSQL

| Version | Dialect | Default |
|---------|---------|---------|
| 17.2 | `pgsql` | |

### SQLite

| Version | Dialect | Default |
|---------|---------|---------|
| 3.x, as linked into PHP's `pdo_sqlite` | `sqlite` | |

## Installation

```bash
composer require --dev k-kinzal/sql-fixture
```

## Usage

```php
use Faker\Factory;
use SqlFixture\FixtureProvider;

$faker = Factory::create();
$faker->addProvider(new FixtureProvider($faker));

$user = $faker->fixture(
    'CREATE TABLE users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        status ENUM("active", "inactive")
    )',
    ['name' => 'Alice'],
);
// ['name' => 'Alice', 'status' => 'active']
```

## License

MIT License. See [LICENSE](LICENSE) for details.
