<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Ordering;

use SqlSemantics\Model\OutputColumn;

/**
 * Sorts by a projected result column by its position.
 * @visibility public
  * @example Inspecting OutputPosition
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $original = $binder->bind('SELECT 1 UNION ALL SELECT 2 ORDER BY 1');
 *     $replacement = $binder->bind('SELECT 2147483648');
 *     $changed = new \SqlSemantics\Model\Statement\CompoundStatement($original->origin, $original->left, $replacement, $original->setOperator, $original->orderBy);
 *     $changed->orderBy[0]->key instanceof \SqlSemantics\Model\Query\Ordering\OutputPosition // => true
 */
final class OutputPosition
{
    /**
     * Refers to a required output column by its zero-based result position.
     */
    public function __construct(public readonly OutputColumn $output)
    {
    }
}
