<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\CollatedTextLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\BinaryOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Collation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;

/**
 * Binds a comparison that explicitly selects a supported native text collation.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class TextComparisonLowering
{
    /**
     * Selects the qualified stock operator with two native text arguments.
     */
    public function lower(BinaryOperation $expression, SourceBinding|JoinBinding $binding, bool $catalogFirst): ?TextComparison
    {
        $left = $this->ungroup($expression->left);
        $right = $this->ungroup($expression->right);
        if (!$left instanceof Collation && !$right instanceof Collation) {
            return null;
        }
        $qualifiers = array_column($expression->operator->qualifiers, 'value');
        Check::input(
            ($qualifiers === [] && $catalogFirst) || $qualifiers === ['pg_catalog'],
            'A text comparison must resolve to a pg_catalog operator.',
        );
        $name = $expression->operator->name->value;
        $operator = TextOperator::tryFrom($name === '!=' ? '<>' : $name);
        Check::input($operator !== null, 'A collated predicate needs a supported text comparison operator.');

        return new TextComparison(
            $operator,
            $this->operand($left, $binding, $catalogFirst),
            $this->operand($right, $binding, $catalogFirst),
            TextCollation::C,
        );
    }

    /**
     * Resolves one text argument, including any explicit collation.
     */
    public function operand(Scalar $operand, SourceBinding|JoinBinding $binding, bool $catalogFirst): TextLiteral|TextColumnReference|NullLiteral
    {
        $text = new CollatedTextLowering();

        return $operand instanceof Collation ? $text->lower($operand, $binding, $catalogFirst)->operand
            : $text->operand($operand, $binding, $catalogFirst);
    }

    /**
     * Exposes the grouped scalar to resolve its type and explicit collation.
     */
    public function ungroup(Scalar $expression): Scalar
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }

        return $expression;
    }
}
