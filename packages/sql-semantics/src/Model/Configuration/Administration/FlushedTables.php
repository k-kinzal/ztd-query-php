<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Administration;

use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * Checks the table operands of the FLUSH TABLES forms.
 * @visibility SqlSemantics
 */
final class FlushedTables
{
    /**
     * Requires MySQL, unaliased physical tables in the statement dialect and, when required, at least one table.
     * @param list<TableReference> $tables
     * @return list<TableReference>
     * @throws InvalidStructure
     */
    public static function check(Origin $origin, array $tables, string $form, bool $required = false): array
    {
        ReplicationRelease::require($origin, $form);
        Collections::objects($required ? Collections::nonEmpty($tables) : $tables, TableReference::class);
        foreach ($tables as $table) {
            if ($table->alias !== null) {
                throw new InvalidStructure($form . ' cannot use table aliases.');
            }
            StatementOperands::relation($table, $origin->dialect);
        }
        return $tables;
    }
}
