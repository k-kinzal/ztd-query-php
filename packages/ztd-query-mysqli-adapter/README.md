# ZTD Query MySQLi Adapter

[![CI](https://github.com/k-kinzal/ztd-query-php/actions/workflows/ztd-query-mysqli-adapter.yml/badge.svg)](https://github.com/k-kinzal/ztd-query-php/actions/workflows/ztd-query-mysqli-adapter.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)

MySQLi adapter for [ZTD Query PHP](https://github.com/k-kinzal/ztd-query-php). Drop-in replacement for mysqli that transparently applies Zero Table Dependency query transformation.

## Overview

This package provides `ZtdMysqli` and `ZtdMysqliStatement`, which extend `mysqli` and `mysqli_stmt` respectively. They intercept SQL queries and transform them using CTE (Common Table Expression) shadowing, enabling SQL unit testing without modifying physical databases.

- **Drop-in replacement** - `ZtdMysqli` extends `mysqli` and is type-compatible everywhere `mysqli` is expected
- **Transparent rewriting** - All queries are automatically rewritten at `prepare()`/`query()`/`execute_query()` time
- **Toggle on/off** - Enable or disable ZTD mode at runtime with `enableZtd()`/`disableZtd()`
- **Wrap existing connections** - Use `ZtdMysqli::fromMysqli()` to wrap an existing mysqli instance without creating a new connection

## Requirements

- PHP 8.1 or higher
- MySQLi extension
- MySQL 5.6 - 9.1
- [k-kinzal/ztd-query-php](https://github.com/k-kinzal/ztd-query-php) (core)
- [k-kinzal/ztd-query-mysql](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/ztd-query-mysql) (MySQL platform)

## Installation

```bash
composer require --dev k-kinzal/ztd-query-mysqli-adapter
```

## Usage

### Creating a New Connection

```php
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;

$mysqli = new ZtdMysqli('localhost', 'user', 'password', 'test');

// Define schema and insert fixture data
$mysqli->query('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255), email VARCHAR(255))');
$mysqli->query("INSERT INTO users (id, name, email) VALUES (1, 'Alice', 'alice@example.com')");
$mysqli->query("INSERT INTO users (id, name, email) VALUES (2, 'Bob', 'bob@example.com')");

// Query against fixture data (no physical table access)
$stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $id);
$id = 1;
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
// ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']
```

### Wrapping an Existing mysqli Instance

```php
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;

$existingMysqli = new mysqli('localhost', 'user', 'password', 'test');
$ztdMysqli = ZtdMysqli::fromMysqli($existingMysqli);
```

### Testing Write Operations

INSERT/UPDATE/DELETE statements are converted to SELECT queries that return the affected rows:

```php
$mysqli->query('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
$mysqli->query("INSERT INTO users (id, name) VALUES (1, 'Alice')");

// INSERT returns the inserted row data
$result = $mysqli->query("INSERT INTO users (id, name) VALUES (2, 'Bob')");
$row = $result->fetch_assoc();
// ['id' => 2, 'name' => 'Bob']

// UPDATE returns the updated row data
$result = $mysqli->query("UPDATE users SET name = 'Alice Updated' WHERE id = 1");
$row = $result->fetch_assoc();
// ['id' => 1, 'name' => 'Alice Updated']

// DELETE returns the deleted row data
$result = $mysqli->query("DELETE FROM users WHERE id = 1");
$row = $result->fetch_assoc();
// ['id' => 1, 'name' => 'Alice']
```

### Enabling/Disabling ZTD Mode

```php
$mysqli = new ZtdMysqli('localhost', 'user', 'password', 'test');

// Disable ZTD to execute against physical database
$mysqli->disableZtd();
$mysqli->query('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');

// Re-enable ZTD for testing
$mysqli->enableZtd();

// Check current status
$mysqli->isZtdEnabled(); // true
```

### Affected Row Count

Due to PHP's C extension property handler, `$mysqli->affected_rows` may not work reliably with ZTD operations. Use the dedicated method instead:

```php
$mysqli->query("INSERT INTO users (id, name) VALUES (1, 'Alice')");
$affectedRows = $mysqli->lastAffectedRows();
// 1
```

### Configuration

```php
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;
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

$mysqli = new ZtdMysqli('localhost', 'user', 'password', 'test', config: $config);
```

| Option | Values | Description |
|--------|--------|-------------|
| `unsupportedBehavior` | `Ignore`, `Notice`, `Exception` | Default behavior when unsupported SQL is executed |
| `unknownSchemaBehavior` | `Passthrough`, `Exception` | Behavior when unknown table is referenced |
| `behaviorRules` | `array<string, UnsupportedSqlBehavior>` | Per-pattern behavior overrides (first match wins) |

## API Reference

### ZtdMysqli

| Method | Description |
|--------|-------------|
| `__construct($hostname, $username, $password, $database, $port, $socket, $config)` | Create a new ZTD-wrapped mysqli connection |
| `ZtdMysqli::fromMysqli($mysqli, $config)` | Wrap an existing mysqli instance |
| `enableZtd()` | Enable ZTD mode |
| `disableZtd()` | Disable ZTD mode |
| `isZtdEnabled()` | Check whether ZTD mode is enabled |
| `lastAffectedRows()` | Get affected row count from the last ZTD or regular operation |
| `prepare($query)` | Prepare a statement (rewritten if ZTD enabled) |
| `query($query, $resultMode)` | Execute a query with ZTD processing |
| `real_query($query)` | Execute a query without fetching results |
| `execute_query($query, $params)` | Execute a parameterized query (PHP 8.2+) |

All other mysqli methods (`begin_transaction`, `commit`, `rollback`, `real_escape_string`, etc.) are delegated to the inner mysqli instance. Properties are delegated via `__get`/`__isset`.

### ZtdMysqliStatement

Extends `mysqli_stmt` with ZTD-aware behavior. `execute()`, `get_result()`, and `bind_param()` work transparently. Use `ztdAffectedRows()` to get the ZTD-aware affected row count for write operations.

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

MIT License. See [LICENSE](../../LICENSE) for details.
