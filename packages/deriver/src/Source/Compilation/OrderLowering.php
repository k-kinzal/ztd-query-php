<?php

declare(strict_types=1);

namespace Deriver\Source\Compilation;

use Deriver\ControlFlow\Terminator;
use PhpParser\Node\Expr;

/**
 * Explores both operand orders while keeping one source observation per expression.
 * @visibility root
 */
final class OrderLowering
{
    /**
     * @param Lowering $lowering Shared graph construction
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Evaluates each operand once per path and joins their frozen values.
     * @param Expr\BinaryOp $node Operator with potentially interacting operands
     * @return string Result register
     */
    public function binary(Expr\BinaryOp $node): string
    {
        $g = $this->lowering->graph;
        $order = $g->emit($node, 'evaluation-order');
        $left = $g->block();
        $right = $g->block();
        $join = $g->block();
        $g->end(new Terminator('branch', $order, [$left, $right]));
        $g->current = $left;
        $a = $this->lowering->expression($node->left);
        $g->end(new Terminator('branch', $order, [$right, $join]));
        $g->current = $right;
        $b = $this->lowering->expression($node->right);
        $g->end(new Terminator('branch', $order, [$join, $left]));
        $g->current = $join;
        return $g->emit($node, 'binary', [$a, $b], $node->getOperatorSigil(), attributes: ['evaluation-order' => $order]);
    }
}
