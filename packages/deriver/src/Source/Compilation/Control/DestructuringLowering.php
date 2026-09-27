<?php

declare(strict_types=1);

namespace Deriver\Source\Compilation\Control;

use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\Lowering;
use Deriver\Value\Term;
use PhpParser\Node\Expr;

/**
 * Preserves array snapshots and shared cells while lowering assignment patterns.
 * @visibility root
 */
final class DestructuringLowering
{
    /**
     * @param Lowering $lowering Current callable compiler
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Evaluates a destructured source once before assigning its entries in order.
     * @param Expr\Assign $node Assignment with an array or list pattern
     * @return string Assigned source value
     */
    public function assignment(Expr\Assign $node): string
    {
        if (!$node->var instanceof Expr\List_ && !$node->var instanceof Expr\Array_) {
            return (new AssignmentLowering($this->lowering))->lower($node);
        }
        $g = $this->lowering->graph;
        if (!self::references($node->var)) {
            return $this->assign($node->var, $this->lowering->expression($node->expr));
        }
        $address = $this->source($node->expr, true);
        $reference = $g->emit($node->expr, 'reference', [$address]);
        $address = $g->emit($node->expr, 'returned-address', [$reference]);
        return $this->assign($node->var, '', $address);
    }

    /**
     * Assigns nested patterns from an immutable value or a previously anchored reference.
     * @param Expr\List_|Expr\Array_ $pattern Destination pattern
     * @param string $value Captured value register for ordinary destructuring
     * @param string $address Anchored source cell when the pattern contains references
     * @return string Assigned source value
     */
    public function assign(Expr\List_|Expr\Array_ $pattern, string $value, string $address = ''): string
    {
        $g = $this->lowering->graph;
        foreach ($pattern->items as $index => $item) {
            if ($item === null) {
                continue;
            }
            $key = $item->key === null ? $g->emit($item, 'constant', constant: Term::constant($index)) : $this->lowering->expression($item->key);
            if ($item->byRef || self::references($item->value)) {
                $element = $g->emit($item, 'element-address', [$address, $key]);
                $reference = $g->emit($item, 'reference', [$element]);
                $element = $g->emit($item, 'returned-address', [$reference]);
                if ($item->value instanceof Expr\List_ || $item->value instanceof Expr\Array_) {
                    $this->assign($item->value, '', $element);
                } else {
                    $g->emit($item, 'alias', [$this->lowering->location($item->value), $element]);
                }
                continue;
            }
            $container = $address === '' ? $value : $g->emit($item, 'read', [$address]);
            $element = $g->emit($item, 'array-read', [$container, $key], attributes: ['destructure' => true]);
            (new AssignmentLowering($this->lowering))->assign($item->value, $element);
        }
        return $address === '' ? $value : $g->emit($pattern, 'read', [$address]);
    }

    /**
     * Finds references at any depth without treating ordinary array expressions as cells.
     * @param Expr $pattern Possible assignment pattern
     * @return bool Whether any leaf binds by reference
     */
    public static function references(Expr $pattern): bool
    {
        $pending = [$pattern];
        while ($pending !== []) {
            $current = array_pop($pending);
            if (!$current instanceof Expr\List_ && !$current instanceof Expr\Array_) {
                continue;
            }
            foreach ($current->items as $item) {
                if ($item === null) {
                    continue;
                }
                if ($item->byRef) {
                    return true;
                }
                $pending[] = $item->value;
            }
        }
        return false;
    }

    /**
     * Anchors writable storage or a temporary returned array before reference iteration.
     * @param Expr $expression Evaluated source expression
     * @param bool $diagnostic Whether a non-reference return requires a target notice
     * @return string Source address register
     */
    public function source(Expr $expression, bool $diagnostic): string
    {
        if ($expression instanceof Expr\Variable || $expression instanceof Expr\ArrayDimFetch || $expression instanceof Expr\PropertyFetch || $expression instanceof Expr\StaticPropertyFetch) {
            return $this->lowering->location($expression);
        }
        return $this->lowering->graph->emit($expression, 'returned-address', [$this->lowering->expression($expression)], attributes: ['temporary-reference' => true, 'temporary-warning' => $diagnostic]);
    }
}
