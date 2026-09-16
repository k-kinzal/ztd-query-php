<?php

declare(strict_types=1);

namespace Tests\Connection;

use Override;
use PDO;
use ZtdQuery\Adapter\Pdo\ZtdPdo;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\Platform\Sqlite\SqliteSessionFactory;

/**
 * Custom adapter that supplies a factory through the shared extension point.
 */
final class CustomPdo extends ZtdPdo
{
    /**
     * Use SQLite by default while retaining the parent resolution contract.
     */
    #[Override]
    protected static function resolveFactory(PDO $pdo, ?SessionFactory $factory): SessionFactory
    {
        return parent::resolveFactory($pdo, $factory ?? new SqliteSessionFactory());
    }
}
