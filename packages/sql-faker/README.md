# SQL Faker

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)

`sql-faker` is a [FakerPHP](https://github.com/FakerPHP/Faker) provider for generating SQL from supported-language grammars compiled from upstream database grammar snapshots.
It currently ships providers for MySQL, PostgreSQL, and SQLite and is intended for syntax fuzzing, parser testing, and SQL-heavy test fixture generation.

## Overview

- Grammar-driven generation from upstream grammars: MySQL `sql_yacc.yy`, PostgreSQL `gram.y`, and SQLite `parse.y`
- Supported-language compilation is an explicit phase before generation; there is no post-generation syntax repair
- Full statement generation plus dialect-specific fragments and lexical helpers
- Seeded Faker generators produce deterministic output for a fixed dialect, grammar version, start rule, and `maxDepth`
- `maxDepth` bounds recursive growth; once reached, the generator prefers the shortest terminating branch defined by the contract
- The package targets a documented supported language for syntax fuzzing, not full semantic validity against a live database

## Requirements

- PHP 8.1 or higher
- `fakerphp/faker` ^1.23

## Supported Dialects

| Dialect | Provider | Package grammar coverage | Default grammar snapshot | Available grammar snapshots |
|---------|----------|--------------------------|--------------------------|-----------------------------|
| MySQL | `SqlFaker\MySqlProvider` | `MySQL 5.6`, `5.7`, `8.0`, `8.1`, `8.2`, `8.3`, `8.4`, `9.0`, `9.1` | `mysql-8.4.7` | `mysql-5.6.51`, `mysql-5.7.44`, `mysql-8.0.44`, `mysql-8.1.0`, `mysql-8.2.0`, `mysql-8.3.0`, `mysql-8.4.7`, `mysql-9.0.1`, `mysql-9.1.0` |
| PostgreSQL | `SqlFaker\PostgreSqlProvider` | `PostgreSQL 17` | `pg-17.2` | `pg-17.2` |
| SQLite | `SqlFaker\SqliteProvider` | `SQLite 3` | `sqlite-3.47.2` | `sqlite-3.47.2` |

## Installation

```bash
composer require --dev k-kinzal/sql-faker
```

## Quick Start

Constructing a provider automatically registers it with the Faker generator.

```php
use Faker\Factory;
use SqlFaker\MySql\StatementType;
use SqlFaker\MySqlProvider;

$faker = Factory::create();
new MySqlProvider($faker);

$anySql = $faker->sql();
$select = $faker->sql(StatementType::Select, maxDepth: 6);
$insert = $faker->insertStatement(maxDepth: 6);
$expr = $faker->expr(maxDepth: 3);
$identifier = $faker->quotedIdentifier();
```

For PostgreSQL and SQLite, swap the provider and matching enum:

```php
use Faker\Factory;
use SqlFaker\PostgreSql\StatementType;
use SqlFaker\PostgreSqlProvider;

$faker = Factory::create();
new PostgreSqlProvider($faker, 'pg-17.2');

$sql = $faker->sql(StatementType::Delete, maxDepth: 6);
```

```php
use Faker\Factory;
use SqlFaker\Sqlite\StatementType;
use SqlFaker\SqliteProvider;

$faker = Factory::create();
new SqliteProvider($faker);

$sql = $faker->sql(StatementType::CreateTable, maxDepth: 6);
```

## Development

Relevant design documents:

- [`docs/algorithm.md`](docs/algorithm.md): generation algorithm, contract phases, and derivation rules
- [`docs/supported-language-design.md`](docs/supported-language-design.md): supported-language breadth, rewrite judgment, and ZTD-fuzzing-oriented sufficiency criteria

Rebuild or verify the upstream grammar snapshots with the package build scripts:

```bash
composer build
composer build:verify
composer spec
```

## License

MIT License. See [LICENSE](LICENSE) for details.
