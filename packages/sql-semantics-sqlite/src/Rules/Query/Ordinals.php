<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Query;

use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\HexLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Statement\Scalar;

/**
 * Recognises the integer constants that SQLite reads as result column positions in ORDER BY and GROUP BY.
 *
 * Rule: SQLITE-ORDINAL-001. After any COLLATE operators and parentheses
 * around it, a term is an integer constant when it is an integer literal
 * that fits 32 bits, or such a constant under a unary plus or minus (and
 * parentheses). Such a term selects the result column at that position and
 * is not evaluated as an expression. Terminates: every step removes one
 * operator of a finite expression.
 * Source: https://sqlite.org/lang_select.html#the_order_by_clause. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class Ordinals
{
    /**
     * Answers the integer a term denotes as a result column position, or null when the term is an ordinary expression.
     */
    public function value(Scalar $term): ?int
    {
        while ($term instanceof Collate || $term instanceof Grouped) {
            $term = $term->operand;
        }
        $sign = 1;
        while ($term instanceof Grouped || ($term instanceof Unary && $term->operator !== UnaryOperator::Not && $term->operator !== UnaryOperator::BitNot)) {
            if ($term instanceof Unary && $term->operator === UnaryOperator::Minus) {
                $sign = -$sign;
            }
            $term = $term->operand;
        }
        if ($term instanceof IntegerLiteral) {
            $digits = ltrim($term->digits, '0');

            return strlen($term->digits) <= 10 && strlen($digits) <= 10 && (int) $term->digits <= 2147483647 ? $sign * (int) $term->digits : null;
        }
        if ($term instanceof HexLiteral) {
            $digits = ltrim($term->digits, '0');

            return strlen($digits) <= 8 && hexdec($digits === '' ? '0' : $digits) <= 2147483647 ? $sign * (int) hexdec($digits === '' ? '0' : $digits) : null;
        }

        return null;
    }
}
