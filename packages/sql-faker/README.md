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
use SqlFaker\MySql\MySqlProvider;

$faker = Factory::create();
$faker->addProvider(new MySqlProvider($faker));

$sql = $faker->sql(maxDepth: 6);
$select = $faker->selectStatement(maxDepth: 6);
```

## Grammar coverage seeds

[seeds/](seeds/) holds PHP-Fuzzer corpora that make each default grammar take every production reachable from its statement rule; its README explains how to replay them through a fuzz target. `bin/seeds.php` builds and checks them:

```sh
php bin/seeds.php build --tag mysql-8.4.7
php bin/seeds.php check
```

`SqlFaker\Generation\Choice\BytePlanEncoder` is the inverse of `BytePlanCompiler`: it records the choices of one guided plan construction as the bytes the compiler decodes to the same plan, which is how the seeds are produced.

## License

MIT License. See [LICENSE](LICENSE) for details.

## Architecture

The core consists of `Grammar` (the grammar model and resources), `Generation`
(the planning and generation engine and its lexical/rewrite contracts), and
`Compiler` (upstream grammar readers). These concepts contain no database names
and do not depend on database implementations.

`MySql`, `PostgreSql`, and `Sqlite` each own their provider, grammar declarations,
lexical behavior, rewrites, and generator factory. Each implementation depends
on core contracts; implementations never depend on one another. Build utilities
in `bin/` are development tools, not a shipped CLI layer.

`Compatibility` preserves the original provider names and convenience factory
as inward-facing adapters. New code should use the providers and factories in
the database namespaces. Core and platform code cannot depend on compatibility.

`composer deptrac` enforces dependency direction without exceptions or uncovered
classes. `composer phpstan` also rejects database terms anywhere in core source,
including strings and PHPDoc, and rejects other database names in each platform.
