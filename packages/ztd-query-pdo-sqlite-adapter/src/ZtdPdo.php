<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Sqlite;

use Override;
use PDO;
use RuntimeException;
use ZtdQuery\Adapter\Pdo\ZtdPdo as BaseZtdPdo;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\Platform\Sqlite\SqliteSessionFactory;

/**
 * PDO facade that binds Sqlite connections to their ZTD platform.
 *
 * @visibility public
 * @example Simulate a write on SQLite
 *     $native = new \PDO('sqlite::memory:');
 *     $native->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
 *     $pdo = \ZtdQuery\Adapter\Pdo\Sqlite\ZtdPdo::fromPdo($native);
 *     $pdo->exec('INSERT INTO users VALUES (1)') // => 1
 *     $native->query('SELECT COUNT(*) FROM users')->fetchColumn() // => 0
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
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            throw new RuntimeException('The Sqlite PDO adapter requires the "sqlite" driver.');
        }

        return $factory ?? new SqliteSessionFactory();
    }
}
