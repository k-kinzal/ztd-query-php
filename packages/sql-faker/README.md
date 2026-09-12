# SQL Faker

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-sql--faker-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/sql-faker/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)

A [FakerPHP](https://github.com/FakerPHP/Faker) provider for generating syntactically valid MySQL SQL statements based on MySQL's official Bison grammar (`sql_yacc.yy`).

## Overview

SQL Faker uses formal grammar derivation to generate random but syntactically valid MySQL SQL. It parses MySQL's actual Bison grammar definition and uses it to produce any MySQL statement type (DML, DDL, TCL, etc.) and SQL fragments (expressions, clauses, subqueries, CTEs).

- **Grammar-accurate** - Derived from MySQL's official `sql_yacc.yy`, not hand-written rules
- **Version-aware** - Supports MySQL 5.6 through 9.1, generating only syntax valid for the target version
- **Depth-controllable** - `maxDepth` parameter controls SQL complexity from simple to arbitrarily nested
- **Fragment generation** - Generate not just full statements but individual expressions, clauses, and subqueries
- **FakerPHP integration** - Standard Faker provider pattern, works with seeded generators for reproducibility

## Requirements

- PHP 8.1 or higher
- [fakerphp/faker](https://github.com/FakerPHP/Faker) ^1.23

## Installation

```bash
composer require --dev k-kinzal/sql-faker
```

## Usage

### Basic Usage

```php
use Faker\Factory;
use SqlFaker\MySqlProvider;

$faker = Factory::create();
$faker->addProvider(new MySqlProvider($faker));

// Generate random SQL statements
$faker->sql();              // Any valid MySQL statement
$faker->selectStatement();  // SELECT statement
$faker->insertStatement();  // INSERT statement
$faker->updateStatement();  // UPDATE statement
$faker->deleteStatement();  // DELETE statement
```

### Statement Types

```php
// DML
$faker->selectStatement();
$faker->insertStatement();
$faker->updateStatement();
$faker->deleteStatement();
$faker->replaceStatement();
$faker->truncateStatement();

// DDL
$faker->createTableStatement();
$faker->alterTableStatement();
$faker->dropTableStatement();
$faker->createIndexStatement();
$faker->dropIndexStatement();

// TCL
$faker->beginStatement();
$faker->commitStatement();
$faker->rollbackStatement();

// Any simple statement
$faker->simpleStatement();
```

### SQL Fragments

Generate individual SQL components for targeted testing:

```php
// Expressions
$faker->expr();           // Any expression
$faker->simpleExpr();     // Simple expression
$faker->literal();        // Literal value
$faker->predicate();      // Predicate (comparison)

// Clauses
$faker->whereClause();    // WHERE clause
$faker->orderClause();    // ORDER BY clause
$faker->limitClause();    // LIMIT clause

// Table references
$faker->tableReference();  // Table reference
$faker->joinedTable();     // Joined table expression
$faker->tableIdent();      // Table identifier

// Subqueries and CTEs
$faker->subquery();        // Subquery
$faker->withClause();      // CTE (WITH clause)
```

### Terminal Generators

Generate individual lexical tokens:

```php
$faker->identifier();              // e.g., "t1", "col42"
$faker->quotedIdentifier();        // e.g., "`my_table`"
$faker->stringLiteral();           // e.g., "'abc123'"
$faker->nationalStringLiteral();   // e.g., "N'abc'"
$faker->integerLiteral();          // e.g., "42"
$faker->longIntegerLiteral();      // e.g., "2147483647"
$faker->unsignedBigIntLiteral();   // e.g., "18446744073709551615"
$faker->decimalLiteral();          // e.g., "123.45"
$faker->floatLiteral();            // e.g., "1.23e10"
$faker->hexLiteral();              // e.g., "0xdeadbeef"
$faker->binaryLiteral();           // e.g., "0b1010"
```

### Controlling Complexity

Use `maxDepth` to control the complexity of generated SQL. Lower values produce simpler statements:

```php
$faker->selectStatement(maxDepth: 3);   // Simple SELECT
$faker->selectStatement(maxDepth: 6);   // Moderate SELECT
$faker->selectStatement();              // Complex SELECT (unlimited depth)
```

The generator uses shortest-path termination: once the target depth is reached, it selects the shortest production alternative at each step to terminate quickly.

### Specifying MySQL Version

```php
// Use specific MySQL version (default: mysql-8.4.7)
$faker->addProvider(new MySqlProvider($faker, 'mysql-5.7.44'));
```

Supported versions:

| Version | Tag |
|---------|-----|
| MySQL 5.6 | `mysql-5.6.51` |
| MySQL 5.7 | `mysql-5.7.44` |
| MySQL 8.0 | `mysql-8.0.44` |
| MySQL 8.1 | `mysql-8.1.0` |
| MySQL 8.2 | `mysql-8.2.0` |
| MySQL 8.3 | `mysql-8.3.0` |
| MySQL 8.4 | `mysql-8.4.7` (default) |
| MySQL 9.0 | `mysql-9.0.1` |
| MySQL 9.1 | `mysql-9.1.0` |

### Reproducible Generation

Use a seeded Faker generator for reproducible SQL output:

```php
$faker = Factory::create();
$faker->addProvider(new MySqlProvider($faker));
$faker->seed(12345);

// Same seed always produces the same SQL
$sql = $faker->selectStatement(maxDepth: 6);
```

## License

MIT License. See [LICENSE](LICENSE) for details.
