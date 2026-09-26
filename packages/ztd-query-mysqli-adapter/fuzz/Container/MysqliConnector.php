<?php

declare(strict_types=1);

namespace Fuzz\Container;

use Container\Endpoint;
use Container\MySql80Container;
use Container\MySql84Container;
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
     * @throws RuntimeException If the requested server version is unsupported.
     */
    public static function endpoint(): array
    {
        $version = getenv('MYSQL_VERSION');
        $container = match ($version === false ? '8.0.44' : $version) {
            '8.0.44' => MySql80Container::class,
            '8.4.7' => MySql84Container::class,
            default => throw new RuntimeException('Unsupported MYSQL_VERSION.'),
        };
        $endpoint = Testcontainers::run($container)->getData(Endpoint::class);

        return [$endpoint->host, $endpoint->port];
    }
}
