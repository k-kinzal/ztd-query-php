<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Window;

/**

 * @visibility public
 * @example Referring to a named window definition
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT sum(id) OVER w FROM t WINDOW w AS (PARTITION BY n)');
 *     $window = $query->outputs[0]->expression->window;
 *     $window instanceof \SqlSemantics\Model\Window\NamedWindow // => true
 *     $window->name // => 'w'
 *     $window->expressions() // => []

 */
final class NamedWindow implements Window
{
    /**
     */
    public function __construct(
        public readonly string $name
    ) {
    }
    /**
     * Returns no local expressions: this window refers to a named definition.
     */
    public function expressions(): array
    {
        return [];
    }
}
