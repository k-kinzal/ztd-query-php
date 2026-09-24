<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public
 * @example Reading an unbounded frame edge
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT sum(id) OVER (ORDER BY id ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) FROM t");
 *     $frame = $query->outputs[0]->expression->window->frame;
 *     $frame->start instanceof \SqlSemantics\Model\Window\Unbounded // => true
 *     $frame->start->direction // => \SqlSemantics\Model\Window\Direction::Preceding
 *     $frame->start->expressions() // => []

 */
final class Unbounded implements Boundary
{
    /**
     */
    public function __construct(
        public readonly Direction $direction
    ) {
    }
    /**
     * Returns no offset expressions: this boundary denotes an unbounded frame edge.
     */
    public function expressions(): array
    {
        return [];
    }
}
