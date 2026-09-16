# ZTD Query PDO MySQL Adapter

MySQL PDO adapter for PHP 8.1+. `ZtdQuery\Adapter\Pdo\MySql\ZtdPdo` extends the shared PDO proxy and selects the MySQL session factory. It simulates writes and shadows reads without changing physical tables.

## Installation

```bash
composer require --dev k-kinzal/ztd-query-pdo-mysql-adapter
```

Requires `ext-pdo_mysql`. Composer installs the shared PDO adapter, ZTD core, and the MySQL platform. Other database platforms and PDO drivers are not required.

## Usage

```php
use ZtdQuery\Adapter\Pdo\MySql\ZtdPdo;

$native = new PDO('mysql:host=localhost;dbname=test', 'user', 'password');
$pdo = ZtdPdo::fromPdo($native);
$pdo->exec('CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
$pdo->exec("INSERT INTO users VALUES (1, 'Alice')");
$statement = $pdo->prepare('SELECT name FROM users WHERE id = ?');
$statement->execute([1]);
$name = $statement->fetchColumn();
```

Construct a connection with `new ZtdPdo($dsn, $username, $password, $options, $config)`, or use `ZtdPdo::connect($dsn, $username, $password, $options, $config)`. `fromPdo()` retains the existing connection and its options. All three entry points validate that the connection uses the `mysql` PDO driver.

Pass `config: $config` to customize `ZtdConfig`. An explicit `factory: $factory` overrides session construction after the driver is validated. `enableZtd()`, `disableZtd()`, transactions, parameter binding, and fetch modes use the [shared PDO implementation](../ztd-query-pdo-adapter/README.md).

Statements and exceptions retain their shared types: `ZtdQuery\Adapter\Pdo\ZtdPdoStatement` and `ZtdQuery\Adapter\Pdo\ZtdPdoException`.

## Migrating from ztd-query-pdo-adapter

Install this package and change the connection import from `ZtdQuery\Adapter\Pdo\ZtdPdo` to `ZtdQuery\Adapter\Pdo\MySql\ZtdPdo`. Existing constructor and `fromPdo()` arguments remain valid. The shared package no longer automatically detects installed database platforms.

## Development

```bash
composer install
composer test
composer lint
composer fuzz:smoke
```

The package owns its MySQL integration tests and fuzz targets. Tests and fuzzing require Docker. CI checks PHP 8.1–8.5 and verifies an installation without development dependencies excludes unrelated platforms.

## License

MIT. See [LICENSE](LICENSE).
