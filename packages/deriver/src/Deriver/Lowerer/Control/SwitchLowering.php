<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Control;

use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\LoweringScope;
use PhpParser\Node\Stmt;

/**
 * Preserves switch comparison order and body fall-through.
 * @visibility Deriver\Deriver\Lowerer\Declaration
 * @visibility Deriver\Deriver\Lowerer
 * @visibility Deriver\Deriver\Sources
 */
final class SwitchLowering
{
    /**
     * @param LoweringScope $lowering Callable lowering
     */
    public function __construct(public readonly LoweringScope $lowering)
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
            $g->end(new Terminator(\Deriver\Deriver\Lowerer\Graph\TerminatorKind::Branch, $condition, [$bodies[$index], $next]));
            $g->current = $next;
        }
        $g->jump($default);
        $g->loops[] = ['break' => $exit, 'continue' => $exit, 'depth' => $g->handlerDepth];
        $g->scopes[] = ['key' => 'switch:' . $exit, 'kind' => ScopeKind::Switch, 'iterator' => '', 'depth' => $g->handlerDepth];
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
