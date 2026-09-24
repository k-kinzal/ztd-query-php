<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Derivation\Objects;

use PhpParser\Node\Expr;
use SqlCatalog\Analysis\ExpressionEvaluator;
use SqlCatalog\Analysis\FunctionScope;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\Environment;
use SqlCatalog\Text\Origin;
use SqlCatalog\Type\TypeShape;

/**
 * Isolates conditional effects while honoring resolved short-circuit conditions.
 *
 * @visibility root
 */
final class BranchEffects
{
    /**
     * A resolved PHP truth value; tracked objects are always truthy.
     */
    public function truth(Domain $value): ?bool
    {
        $literal = $value->soleLiteral();
        if ($literal !== null) {
            return (bool) $literal->value;
        }

        return $value->soleObject() === null ? null : true;
    }

    /**
     * Evaluates only the necessary coalescing branch, joining effects when nullability is open.
     */
    public function coalesce(Expr\BinaryOp\Coalesce $node, Environment $environment, FunctionScope $scope, ExpressionEvaluator $expressions): Domain
    {
        $left = $expressions->evaluate($node->left, $environment, $scope);
        if (!$left->type()->isUnknown() && !$left->type()->isNullable()) {
            return $left;
        }
        if ($left->soleLiteral()?->value === null && $left->soleLiteral() !== null) {
            return $expressions->evaluate($node->right, $environment, $scope);
        }
        $right = $environment->copy();
        $value = $expressions->evaluate($node->right, $right, $scope);
        $environment->mergeBranches($environment, $right);

        return $left->union($value);
    }

    /**
     * Evaluates the right logical operand in its own branch unless the left decides execution.
     */
    public function shortCircuit(Expr\BinaryOp $node, Environment $environment, FunctionScope $scope, ExpressionEvaluator $expressions): Domain
    {
        $left = $this->truth($expressions->evaluate($node->left, $environment, $scope));
        $and = $node instanceof Expr\BinaryOp\BooleanAnd || $node instanceof Expr\BinaryOp\LogicalAnd;
        if ($left !== null && $left !== $and) {
            return Domain::literal($left);
        }
        $branch = $left === null ? $environment->copy() : $environment;
        $right = $this->truth($expressions->evaluate($node->right, $branch, $scope));
        if ($left === null) {
            $environment->mergeBranches($environment, $branch);
        }

        return $left !== null && $right !== null ? Domain::literal($right) : Domain::opaque(TypeShape::of(['bool']), Origin::Unresolved);
    }
}
