<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows\Values;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A constant row in a PostgreSQL VALUES query.
 *
 * @visibility public
 * @example Constructing a NULL row
 *     $cell = new \SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesCell(new \SqlSemantics\Platform\PostgreSql\Ir\NullLiteral());
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesRow([$cell]))->toString() // => '(NULL)'
 */
final class ValuesRow
{
    use Snapshot;

    /**
     * @var non-empty-list<ValuesCell> The owned cells in column order
     */
    public readonly array $cells;

    /**
     * Checks the closed operands and native row width.
     * @param list<ValuesCell> $cells The ordered operands
     */
    public function __construct(array $cells)
    {
        $checked = Check::listOf($cells, ValuesCell::class, 'VALUES requires a nonempty ordered list of cells.', 1);
        Check::input(count($checked) <= 1664, 'A PostgreSQL VALUES result can have at most 1664 columns.');
        $this->cells = $checked;
    }

    /**
     * Emits one complete row in positional order.
     */
    public function toString(): string
    {
        $sql = [];
        foreach ($this->cells as $cell) {
            $sql[] = $cell->toString();
        }

        return '(' . implode(', ', $sql) . ')';
    }
}
