# Database Containers

Shared Testcontainers definitions for integration and fuzz tests in this monorepo.
Requires PHP 8.1+, Docker, and the PDO driver for the database being started.
The MySQLi helper also requires `ext-mysqli`.

Add `k-kinzal/container: dev-main` to the consuming package's `require-dev` and
`{"type": "path", "url": "../container", "options": {"versions": {"k-kinzal/container": "dev-main"}}}` to its Composer repositories.
Run the container-dependent tests from this monorepo. This internal development dependency is removed when packages are split for publication.

## Definitions

The profiles preserve the existing images, credentials, database names, wait
strategies, and container lifecycle settings.

| Namespace | Definitions | Behavior |
|-----------|-------------|----------|
| `Container\Fuzz` | `MySql56Container`, `MySql57Container`, `MySql80Container`, `MySql81Container`, `MySql82Container`, `MySql83Container`, `MySql84Container`, `MySql90Container`, `MySql91Container`, `PostgreSqlContainer` | Pinned MySQL releases and PostgreSQL 17.2; disposable containers for SQL grammar fuzzing |
| `Container\Reusable` | `MySql80Container`, `MySql84Container`, `PostgreSqlContainer` | MySQL 8.0.44 / 8.4.7 and PostgreSQL 16; reused within an adapter fuzzing process |
| `Container\Fixture` | `MySql84Container` | MySQL 8.4.7 with the `test` database initialized |
| `Container\Pdo` | `MySqlContainer`, `PostgreSqlContainer` | MySQL 8.0.44 (overridable with `MYSQL_VERSION`) and PostgreSQL 16; cached PDO connections |
| `Container\Mysqli` | `MySql80Container`, `MySql84Container` | MySQL 8.0.44 / 8.4.7; fresh containers with a native MySQLi connection and an initialized `test` database |

MySQL containers use `root` / `root`. PostgreSQL containers use `test` / `test`,
with `fuzz_test` for fuzzing and `ztd_test` for integration tests.
PostgreSQL containers wait for the server readiness log message.

## Usage

```php
use Container\Fuzz\MySql80Container;
use Testcontainers\Testcontainers;

$instance = Testcontainers::run(MySql80Container::class);
$host = $instance->getHost();
$port = $instance->getMappedPort(3306);
$grammarVersion = MySql80Container::getGrammarVersion();
```

```php
use Container\Pdo\MySqlContainer;
use Testcontainers\Testcontainers;

$instance = Testcontainers::run(MySqlContainer::class);
$pdo = $instance->getData(PDO::class);
```

Use `Container\Mysqli\MySql80Container` or `MySql84Container` and
`$instance->getData(mysqli::class)` for a MySQLi connection. PDO containers are
reused within the process; MySQLi containers restart on each `run()` call.
Testcontainers stops the containers at process shutdown.

## Development

```bash
composer install
composer test:unit
composer test:integration
composer lint
```

Integration tests require Docker and `pdo_mysql`, `pdo_pgsql`, and `mysqli`.
