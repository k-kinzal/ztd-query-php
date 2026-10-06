<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Operator;

use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A temporal interval: `INTERVAL quantity unit`, the operand of interval arithmetic and of DATE_ADD and DATE_SUB.
 *
 * An interval is not a value of its own; the expression that holds it
 * derives the quantity. The quantity is enclosed between INTERVAL and the
 * unit, so it may be any expression.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/expressions.html#temporal-intervals.
 *
 * @visibility public
 * @example Reading the quantity and the unit
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a + INTERVAL 2 DAY_HOUR');
 *     [$query->statement->where->interval->quantity->text, $query->statement->where->interval->unit->value] // => ['2', 'DAY_HOUR']
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
