# SQL Fixture

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)

A [FakerPHP](https://github.com/FakerPHP/Faker) provider for generating test fixture data from SQL schemas. Parses CREATE TABLE statements and generates type-appropriate fake data.

## Overview

SQL Fixture reads your database schema (CREATE TABLE) and automatically generates realistic fixture data with correct types, lengths, and constraints. It supports MySQL and SQLite with three usage modes:

- **`FixtureProvider`** - Generate fixtures from CREATE TABLE SQL strings
- **`DatabaseFixtureProvider`** - Generate fixtures from a live database connection (PDO)
- **`FileFixtureProvider`** - Generate fixtures from a directory of DDL files

All modes support object hydration via `ReflectionHydrator`, converting generated data directly into typed PHP objects.

## Requirements

- PHP 8.1 or higher
- [fakerphp/faker](https://github.com/FakerPHP/Faker) ^1.23
- [phpmyadmin/sql-parser](https://github.com/phpmyadmin/sql-parser) ^5.11

## Installation

```bash
composer require --dev k-kinzal/sql-fixture
```

## Usage

### From SQL Strings

Use `FixtureProvider` when you have CREATE TABLE SQL available:

```php
use Faker\Factory;
use SqlFixture\FixtureProvider;

$faker = Factory::create();
$faker->addProvider(new FixtureProvider($faker));

$fixture = $faker->fixture(
    'CREATE TABLE users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL,
        age INT UNSIGNED,
        status ENUM("active", "inactive"),
        balance DECIMAL(10, 2),
        created_at DATETIME
    )'
);
// Returns: ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 28, ...]
// Note: auto_increment columns (id) are skipped by default
```

### From Database Connection

Use `DatabaseFixtureProvider` with a PDO connection to generate fixtures from your actual database schema:

```php
use Faker\Factory;
use SqlFixture\DatabaseFixtureProvider;

$pdo = new PDO('mysql:host=localhost;dbname=myapp', 'user', 'password');
$faker = Factory::create();
$faker->addProvider(new DatabaseFixtureProvider($faker, $pdo));

// Generate fixture by table name
$fixture = $faker->fixture('users');
// Schema is fetched via SHOW CREATE TABLE and cached
```

### From DDL Files

Use `FileFixtureProvider` with a directory containing `.sql` files:

```php
use Faker\Factory;
use SqlFixture\FileFixtureProvider;

// Directory contains: users.sql, posts.sql, comments.sql
$faker = Factory::create();
$faker->addProvider(new FileFixtureProvider($faker, '/path/to/ddl/'));

$fixture = $faker->fixture('users');

// List available tables
$tables = $faker->getTableNames(); // ['users', 'posts', 'comments']

// Register additional schemas at runtime
$faker->registerSchema('CREATE TABLE tags (id INT PRIMARY KEY, name VARCHAR(50))');
```

### Overriding Values

Pass specific values to override generated data:

```php
$fixture = $faker->fixture(
    'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))',
    ['id' => 1, 'name' => 'Alice']
);
// Returns: ['id' => 1, 'name' => 'Alice', 'email' => '<generated>']
```

### Object Hydration

Generate typed PHP objects directly using `ReflectionHydrator`:

```php
class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $userName,
        public readonly string $email,
    ) {}
}

$user = $faker->fixture(
    'CREATE TABLE users (id INT PRIMARY KEY, user_name VARCHAR(255), email VARCHAR(255))',
    ['id' => 1],
    User::class
);
// Returns: User(id: 1, userName: 'generated_name', email: 'generated@example.com')
```

The hydrator handles:
- Constructor parameter matching (exact name or snake_case to camelCase conversion)
- Type casting (string to int/float/bool, JSON decode for arrays)
- Default values and nullable parameters
- Property-based hydration for classes without constructors

### SQLite Support

```php
use SqlFixture\FixtureProvider;
use SqlFixture\Platform\PlatformFactory;

// Specify dialect explicitly
$faker->addProvider(new FixtureProvider($faker, dialect: PlatformFactory::DRIVER_SQLITE));

$fixture = $faker->fixture(
    'CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        score REAL
    )'
);

// DatabaseFixtureProvider auto-detects dialect from PDO driver
$pdo = new PDO('sqlite::memory:');
$faker->addProvider(new DatabaseFixtureProvider($faker, $pdo));
// SQLite dialect is automatically detected
```

## Supported Types

### MySQL

| Category | Types |
|----------|-------|
| Integer | TINYINT, SMALLINT, MEDIUMINT, INT, BIGINT |
| Decimal | FLOAT, DOUBLE, REAL, DECIMAL, NUMERIC |
| String | CHAR, VARCHAR, TINYTEXT, TEXT, MEDIUMTEXT, LONGTEXT |
| Binary | BINARY, VARBINARY, TINYBLOB, BLOB, MEDIUMBLOB, LONGBLOB |
| Enum/Set | ENUM, SET |
| Date/Time | DATE, TIME, DATETIME, TIMESTAMP, YEAR |
| JSON | JSON |
| Boolean | BOOL, BOOLEAN |
| Bit | BIT |
| Spatial | POINT, LINESTRING, POLYGON, MULTIPOINT, MULTILINESTRING, MULTIPOLYGON, GEOMETRYCOLLECTION |

### SQLite

| Affinity | Mapped Types |
|----------|-------------|
| INTEGER | INT, INTEGER, TINYINT, SMALLINT, MEDIUMINT, BIGINT, INT2, INT8 |
| TEXT | CHAR, VARCHAR, TEXT, CLOB, TINYTEXT, MEDIUMTEXT, LONGTEXT |
| REAL | FLOAT, DOUBLE, REAL |
| BLOB | BLOB, BINARY, VARBINARY |
| NUMERIC | BOOLEAN, DATE, DATETIME, TIMESTAMP, DECIMAL |

## Column Handling

- **AUTO_INCREMENT / AUTOINCREMENT** columns are skipped (not generated)
- **Generated columns** (`AS` expressions) are skipped
- **Nullable columns** have a 10% chance of returning null/default
- **UNSIGNED** constraint is respected for numeric types
- **Length/precision/scale** are respected for string and decimal types
- **ENUM/SET** values are randomly selected from the declared options
- **DEFAULT** values are used when a nullable column generates null

## Extensibility

Customize behavior by providing your own implementations:

```php
use SqlFixture\FixtureProvider;
use SqlFixture\TypeMapper\TypeMapperInterface;
use SqlFixture\Schema\SchemaParserInterface;
use SqlFixture\Hydrator\HydratorInterface;

$provider = new FixtureProvider(
    $faker,
    typeMapper: $customTypeMapper,      // Custom type-to-value mapping
    hydrator: $customHydrator,          // Custom object hydration
    schemaParser: $customSchemaParser,  // Custom CREATE TABLE parsing
    dialect: 'mysql',
);
```

## Development

```bash
# Run unit tests
composer test:unit

# Run all tests
composer test

# Run linter (PHP-CS-Fixer + PHPStan level max)
composer lint

# Run fuzz tests
composer fuzz:create-table
composer fuzz:insert-select

# Fix code style
composer format
```

## License

MIT License. See [LICENSE](LICENSE) for details.
