<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Expression;

/**
 * An explicit comparison collation for a new operand.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Expression\CollationInput(new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Identifier\Name('nocase'));
 *     $input->collation->value // => 'nocase'
 */
final class CollationInput implements \SqlSemantics\Statement\Construction\ScalarInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Retains explicit new inputs; resolution belongs to their new enclosing query.
     */
    public function __construct(public readonly \SqlSemantics\Statement\Construction\ScalarInput $operand, public readonly \SqlSemantics\Statement\Identifier\Name $collation)
    {
        \SqlSemantics\Statement\Construction\InputDomain::check($operand);
    }
}
