# Connections and configuration

`ZtdPdo::fromPdo()` retains an existing connection and its PDO options. Driver detection selects the MySQL, PostgreSQL, or SQLite platform package. Each wrapper owns its shadow state.

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;

$native = new PDO('sqlite::memory:', options: [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$pdo = ZtdPdo::fromPdo($native);
assert($pdo instanceof PDO);
assert($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
assert($pdo->isZtdEnabled());
```

To open a connection directly, use the constructor with the usual PDO DSN, username, password, and options. The optional `config` and `factory` arguments follow those native arguments.

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Platform\Sqlite\SqliteSessionFactory;

$pdo = new ZtdPdo(
    'sqlite::memory:',
    config: new ZtdConfig(
        unsupportedBehavior: UnsupportedSqlBehavior::Exception,
        unknownSchemaBehavior: UnknownSchemaBehavior::Exception,
    ),
    factory: new SqliteSessionFactory(),
);
assert($pdo->isZtdEnabled());
```

`unsupportedBehavior` selects `Ignore`, `Notice`, or `Exception` for unsupported SQL. `unknownSchemaBehavior` selects `Passthrough` or `Exception` for an unknown table. `behaviorRules` provides ordered SQL-pattern overrides. See the [core package](https://github.com/k-kinzal/ztd-query-php/tree/main/packages/ztd-query-core) for the configuration contract.

`disableZtd()` sends statements to the native database. Re-enabling ZTD resumes the same shadow session. Schema discovery happens when the wrapper is created, so create the physical schema before wrapping the connection. Native mode can then inspect the physical state while retaining the existing shadow.

```php
use ZtdQuery\Adapter\Pdo\ZtdPdo;

$native = new PDO('sqlite::memory:');
$native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
$pdo = ZtdPdo::fromPdo($native);
$pdo->exec("INSERT INTO users VALUES (1, 'Ada')");
$pdo->disableZtd();
assert($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0);
$pdo->enableZtd();
assert($pdo->query('SELECT name FROM users')->fetchColumn() === 'Ada');
assert($native->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0);
```
