<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Session;

use PDO;
use RuntimeException;
use ZtdQuery\Platform\SessionFactory;

/**
 * Extension point for database adapters to supply their platform factory.
 *
 * @visibility ZtdQuery\Adapter\Pdo
 */
trait SessionFactoryResolver
{
    /**
     * Resolve an explicitly supplied platform, or the subclass default.
     *
     * @throws RuntimeException When the shared facade has no platform to use.
     */
    protected static function resolveFactory(PDO $pdo, ?SessionFactory $factory): SessionFactory
    {
        return $factory ?? throw new RuntimeException('Provide a SessionFactory or use a database-specific PDO adapter.');
    }
}
