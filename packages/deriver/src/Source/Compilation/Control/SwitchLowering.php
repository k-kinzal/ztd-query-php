<?php

declare(strict_types=1);

namespace Deriver\Source\Compilation\Control;

use Deriver\ControlFlow\Terminator;
use Deriver\Source\Compilation\Lowering;
use PhpParser\Node\Stmt;

/**
 * Preserves switch comparison order and body fall-through.
 * @visibility root
 */
final class SwitchLowering
{
    /**
     * @param Lowering $lowering Callable lowering
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Lowers case selection separately from fall-through execution.
     * @param Stmt\Switch_ $node Switch statement
     */
    public function lower(Stmt\Switch_ $node): void
    {
        $l = $this->lowering;
        $g = $l->graph;
        $subject = $l->expression($node->cond);
        $exit = $g->block();
        $bodies = [];
        $default = $exit;
        foreach ($node->cases as $index => $case) {
            $bodies[$index] = $g->block();
            if ($case->cond === null) {
                $default = $bodies[$index];
                continue;
            }
            $condition = $g->emit($case, 'binary', [$subject, $l->expression($case->cond)], '==');
            $next = $g->block();
            $g->end(new Terminator('branch', $condition, [$bodies[$index], $next]));
            $g->current = $next;
        }
        $g->jump($default);
        $g->loops[] = ['break' => $exit, 'continue' => $exit, 'depth' => $g->handlerDepth];
        $g->scopes[] = ['key' => 'switch:' . $exit, 'kind' => 'switch', 'iterator' => '', 'depth' => $g->handlerDepth];
        foreach ($node->cases as $index => $case) {
            $g->current = $bodies[$index];
            $l->statements($case->stmts);
            $g->jump($bodies[$index + 1] ?? $exit);
        }
        array_pop($g->loops);
        array_pop($g->scopes);
        $g->current = $exit;
    }
}
