<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Bound;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `VALUES LESS THAN (values)` or `VALUES LESS THAN MAXVALUE`: the exclusive upper bound of a RANGE partition.
 *
 * Mirrors PT_part_values with `MAXVALUE` written alone (no row) or a row of
 * values. The two spellings of the greatest bound are kept as written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-range.html.
 *
 * @visibility public
 * @example Holding the unbounded last range
 *     (new \SqlSemantics\Platform\MySql\Statement\Partition\Bound\LessThan(null))->row // => null
 */
final class LessThan implements PartitionBound
{
    use Snapshot;

    /**
     * @param PartitionRow|null $row The bound values, or null for the keyword MAXVALUE written without parentheses
     */
    public function __construct(public readonly ?PartitionRow $row)
    {
    }

    /**
     * Derives the bound values as constants.
     */
    public function deriveBound(Derivation $derivation): void
    {
        $this->row?->deriveRow($derivation);
    }

    /**
     * Writes the bound.
     */
    public function render(Output $out): void
    {
        $out->keyword('VALUES', 'LESS', 'THAN');
        if ($this->row === null) {
            $out->keyword('MAXVALUE');
        } else {
            $out->node($this->row);
        }
    }
}
