# Containers

Container definitions for [testcontainers-php](https://github.com/k-kinzal/testcontainers-php) used across [k-kinzal/ztd-query-php](https://github.com/k-kinzal/ztd-query-php). Each definition pins one image version, and every package that runs a container uses the same definition from the `Container` namespace.

## Versions

| Class | Image |
|-------|-------|
| `MySql56Container` | `mysql:5.6.51` |
| `MySql57Container` | `mysql:5.7.44` |
| `MySql80Container` | `mysql:8.0.44` |
| `MySql81Container` | `mysql:8.1.0` |
| `MySql82Container` | `mysql:8.2.0` |
| `MySql83Container` | `mysql:8.3.0` |
| `MySql84Container` | `container-registry.oracle.com/mysql/community-server:8.4.7` |
| `MySql90Container` | `container-registry.oracle.com/mysql/community-server:9.0.1` |
| `MySql91Container` | `container-registry.oracle.com/mysql/community-server:9.1.0` |
| `PostgreSql16Container` | `postgres:16.15` |
| `PostgreSql17Container` | `postgres:17.2` |

## Usage

```php
use Container\Endpoint;
use Container\MySql80Container;
use Testcontainers\Testcontainers;

$endpoint = Testcontainers::run(MySql80Container::class)->getData(Endpoint::class);
$pdo = new PDO($endpoint->dsn(), $endpoint->username, $endpoint->password);
```

MySQL containers require `pdo_mysql` to wait for readiness.

## Selecting a release

`MySqlRelease` and `PostgreSqlRelease` resolve a release number to its container. `fromEnvironment()` reads `MYSQL_VERSION` or `PG_VERSION` and falls back to the default release (`8.4.7` and `17.2`), so CI runs the newest release and any other release runs locally by setting the variable:

```php
use Container\Endpoint;
use Container\MySqlRelease;
use Testcontainers\Testcontainers;

$endpoint = Testcontainers::run(MySqlRelease::fromEnvironment())->getData(Endpoint::class);
```

```console
$ MYSQL_VERSION=8.0.44 vendor/bin/phpunit
$ PG_VERSION=16.6 vendor/bin/phpunit
```

`container($version)` resolves a release given in code, `versions()` lists the releases with a container, and `latest()` names the newest one.
