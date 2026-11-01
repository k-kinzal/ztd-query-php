<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Keyword;

/**
 * The keyword requesting an exact fixed-point type.
 *
 * @visibility public
 * @example Reading the retained keyword
 *     \SqlSemantics\Platform\MySql\Statement\Type\Keyword\DecimalWord::Decimal->value // => 'DECIMAL'
 */
enum DecimalWord: string
{
    case Decimal = 'DECIMAL';
    case Dec = 'DEC';
    case Numeric = 'NUMERIC';
    case Fixed = 'FIXED';
}
