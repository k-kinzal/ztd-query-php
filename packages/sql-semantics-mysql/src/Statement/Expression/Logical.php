<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;

/**
 * A logical AND, OR or XOR of two operands, in the order written (`Item_cond_and`, `Item_cond_or`, `Item_func_xor`).
 *
 * The operators associate to the left; AND binds tighter than XOR, XOR
 * tighter than OR (MYSQL-PRECEDENCE-001). An operand that would be read
 * differently without a grouping is rejected.
 *
 * Rule: MYSQL-LOGICAL-001. Facts: the result is 1, 0 or NULL, an integer;
 * it can be NULL when an operand can. Both operands take a single value.
 * The symbols `||` and `&&` are deprecated synonyms of OR and AND; reading
 * one raises its warning after the left operand (MYSQL-DEPRECATION-001).
 * Terminates: the operands are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/logical-operators.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading both operands of a conjunction
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a && b');
 *     [$query->query->where->operator->value, $query->query->where->symbolic, $query->toString()] // => ['AND', true, 'SELECT a FROM t WHERE a && b']
 * @example Refusing an operand that would associate differently
 *     $or = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a OR b')->query->where;
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Logical(\SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator::And, $or, new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('c'))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Logical implements Scalar
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param LogicalOperator $operator The operator
     * @param Scalar $left The left operand
     * @param Scalar $right The right operand
     * @param bool $symbolic Whether OR is written `||` or AND `&&`
     */
    public function __construct(public readonly LogicalOperator $operator, public readonly Scalar $left, public readonly Scalar $right, public readonly bool $symbolic = false, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input(!$symbolic || $operator !== LogicalOperator::Xor, 'XOR has no symbolic spelling.');
        $precedence = new Precedence();
        Check::input($precedence->fits($left, $operator->level(), $operator->level()), 'The left operand of ' . $operator->value . ' needs a grouping to keep its place.');
        Check::input($precedence->opening($right) > $operator->level(), 'The right operand of ' . $operator->value . ' needs a grouping to keep its place.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives both operands and combines their NULL facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        Check::input(!$this->symbolic || $this->operator !== LogicalOperator::Or || !$derivation->context->profile->lexical->pipesAsConcat, 'The symbolic OR operator || requires PIPES_AS_CONCAT to be disabled.');
        $operands = new Operands();
        $left = $operands->single($derivation->scalar($this->left, $environment), $derivation);
        if ($this->symbolic) {
            Deprecation::raise($this->operator === LogicalOperator::Or ? Deprecated::PipesOr : Deprecated::AmpersandsAnd, $derivation, $this->left);
        }
        $right = $operands->single($derivation->scalar($this->right, $environment), $derivation);

        return $operands->truth($left->nullability->propagate($right->nullability));
    }

    /**
     * Writes the operands around the operator keyword, or its symbol when it was written so.
     */
    public function render(Output $out): void
    {
        $out->node($this->left);
        if ($this->symbolic) {
            $out->symbol($this->operator === LogicalOperator::Or ? '||' : '&&');
        } else {
            $out->keyword($this->operator->value);
        }
        $out->node($this->right);
    }
}
