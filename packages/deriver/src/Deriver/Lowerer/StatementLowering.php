<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer;

use Deriver\Deriver\Lowerer\Control\ExceptionLowering;
use Deriver\Deriver\Lowerer\Control\GotoLowering;
use Deriver\Deriver\Lowerer\Control\LoopLowering;
use Deriver\Deriver\Lowerer\Control\StaticLowering;
use Deriver\Deriver\Lowerer\Control\SwitchLowering;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use PhpParser\Node\Stmt;

/**
 * Preserves statement control, state, and exceptional completion.
 * @visibility Deriver\Deriver\Lowerer\Declaration
 * @visibility Deriver\Deriver\Lowerer
 * @visibility Deriver\Deriver\Sources
 */
final class StatementLowering
{
    /**
     * @param LoweringScope $lowering Callable lowering
     */
    public function __construct(public readonly LoweringScope $lowering)
    {
    }

    /**
     * Lowers one statement.
     * @param Stmt $node Source statement
     */
    public function lower(Stmt $node): void
    {
        $l = $this->lowering;
        if ($node instanceof Stmt\Expression) {
            $value = $l->expression($node->expr);
            if ($node->expr instanceof \PhpParser\Node\Expr\Throw_) {
                $l->graph->end(new Terminator(Graph\TerminatorKind::Return, $value));
            }
        } elseif ($node instanceof Stmt\Return_) {
            $value = $node->expr === null ? '' : ($l->returnsByReference ? $l->graph->emit($node, 'reference', [$l->location($node->expr)]) : $l->expression($node->expr));
            $l->graph->end(new Terminator(Graph\TerminatorKind::Return, $value));
        } elseif ($node instanceof Stmt\If_) {
            $this->conditional($node);
        } elseif ($node instanceof Stmt\For_ || $node instanceof Stmt\While_ || $node instanceof Stmt\Do_ || $node instanceof Stmt\Foreach_) {
            (new LoopLowering($l))->lower($node);
        } elseif ($node instanceof Stmt\TryCatch) {
            (new ExceptionLowering($l))->lower($node);
        } elseif ($node instanceof Stmt\Switch_) {
            (new SwitchLowering($l))->lower($node);
        } elseif ($node instanceof Stmt\Break_ || $node instanceof Stmt\Continue_) {
            (new LoopLowering($l))->completion($node);
        } elseif ($node instanceof Stmt\Label) {
            (new GotoLowering($l))->label($node);
        } elseif ($node instanceof Stmt\Goto_) {
            (new GotoLowering($l))->jump($node);
        } else {
            $this->other($node);
        }
    }

    /**
     * Builds ordered elseif conditions and one shared continuation.
     * @param Stmt\If_ $node Conditional statement
     */
    public function conditional(Stmt\If_ $node): void
    {
        $l = $this->lowering;
        $g = $l->graph;
        $join = $g->block();
        foreach ([$node, ...$node->elseifs] as $branch) {
            $condition = $l->expression($branch->cond);
            $yes = $g->block();
            $no = $g->block();
            $g->end(new Terminator(Graph\TerminatorKind::Branch, $condition, [$yes, $no]));
            $g->current = $yes;
            $l->statements($branch->stmts);
            $g->jump($join);
            $g->current = $no;
        }
        if ($node->else !== null) {
            $l->statements($node->else->stmts);
        }
        $g->jump($join);
        $g->current = $join;
    }

    /**
     * Lowers storage declarations and explicit unsupported boundaries.
     * @param Stmt $node Source statement
     */
    public function other(Stmt $node): void
    {
        $l = $this->lowering;
        if ($node instanceof Stmt\Const_ || $node instanceof Stmt\Nop || $node instanceof Stmt\Function_ || $node instanceof Stmt\ClassLike || $node instanceof Stmt\Use_ || $node instanceof Stmt\GroupUse) {
            return;
        }
        if ($node instanceof Stmt\Namespace_ || $node instanceof Stmt\Block) {
            $l->statements($node->stmts);
            return;
        }
        if ($node instanceof Stmt\Echo_) {
            foreach ($node->exprs as $expression) {
                $l->graph->emit($expression, 'cast', [$l->expression($expression)], 'string');
            }
            return;
        }
        if ($node instanceof Stmt\Declare_) {
            $l->statements($node->stmts ?? []);
            return;
        }
        if ($node instanceof Stmt\Unset_) {
            foreach ($node->vars as $variable) {
                $l->graph->emit($node, 'unset', [$l->location($variable)]);
            }
            return;
        }
        if ($node instanceof Stmt\Global_) {
            foreach ($node->vars as $variable) {
                $l->graph->emit($node, 'global', [$l->location($variable)]);
            }
            return;
        }
        if ($node instanceof Stmt\Static_) {
            foreach ($node->vars as $variable) {
                (new StaticLowering($l))->lower($variable);
            }
            return;
        }
        $l->graph->emit($node, 'unsupported', name: $node->getType());
    }
}
