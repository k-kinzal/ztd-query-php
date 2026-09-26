<?php

declare(strict_types=1);

namespace Fuzz\Container;

use Container\Endpoint;
use Container\MySql80Container;
use Container\MySql84Container;
use Container\PostgreSql16Container;
use RuntimeException;
use Testcontainers\Testcontainers;

/**
 * Selects a disposable service supplied by CI or starts a local test container.
 */
final class DatabaseEndpoint
{
    /**
     * @return array{string, int}
     * @throws RuntimeException When the service port is invalid.
     */
    public static function mysql(): array
    {
        $host = getenv('MYSQL_HOST');
        if ($host !== false) {
            return [$host, self::port('MYSQL_PORT', 3306)];
        }
        $endpoint = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class)->getData(Endpoint::class);

        return [$endpoint->host, $endpoint->port];
    }

    /**
     * @return array{string, int}
     * @throws RuntimeException When the service port is invalid.
     */
    public static function postgres(): array
    {
        $host = getenv('PG_HOST');
        if ($host !== false) {
            return [$host, self::port('PG_PORT', 5432)];
        }
        $endpoint = Testcontainers::run(PostgreSql16Container::class)->getData(Endpoint::class);

        return [$endpoint->host, $endpoint->port];
    }

    /**
     * @throws RuntimeException When a configured port is not a valid TCP port.
     */
    public static function port(string $variable, int $default): int
    {
        $value = getenv($variable);
        if ($value === false) {
            return $default;
        }
        $port = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        if ($port === false) {
            throw new RuntimeException($variable . ' must be a TCP port between 1 and 65535.');
        }

        return $port;
    }
}
