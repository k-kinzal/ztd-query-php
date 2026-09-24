<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\OperatorSet;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Operand rules of operator class members: strategy and support numbers and associated types.
 * @visibility SqlSemantics
 */
final class MemberNumber
{
    /**
     * Strategy and support numbers range from 1 to 32767.
     * @throws InvalidStructure
     */
    public static function validate(int $number): void
    {
        if ($number < 1 || $number > 32767) {
            throw new InvalidStructure('Operator class members are numbered from 1 to 32767.');
        }
    }

    /**
     * Associated types are both present or both absent and are PostgreSQL declarations.
     * @throws InvalidStructure
     */
    public static function types(?TypeDescriptor $left, ?TypeDescriptor $right): void
    {
        if (($left === null) !== ($right === null) || ($left !== null && $left->dialect !== Dialect::PostgreSql) || ($right !== null && $right->dialect !== Dialect::PostgreSql)) {
            throw new InvalidStructure('A member names both associated PostgreSQL types or neither.');
        }
    }
}
