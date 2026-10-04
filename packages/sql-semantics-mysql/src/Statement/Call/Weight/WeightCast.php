<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Weight;

/**
 * The type WEIGHT_STRING casts its argument to before it computes the weights.
 *
 * Each case holds the keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#function_weight-string.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightCast::Binary->value // => 'BINARY'
 */
enum WeightCast: string
{
    case Char = 'CHAR';
    case Binary = 'BINARY';
}
