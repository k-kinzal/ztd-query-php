<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Bound;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;

/**
 * `VALUES IN (value, …)`: the values of a LIST partition over one expression or column.
 *
 * Mirrors PT_part_values_in_item: one parenthesized row whose items are the
 * listed values.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-list.html.
 *
 * @visibility public
 * @example Holding the values of a list partition
 *     $bound = new \SqlSemantics\Platform\MySql\Statement\Partition\Bound\ValuesIn(new \SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]));
 *     count($bound->row->items) // => 2
 */
final class ValuesIn implements PartitionBound
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param PartitionRow $row The listed values
     */
    public function __construct(public readonly PartitionRow $row, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }

    /**
     * Derives the values as constants.
     */
    public function deriveBound(Derivation $derivation): void
    {
        $this->row->deriveRow($derivation);
    }

    /**
     * Writes the values.
     */
    public function render(Output $out): void
    {
        $out->keyword('VALUES', 'IN')->node($this->row);
    }
}
