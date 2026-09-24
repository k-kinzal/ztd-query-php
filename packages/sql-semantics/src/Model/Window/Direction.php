<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public
 * @example Reading the direction of a frame boundary
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT sum(id) OVER (ORDER BY id ROWS BETWEEN 1 PRECEDING AND UNBOUNDED FOLLOWING) FROM t");
 *     $frame = $query->outputs[0]->expression->window->frame;
 *     $frame->start->direction // => \SqlSemantics\Model\Window\Direction::Preceding
 *     $frame->end->direction // => \SqlSemantics\Model\Window\Direction::Following

 */
enum Direction: string
{
    case Preceding = 'PRECEDING';
    case Following = 'FOLLOWING';
}
