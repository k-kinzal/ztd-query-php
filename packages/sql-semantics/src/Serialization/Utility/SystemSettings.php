<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Utility;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Configuration\System as Statement;
use SqlSemantics\Serialization\Settings;

/**
 * Writes ALTER SYSTEM assignments and removals.
 * @visibility SqlSemantics
 */
final class SystemSettings
{
    /**
     * Returns null for statements outside ALTER SYSTEM.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\AlterSystemSetStatement => new Tree('alter-system', [Build::keyword('ALTER SYSTEM SET'), Settings::assignment($statement->setting, Dialect::PostgreSql)]),
            $statement instanceof Statement\AlterSystemResetStatement => new Tree('alter-system', [Build::keyword('ALTER SYSTEM RESET'), Build::identifier($statement->setting->name, Dialect::PostgreSql)]),
            $statement instanceof Statement\AlterSystemResetAllStatement => Build::keyword('ALTER SYSTEM RESET ALL'),
            default => null,
        };
    }
}
