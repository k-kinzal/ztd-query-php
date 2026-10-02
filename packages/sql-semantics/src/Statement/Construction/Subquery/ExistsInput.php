<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Subquery;

/**
 * An existence test whose result does not expose its projection values.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Subquery\ExistsInput(new \SqlSemantics\Statement\Construction\Query\SelectDefinition(new \SqlSemantics\Statement\Construction\Query\ProjectionDefinition(new \SqlSemantics\Statement\Construction\Query\FieldDefinition(new \SqlSemantics\Statement\Expression\NullConstant()))));
 *     $input->query instanceof \SqlSemantics\Statement\Construction\Query\SelectDefinition // => true
 */
final class ExistsInput implements \SqlSemantics\Statement\Construction\ScalarInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Specifies a fresh nested query; a bound subquery cannot be transplanted here.
     */
    public function __construct(public readonly \SqlSemantics\Statement\Construction\Query\SelectDefinition|\SqlSemantics\Statement\Construction\Query\RowsDefinition $query)
    {
    }
}
