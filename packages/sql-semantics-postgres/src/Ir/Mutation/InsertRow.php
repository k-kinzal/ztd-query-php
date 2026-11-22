<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An immutable VALUES row with explicit positions.
 *
 * @visibility public
 * @example Writing a single-cell row
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertRow([new \SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertValue(null)]))->toString() // => '(NULL)'
 */
final class InsertRow
{
    use Snapshot;

    /**
     * @var non-empty-list<InsertValue> The cells in target column order
     */
    public readonly array $values;

    /**
     * Creates an owned row of checked, explicit values.
     *
     * @param non-empty-list<InsertValue> $values
     */
    public function __construct(array $values)
    {
        $this->values = Check::listOf($values, InsertValue::class, 'An inserted row needs nonempty cells.', 1);
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
