<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Window;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Aggregate\Accumulator;
use MySqlMemory\Evaluation\Frame;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Maintains an exact numeric window fold by adding entering rows and removing leaving rows.
 * Removal reads the argument again, preserving warnings and live variable reads.
 * Floating aggregates are recomputed to avoid losing precision through inverse arithmetic.
 * Verified on MySQL 8.0, 8.4 and 9.1 with moving frames and assignment arguments.
 *
 * @visibility MySqlMemory
 */
final class Sliding
{
    private int $start = 0;

    private int $end = -1;

    private readonly Accumulator $accumulator;

    /**
     * @param Accumulation $accumulation The exact SUM or AVG to maintain
     */
    public function __construct(public readonly Accumulation $accumulation)
    {
        $this->accumulator = $accumulation->start();
    }

    /**
     * Tells whether a fold supports exact inverse arithmetic.
     */
    public static function supports(Accumulation $accumulation): bool
    {
        return in_array($accumulation->function, [AggregateFunction::Sum, AggregateFunction::Average], true) && $accumulation->domain->kind === Kind::Decimal;
    }

    /**
     * Moves the frame and answers its aggregate, reading only entering and leaving rows.
     */
    public function value(Partition $partition, int $row, Frame $frame): int|float|string|null
    {
        [$start, $end] = $partition->extent($row, $frame);
        for ($index = $this->start; $index <= $this->end; $index++) {
            if ($index < $start || $index > $end) {
                $frame->row = $partition->rows[$index];
                $this->accumulator->remove($frame);
            }
        }
        for ($index = $start; $index <= $end; $index++) {
            if ($index < $this->start || $index > $this->end) {
                $frame->row = $partition->rows[$index];
                $this->accumulator->add($frame);
            }
        }
        $this->start = $start;
        $this->end = $end;
        $frame->row = $partition->rows[$row];

        return $this->accumulator->result($frame);
    }
}
