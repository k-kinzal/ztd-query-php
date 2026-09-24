<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public
 * @example Reading a window frame
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT sum(id) OVER (ORDER BY id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE TIES) FROM t");
 *     $frame = $query->outputs[0]->expression->window->frame;
 *     $frame instanceof \SqlSemantics\Model\Window\Frame // => true
 *     $frame->unit // => \SqlSemantics\Model\Window\FrameUnit::Rows
 *     $frame->exclusion // => \SqlSemantics\Model\Window\FrameExclusion::Ties
 *     $frame->start instanceof \SqlSemantics\Model\Window\Offset // => true

 */
final class Frame
{
    /**
     */
    public function __construct(
        public readonly FrameUnit $unit,
        public readonly Boundary $start,
        public readonly Boundary $end,
        public readonly FrameExclusion $exclusion
    ) {
    }
}
