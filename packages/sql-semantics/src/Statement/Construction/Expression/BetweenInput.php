<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Expression;

/**
 * A range test that evaluates its subject according to the range rule.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Expression\BetweenInput(new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Expression\NullConstant(), new \SqlSemantics\Statement\Expression\NullConstant(), true);
 *     $input->negated // => true
 */
final class BetweenInput implements \SqlSemantics\Statement\Construction\ScalarInput
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Retains explicit new inputs; resolution belongs to their new enclosing query.
     */
    public function __construct(public readonly \SqlSemantics\Statement\Construction\ScalarInput $subject, public readonly \SqlSemantics\Statement\Construction\ScalarInput $lower, public readonly \SqlSemantics\Statement\Construction\ScalarInput $upper, public readonly bool $negated = false)
    {
        \SqlSemantics\Statement\Construction\InputDomain::check($subject);
        \SqlSemantics\Statement\Construction\InputDomain::check($lower);
        \SqlSemantics\Statement\Construction\InputDomain::check($upper);
    }
}
