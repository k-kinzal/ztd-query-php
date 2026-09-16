<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Postgres;

use Override;
use PDO;
use RuntimeException;
use ZtdQuery\Adapter\Pdo\ZtdPdo as BaseZtdPdo;
use ZtdQuery\Platform\Postgres\PgSqlSessionFactory;
use ZtdQuery\Platform\SessionFactory;

/**
 * PDO facade that binds PostgreSql connections to their ZTD platform.
 *
 * @example Remain compatible with consumers accepting PDO
 *     is_subclass_of(\ZtdQuery\Adapter\Pdo\Postgres\ZtdPdo::class, \PDO::class) // => true
 * @visibility public
 */
class ZtdPdo extends BaseZtdPdo
{
    /**
     * Validate the native driver before choosing the session factory.
     *
     * @throws RuntimeException When the connection uses a different PDO driver.
     */
    #[Override]
    protected static function resolveFactory(PDO $pdo, ?SessionFactory $factory): SessionFactory
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
            throw new RuntimeException('The PostgreSql PDO adapter requires the "pgsql" driver.');
        }

        return $factory ?? new PgSqlSessionFactory();
    }
}
