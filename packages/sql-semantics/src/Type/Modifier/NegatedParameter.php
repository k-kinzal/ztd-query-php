<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Modifier;

use SqlSemantics\Type\Identity\Numeric\NumericParameter;

/**
 * Numeric negation in a type modifier, retaining the operand without evaluating it.
 * @visibility public
 * @example Retaining nested negation
 *     $inner = new \SqlSemantics\Type\Modifier\NegatedParameter(new \SqlSemantics\Type\Identity\Numeric\NumericParameter('12'));
 *     (new \SqlSemantics\Type\Modifier\NegatedParameter($inner))->operand === $inner // => true
 */
final class NegatedParameter
{
    /**
     * Numeric constants and nested numeric negations are the complete operand domain.
     */
    public function __construct(public readonly NumericParameter|self $operand)
    {
    }
}
