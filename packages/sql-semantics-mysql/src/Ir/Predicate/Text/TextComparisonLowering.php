<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate\Text;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\MySql\Ir\Expression\CollatedTextLowering;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteralLowering;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

/**
 * Resolves the native VARCHAR comparison before publishing the ordered operands.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TextComparisonLowering
{
    /**
     * Chooses the stock collation from a native field or explicit collation.
     */
    public function lower(Comparison $comparison, SourceBinding|JoinBinding $binding): ?TextComparison
    {
        $left = $this->operand($comparison->left, $binding);
        $right = $this->operand($comparison->right, $binding);
        if (!$left instanceof TextColumnReference && !$left instanceof CollatedText && !$right instanceof TextColumnReference && !$right instanceof CollatedText) {
            return null;
        }
        Check::input(
            $left !== null && !$left instanceof ColumnReference && !$left instanceof IntegralColumnReference
                && $right !== null && !$right instanceof ColumnReference && !$right instanceof IntegralColumnReference,
            'A VARCHAR comparison requires two native text operands or NULL.'
        );
        return new TextComparison(TextOperator::from($comparison->operator->value), $left, $right);
    }

    /**
     * Resolves an operand whose native text or signed role is defined by the exact field or literal.
     */
    public function operand(Scalar $operand, SourceBinding|JoinBinding $binding): CollatedText|ColumnReference|IntegralColumnReference|TextColumnReference|TextLiteral|NullLiteral|null
    {
        while ($operand instanceof Grouped) {
            $operand = $operand->operand;
        }
        return match (true) {
            $operand instanceof Collated => (new CollatedTextLowering())->lower($operand, $binding),
            $operand instanceof ColumnUse => $binding->comparisonField($operand),
            $operand instanceof ParsedNull => new NullLiteral(),
            default => (new TextLiteralLowering())->lower($operand),
        };
    }
}
