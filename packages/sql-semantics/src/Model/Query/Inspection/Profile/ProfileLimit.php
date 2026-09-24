<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Profile;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The row window of a SHOW PROFILE result: a required row count and an optional offset.
 * @visibility public
 * @example Inspecting a profile window
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW PROFILE LIMIT 5 OFFSET 2');
 *     [$statement->limit->count->spelling(), $statement->limit->offset?->spelling()] // => ['5', '2']
 */
final class ProfileLimit
{
    /**
     * @param Expression $count Row count operand, written as given
     * @param Expression|null $offset Rows skipped before the window; null starts at the first row
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $count, public readonly ?Expression $offset = null)
    {
        if ($count->type->dialect !== Dialect::MySql || ($offset !== null && $offset->type->dialect !== Dialect::MySql)) {
            throw new InvalidStructure('A profile window requires MySQL operands.');
        }
    }
}
