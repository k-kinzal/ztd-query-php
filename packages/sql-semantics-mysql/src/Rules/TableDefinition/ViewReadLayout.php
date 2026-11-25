<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Statement\Query;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;

/**
 * Derives the field of a reopened source-free view from its stored integer value.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ViewReadLayout
{
    /**
     * Recognizes a single SELECT with no underlying table.
     */
    public function noInput(Query $query): bool
    {
        while ($query instanceof ParenthesizedQuery) {
            $query = $query->query;
        }

        return $query instanceof Select && ($query->from === null || $query->from instanceof Dual);
    }

    /**
     * Classifies a native signed literal after the server reopens the view.
     */
    public function integer(?Scalar $expression, GrammarRelease $release): ?Integral
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
        if ($expression instanceof SignedLiteral) {
            if ($negative && $expression->negative) {
                return null;
            }
            $negative = $negative || $expression->negative;
            $expression = $expression->number;
        }
        if (!$expression instanceof NumberLiteral || $expression->form !== NumberForm::Integer) {
            return null;
        }
        $digits = ltrim($expression->text, '0');
        if ($expression->beyondSigned() && !($negative && $digits === '9223372036854775808')) {
            return null;
        }
        $legacy = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
        $width = max(1, strlen($digits)) + (int) (!$legacy || $negative);

        return new Integral($width < 10 ? IntegralKind::Int : IntegralKind::BigInt, (string) $width);
    }
}
