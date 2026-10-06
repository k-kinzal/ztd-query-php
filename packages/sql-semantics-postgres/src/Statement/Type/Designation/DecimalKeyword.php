<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Designation;

/**
 * The keyword spellings of the `numeric` type.
 *
 * The three spellings denote the same type.
 * Source: https://www.postgresql.org/docs/17/datatype-numeric.html#DATATYPE-NUMERIC-DECIMAL.
 *
 * @visibility public
 * @example Spelling the short form
 *     \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\DecimalKeyword::Dec->value // => 'DEC'
 */
enum DecimalKeyword: string
{
    case Numeric = 'NUMERIC';
    case Decimal = 'DECIMAL';
    case Dec = 'DEC';
}
