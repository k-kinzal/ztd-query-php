<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Filter;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A WHERE predicate over the result fields of a metadata listing.
 * @visibility public
 * @example Inspecting a WHERE filter
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SHOW DATABASES WHERE `Database` <> 'mysql'");
 *     $statement->filter instanceof \SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter // => true
 */
final class ConditionFilter
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $condition)
    {
        if ($condition->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('A metadata condition requires a MySQL expression.');
        }
    }
}
