# ZTD Query PHP

[![CI](https://github.com/k-kinzal/ztd-query-php/actions/workflows/ci.yml/badge.svg)](https://github.com/k-kinzal/ztd-query-php/actions/workflows/ci.yml)
[![Fuzz](https://github.com/k-kinzal/ztd-query-php/actions/workflows/fuzz.yml/badge.svg)](https://github.com/k-kinzal/ztd-query-php/actions/workflows/fuzz.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)

A Zero Table Dependency testing library for PHP 8.1+ that enables SQL unit testing without modifying physical databases.

## Overview

ZTD Query PHP wraps PDO to intercept and transform SQL queries using CTE (Common Table Expression) shadowing. This allows you to:

- Test SQL queries against fixture data without migrations, data seeding, or cleanup
- Use the real MySQL engine for query execution (not mocks)
- Run tests in parallel with complete isolation
- Treat SQL as pure functions: input (fixtures) -> output (results)

### How It Works

**CTE Shadowing** - Table references in SELECT queries are replaced with CTEs containing your fixture data:

```sql
-- Original query
SELECT email FROM users WHERE id = 1

-- Transformed query (with fixture data)
WITH users AS (
  SELECT 1 AS id, 'alice@example.com' AS email
  UNION ALL
  SELECT 2 AS id, 'bob@example.com' AS email
)
SELECT email FROM users WHERE id = 1
```

**Result Select Query** - INSERT/UPDATE/DELETE statements are converted to SELECT queries that return the affected rows:

```sql
-- Original
UPDATE users SET name = 'Alice' WHERE id = 1

-- Transformed (returns rows that would be affected)
WITH users AS (...fixture data...)
SELECT id, 'Alice' AS name FROM users WHERE id = 1
```

## Requirements

- PHP 8.1 or higher
- MySQL 5.6 - 9.1
- PDO extension

## Installation

```bash
composer require --dev k-kinzal/ztd-query-php
```

## Usage

### Basic Example

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;

// Create ZTD-wrapped PDO connection
$pdo = new ZtdPdo('mysql:host=localhost;dbname=test', 'user', 'password');

// Define schema and insert fixture data
$pdo->exec('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
$pdo->exec("INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@example.com')");
$pdo->exec("INSERT INTO users (id, name, email) VALUES (2, 'Bob', 'bob@example.com')");

// Execute queries against fixture data (no physical table access)
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([1]);
$result = $stmt->fetchAll();
// Returns: [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]
```

### Wrapping Existing PDO

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;

$existingPdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'password');

// Wrap without creating a new connection
$ztdPdo = ZtdPdo::fromPdo($existingPdo);
```

### Testing Write Operations

```php
$pdo->exec('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
$pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice')");

// INSERT returns the inserted row data
$stmt = $pdo->prepare('INSERT INTO users (id, name) VALUES (?, ?)');
$stmt->execute([2, 'Bob']);
$inserted = $stmt->fetchAll();
// Returns: [['id' => 2, 'name' => 'Bob']]

// UPDATE returns the updated row data
$stmt = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');
$stmt->execute(['Alice Updated', 1]);
$updated = $stmt->fetchAll();
// Returns: [['id' => 1, 'name' => 'Alice Updated']]

// DELETE returns the deleted row data
$stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
$stmt->execute([1]);
$deleted = $stmt->fetchAll();
// Returns: [['id' => 1, 'name' => 'Alice']]
```

### Enabling/Disabling ZTD Mode

```php
$pdo = new ZtdPdo($dsn, $user, $password);

// Disable ZTD to execute against physical database
$pdo->disableZtd();
$pdo->exec('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');

// Re-enable ZTD for testing
$pdo->enableZtd();
```

## Configuration

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\UnknownSchemaBehavior;

$config = new ZtdConfig(
    // How to handle unsupported SQL statements (default behavior)
    unsupportedBehavior: UnsupportedSqlBehavior::Exception, // or Ignore, Notice

    // How to handle references to unknown tables
    unknownSchemaBehavior: UnknownSchemaBehavior::Exception, // or Passthrough

    // Per-pattern behavior rules (first match wins)
    behaviorRules: [
        // Prefix-based rules (case-insensitive)
        'BEGIN' => UnsupportedSqlBehavior::Ignore,
        'COMMIT' => UnsupportedSqlBehavior::Ignore,
        'ROLLBACK' => UnsupportedSqlBehavior::Ignore,

        // Regex-based rules (patterns starting with '/')
        '/^SET\s+SESSION/i' => UnsupportedSqlBehavior::Ignore,
        '/^SET\s+/i' => UnsupportedSqlBehavior::Notice,
    ],
);

$pdo = new ZtdPdo($dsn, $user, $password, config: $config);
```

### Configuration Options

| Option | Values | Description |
|--------|--------|-------------|
| `unsupportedBehavior` | `Ignore`, `Notice`, `Exception` | Default behavior when unsupported SQL is executed |
| `unknownSchemaBehavior` | `Passthrough`, `Exception` | Behavior when unknown table is referenced |
| `behaviorRules` | `array<string, UnsupportedSqlBehavior>` | Per-pattern behavior overrides (first match wins) |

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

### Ignored (No-op)

- Transaction control: BEGIN, COMMIT, ROLLBACK, SAVEPOINT

### Unsupported

- Stored procedures, triggers, functions, views
- Database/schema operations
- User/permission management
- Server operations (FLUSH, RESET, etc.)

See [docs/sql-support-matrix.md](docs/sql-support-matrix.md) for the complete support matrix.

## License

MIT License. See [LICENSE](LICENSE) for details.
