<?php

declare(strict_types=1);

namespace Deriver\Source\Declaration;

use Deriver\Source\Compilation\EffectInspection;
use PhpParser\Node;
use PhpParser\Node\Expr;

/**
 * Selects possible property writers from syntax before lowering any body.
 * @visibility root
 */
final class PropertyWriteIndex
{
    /**
     * Uses captured syntax to select possible property writers.
     */
    public function __construct(private readonly ProjectIndex $index)
    {
    }

    /**

     * @return list<string>

     */
    public function owners(string $name): array
    {
        $owners = [];
        foreach ($this->index->declarations as $source) {
            if ($this->contains($source->node, $name)) {
                $owners[] = $source->symbol;
            }
        }
        return $owners;
    }

    /**
     * Inspects one lexical body for writes that may name the requested property.
     */
    public function contains(Node $node, string $name, bool $root = true): bool
    {
        if (!$root && ($node instanceof Node\FunctionLike || $node instanceof Node\Stmt\ClassLike)) {
            return false;
        }
        if ($node instanceof Node\Param) {
            return $node->flags !== 0 && $node->var instanceof Expr\Variable && $node->var->name === $name;
        }
        if ($this->mutation($node)) {
            $target = $node->var;
            while ($target instanceof Expr\ArrayDimFetch) {
                $target = $target->var;
            }
            if ($target instanceof Expr\PropertyFetch || $target instanceof Expr\StaticPropertyFetch) {
                if (!$target->name instanceof Node\Identifier || $target->name->toString() === $name) {
                    return true;
                }
            }
        }
        foreach ((new EffectInspection())->children($node) as $child) {
            if ($this->contains($child, $name, false)) {
                return true;
            }
        }
        return false;
    }
    /**
     * Selects syntax nodes whose variable operand denotes written storage.
     * @phpstan-assert-if-true Expr\Assign|Expr\AssignOp|Expr\AssignRef|Expr\PreInc|Expr\PostInc|Expr\PreDec|Expr\PostDec $node
     */
    public function mutation(Node $node): bool
    {
        return $node instanceof Expr\Assign || $node instanceof Expr\AssignOp || $node instanceof Expr\AssignRef || $node instanceof Expr\PreInc || $node instanceof Expr\PostInc || $node instanceof Expr\PreDec || $node instanceof Expr\PostDec;
    }

}
