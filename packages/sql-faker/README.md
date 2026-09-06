# SQL Faker

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)

A [FakerPHP](https://github.com/FakerPHP/Faker) provider for generating SQL for
MySQL, PostgreSQL, and SQLite from their official grammars.

Generate statements, expressions, clauses, and lexical tokens for testing SQL
parsers and other tools that consume SQL. Choose a database version, control
complexity with `maxDepth`, and seed Faker for reproducible input. Generated SQL
does not assume that particular tables or columns exist in your database.

## Requirements

- PHP 8.1 or higher
- [fakerphp/faker](https://github.com/FakerPHP/Faker) ^1.23

## Installation

```sh
composer require --dev k-kinzal/sql-faker
```

## Quick start

```php
require 'vendor/autoload.php';

$faker = \Faker\Factory::create();
$provider = new \SqlFaker\MySqlProvider($faker);
$faker->seed(12345);

$sql = $provider->selectStatement(maxDepth: 3);
```

Use `PostgreSqlProvider` or `SqliteProvider` for another dialect. Providers also
register their formatters with Faker, so `$faker->selectStatement(maxDepth: 3)`
is available after construction.

## Documentation

- [User guide](docs/usage.md): choose a provider, generate SQL, and control output.
- [API reference](docs/api.md): statements, fragments, tokens, and errors.
- [Supported versions](docs/versions.md): available database versions and defaults.

## License

MIT License. See [LICENSE](LICENSE) for details.
