<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity\Numeric;

use SqlSemantics\Model\Scalar\Value\LiteralClassification;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One numeric type parameter, retaining arbitrary precision without evaluating it.
 * @visibility public
 * @example Retaining an arbitrary-precision numeric operand
 *     (new \SqlSemantics\Type\Identity\Numeric\NumericParameter('99999999999999999999'))->spelling // => '99999999999999999999'
 */
final class NumericParameter
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $spelling)
    {
        if (LiteralClassification::of($spelling) !== LiteralKind::Number) {
            throw new InvalidStructure('A numeric type parameter requires one numeric literal.');
        }
    }
}
