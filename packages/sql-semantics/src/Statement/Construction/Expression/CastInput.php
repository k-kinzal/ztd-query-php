<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Expression;

/**
 * An explicit conversion of a new operand to a decoded type name.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Expression\CastInput(new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Type\SqliteCastTarget('TEXT'));
 *     $input->target->affinity // => \SqlSemantics\Statement\Declaration\Affinity::Text
 */
final class CastInput implements \SqlSemantics\Statement\Construction\ScalarInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Retains explicit new inputs; resolution belongs to their new enclosing query.
     */
    public function __construct(public readonly \SqlSemantics\Statement\Construction\ScalarInput $operand, public readonly \SqlSemantics\Statement\Type\SqliteCastTarget $target)
    {
        \SqlSemantics\Statement\Construction\InputDomain::check($operand);
    }
}
