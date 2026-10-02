<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Literal;

/**
 * An expression the raw parser already reads as a constant, which may denote an integer.
 *
 * Type modifiers must be such constants: `numeric(10, 2)` is a type,
 * `numeric(a, 2)` is an error. A numeric constant, a string constant and a
 * negated numeric constant qualify; every other expression does not
 * implement this interface.
 * Source: https://www.postgresql.org/docs/17/datatype.html.
 *
 * @visibility public
 * @example Reading the integer a constant denotes
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('10')))->integerValue() // => '10'
 */
interface IntegerValued
{
    /**
     * Answers the integer the constant denotes as canonical decimal digits with an optional minus sign, or null when it denotes none.
     */
    public function integerValue(): ?string;
}
