<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * Validates the table and column operands shared by COPY FROM and COPY TO.
 * @visibility SqlSemantics
 */
final class CopyTable
{
    /**
     * @param list<string> $columns Copied columns in written order; empty copies every column
     * @throws InvalidStructure
     */
    public static function validate(Origin $origin, TableReference $table, array $columns, CopyOptions $options): void
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('COPY requires PostgreSQL.');
        }
        if ($table->alias !== null) {
            throw new InvalidStructure('A COPY table cannot use a query alias.');
        }
        StatementOperands::relation($table, $origin->dialect);
        if (in_array('', $columns, true) || count(array_unique($columns)) !== count($columns)) {
            throw new InvalidStructure('COPY columns require unique nonempty names.');
        }
        foreach ([$options->forceQuote, $options->forceNotNull, $options->forceNull] as $choice) {
            if ($columns !== [] && $choice instanceof ListedColumns && array_diff($choice->columns, $columns) !== []) {
                throw new InvalidStructure('A forced column must be one of the copied columns.');
            }
        }
    }
}
