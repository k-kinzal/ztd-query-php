# ZTD Query PDO Adapter

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Documentation](https://img.shields.io/badge/docs-API-blue)](https://k-kinzal.github.io/ztd-query-php/pdo/k-kinzal/ztd-query-pdo-adapter/)

Test SQL through a PDO-compatible connection without changing physical table data. `ZtdPdo` wraps a native `PDO` connection, rewrites reads against an in-memory shadow, and simulates writes. `ZtdPdoStatement` retains the familiar preparation, binding, execution, and fetch interfaces.

MySQL, PostgreSQL, and SQLite are supported through their respective ZTD platform packages. PHP 8.1 or later and the corresponding PDO driver are required.

## Installation

Install the adapter and the platform used by your application:

```bash
composer require --dev k-kinzal/ztd-query-pdo-adapter k-kinzal/ztd-query-sqlite
```

Use `k-kinzal/ztd-query-mysql` or `k-kinzal/ztd-query-postgres` for the other platforms. The adapter detects the platform from the wrapped connection; an explicit session factory can also be supplied.

## Quick start

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;

$native = new PDO('sqlite::memory:');
$native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$pdo = ZtdPdo::fromPdo($native);

$insert = $pdo->prepare('INSERT INTO users VALUES (?, ?)');
$insert->execute([1, 'Ada']);
assert($insert->rowCount() === 1);

$select = $pdo->prepare('SELECT name FROM users WHERE id = ?');
$select->execute([1]);
assert($select->fetchColumn() === 'Ada');
assert($native->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0);
```

The schema belongs to the native connection. Fixture rows and simulated mutations belong to the wrapper's session. Reusing a prepared statement reads the current shadow on each execution.

## Guides

- [Connections and configuration](docs/connections.md): wrap an existing connection, select a platform, and toggle native access.
- [Statements and transactions](docs/statements.md): bind parameters, inspect affected rows, fetch `RETURNING` results, and roll back shadow changes.
- [PostgreSQL COPY](docs/copy.md): import and export shadow rows through the PDO COPY methods.
- [API reference](https://k-kinzal.github.io/ztd-query-php/pdo/k-kinzal/ztd-query-pdo-adapter/): public classes and executable examples.
- [SQL support matrix](https://github.com/k-kinzal/ztd-query-php/blob/main/docs/sql-support-matrix.md): supported statements and platform limitations.

A write without a result clause reports its affected count through `rowCount()`; it does not expose rows through `fetchAll()`. Use a supported `RETURNING` clause or a subsequent `SELECT` when the test needs the modified values.

## License

MIT License. See [LICENSE](LICENSE).
