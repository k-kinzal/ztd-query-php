<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Mutation;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An immutable VALUES row with explicit positions.
 *
 * @visibility public
 * @example Writing a single-cell row
 *     (new \SqlSemantics\Platform\MySql\Ir\Mutation\InsertRow([new \SqlSemantics\Platform\MySql\Ir\Mutation\InsertCell(null)]))->toString() // => '(NULL)'
 */
final class InsertRow
{
    use Snapshot;

    /**
     * @var non-empty-list<InsertCell> The cells in target column order
     */
    public readonly array $values;

    /**
     * Creates an owned row of checked, explicit values.
     *
     * @param list<InsertCell> $values
     */
    public function __construct(array $values)
    {
        $this->values = Check::listOf($values, InsertCell::class, 'An inserted row needs nonempty cells.', 1);
    }

    /**
     * Writes the row in the order of its target slots.
     */
    public function toString(): string
    {
        $sql = [];
        foreach ($this->values as $item) {
            $sql[] = $item->toString();
        }

        return '(' . implode(', ', $sql) . ')';
    }
}
