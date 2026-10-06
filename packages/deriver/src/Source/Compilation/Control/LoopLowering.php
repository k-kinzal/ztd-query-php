<?php

declare(strict_types=1);

namespace Deriver\Source\Compilation\Control;

use Deriver\ControlFlow\Terminator;
use Deriver\Source\Compilation\AssignmentLowering;
use Deriver\Source\Compilation\Lowering;
use Deriver\Value\Term;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;

/**
 * Builds loop backedges and preserves multi-level break and continue targets.
 * @visibility root
 */
final class LoopLowering
{
    /**
     * @param Lowering $lowering Callable lowering
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a loop to explicit test, body, update, and exit blocks.
     * @param Stmt\For_|Stmt\While_|Stmt\Do_|Stmt\Foreach_ $node Loop statement
     */
    public function lower(Stmt\For_|Stmt\While_|Stmt\Do_|Stmt\Foreach_ $node): void
    {
        $l = $this->lowering;
        $g = $l->graph;
        $iterator = $this->initialize($node);
        $header = $g->block(true);
        $body = $g->block();
        $update = $g->block();
        $exit = $g->block();
        $g->jump($node instanceof Stmt\Do_ ? $body : $header);
        $g->loops[] = ['break' => $exit, 'continue' => $update, 'depth' => $g->handlerDepth];
        $g->scopes[] = ['key' => 'loop:' . $exit, 'kind' => 'loop', 'iterator' => $iterator, 'depth' => $g->handlerDepth];
        $g->current = $header;
        $condition = $this->condition($node, $iterator);
        $g->end(new Terminator('branch', $condition, [$body, $exit]));
        $g->current = $body;
        if ($node instanceof Stmt\Foreach_) {
            $this->bindIteration($node, $iterator);
        }
        $l->statements($node->stmts);
        $g->jump($update);
        $g->current = $update;
        if ($node instanceof Stmt\For_) {
            foreach ($node->loop as $expression) {
                $l->expression($expression);
            }
        }
        $g->jump($header);
        array_pop($g->loops);
        array_pop($g->scopes);
        $g->current = $exit;
        if ($node instanceof Stmt\Foreach_) {
            $g->emit($node, 'iterator-release', [$iterator]);
        }
    }

    /**
     * Evaluates initialization and the foreach iterable once.
     * @param Stmt\For_|Stmt\While_|Stmt\Do_|Stmt\Foreach_ $node Loop
     * @return string Iterator register, or an empty register for other loops
     */
    public function initialize(Stmt\For_|Stmt\While_|Stmt\Do_|Stmt\Foreach_ $node): string
    {
        if ($node instanceof Stmt\For_) {
            foreach ($node->init as $expression) {
                $this->lowering->expression($expression);
            }
        }
        if ($node instanceof Stmt\Foreach_) {
            $byReference = $node->byRef || DestructuringLowering::references($node->valueVar);
            $source = $byReference ? (new DestructuringLowering($this->lowering))->source($node->expr, false) : $this->lowering->expression($node->expr);
            return $this->lowering->graph->emit($node, 'iterator', [$source], attributes: ['byReference' => $byReference]);
        }
        return '';
    }

    /**
     * Produces the next loop predicate.
     * @param Stmt\For_|Stmt\While_|Stmt\Do_|Stmt\Foreach_ $node Loop
     * @param string $iterator Iterator register
     * @return string Predicate register
     */
    public function condition(Stmt\For_|Stmt\While_|Stmt\Do_|Stmt\Foreach_ $node, string $iterator): string
    {
        if ($node instanceof Stmt\Foreach_) {
            return $this->lowering->graph->emit($node, 'iterate', [$iterator]);
        }
        if ($node instanceof Stmt\For_) {
            $last = $this->lowering->graph->emit($node, 'constant', constant: Term::constant(true));
            foreach ($node->cond as $condition) {
                $last = $this->lowering->expression($condition);
            }
            return $last;
        }
        return $this->lowering->expression($node->cond);
    }

    /**
     * Binds foreach variables, retaining the final reference after the loop.
     * @param Stmt\Foreach_ $node Loop
     * @param string $iterator Iterator register
     */
    public function bindIteration(Stmt\Foreach_ $node, string $iterator): void
    {
        $l = $this->lowering;
        $byReference = $node->byRef || DestructuringLowering::references($node->valueVar);
        $value = $l->graph->emit($node, $byReference ? 'iterator-address' : 'iterator-value', [$iterator]);
        if ($byReference && ($node->valueVar instanceof Expr\List_ || $node->valueVar instanceof Expr\Array_)) {
            $reference = $l->graph->emit($node, 'reference', [$value]);
            $address = $l->graph->emit($node, 'returned-address', [$reference]);
            (new DestructuringLowering($l))->assign($node->valueVar, '', $address);
        } elseif ($byReference) {
            $l->graph->emit($node, 'alias', [$l->location($node->valueVar), $value]);
        } else {
            (new AssignmentLowering($l))->assign($node->valueVar, $value);
        }
        if ($node->keyVar !== null) {
            (new AssignmentLowering($l))->assign($node->keyVar, $l->graph->emit($node, 'iterator-key', [$iterator]));
        }
    }

    /**
     * Resolves break and continue levels before exception unwinding.
     * @param Stmt\Break_|Stmt\Continue_ $node Completion statement
     */
    public function completion(Stmt\Break_|Stmt\Continue_ $node): void
    {
        $g = $this->lowering->graph;
        $level = $node->num instanceof Scalar\Int_ ? $node->num->value : 1;
        $loop = $g->loops[count($g->loops) - $level] ?? null;
        if ($loop === null || $level < 1) {
            $g->emit($node, 'raise', name: 'Error');
            return;
        }
        $g->end(new Terminator('complete-jump', targets: [$loop[$node instanceof Stmt\Break_ ? 'break' : 'continue']], handlerDepth: $loop['depth']));
    }
}
