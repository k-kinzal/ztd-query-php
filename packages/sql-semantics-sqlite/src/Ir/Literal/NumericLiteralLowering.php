<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Literal;

use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral as ParsedHex;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral as ParsedInteger;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\RealLiteral as ParsedReal;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;

/**
 * Chooses the SQLite storage role of a directly signed numeric literal.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class NumericLiteralLowering
{
    /**
     * Converts the digits using the integer range in the written direction.
     */
    public function lower(ParsedInteger|ParsedHex|ParsedReal $scalar, bool $negative): IntegerLiteral|HexIntegerLiteral|RealLiteral
    {
        if ($scalar instanceof ParsedHex) {
            $digits = ltrim($scalar->digits, '0');

            return new HexIntegerLiteral($digits === '' ? '0' : $digits, $negative);
        }
        if ($scalar instanceof ParsedReal) {
            return new RealLiteral($scalar->whole, $scalar->fraction, $scalar->exponent, $negative, $scalar->uppercaseExponent);
        }

        $digits = ltrim($scalar->digits, '0');
        $boundary = $negative ? '9223372036854775808' : '9223372036854775807';
        if (strlen($digits) > 19 || (strlen($digits) === 19 && strcmp($digits, $boundary) > 0)) {
            return new RealLiteral($scalar->digits, null, null, $negative);
        }

        return new IntegerLiteral($digits === '' ? '0' : ($negative ? '-' : '') . $digits);
    }

    /**
     * Resolves a signed numeric literal through grouping and intervening plus operations.
     */
    public function signed(Unary $expression): IntegerLiteral|HexIntegerLiteral|RealLiteral|null
    {
        if ($expression->operator !== UnaryOperator::Plus && $expression->operator !== UnaryOperator::Minus) {
            return null;
        }
        $operand = $expression->operand;
        while ($operand instanceof Grouped || ($operand instanceof Unary && $operand->operator === UnaryOperator::Plus)) {
            $operand = $operand->operand;
        }

        return $operand instanceof ParsedInteger || $operand instanceof ParsedHex || $operand instanceof ParsedReal
            ? $this->lower($operand, $expression->operator === UnaryOperator::Minus)
            : null;
    }
}
