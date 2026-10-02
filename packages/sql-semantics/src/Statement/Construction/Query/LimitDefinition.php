<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Query;

/**
 * New count and offset expressions in the row restriction environment.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Query\LimitDefinition(new \SqlSemantics\Statement\Expression\NullConstant());
 *     $input->offset // => null
 */
final class LimitDefinition
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Retains explicit new inputs; no existing scope or bound field is accepted.
     */
    public function __construct(public readonly \SqlSemantics\Statement\Construction\ScalarInput $count, public readonly ?\SqlSemantics\Statement\Construction\ScalarInput $offset = null, public readonly bool $commaSyntax = false)
    {
        \SqlSemantics\Statement\Construction\InputDomain::check($count);
        if ($offset !== null) {
            \SqlSemantics\Statement\Construction\InputDomain::check($offset);
        }
    }
}
