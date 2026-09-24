<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public
 * @example Reading the window of a window function call
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT sum(id) OVER (PARTITION BY n ORDER BY id) FROM t');
 *     $window = $query->outputs[0]->expression->window;
 *     $window instanceof \SqlSemantics\Model\Window\Window // => true
 *     $window instanceof \SqlSemantics\Model\Window\WindowSpecification // => true
 *     count($window->expressions()) // => 2

 */
interface Window
{
    /**
     * @return list<\SqlSemantics\Model\Expression>
     */
    public function expressions(): array;
}
