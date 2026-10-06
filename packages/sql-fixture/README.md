# SQL Fixture

[![Packagist Downloads](https://img.shields.io/packagist/dt/k-kinzal/sql-fixture.svg?label=Packagist)](https://packagist.org/packages/k-kinzal/sql-fixture)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--fixture-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-fixture/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

SQL Fixture is a [FakerPHP](https://fakerphp.org/) provider that generates fixture rows from table definitions for MySQL, PostgreSQL, and SQLite. It reads the schema from CREATE TABLE statements (`FixtureProvider`), a live PDO connection (`DatabaseFixtureProvider`), or a directory of DDL files (`FileFixtureProvider`), and generates values that match each column's type, length, and constraints. Generated rows can be returned as arrays or hydrated into PHP objects, and related rows can be generated together from a fixture plan.

## Requirements

- PHP 8.1+
- [fakerphp/faker](https://github.com/FakerPHP/Faker) ^1.23
- [k-kinzal/sql-semantics](../sql-semantics/) with [sql-semantics-mysql](../sql-semantics-mysql/), [sql-semantics-postgres](../sql-semantics-postgres/), and [sql-semantics-sqlite](../sql-semantics-sqlite/), which read CREATE TABLE statements with the grammar and rules of each release

## Support Syntax

The following database versions are supported. Pass the dialect and, optionally, the version tag to `FixtureProvider` or `FileFixtureProvider`; omitting the version tag uses the default for that database. `DatabaseFixtureProvider` reads both from the connection.

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
| 16.6 | `pg-16.6` | |
| 17.2 | `pg-17.2` | Yes |

### SQLite

| Version | Version tag | Default |
|---------|-------------|---------|
| 3.47.2 | `sqlite-3.47.2` | Yes |

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

### How a statement is read

A CREATE TABLE statement is analyzed as the selected release analyzes it, so the schema matches the table the server would create:

- A statement the release refuses is rejected with `InvalidSqlException`, for example a duplicate column, two primary keys, a key on a column the table lacks, or syntax the release does not have. A foreign key may name a table the input does not declare.
- Names are compared and folded as the server does: an unquoted PostgreSQL name is folded to lower case, and a MySQL key names its column without regard to case.
- Types are named as the server names them. MySQL `INTEGER` is `INT` and `NUMERIC`, `DEC`, and `FIXED` are `DECIMAL`. PostgreSQL types are named after their catalog entries: `INT` is `INTEGER`, `DECIMAL` is `NUMERIC`, `TIMESTAMP WITH TIME ZONE` is `TIMESTAMPTZ`, and `CHARACTER VARYING` is `VARCHAR`. A SQLite type keeps its declared words, without a trailing `GENERATED ALWAYS`.
- A column admits NULL as the server decides: `NOT NULL` followed by `NULL` admits it in MySQL and not in SQLite, and PostgreSQL refuses the pair.
- A default written as a literal has its value, such as `'it''s'`, `-9.99`, `TRUE`, `X'41'`, or `'{}'::jsonb`. A default the server computes when a row is inserted, such as `CURRENT_TIMESTAMP`, `now()`, or `(1 + 2)`, has none (`null`).
- The input must create exactly one table with a column list. `CREATE TABLE ... LIKE`, `CREATE TABLE ... AS SELECT`, and a table without columns are rejected with `MissingColumnDefinitionsException`.

## License

MIT License. See [LICENSE](LICENSE) for details.
