<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertedRow;

/**
 * Counts written INSERT operands without consulting a table declaration.
 *
 * Every row has the same width, including an empty defaults row. An explicit
 * nonempty column list fixes that width. An absent or empty column list leaves
 * the destination width to catalog resolution; it does not permit unequal rows.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class InsertWidths
{
    /**
     * Finds the first contradiction between written row and column counts.
     *
     * @param non-empty-list<InsertedRow|DeferredInsertedRow> $rows The complete VALUES operands
     */
    public function problem(InsertInto $into, array $rows): ?ValueCountMismatch
    {
        $listed = count($into->columns->columns ?? []);
        $width = $listed === 0 ? count($rows[0]->values) : $listed;
        foreach ($rows as $index => $row) {
            $actual = count($row->values);
            if ($actual !== $width) {
                return new ValueCountMismatch($width, $actual, $index + 1);
            }
        }
        return null;
    }
}
