<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Literal;

/**
 * The three kinds of number literal the MySQL lexer tells apart.
 *
 * Digits alone are an integer while they fit an unsigned 64-bit integer and
 * a decimal beyond that; digits with a decimal point are a decimal; a number
 * with an exponent is a floating-point number.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/number-literals.html.
 *
 * @visibility public
 * @example Classifying the text of a number
 *     [\SqlSemantics\Platform\MySql\Statement\Literal\NumberForm::of('18446744073709551616'), \SqlSemantics\Platform\MySql\Statement\Literal\NumberForm::of('1e3')] // => [\SqlSemantics\Platform\MySql\Statement\Literal\NumberForm::Decimal, \SqlSemantics\Platform\MySql\Statement\Literal\NumberForm::Float]
 */
enum NumberForm
{
    case Integer;
    case Decimal;
    case Float;

    /**
     * Classifies the text of an unsigned number as the lexer does, or answers null when it is no number.
     */
    public static function of(string $text): ?self
    {
        if (preg_match('/\A(?:[0-9]+\.?[0-9]*|\.[0-9]+)[eE][+-]?[0-9]+\z/', $text) === 1) {
            return self::Float;
        }
        if (preg_match('/\A(?:[0-9]+\.[0-9]*|\.[0-9]+)\z/', $text) === 1) {
            return self::Decimal;
        }
        if (preg_match('/\A[0-9]+\z/', $text) !== 1) {
            return null;
        }
        $digits = ltrim($text, '0');

        return strlen($digits) < 20 || (strlen($digits) === 20 && strcmp($digits, '18446744073709551615') <= 0) ? self::Integer : self::Decimal;
    }
}
