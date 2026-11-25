<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Literal;

use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;

/**
 * Normalizes the literal token before assigning its allowed signed width.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class IntegerTokenLowering
{
    /**
     * Returns the signed digits, effective leading zeroes and explicit negative-zero flag.
     * @return array{string, int, bool}|null
     */
    public function lower(Scalar $expression): ?array
    {
        $spelling = $this->spelling($expression);
        if ($spelling === null) {
            return null;
        }
        $negative = $spelling[0] === '-';
        $text = ltrim($spelling, '-');
        $digits = ltrim($text, '0');
        $zeroes = min(strlen($text) - max(1, strlen($digits)), IntegerLiteral::MAX_LEADING_ZEROES);

        return [$digits === '' ? '0' : (($negative ? '-' : '') . $digits), $zeroes, $negative && $digits === ''];
    }

    /**
     * Returns the exact digits and significant polarity of an integral token.
     */
    public function spelling(Scalar $expression): ?string
    {
        $negative = false;
        if ($expression instanceof Unary && $expression->operand instanceof NumberLiteral
            && ($expression->operator === UnaryOperator::Minus || $expression->operator === UnaryOperator::Plus)) {
            $negative = $expression->operator === UnaryOperator::Minus;
            $expression = $expression->operand;
        } elseif ($expression instanceof SignedLiteral) {
            $negative = $expression->negative;
            $expression = $expression->number;
        }
        if ($expression instanceof NumberLiteral) {
            Check::input($expression->form === NumberForm::Integer, 'The numeric literal must have the signed integer role.');

            return ($negative ? '-' : '') . $expression->text;
        }

        return null;
    }

    /**
     * Removes transparent projection wrappers before reading the exact signed token.
     */
    public function groupedSpelling(Scalar $expression): ?string
    {
        $negative = false;
        while ($expression instanceof Grouped || $expression instanceof Unary) {
            if ($expression instanceof Unary && $expression->operator !== UnaryOperator::Plus) {
                if ($negative || $expression->operator !== UnaryOperator::Minus) {
                    return null;
                }
                $negative = true;
            }
            $expression = $expression->operand;
        }
        if ($negative && $expression instanceof SignedLiteral) {
            return null;
        }
        $token = $this->spelling($expression);

        return $token !== null && $negative ? '-' . $token : $token;
    }
}
