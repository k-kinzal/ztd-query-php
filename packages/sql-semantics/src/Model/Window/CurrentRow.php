<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public
 * @example Completing a single-bound frame at the current row
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT sum(id) OVER (ORDER BY id ROWS 2 PRECEDING) FROM t");
 *     $frame = $query->outputs[0]->expression->window->frame;
 *     $frame->end instanceof \SqlSemantics\Model\Window\CurrentRow // => true
 *     $frame->end->expressions() // => []

 */
final class CurrentRow implements Boundary
{
    /**
     */
    public function __construct(

    ) {
    }
    /**
     * Returns no offset expressions: this boundary denotes the current row.
     */
    public function expressions(): array
    {
        return [];
    }
}
