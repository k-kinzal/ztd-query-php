<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Ordering;

use SqlSemantics\Model\OutputColumn;

/**
 * Sorts by a projected result column by its alias.
 * @visibility public
  * @example Inspecting OutputAlias
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT random() AS draw ORDER BY draw', strict: false);
 *     $key = $statement->orderBy[0]->key;
 *     $key instanceof \SqlSemantics\Model\Query\Ordering\OutputAlias // => true
 */
final class OutputAlias
{
    /**
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly OutputColumn $output)
    {
        if ($output->name === null) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An output alias requires a named result column.');
        }

    }
}
