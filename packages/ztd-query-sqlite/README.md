# ZTD Query SQLite

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![API Documentation](https://img.shields.io/badge/API-documentation-blue)](https://k-kinzal.github.io/ztd-query-php/sqlite/k-kinzal/ztd-query-sqlite/)

SQLite platform support for [ZTD Query PHP](https://github.com/k-kinzal/ztd-query-php). Parse and classify SQL, shadow table reads with fixture rows, simulate writes, and reflect SQLite schemas without changing physical tables.

## Overview

This package implements the SQLite-specific operations used by ZTD (Zero Table Dependency):

- Parse statement structure and classify reads, simulated writes, and virtual DDL.
- Rewrite reads using CTEs that shadow tables with fixture data.
- Convert INSERT, UPDATE, DELETE, and REPLACE into SELECT queries returning affected rows.
- Reflect SQLite table and view definitions and track virtual schema changes.
- Classify missing-schema errors reported by SQLite.

The [PDO adapter](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/ztd-query-pdo-adapter) provides the usual PDO interface. Custom adapters can compose this package directly through `SqliteSessionFactory` or the lower-level rewrite components.

## Requirements

- PHP 8.1 or higher
- [k-kinzal/ztd-query-core](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/ztd-query-core)
- PDO SQLite to execute the examples below

## Installation

```sh
composer require k-kinzal/ztd-query-sqlite
```

## Usage

Shadow a table with fixture rows for a SELECT. This example does not need a physical `users` table:

```php
require 'vendor/autoload.php';

$tables = [
    'users' => [
        'columns' => ['id', 'name'],
        'columnTypes' => [],
        'rows' => [['id' => 1, 'name' => 'Alice']],
    ],
];
$transformer = new \ZtdQuery\Platform\Sqlite\Transformer\SelectTransformer();
$sql = $transformer->transform('SELECT name FROM users WHERE id = 1', $tables);
$pdo = new \PDO('sqlite::memory:');

$pdo->query($sql)->fetchColumn(); // 'Alice'
```

For writes and transaction state, use a session or the complete rewrite pipeline. A SELECT transformer alone does not apply mutations.

- [Parsing, classification, schema inspection, and errors](docs/parsing.md)
- [Sessions, shadow reads, and simulated writes](docs/rewriting.md)
- [Generated API reference and executable examples](https://k-kinzal.github.io/ztd-query-php/sqlite/k-kinzal/ztd-query-sqlite/)
- [Detailed SQLite behavior and limitations](https://github.com/k-kinzal/ztd-query-php/blob/main/docs/sqlite-spec.md)
- [SQL support matrix](https://github.com/k-kinzal/ztd-query-php/blob/main/docs/sql-support-matrix.md)

## License

MIT License. See [LICENSE](LICENSE) for details.
