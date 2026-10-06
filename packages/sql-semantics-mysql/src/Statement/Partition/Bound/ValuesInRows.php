<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Bound;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `VALUES IN ((value, …), …)`: the value tuples of a LIST COLUMNS partition.
 *
 * Mirrors PT_part_values_in_list: a parenthesized list of rows, one tuple
 * of column values each.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-columns-list.html.
 *
 * @visibility public
 * @example Holding the tuples of a list columns partition
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     count((new \SqlSemantics\Platform\MySql\Statement\Partition\Bound\ValuesInRows([$row]))->rows) // => 1
 */
final class ValuesInRows implements PartitionBound
{
    use Snapshot;

    /**
     * @var list<PartitionRow> The tuples in order; at least one
     */
    public readonly array $rows;

    /**
     * @param list<PartitionRow> $rows The tuples in order; at least one
     */
    public function __construct(array $rows)
    {
        Check::input($rows !== [], 'VALUES IN lists at least one tuple.');
        $this->rows = Check::listOf($rows, PartitionRow::class, 'The tuples of VALUES IN are a list of rows.');
    }

    /**
     * Derives the values as constants.
     */
    public function deriveBound(Derivation $derivation): void
    {
        foreach ($this->rows as $row) {
            $row->deriveRow($derivation);
        }
    }

    /**
     * Writes the tuples.
     */
    public function render(Output $out): void
    {
        $out->keyword('VALUES', 'IN')->symbol('(')->list($this->rows)->symbol(')');
    }
}
