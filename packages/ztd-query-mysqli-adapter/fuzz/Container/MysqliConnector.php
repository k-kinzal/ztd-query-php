<?php

declare(strict_types=1);

namespace Fuzz\Container;

use RuntimeException;
use Testcontainers\Testcontainers;

/**
 * Resolves a disposable MySQL service for a versioned fuzz campaign.
 */
final class MysqliConnector
{
    /**
     * Use the explicitly supplied local service, or start a pinned container.
     *
     * @return array{string, int}
     * @throws RuntimeException If the requested server version or port is invalid.
     */
    public static function endpoint(): array
    {
        $version = getenv('MYSQL_VERSION');
        $container = match ($version === false ? '8.0.44' : $version) {
            '8.0.44' => MySql80Container::class,
            '8.4.7' => MySql84Container::class,
            default => throw new RuntimeException('Unsupported MYSQL_VERSION.'),
        };
        $host = getenv('MYSQL_HOST');
        if ($host !== false) {
            $configuredPort = getenv('MYSQL_PORT');
            $port = filter_var($configuredPort === false ? '3306' : $configuredPort, FILTER_VALIDATE_INT);
            if ($port === false) {
                throw new RuntimeException('MYSQL_PORT must be an integer.');
            }
            return [$host, $port];
        }
        $instance = Testcontainers::run($container);
        return [str_replace('localhost', '127.0.0.1', $instance->getHost()), $instance->getMappedPort(3306) ?? throw new RuntimeException('MySQL port was not mapped.')];
    }
}
