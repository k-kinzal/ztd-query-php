# ZTD Query Shared PDO Adapter

This package provides the database-independent PDO execution layer for ZTD Query on PHP 8.1+. It owns the PDO/PDOStatement proxies, connection delegation, prepared parameters, result fetching, transaction synchronization, and PDO exception translation.

## Choose a database adapter

| Database | Install | Connection class |
| --- | --- | --- |
| MySQL | `k-kinzal/ztd-query-pdo-mysql-adapter` | `ZtdQuery\Adapter\Pdo\MySql\ZtdPdo` |
| PostgreSQL | `k-kinzal/ztd-query-pdo-postgres-adapter` | `ZtdQuery\Adapter\Pdo\Postgres\ZtdPdo` |
| SQLite | `k-kinzal/ztd-query-pdo-sqlite-adapter` | `ZtdQuery\Adapter\Pdo\Sqlite\ZtdPdo` |

Each database adapter requires this package and its own platform. This package's runtime dependencies are only PHP, PDO, and ZTD core. It does not detect drivers or depend on a concrete platform. SQLite is a development dependency used to exercise the shared behavior with real in-memory connections.

## Why keep a shared package?

PDO's connection and statement contracts are the same across drivers. Query execution coordinates a core `Session`, parameter bindings, fetch modes, affected rows, and transaction state independently of SQL dialect. Copying that implementation into three packages would duplicate one responsibility and make fixes diverge. Database selection and its Composer dependency belong to the concrete adapters; SQL parsing and rewriting remain in the existing platform packages.

## Custom platforms

The shared `ZtdPdo` remains usable with an explicit `SessionFactory`:

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Platform\Sqlite\SqliteSessionFactory;

$native = new PDO('sqlite::memory:');
$pdo = ZtdPdo::fromPdo($native, factory: new SqliteSessionFactory());
```

This example requires installing the SQLite platform separately. `new ZtdPdo(...)` and `ZtdPdo::connect(...)` also accept named `config` and `factory` arguments. Omitting the factory on the shared facade raises `RuntimeException`; a concrete database adapter supplies its own default.

## Shared API

- `enableZtd()`, `disableZtd()`, and `isZtdEnabled()` control shadowing.
- `prepare()`, `query()`, and `exec()` coordinate core session rewriting.
- `beginTransaction()`, `commit()`, and `rollBack()` synchronize native and shadow transactions.
- `ZtdPdoStatement` supports PDO binding and fetch modes and reports simulated affected rows.
- `ZtdPdoException` preserves the PDO exception contract for ZTD failures.

## Migration

Replace the former generic adapter dependency with the database adapter above and update the `ZtdPdo` import. Constructor and `fromPdo()` arguments are preserved. Statement and exception namespaces do not change. Applications intentionally using a custom factory can keep this package and the existing connection class.

## Development

`composer test`, `composer lint`, and `composer bench` exercise the common behavior with SQLite. Database integration and fuzz suites live in their respective adapter packages.

## License

MIT. See [LICENSE](LICENSE).
