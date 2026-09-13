<?php

declare(strict_types=1);

namespace Fuzz\Container;

use RuntimeException;
use Testcontainers\Testcontainers;

/**
 * Selects a disposable service supplied by CI or starts a local test container.
 */
final class DatabaseEndpoint
{
    /**
     * @return array{string, int}
     * @throws RuntimeException When the service port is invalid or unmapped.
     */
    public static function mysql(): array
    {
        $host = getenv('MYSQL_HOST');
        if ($host !== false) {
            return [$host, self::port('MYSQL_PORT', 3306)];
        }
        $instance = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);

        return [str_replace('localhost', '127.0.0.1', $instance->getHost()), $instance->getMappedPort(3306) ?? throw new RuntimeException('MySQL port was not mapped.')];
    }

    /**
     * @return array{string, int}
     * @throws RuntimeException When the service port is invalid or unmapped.
     */
    public static function postgres(): array
    {
        $host = getenv('PG_HOST');
        if ($host !== false) {
            return [$host, self::port('PG_PORT', 5432)];
        }
        $instance = Testcontainers::run(PostgreSqlContainer::class);

        return [str_replace('localhost', '127.0.0.1', $instance->getHost()), $instance->getMappedPort(5432) ?? throw new RuntimeException('PostgreSQL port was not mapped.')];
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
