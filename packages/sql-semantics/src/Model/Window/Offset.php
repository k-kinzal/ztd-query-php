<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public
 * @example Reading an offset frame boundary
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT sum(id) OVER (ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) FROM t");
 *     $frame = $query->outputs[0]->expression->window->frame;
 *     $frame->end instanceof \SqlSemantics\Model\Window\Offset // => true
 *     $frame->end->direction // => \SqlSemantics\Model\Window\Direction::Following
 *     $frame->end->value->spelling() // => '1'
 *     count($frame->end->expressions()) // => 1
 * @example Reading a MySQL interval offset
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(d DATE, n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT sum(n) OVER (ORDER BY d RANGE INTERVAL 2 DAY PRECEDING) FROM t');
 *     $query->outputs[0]->expression->window->frame->start->unit // => \SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Day

 */
final class Offset implements Boundary
{
    /**
     * A MySQL RANGE offset may be an interval, `INTERVAL value unit PRECEDING`; the unit then names how the value is read.
     *
     * @param \SqlSemantics\Model\Scalar\Temporal\MySqlUnit|null $unit Interval unit of a MySQL temporal offset
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        public readonly Direction $direction,
        public readonly \SqlSemantics\Model\Expression $value,
        public readonly ?\SqlSemantics\Model\Scalar\Temporal\MySqlUnit $unit = null,
    ) {
        if ($unit !== null && $value->type->dialect !== \SqlSemantics\Dialect::MySql) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('Only a MySQL frame offset is an interval.');
        }
    }
    /**
     * Returns the required offset expression evaluated for this frame boundary.
     */
    public function expressions(): array
    {
        return [$this->value];
    }
}
