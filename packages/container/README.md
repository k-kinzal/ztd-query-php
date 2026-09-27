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
| `MySql84Container` | `container-registry.oracle.com/mysql/community-server:8.4.12` |
| `MySql90Container` | `container-registry.oracle.com/mysql/community-server:9.0.1` |
| `MySql91Container` | `container-registry.oracle.com/mysql/community-server:9.1.0` |
| `PostgreSql16Container` | `postgres:16.6` |
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
