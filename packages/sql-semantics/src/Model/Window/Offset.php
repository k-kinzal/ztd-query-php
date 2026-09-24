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

 */
final class Offset implements Boundary
{
    /**
     */
    public function __construct(
        public readonly Direction $direction,
        public readonly \SqlSemantics\Model\Expression $value
    ) {
    }
    /**
     * Returns the required offset expression evaluated for this frame boundary.
     */
    public function expressions(): array
    {
        return [$this->value];
    }
}
