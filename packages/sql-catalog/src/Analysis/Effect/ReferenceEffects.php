<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Effect;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;
use SqlCatalog\Analysis\Derivation\ModifiedNames;
use WeakMap;

/**
 * Names which may share storage in a body, without deciding which paths execute.
 *
 * @visibility root
 */
final class ReferenceEffects
{
    /** @var WeakMap<Node, array<string, true>> */
    private WeakMap $cache;

    /**
     * Starts an empty cache of reference exposure per body.
     */
    public function __construct()
    {
        $this->cache = new WeakMap();
    }

    /**
     * Names potentially sharing storage with a write in this body.
     *
     * @param array<string, true> $written
     * @return array<string, true>
     */
    public function affected(Node $node, array $written): array
    {
        $body = $node;
        while (!$body instanceof FunctionLike) {
            $parent = $body->getAttribute('parent');
            if (!$parent instanceof Node) {
                break;
            }
            $body = $parent;
        }
        $aliases = $this->cache[$body] ?? null;
        if ($aliases === null) {
            $aliases = $this->collect($body, true);
            $siblings = $body instanceof FunctionLike ? null : $body->getAttribute('fileStatements');
            foreach (is_array($siblings) ? $siblings : [] as $sibling) {
                if ($sibling instanceof Node) {
                    $aliases += $this->collect($sibling);
                }
            }
            $this->cache[$body] = $aliases;
        }

        return !$node instanceof Expr\CallLike && !isset($aliases[WriteEffects::ALL]) && array_intersect_key($aliases, $written) === [] ? [] : $aliases;
    }

    /**
     * Collects possible reference participants, excluding nested function bodies.
     *
     * @return array<string, true>
     */
    public function collect(Node $node, bool $root = false): array
    {
        $names = new ModifiedNames();
        $captured = [];
        if ($node instanceof Expr\Closure) {
            foreach ($node->uses as $use) {
                if ($use->byRef) {
                    $captured += $names->targets($use->var);
                }
            }
            if (!$root) {
                return $captured;
            }
        }
        if (!$root && ($node instanceof FunctionLike || $node instanceof Stmt\ClassLike)) {
            return [];
        }
        $found = $captured + ($node instanceof Stmt\Global_ ? $names->all($node->vars) : []);
        if ($node instanceof Expr\AssignRef) {
            $effects = new WriteEffects();
            $found = $names->targets($node->var) + $names->targets($node->expr)
                + $effects->dynamicTarget($node->var) + $effects->dynamicTarget($node->expr);
        }
        if ($node instanceof Stmt\Foreach_ && $node->byRef) {
            $found = $names->targets($node->valueVar) + $names->targets($node->expr);
        }
        foreach (get_object_vars($node) as $value) {
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node) {
                    $found += $this->collect($child);
                }
            }
        }

        return $found;
    }
}
