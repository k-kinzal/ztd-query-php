<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public
 * @example Reading the unit of a window frame
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT sum(id) OVER (ORDER BY id RANGE BETWEEN 1 PRECEDING AND 1 FOLLOWING) FROM t");
 *     $frame = $query->outputs[0]->expression->window->frame;
 *     $frame->unit // => \SqlSemantics\Model\Window\FrameUnit::Range

 */
enum FrameUnit: string
{
    case Rows = 'ROWS';
    case Range = 'RANGE';
    case Groups = 'GROUPS';
}
