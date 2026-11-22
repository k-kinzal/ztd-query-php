<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;

/**
 * A comparison of two operands, in the order written.
 *
 * Comparisons belong to the bool_pri level and associate to the left; the
 * right operand is a predicate (MYSQL-PRECEDENCE-001). An operand that would
 * be read differently without a grouping, such as a comparison on the right
 * or a variable assignment on the left, is rejected (`Item_func_eq`,
 * `Item_func_equal`, `Item_func_ne`, `Item_func_lt`, …).
 *
 * Rule: MYSQL-COMPARISON-001. Facts: a comparison yields 1, 0 or NULL, an
 * integer; it can be NULL when an operand can, except `<=>`, which never
 * is. Two rows are compared element by element and must have the same
 * width (MYSQL-OPERAND-COLUMNS-001). Terminates: the operands are strict
 * parts. Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html,
 * https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Comparison implements Scalar
{
    use Snapshot;

    /**
     * @param ComparisonOperator $operator The operator
     * @param Scalar $left The left operand
     * @param Scalar $right The right operand
     */
    public function __construct(public readonly ComparisonOperator $operator, public readonly Scalar $left, public readonly Scalar $right)
    {
        $precedence = new Precedence();
        Check::input($precedence->fits($left, Precedence::BOOL_PRI, Precedence::BOOL_PRI), 'The left operand of a comparison needs a grouping to keep its place.');
        Check::input($precedence->opening($right) >= Precedence::PREDICATE, 'The right operand of a comparison needs a grouping to keep its place.');
    }

    /**
     * Derives both operands and combines their NULL facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $left = $derivation->scalar($this->left, $environment);
        $right = $derivation->scalar($this->right, $environment);
        $operands = new Operands();
        $operands->comparable([$left, $right], $derivation);

        return $operands->truth($this->operator === ComparisonOperator::NullSafeEqual ? Nullability::NotNull : $left->nullability->propagate($right->nullability));
    }

    /**
     * Writes the operands around the operator.
     */
    public function render(Output $out): void
    {
        $out->node($this->left)->symbol($this->operator->value)->node($this->right);
    }
}
