<?php

declare(strict_types=1);

namespace Deriver\Internal\Frontend\Php;

use Deriver\Internal\Frontend\Php\Control\ConditionalLowering;
use Deriver\Value\Term;
use PhpParser\Node\Expr;

/**
 * Lowers assignments without conflating values and reference addresses.
 * @visibility root
 */
final class AssignmentLowering
{
    /**
     * @param Lowering $lowering Callable lowering
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an assignment or increment.
     * @param Expr $node Assignment expression
     * @return string Evaluated result
     */
    public function lower(Expr $node): string
    {
        $g = $this->lowering->graph;
        if ($node instanceof Expr\Assign) {
            $value = $this->lowering->expression($node->expr);
            $assigned = $this->assign($node->var, $value);
            return $g->emit($node, 'copy', [$assigned]);
        }
        if ($node instanceof Expr\AssignRef) {
            return $g->emit($node, 'alias', [$this->lowering->location($node->var), $this->lowering->location($node->expr)]);
        }
        if ($node instanceof Expr\AssignOp\Coalesce) {
            return (new ConditionalLowering($this->lowering))->coalesceAssign($node);
        }
        if ($node instanceof Expr\AssignOp) {
            $address = $this->lowering->location($node->var);
            return $g->emit($node, 'compound', [$address, $this->lowering->expression($node->expr)], $this->operator($node));
        }
        if ($node instanceof Expr\PreInc || $node instanceof Expr\PostInc || $node instanceof Expr\PreDec || $node instanceof Expr\PostDec) {
            $address = $this->lowering->location($node->var);
            return $g->emit($node, 'increment', [$address], attributes: ['delta' => $node instanceof Expr\PreDec || $node instanceof Expr\PostDec ? -1 : 1, 'post' => $node instanceof Expr\PostInc || $node instanceof Expr\PostDec]);
        }
        return $g->emit($node, 'unsupported', name: $node->getType());
    }

    /**
     * Assigns one value, recursively destructuring array patterns.
     * @param Expr $target Assignment target
     * @param string $value Value register
     * @return string Assignment expression result
     */
    public function assign(Expr $target, string $value): string
    {
        $g = $this->lowering->graph;
        if ($target instanceof Expr\List_ || $target instanceof Expr\Array_) {
            foreach ($target->items as $index => $item) {
                if ($item === null) {
                    continue;
                }
                $key = $item->key === null ? $g->emit($item, 'constant', constant: Term::constant($index)) : $this->lowering->expression($item->key);
                $element = $g->emit($item, 'array-read', [$value, $key]);
                $this->assign($item->value, $element);
            }
            return $value;
        }
        return $g->emit($target, 'write', [$this->lowering->location($target), $value]);
    }
    /**
     * Maps compound assignment syntax to its corresponding binary operation.
     * @param Expr\AssignOp $node Compound assignment
     * @return string Binary operator
     */
    public function operator(Expr\AssignOp $node): string
    {
        return match ($node->getType()) {
            'Expr_AssignOp_Plus' => '+', 'Expr_AssignOp_Minus' => '-',
            'Expr_AssignOp_Mul' => '*', 'Expr_AssignOp_Div' => '/',
            'Expr_AssignOp_Mod' => '%', 'Expr_AssignOp_Pow' => '**',
            'Expr_AssignOp_Concat' => '.', 'Expr_AssignOp_BitwiseAnd' => '&',
            'Expr_AssignOp_BitwiseOr' => '|', 'Expr_AssignOp_BitwiseXor' => '^',
            'Expr_AssignOp_ShiftLeft' => '<<', 'Expr_AssignOp_ShiftRight' => '>>',
            default => 'unsupported',
        };
    }

}
