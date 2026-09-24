<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\PostgreSql;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * Validates the dialect and relations shared by PostgreSQL VACUUM, ANALYZE and CLUSTER.
 * @visibility SqlSemantics
 */
final class MaintenanceTargets
{
    /**
     * @param list<MaintenanceTarget> $targets Relations in written order; empty processes every relation the user may maintain
     * @throws InvalidStructure
     */
    public static function validate(Origin $origin, array $targets): void
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('This maintenance form requires PostgreSQL.');
        }
        Collections::objects($targets, MaintenanceTarget::class);
        foreach ($targets as $target) {
            self::table($origin, $target->table);
        }
    }

    /**
     * @throws InvalidStructure
     */
    public static function table(Origin $origin, TableReference $table): void
    {
        StatementOperands::relation($table, $origin->dialect);
    }
}
