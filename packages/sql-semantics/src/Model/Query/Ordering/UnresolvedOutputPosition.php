<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Ordering;

use SqlSemantics\Type\Identity\Numeric\NumericParameter;

/**
 * An output position whose wildcard expansion needs a missing table declaration.
 * @visibility public
 * @example Reading an ordering position that awaits a table declaration
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT * FROM t ORDER BY 2', strict: false);
 *     $statement->orderBy[0]->key->position->spelling // => '2'
 */
final class UnresolvedOutputPosition
{
    /**
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly NumericParameter $position)
    {
        if (preg_match('/^0*[1-9][0-9]*$/D', $position->spelling) !== 1) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An output position must be a positive integer.');
        }
    }
}
