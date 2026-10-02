<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Expression;

/**
 * One ordered unary evaluation request.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Expression\UnaryInput(\SqlSemantics\Statement\Expression\SqliteUnaryOperator::Not, new \SqlSemantics\Statement\Expression\NullConstant());
 *     $input->operator->value // => 'NOT'
 */
final class UnaryInput implements \SqlSemantics\Statement\Construction\ScalarInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Retains explicit new inputs; resolution belongs to their new enclosing query.
     */
    public function __construct(public readonly \SqlSemantics\Statement\Expression\SqliteUnaryOperator $operator, public readonly \SqlSemantics\Statement\Construction\ScalarInput $operand)
    {
        \SqlSemantics\Statement\Construction\InputDomain::check($operand);
    }
}
