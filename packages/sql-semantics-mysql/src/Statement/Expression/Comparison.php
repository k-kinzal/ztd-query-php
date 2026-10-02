<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A comparison of two operands, in the order written.
 *
 * Comparisons associate to the left and bind tighter than a variable
 * assignment: a comparison as right operand, or an assignment as left
 * operand, would be read differently when written without a grouping, so
 * neither is accepted.
 *
 * Slice of the expression family: it covers the plain comparison operators
 * only and is completed or replaced by that family.
 *
 * Rule: MYSQL-COMPARISON-001. Facts: a comparison yields 1, 0 or NULL, an
 * integer; it can be NULL when an operand can, except `<=>`, which never
 * is. Diagnostics: none. Terminates: the operands are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading both operands
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a >= 10');
 *     [$query->statement->where->left->name->value, $query->statement->where->right->text] // => ['a', '10']
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
        Check::input(!$right instanceof self, 'A comparison as right operand of a comparison needs a grouping.');
        Check::input(!$left instanceof VariableAssignment, 'A variable assignment as left operand of a comparison needs a grouping.');
    }

    /**
     * Derives both operands and combines their NULL facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $left = $derivation->scalar($this->left, $environment);
        $right = $derivation->scalar($this->right, $environment);

        return new ScalarFact(
            new Known(new Integral(IntegralKind::BigInt)),
            $this->operator === ComparisonOperator::NullSafeEqual ? Nullability::NotNull : $left->nullability->propagate($right->nullability),
        );
    }

    /**
     * Writes the operands around the operator.
     */
    public function render(Output $out): void
    {
        $out->node($this->left)->symbol($this->operator->value)->node($this->right);
    }
}
