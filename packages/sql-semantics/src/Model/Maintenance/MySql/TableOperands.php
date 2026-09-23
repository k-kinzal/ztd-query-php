<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\MySql;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * Validates the physical-table operands shared by MySQL maintenance operations.
 * @visibility SqlSemantics
 */
final class TableOperands
{
    /**
     * @param non-empty-list<TableReference> $tables Ordered affected tables
     * @throws InvalidStructure
     */
    public static function validate(Origin $origin, array $tables): void
    {
        Collections::objects(Collections::nonEmpty($tables), TableReference::class);
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('These maintenance operations require MySQL.');
        }
        foreach ($tables as $table) {
            if ($table->alias !== null) {
                throw new InvalidStructure('Table maintenance cannot use query aliases.');
            }
            StatementOperands::relation($table, $origin->dialect);
        }
    }
}
