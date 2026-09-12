# SQL Faker

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--faker-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-faker/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Faker is a grammar-based SQL generator for MySQL, PostgreSQL, and SQLite. It derives random statements and SQL fragments from official grammar definitions and provides both a [FakerPHP](https://fakerphp.org/) provider interface and a direct `SqlGenerator` interface. Generation plans let you select syntax and constrain its structure. Generated SQL does not require a database connection; its execution and semantic validity are subject to the [algorithm's limitations](docs/algorithm.md#limitations).

## Requirements

- PHP 8.1 or higher
- [fakerphp/faker](https://github.com/FakerPHP/Faker) ^1.23

## Support Syntax

The following grammar versions are bundled. Pass the version tag to select a grammar; omitting it uses the default for that database. Syntax availability depends on the selected version.

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
composer require --dev k-kinzal/sql-faker
```

## Usage

```php
use Faker\Factory;
use SqlFaker\MySqlProvider;

$faker = Factory::create();
$faker->addProvider(new MySqlProvider($faker));

$sql = $faker->sql(maxDepth: 6);
$select = $faker->selectStatement(maxDepth: 6);
```

## License

MIT License. See [LICENSE](LICENSE) for details.
