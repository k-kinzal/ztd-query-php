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

The following database versions are supported. Pass the dialect and, optionally, the version tag to `FixtureProvider` or `FileFixtureProvider` to select the release the CREATE TABLE statements are read for; omitting the version tag uses the default for that database. `DatabaseFixtureProvider` reads the dialect and the release from the PDO connection, matching the version the server reports to the closest supported release. The version tags are the ones the other sql-* packages accept.

Every release is verified against the server itself: the schema each server reports is read back, and the rows generated from it are inserted into that server.

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

SQLite runs inside PHP, so `DatabaseFixtureProvider` reads the release `pdo_sqlite` links and matches it to the tag above.

## Installation

```bash
composer require --dev k-kinzal/sql-fixture
```

## Usage

```php
use Faker\Factory;
use SqlFixture\Provider\FixtureProvider;

$faker = Factory::create();
$faker->addProvider(new FixtureProvider($faker));

$user = $faker->fixture(
    'CREATE TABLE users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        status ENUM("active", "inactive") NOT NULL
    )',
    ['name' => 'Alice'],
);
// For example: ['name' => 'Alice', 'status' => 'active']
```

To read the statements as another release does, pass the dialect and the version tag:

```php
$provider = new FixtureProvider($faker, dialect: 'mysql', version: 'mysql-8.0.44');
$provider->getVersion(); // 'mysql-8.0.44'
```

`DatabaseFixtureProvider` detects the release of the connected server:

```php
use SqlFixture\Provider\DatabaseFixtureProvider;

$provider = new DatabaseFixtureProvider($faker, $pdo);
$provider->getVersion(); // For example: 'mysql-8.0.44' for a MySQL 8.0 server
```

## License

MIT License. See [LICENSE](LICENSE) for details.
