<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public
 * @example Reading the exclusion of a window frame
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT sum(id) OVER (ORDER BY id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW EXCLUDE GROUP) FROM t");
 *     $frame = $query->outputs[0]->expression->window->frame;
 *     $frame->exclusion // => \SqlSemantics\Model\Window\FrameExclusion::Group

 */
enum FrameExclusion: string
{
    case None = 'NO OTHERS';
    case Current = 'CURRENT ROW';
    case Group = 'GROUP';
    case Ties = 'TIES';
}
