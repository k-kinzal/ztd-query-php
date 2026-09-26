# ZTD Query PDO Adapter

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Docs](https://img.shields.io/badge/docs-ztd--query--pdo--adapter-0969da?logo=php&logoColor=white)](https://k-kinzal.github.io/ztd-query-php/k-kinzal/ztd-query-pdo-adapter/)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/k-kinzal/ztd-query-php)

ZTD Query is a Zero Table Dependency testing library for PHP: it runs the SQL of an application on a real database engine without reading or writing any physical table. Before a query reaches the database, every table it references is replaced by a CTE holding the rows the test has written, and every INSERT, UPDATE, and DELETE is turned into a SELECT whose result is kept in the session, so later queries see the change. Tests therefore need no migrations, seeding, or cleanup, and they can run in parallel against one empty database. This package is the PDO adapter: `ZtdPdo` extends `PDO` and applies ZTD to every query it runs, with the platform package of the connected database.

## Requirements

- PHP 8.1+ with the PDO extension and the driver of your database
- MySQL 8.0.11–9.1, PostgreSQL 16–17, or SQLite 3.x

## Installation

MySQL:

```bash
composer require --dev k-kinzal/ztd-query-pdo-adapter k-kinzal/ztd-query-mysql
```

PostgreSQL:

```bash
composer require --dev k-kinzal/ztd-query-pdo-adapter k-kinzal/ztd-query-postgres
```

SQLite:

```bash
composer require --dev k-kinzal/ztd-query-pdo-adapter k-kinzal/ztd-query-sqlite
```

## Usage

`ZtdPdo` extends `PDO`, so it can be passed wherever the application expects a PDO connection. Tables are created and filled through the same connection; they exist only in the session.

```php
use PDO;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

final class UserQueryTest extends TestCase
{
    public function testSelectsActiveUsers(): void
    {
        $pdo = new ZtdPdo('mysql:host=127.0.0.1;dbname=test', 'root', 'root');
        $pdo->exec('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, active BOOLEAN NOT NULL)');
        $pdo->exec("INSERT INTO users (id, name, active) VALUES (1, 'Alice', TRUE), (2, 'Bob', FALSE)");

        $statement = $pdo->prepare('SELECT name FROM users WHERE active = ? ORDER BY id');
        $statement->execute([1]);

        self::assertSame(['Alice'], $statement->fetchAll(PDO::FETCH_COLUMN));
    }
}
```

`ZtdPdo::fromPdo($pdo)` wraps an existing connection instead of opening a new one, and `disableZtd()` and `enableZtd()` switch between the physical database and the session.

## Configuration

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;

$config = new ZtdConfig(
    // Unsupported statements: Exception (default), Notice, or Ignore
    unsupportedBehavior: UnsupportedSqlBehavior::Exception,

    // Tables the session does not know: Passthrough to the database (default), or Exception
    unknownSchemaBehavior: UnknownSchemaBehavior::Exception,

    // Per-statement overrides of unsupportedBehavior; the first matching rule wins
    behaviorRules: [
        'CREATE INDEX' => UnsupportedSqlBehavior::Ignore,       // case-insensitive prefix
        '/^SET\s+/i' => UnsupportedSqlBehavior::Notice,          // regular expression
    ],
);

$pdo = new ZtdPdo($dsn, $user, $password, config: $config);
```

`Ignore` skips the statement, `Notice` skips it and raises a PHP notice, and `Exception` throws an exception.

## SQL Support

| Statement | MySQL | PostgreSQL | SQLite |
|-----------|-------|------------|--------|
| SELECT, including joins, grouping, set operations, subqueries, CTEs, recursive CTEs, and window functions | Supported | Supported | Supported |
| INSERT with VALUES or SELECT | Supported | Supported | Supported |
| Upsert | `ON DUPLICATE KEY UPDATE`, `INSERT IGNORE`, `REPLACE` | `ON CONFLICT` | `ON CONFLICT`, `INSERT OR ...`, `REPLACE` |
| UPDATE and DELETE | Supported, including multi-table forms and `ORDER BY ... LIMIT` | Supported, including `UPDATE ... FROM` and `DELETE ... USING` | Supported, including `UPDATE ... FROM` |
| `RETURNING` | – | Supported | Supported |
| TRUNCATE | Supported | Supported | – |
| CREATE TABLE, DROP TABLE | Supported | Supported | Supported |
| ALTER TABLE | Supported | Unsupported | Supported |
| BEGIN, COMMIT, ROLLBACK | Applied to the session: ROLLBACK discards the writes made since BEGIN | Same | Same |
| Views, indexes, routines, triggers, SET, and server or user administration | Unsupported | Unsupported | Unsupported |

Unsupported statements are handled as configured by `unsupportedBehavior`. The full specification of each database is in [ztd-query-mysql](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/ztd-query-mysql/docs/spec.md), [ztd-query-postgres](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/ztd-query-postgres/docs/spec.md), and [ztd-query-sqlite](https://github.com/k-kinzal/ztd-query-php/blob/main/packages/ztd-query-sqlite/docs/spec.md).

## License

MIT License. See [LICENSE](LICENSE) for details.
