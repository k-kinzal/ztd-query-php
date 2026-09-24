<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection;

use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * Validates the single physical table an inspection describes.
 * @visibility SqlSemantics
 */
final class InspectedTable
{
    /**
     * @throws InvalidStructure
     */
    public static function validate(Origin $origin, TableReference $table): void
    {
        if ($table->alias !== null) {
            throw new InvalidStructure('An inspected table cannot use a query alias.');
        }
        StatementOperands::relation($table, $origin->dialect);
    }

    /**
     * @throws InvalidStructure
     */
    public static function extended(Origin $origin, bool $extended): void
    {
        if ($extended && in_array($origin->context?->schema()->grammarVersion, ['mysql-5.6.51', 'mysql-5.7.44'], true)) {
            throw new InvalidStructure('EXTENDED inspection requires MySQL 8.0 or later.');
        }
    }
}
