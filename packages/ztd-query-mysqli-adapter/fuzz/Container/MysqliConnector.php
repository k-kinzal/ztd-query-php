<?php

declare(strict_types=1);

namespace Fuzz\Container;

use Container\Mysqli\MySql80Container;
use Container\Mysqli\MySql84Container;
use RuntimeException;
use Testcontainers\Testcontainers;

/**
 * Resolves a disposable MySQL service for a versioned fuzz campaign.
 */
final class MysqliConnector
{
    /**
     * Start a pinned container and return its mapped endpoint.
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
        $instance = Testcontainers::run($container);
        return [str_replace('localhost', '127.0.0.1', $instance->getHost()), $instance->getMappedPort(3306) ?? throw new RuntimeException('MySQL port was not mapped.')];
    }
}
