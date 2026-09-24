<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public
 * @example Reading the boundaries of a window frame
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT sum(id) OVER (ORDER BY id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) FROM t");
 *     $frame = $query->outputs[0]->expression->window->frame;
 *     $frame->start instanceof \SqlSemantics\Model\Window\Boundary // => true
 *     $frame->start->expressions()[0]->spelling() // => '1'
 *     $frame->end->expressions() // => []

 */
interface Boundary
{
    /**
     * @return list<\SqlSemantics\Model\Expression>
     */
    public function expressions(): array;
}
