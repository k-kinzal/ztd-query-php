<?php

declare(strict_types=1);

namespace Deriver\Source\Compilation;

use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Detects potentially interacting effects in expressions without ordered semantics.
 * @visibility root
 */
final class EffectInspection
{
    /**
     * Checks whether two expressions can observe each other's effects.
     * @param Expr $left Left operand
     * @param Expr $right Right operand
     * @return bool Whether a conservative ordering boundary is needed
     */
    public function conflicts(Expr $left, Expr $right): bool
    {
        return ($this->effectful($left) && $this->observes($right)) || ($this->effectful($right) && $this->observes($left));
    }

    /**
     * Detects writes and implicit or explicit calls.
     * @param Node $node Expression subtree
     * @return bool Whether evaluation may change state
     */
    public function effectful(Node $node): bool
    {
        if ($node instanceof Expr\Assign || $node instanceof Expr\AssignOp || $node instanceof Expr\AssignRef || $node instanceof Expr\CallLike || $node instanceof Expr\PreInc || $node instanceof Expr\PostInc || $node instanceof Expr\PreDec || $node instanceof Expr\PostDec || $node instanceof Expr\Include_ || $node instanceof Expr\Eval_) {
            return true;
        }
        foreach ($this->children($node) as $child) {
            if ($this->effectful($child)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detects reads whose result could depend on other evaluation effects.
     * @param Node $node Expression subtree
     * @return bool Whether the expression observes state
     */
    public function observes(Node $node): bool
    {
        if ($node instanceof Expr\Variable || $node instanceof Expr\PropertyFetch || $this->effectful($node)) {
            return true;
        }
        foreach ($this->children($node) as $child) {
            if ($this->observes($child)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Visits parser children without depending on their concrete class.
     * @param Node $node Parent node
     * @return list<Node> Immediate children
     */
    public function children(Node $node): array
    {
        $children = [];
        foreach ($node->getSubNodeNames() as $name) {
            $child = get_object_vars($node)[$name] ?? null;
            if ($child instanceof Node) {
                $children[] = $child;
            } elseif (is_array($child)) {
                foreach ($child as $element) {
                    if ($element instanceof Node) {
                        $children[] = $element;
                    }
                }
            }
        }
        return $children;
    }
}
