<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Expression;

/**
 * One binary operation with distinct left and right inputs.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Expression\BinaryInput(new \SqlSemantics\Statement\Expression\NullConstant(), \SqlSemantics\Statement\Expression\SqliteBinaryOperator::Is, new \SqlSemantics\Statement\Expression\NullConstant());
 *     $input->operator->value // => 'IS'
 */
final class BinaryInput implements \SqlSemantics\Statement\Construction\ScalarInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Retains explicit new inputs; resolution belongs to their new enclosing query.
     */
    public function __construct(public readonly \SqlSemantics\Statement\Construction\ScalarInput $left, public readonly \SqlSemantics\Statement\Expression\SqliteBinaryOperator $operator, public readonly \SqlSemantics\Statement\Construction\ScalarInput $right, public readonly ?\SqlSemantics\Statement\Expression\Rendering\SqliteBinaryLayout $layout = null)
    {
        \SqlSemantics\Statement\Construction\InputDomain::check($left);
        \SqlSemantics\Statement\Construction\InputDomain::check($right);
    }
}
