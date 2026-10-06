<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A logical AND, OR or XOR of two operands, in the order written (`Item_cond_and`, `Item_cond_or`, `Item_func_xor`).
 *
 * The operators associate to the left; AND binds tighter than XOR, XOR
 * tighter than OR (MYSQL-PRECEDENCE-001). An operand that would be read
 * differently without a grouping is rejected.
 *
 * Rule: MYSQL-LOGICAL-001. Facts: the result is 1, 0 or NULL, an integer;
 * it can be NULL when an operand can. Both operands take a single value.
 * Terminates: the operands are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/logical-operators.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading both operands of a conjunction
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a && b');
 *     [$query->statement->where->operator->value, $query->toString()] // => ['AND', 'SELECT a FROM t WHERE a AND b']
 * @example Refusing an operand that would associate differently
 *     $or = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a OR b')->statement->where;
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Logical(\SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator::And, $or, new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('c'))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Logical implements Scalar
{
    use Snapshot;

    /**
     * @param LogicalOperator $operator The operator
     * @param Scalar $left The left operand
     * @param Scalar $right The right operand
     */
    public function __construct(public readonly LogicalOperator $operator, public readonly Scalar $left, public readonly Scalar $right)
    {
        $precedence = new Precedence();
        Check::input($precedence->fits($left, $operator->level(), $operator->level()), 'The left operand of ' . $operator->value . ' needs a grouping to keep its place.');
        Check::input($precedence->opening($right) > $operator->level(), 'The right operand of ' . $operator->value . ' needs a grouping to keep its place.');
    }

    /**
     * Derives both operands and combines their NULL facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operands = new Operands();
        $left = $operands->single($derivation->scalar($this->left, $environment), $derivation);
        $right = $operands->single($derivation->scalar($this->right, $environment), $derivation);

        return $operands->truth($left->nullability->propagate($right->nullability));
    }

    /**
     * Writes the operands around the operator keyword.
     */
    public function render(Output $out): void
    {
        $out->node($this->left)->keyword($this->operator->value)->node($this->right);
    }
}
