<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Aggregation;

use SqlSemantics\Platform\Sqlite\Ir\Expression\BinaryOperatorLowering;
use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;

/**
 * Resolves the group-time aggregate separately from its scalar threshold.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class GroupFilterLowering
{
    /**
     * Binds either order of the count and threshold operands.
     * @throws InvalidConstruction When the filter falls outside the count comparison profile
     */
    public function lower(Scalar $expression, GroupBinding $binding, SourceBinding $source): CountComparison
    {
        $comparison = $this->operand($expression);
        Check::input($comparison instanceof Binary, 'HAVING requires a comparison of one COUNT and one threshold.');
        $operator = (new BinaryOperatorLowering())->lower($comparison->operator);
        Check::input($operator instanceof ComparisonOperator, 'HAVING requires a comparison of one COUNT and one threshold.');
        $left = $this->operand($comparison->left);
        $right = $this->operand($comparison->right);
        $thresholdFirst = !$left instanceof FunctionCall;
        $aggregate = $thresholdFirst ? $right : $left;
        $threshold = $thresholdFirst ? $left : $right;
        Check::input($aggregate instanceof FunctionCall && !$threshold instanceof FunctionCall, 'HAVING requires a comparison of one COUNT and one threshold.');
        $count = (new CountBinding())->lower($aggregate, $binding);
        if (!$thresholdFirst && ($operator === ComparisonOperator::Is || $operator === ComparisonOperator::IsNot)
            && $threshold instanceof TruthWord && ($desired = $source->constantTruth($threshold)) !== null) {
            $nonzero = $desired === ($operator === ComparisonOperator::Is);

            return new CountComparison($count, $nonzero ? ComparisonOperator::NotEqual : ComparisonOperator::Equal, new IntegerLiteral('0'));
        }
        $value = (new ExpressionLowering())->lower($threshold, $source);
        Check::input($value instanceof IntegerLiteral || $value instanceof NullLiteral || $value instanceof Parameter, 'HAVING requires an integer, NULL or numbered threshold.');

        return new CountComparison($count, $operator, $value, $thresholdFirst);
    }

    /**
     * Finds the operation inside equivalent expression grouping.
     */
    public function operand(Scalar $expression): Scalar
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }

        return $expression;
    }
}
