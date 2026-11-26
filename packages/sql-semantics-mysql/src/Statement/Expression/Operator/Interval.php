<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Operator;

use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;

/**
 * A temporal interval: `INTERVAL quantity unit`, the operand of interval arithmetic and of DATE_ADD and DATE_SUB.
 *
 * An interval is not a value of its own; the expression that holds it
 * derives the quantity. The quantity is enclosed between INTERVAL and the
 * unit, so it may be any expression.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/expressions.html#temporal-intervals.
 *
 * @visibility SqlSemantics
 */
final class Interval implements Node
{
    use Snapshot;

    /**
     * @param Scalar $quantity The number of units, or the formatted string of a compound unit
     * @param IntervalUnit $unit The unit
     */
    public function __construct(public readonly Scalar $quantity, public readonly IntervalUnit $unit)
    {
    }

    /**
     * Writes INTERVAL, the quantity and the unit keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword('INTERVAL')->node($this->quantity)->keyword($this->unit->value);
    }
}
