<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Preparation;

use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Operator\Logic;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Evaluation\Subquery\CachedProjection;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Statement\Scalar;

/**
 * Holds constant boolean operands for one WHERE evaluation lifetime.
 * The first reached read owns conversion warnings; later input rows reuse it.
 *
 * @visibility MySqlMemory
 */
final class Predicates
{
    /**
     * Compiles boolean structure without evaluating unreachable constant operands.
     *
     * @throws \MySqlMemory\Error\SqlError When an operand cannot be compiled
     */
    public function compile(Scalar $node, Scope $scope, Compiler $compiler, bool $nested = false): Evaluable
    {
        if ($compiler->constancy($node) === Constancy::Resolved) {
            $value = $compiler->compile($node, $scope);
            if (!$nested) {
                return $value;
            }
            $truth = $compiler->operators->truth($value->domain()->nullable);
            return new CachedProjection(new Logic(LogicalOperator::Or, $value, new Constant($compiler->operators->truth(false), 0), $truth));
        }
        if ($node instanceof Grouped) {
            return $this->compile($node->operand, $scope, $compiler, $nested);
        }
        if ($node instanceof Logical) {
            return new Logic($node->operator, $this->compile($node->left, $scope, $compiler, true), $this->compile($node->right, $scope, $compiler, true), $compiler->domain($node));
        }
        return $compiler->compile($node, $scope);
    }
}
