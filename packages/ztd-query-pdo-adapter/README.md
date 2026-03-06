# ZTD Query PDO Adapter

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)

PDO adapter for [ZTD Query PHP](https://github.com/k-kinzal/ztd-query-core). Drop-in replacement for PDO that transparently applies Zero Table Dependency query transformation.

## Overview

This package provides `ZtdPdo` and `ZtdPdoStatement`, which extend `PDO` and `PDOStatement` respectively. They intercept SQL queries and transform them using CTE (Common Table Expression) shadowing, enabling SQL unit testing without modifying physical databases.

- **Drop-in replacement** - `ZtdPdo` extends `PDO` and is type-compatible everywhere `PDO` is expected
- **Transparent rewriting** - All queries are automatically rewritten at `prepare()`/`query()`/`exec()` time
- **Toggle on/off** - Enable or disable ZTD mode at runtime with `enableZtd()`/`disableZtd()`
- **Wrap existing connections** - Use `ZtdPdo::fromPdo()` to wrap an existing PDO instance without creating a new connection

## Requirements

- PHP 8.1 or higher
- PDO extension
- MySQL 5.6 - 9.1
- [k-kinzal/ztd-query-php](https://github.com/k-kinzal/ztd-query-core) (core)
- [k-kinzal/ztd-query-mysql](https://github.com/k-kinzal/ztd-query-mysql) (MySQL platform)

## Installation

```bash
composer require --dev k-kinzal/ztd-query-pdo-adapter
```

## Usage

### Creating a New Connection

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;

$pdo = new ZtdPdo('mysql:host=localhost;dbname=test', 'user', 'password');

// Define schema and insert fixture data
$pdo->exec('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
$pdo->exec("INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@example.com')");
$pdo->exec("INSERT INTO users (id, name, email) VALUES (2, 'Bob', 'bob@example.com')");

// Query against fixture data (no physical table access)
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([1]);
$result = $stmt->fetchAll();
// [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]
```

### Wrapping an Existing PDO Instance

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;

$existingPdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'password');
$ztdPdo = ZtdPdo::fromPdo($existingPdo);
```

### Testing Write Operations

INSERT/UPDATE/DELETE statements are converted to SELECT queries that return the affected rows:

```php
$pdo->exec('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
$pdo->exec("INSERT INTO users (id, name) VALUES (1, 'Alice')");

// INSERT returns the inserted row data
$stmt = $pdo->prepare('INSERT INTO users (id, name) VALUES (?, ?)');
$stmt->execute([2, 'Bob']);
$inserted = $stmt->fetchAll();
// [['id' => 2, 'name' => 'Bob']]

// UPDATE returns the updated row data
$stmt = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');
$stmt->execute(['Alice Updated', 1]);
$updated = $stmt->fetchAll();
// [['id' => 1, 'name' => 'Alice Updated']]

// DELETE returns the deleted row data
$stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
$stmt->execute([1]);
$deleted = $stmt->fetchAll();
// [['id' => 1, 'name' => 'Alice']]
```

### Enabling/Disabling ZTD Mode

```php
$pdo = new ZtdPdo($dsn, $user, $password);

// Disable ZTD to execute against physical database
$pdo->disableZtd();
$pdo->exec('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');

// Re-enable ZTD for testing
$pdo->enableZtd();

// Check current status
$pdo->isZtdEnabled(); // true
```

### Configuration

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\UnknownSchemaBehavior;

$config = new ZtdConfig(
    unsupportedBehavior: UnsupportedSqlBehavior::Exception,
    unknownSchemaBehavior: UnknownSchemaBehavior::Exception,
    behaviorRules: [
        'BEGIN' => UnsupportedSqlBehavior::Ignore,
        'COMMIT' => UnsupportedSqlBehavior::Ignore,
        'ROLLBACK' => UnsupportedSqlBehavior::Ignore,
    ],
);

$pdo = new ZtdPdo($dsn, $user, $password, config: $config);
```

| Option | Values | Description |
|--------|--------|-------------|
| `unsupportedBehavior` | `Ignore`, `Notice`, `Exception` | Default behavior when unsupported SQL is executed |
| `unknownSchemaBehavior` | `Passthrough`, `Exception` | Behavior when unknown table is referenced |
| `behaviorRules` | `array<string, UnsupportedSqlBehavior>` | Per-pattern behavior overrides (first match wins) |

## API Reference

### ZtdPdo

| Method | Description |
|--------|-------------|
| `__construct($dsn, $username, $password, $options, $config)` | Create a new ZTD-wrapped PDO connection |
| `ZtdPdo::fromPdo($pdo, $config)` | Wrap an existing PDO instance |
| `enableZtd()` | Enable ZTD mode |
| `disableZtd()` | Disable ZTD mode |
| `isZtdEnabled()` | Check whether ZTD mode is enabled |
| `prepare($query, $options)` | Prepare a statement (rewritten if ZTD enabled) |
| `query($query, $fetchMode, ...$fetchModeArgs)` | Execute a query and return the statement |
| `exec($statement)` | Execute a statement and return affected row count |

All other PDO methods (`beginTransaction`, `commit`, `rollBack`, `quote`, etc.) are delegated to the inner PDO instance.

### ZtdPdoStatement

Extends `PDOStatement` with ZTD-aware behavior. All fetch methods (`fetch`, `fetchAll`, `fetchColumn`, `fetchObject`) and parameter binding methods (`bindValue`, `bindParam`, `bindColumn`) work transparently. `rowCount()` returns the ZTD-aware affected row count for write operations.

## Development

```bash
# Run unit tests
composer test:unit

# Run integration tests (requires Docker)
composer test:integration

# Run all tests
composer test

# Run linter (PHP-CS-Fixer + PHPStan level max)
composer lint

# Fix code style
composer format
```

## License

MIT License. See [LICENSE](LICENSE) for details.
