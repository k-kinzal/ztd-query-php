<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\MySql;

use Override;
use PDO;
use RuntimeException;
use ZtdQuery\Adapter\Pdo\ZtdPdo as BaseZtdPdo;
use ZtdQuery\Platform\MySql\MySqlSessionFactory;
use ZtdQuery\Platform\SessionFactory;

/**
 * PDO facade that binds MySql connections to their ZTD platform.
 *
 * @example Remain compatible with consumers accepting PDO
 *     is_subclass_of(\ZtdQuery\Adapter\Pdo\MySql\ZtdPdo::class, \PDO::class) // => true
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
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('The MySql PDO adapter requires the "mysql" driver.');
        }

        return $factory ?? new MySqlSessionFactory();
    }
}
