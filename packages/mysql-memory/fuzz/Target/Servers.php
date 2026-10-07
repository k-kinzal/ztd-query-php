<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Container\Endpoint;
use Container\MySqlRelease;
use MySqlMemory\Server\Server;
use PDO;
use Testcontainers\Testcontainers;

/**
 * Starts the two servers of a differential run: a MySQL server of the release under test, and mysql-memory emulating it.
 *
 * MYSQL_VERSION names the release (default 8.4.7). MYSQL_MEMORY_NATIVE_DSN, with
 * MYSQL_MEMORY_NATIVE_USER and MYSQL_MEMORY_NATIVE_PASSWORD, names a running server to use
 * instead of a container. mysql-memory starts with the global variables of the MySQL server, so
 * both report the same host name, paths and identities.
 */
final class Servers
{
    /**
     * Starts both servers and answers the differential target over them, and the grammar of the release.
     *
     * @return array{Differential, string, Server}
     */
    public function start(bool $emulate = true): array
    {
        $version = getenv('MYSQL_VERSION') !== false ? (string) getenv('MYSQL_VERSION') : MySqlRelease::DEFAULT;
        $dsn = getenv('MYSQL_MEMORY_NATIVE_DSN');
        if ($dsn !== false && $dsn !== '') {
            $user = (string) (getenv('MYSQL_MEMORY_NATIVE_USER') ?: 'root');
            $password = (string) (getenv('MYSQL_MEMORY_NATIVE_PASSWORD') ?: 'root');
        } else {
            $endpoint = Testcontainers::run(MySqlRelease::container($version))->getData(Endpoint::class);
            $dsn = 'mysql:host=' . $endpoint->host . ';port=' . $endpoint->port;
            $user = $endpoint->username;
            $password = $endpoint->password;
        }
        $native = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $globals = [];
        $statement = $native->query('SELECT VARIABLE_NAME, VARIABLE_VALUE FROM performance_schema.global_variables');
        foreach ($statement === false ? [] : $statement->fetchAll(PDO::FETCH_NUM) as [$name, $value]) {
            $globals[strtolower((string) $name)] = (string) $value;
        }
        $account = (string) $native->query('SELECT USER()')?->fetchColumn();
        $server = Server::start($version, [], $globals, substr($account, (int) strrpos($account, '@') + 1));

        return [new Differential($dsn, $user, $password, $server->dsn(), $emulate), 'mysql-' . $version, $server];
    }
}
