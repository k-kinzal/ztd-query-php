<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The LIMIT window of a SHOW WARNINGS, SHOW ERRORS, or log event listing: a required row count and an optional offset.
 * @visibility public
 * @example Inspecting a diagnostics window
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW WARNINGS LIMIT 2, 5');
 *     [$statement->limit->count->spelling(), $statement->limit->offset?->spelling()] // => ['5', '2']
 */
final class RowWindow
{
    /**
     * @param Expression $count Row count operand, written as given
     * @param Expression|null $offset Rows skipped before the window; null starts at the first row
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $count, public readonly ?Expression $offset = null)
    {
        if ($count->type->dialect !== Dialect::MySql || ($offset !== null && $offset->type->dialect !== Dialect::MySql)) {
            throw new InvalidStructure('A row window requires MySQL operands.');
        }
    }
}
