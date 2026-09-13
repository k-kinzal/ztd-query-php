<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use PDO;
use RuntimeException;
use ZtdQuery\Platform\SessionFactory;

/**
 * Answers the session factory a connection's driver needs.
 *
 * A PDO connection says which driver it speaks; each driver ZTD supports has a
 * platform package that knows how to rewrite that dialect. This is the one
 * place that pairing is written down, so nothing else has to guess it from a
 * driver name.
 */
final class DriverSessionFactory
{
    /**
     * The platform package each driver name is served by.
     *
     * @var array<string, array{class: string, package: string}>
     */
    private const DRIVER_MAP = [
        'mysql' => [
            'class' => 'ZtdQuery\\Platform\\MySql\\MySqlSessionFactory',
            'package' => 'k-kinzal/ztd-query-mysql',
        ],
        'pgsql' => [
            'class' => 'ZtdQuery\\Platform\\Postgres\\PgSqlSessionFactory',
            'package' => 'k-kinzal/ztd-query-postgres',
        ],
        'sqlite' => [
            'class' => 'ZtdQuery\\Platform\\Sqlite\\SqliteSessionFactory',
            'package' => 'k-kinzal/ztd-query-sqlite',
        ],
    ];

    /**
     * Answers every driver name ZTD has a platform package for.
     *
     * @return list<string> The driver names, as PDO reports them
     */
    public function driverNames(): array
    {
        return array_keys(self::DRIVER_MAP);
    }

    /**
     * Answers the factory that builds a session for the connection's driver.
     *
     * @param PDO $pdo Connection to read the driver name off
     *
     * @return SessionFactory The factory for that driver
     *
     * @throws RuntimeException When ZTD has no platform for the driver, or its package is not installed
     */
    public function forConnection(PDO $pdo): SessionFactory
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return $this->forDriver(is_string($driver) ? $driver : '');
    }

    /**
     * Answers the factory that builds a session for a driver name.
     *
     * @param string $driver Driver name, as PDO reports it
     *
     * @return SessionFactory The factory for that driver
     *
     * @throws RuntimeException When ZTD has no platform for the driver, or its package is not installed
     */
    public function forDriver(string $driver): SessionFactory
    {
        if (!isset(self::DRIVER_MAP[$driver])) {
            throw new RuntimeException(sprintf(
                'Unsupported PDO driver: "%s". Supported drivers: %s.',
                $driver,
                implode(', ', $this->driverNames())
            ));
        }

        $mapping = self::DRIVER_MAP[$driver];
        /** @var class-string<SessionFactory> $className */
        $className = $mapping['class'];

        if (!class_exists($className)) {
            throw new RuntimeException(sprintf(
                'Platform package for PDO driver "%s" is not installed. Install it with: composer require %s',
                $driver,
                $mapping['package']
            ));
        }

        return new $className();
    }
}
