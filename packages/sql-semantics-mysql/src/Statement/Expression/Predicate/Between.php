<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Predicate;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;

/**
 * A range test: `x [NOT] BETWEEN low AND high` (`Item_func_between`).
 *
 * The operand and the lower bound are bit_expr; the upper bound is a
 * predicate, so `a BETWEEN 1 AND 2 BETWEEN 3 AND 4` nests in the upper bound.
 * A lower bound whose right edge would take the AND, such as a variable
 * assignment, is rejected (MYSQL-PRECEDENCE-001).
 *
 * Rule: MYSQL-BETWEEN-001. Facts: 1, 0 or NULL, an integer; it can be NULL
 * when an operand can. The three operands must have the same number of
 * columns (MYSQL-OPERAND-COLUMNS-001). Terminates: the operands are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html#operator_between.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Between implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The tested expression
     * @param Scalar $low The lower bound
     * @param Scalar $high The upper bound
     * @param bool $negated Whether NOT is written before BETWEEN
     */
    public function __construct(public readonly Scalar $operand, public readonly Scalar $low, public readonly Scalar $high, public readonly bool $negated = false)
    {
        $precedence = new Precedence();
        Check::input($precedence->fits($operand, Precedence::PREDICATE, Precedence::BIT_EXPR), 'The operand of BETWEEN needs a grouping to keep its place.');
        Check::input($precedence->admits($low, Precedence::BIT_EXPR) && !$precedence->absorbs($low, Precedence::CONJUNCTION, Precedence::CONJUNCTION), 'The lower bound of BETWEEN needs a grouping to keep its place.');
        Check::input($precedence->admits($high, Precedence::PREDICATE), 'The upper bound of BETWEEN needs a grouping to keep its place.');
    }

    /**
     * Derives the three operands, checks their widths and combines their NULL facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $low = $derivation->scalar($this->low, $environment);
        $high = $derivation->scalar($this->high, $environment);
        $operands = new Operands();
        $operands->comparable([$operand, $low, $high], $derivation);

        return $operands->truth($operand->nullability->propagate($low->nullability)->propagate($high->nullability));
    }

    /**
     * Writes the operand, the optional NOT, BETWEEN and the bounds.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword('BETWEEN')->node($this->low)->keyword('AND')->node($this->high);
    }
}
