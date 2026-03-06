# ZTD Query MySQL

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)

MySQL platform support for [ZTD Query PHP](https://github.com/k-kinzal/ztd-query-core). Provides SQL parsing, classification, rewriting, and schema management for MySQL.

## Overview

This package implements the MySQL-specific logic for ZTD (Zero Table Dependency) query transformation. It handles:

- **SQL Parsing** - Parse MySQL statements using [phpMyAdmin SQL Parser](https://github.com/phpmyadmin/sql-parser)
- **Query Classification** - Classify queries as READ, WRITE_SIMULATED, or DDL_SIMULATED
- **CTE Rewriting** - Transform SELECT queries to use CTE-shadowed fixture data
- **Result Select Query** - Convert INSERT/UPDATE/DELETE/REPLACE into SELECT queries returning affected rows
- **Schema Management** - Reflect and track MySQL table definitions for virtual DDL operations
- **Error Classification** - Identify MySQL-specific error codes for unknown schema detection

This package is used internally by the [PDO adapter](https://github.com/k-kinzal/ztd-query-pdo-adapter) and [MySQLi adapter](https://github.com/k-kinzal/ztd-query-mysqli-adapter), but can also be used directly for custom adapter implementations.

## Requirements

- PHP 8.1 or higher
- [k-kinzal/ztd-query-php](https://github.com/k-kinzal/ztd-query-core) (core)

## Installation

```bash
composer require k-kinzal/ztd-query-mysql
```

## Usage

### Creating a MySQL Session

`MySqlSessionFactory` is the main entry point. It creates a fully configured `Session` instance for MySQL:

```php
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Platform\MySql\MySqlSessionFactory;

// $connection implements ZtdQuery\Connection\ConnectionInterface
$session = MySqlSessionFactory::create($connection, ZtdConfig::default());
```

The factory automatically:
1. Reflects the database schema via `SHOW CREATE TABLE`
2. Sets up the SQL parser, query guard, and all transformers
3. Configures the shadow store for virtual write tracking

### Query Classification

`MySqlQueryGuard` classifies SQL statements into query kinds:

```php
use ZtdQuery\Platform\MySql\MySqlQueryGuard;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Rewrite\QueryKind;

$parser = new MySqlParser();
$guard = new MySqlQueryGuard($parser);

$guard->classify('SELECT * FROM users');
// => QueryKind::READ

$guard->classify('INSERT INTO users (name) VALUES ("Alice")');
// => QueryKind::WRITE_SIMULATED

$guard->classify('CREATE TABLE logs (id INT)');
// => QueryKind::DDL_SIMULATED

$guard->classify('BEGIN');
// => null (unsupported)
```

### SQL Rewriting

`MySqlRewriter` transforms SQL statements for ZTD execution:

```php
use ZtdQuery\Platform\MySql\MySqlRewriter;

// Rewrite a single statement
$plan = $rewriter->rewrite('SELECT email FROM users WHERE id = 1');
// $plan->sql() returns the CTE-shadowed query
// $plan->kind() returns the QueryKind

// Rewrite multiple statements (e.g., multi-query)
$plans = $rewriter->rewriteMultiple('SELECT 1; SELECT 2');
```

### Error Classification

`MySqlErrorClassifier` identifies MySQL error codes related to unknown schemas:

```php
use ZtdQuery\Platform\MySql\MySqlErrorClassifier;

$classifier = new MySqlErrorClassifier();

$classifier->isUnknownSchemaError(1146); // true (Table doesn't exist)
$classifier->isUnknownSchemaError(1054); // true (Unknown column)
$classifier->isUnknownSchemaError(1064); // false (Syntax error)
```

## Architecture

```
MySqlSessionFactory
    |
    +-- MySqlParser (SQL parsing via phpmyadmin/sql-parser)
    +-- MySqlQueryGuard (query classification)
    +-- MySqlSchemaReflector (database schema reflection)
    +-- MySqlSchemaParser (CREATE TABLE parsing)
    +-- MySqlRewriter (query rewriting orchestrator)
    |       +-- MySqlTransformer
    |       |       +-- SelectTransformer (CTE injection)
    |       |       +-- InsertTransformer (INSERT -> SELECT)
    |       |       +-- UpdateTransformer (UPDATE -> SELECT)
    |       |       +-- DeleteTransformer (DELETE -> SELECT)
    |       |       +-- ReplaceTransformer (REPLACE -> SELECT)
    |       +-- MySqlMutationResolver (virtual DDL tracking)
    +-- MySqlErrorClassifier (error code classification)
```

## SQL Support

### Fully Supported

- **SELECT**: All clauses including JOIN, GROUP BY, HAVING, ORDER BY, LIMIT, UNION, subqueries, CTEs, window functions
- **INSERT**: VALUES, SELECT, ON DUPLICATE KEY UPDATE, IGNORE
- **REPLACE**
- **UPDATE**: Single/multi-table with ORDER BY/LIMIT
- **DELETE**: Single/multi-table with ORDER BY/LIMIT
- **TRUNCATE**
- **DDL**: CREATE TABLE, ALTER TABLE, DROP TABLE (virtual schema)
- **WITH**: CTE and recursive CTE

### Unsupported

- Stored procedures, triggers, functions, views
- Database/schema operations
- User/permission management
- Server operations (FLUSH, RESET, etc.)

## Development

```bash
# Run tests
composer test

# Run linter (PHP-CS-Fixer + PHPStan level max)
composer lint

# Run fuzz tests
composer fuzz:robustness
composer fuzz:robustness:classify
composer fuzz:robustness:rewrite

# Fix code style
composer format
```

## License

MIT License. See [LICENSE](LICENSE) for details.
