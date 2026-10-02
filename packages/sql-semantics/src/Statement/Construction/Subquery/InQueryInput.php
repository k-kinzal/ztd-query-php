<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Subquery;

/**
 * A membership comparison with a query output.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Subquery\InQueryInput(new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Construction\Query\RowsDefinition(new \SqlSemantics\Statement\Construction\Query\RowDefinition(new \SqlSemantics\Statement\Expression\NullConstant())), true);
 *     $input->negated // => true
 */
final class InQueryInput implements \SqlSemantics\Statement\Construction\ScalarInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Specifies a fresh nested query; a bound subquery cannot be transplanted here.
     */
    public function __construct(public readonly \SqlSemantics\Statement\Construction\ScalarInput $subject, public readonly \SqlSemantics\Statement\Construction\Query\SelectDefinition|\SqlSemantics\Statement\Construction\Query\RowsDefinition $query, public readonly bool $negated = false)
    {
        \SqlSemantics\Statement\Construction\InputDomain::check($subject);
    }
}
