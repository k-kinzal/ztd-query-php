# Database Containers

Shared database container definitions for PHP 8.1+ and Docker. Every consumer uses
one definition per database version from the `Container` namespace.

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
| `PostgreSql16Container` | `postgres:16` |
| `PostgreSql17Container` | `postgres:17.2` |

All containers initialize the `test` database. MySQL uses `root` / `root` and
PostgreSQL uses `test` / `test`. MySQL data is stored in tmpfs, and timezone table
loading is disabled to keep startup fast. Running the same class reuses its
container until it is stopped; containers are removed when stopped.

`MySqlContainer` and `PostgreSqlContainer` are abstract bases for the shared
settings. Connection creation, database/schema isolation, and version selection
belong to the caller. MySQL classes expose `getGrammarVersion()` for SQL grammar
selection.

## Usage

Add `k-kinzal/container: dev-main` to the consuming package's `require-dev` and
`{"type": "path", "url": "../container", "options": {"versions": {"k-kinzal/container": "dev-main"}}}`
to its Composer repositories. This internal development dependency is removed
when packages are split for publication.

```php
use Container\MySql80Container;
use Testcontainers\Testcontainers;

$instance = Testcontainers::run(MySql80Container::class);
$host = str_replace('localhost', '127.0.0.1', $instance->getHost());
$port = $instance->getMappedPort(3306);
$pdo = new PDO("mysql:host=$host;port=$port;dbname=test;charset=utf8mb4", 'root', 'root');
$mysqli = new mysqli($host, 'root', 'root', 'test', $port);
$mysqli->set_charset('utf8mb4');
```

MySQL readiness checks require `pdo_mysql`. PostgreSQL waits for the server's
readiness log message. Install the appropriate PHP driver for the connections
used by your code.

## Development

```bash
composer install
composer test:unit
composer test:integration
composer lint
```

Integration tests require Docker and `pdo_mysql`, `pdo_pgsql`, and `mysqli`.
