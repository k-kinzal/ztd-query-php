<?php

declare(strict_types=1);

namespace Deriver\Source\Compilation\Control;

use Deriver\ControlFlow\CatchTarget;
use Deriver\ControlFlow\ExceptionRegion;
use Deriver\ControlFlow\Terminator;
use Deriver\Source\Compilation\Lowering;
use PhpParser\Node\Stmt;

/**
 * Lowers try/catch/finally to exception regions and resumable completion.
 * @visibility root
 */
final class ExceptionLowering
{
    /**
     * @param Lowering $lowering Callable lowering
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Preserves the return value evaluated before finally and finally overrides.
     * @param Stmt\TryCatch $node Exception region
     */
    public function lower(Stmt\TryCatch $node): void
    {
        $l = $this->lowering;
        $g = $l->graph;
        $after = $g->block();
        $finally = $node->finally === null ? null : $g->block();
        $catches = [];
        foreach ($node->catches as $catch) {
            $types = array_values(array_map(static fn ($type): string => $type->toString(), $catch->types));
            $catches[] = new CatchTarget($types, $catch->var !== null && is_string($catch->var->name) ? $catch->var->name : '', $g->block());
        }
        $region = count($g->regions);
        $g->regions[$region] = new ExceptionRegion($catches, $finally, $after);
        $g->emit($node, 'enter-try', attributes: ['region' => $region]);
        $g->handlerDepth++;
        $l->statements($node->stmts);
        $g->end($g->terminators[$g->current] ?? new Terminator('leave-try'));
        foreach ($node->catches as $index => $catch) {
            $g->current = $catches[$index]->block;
            $l->statements($catch->stmts);
            $g->end($g->terminators[$g->current] ?? new Terminator('leave-try'));
        }
        if ($node->finally !== null && $finally !== null) {
            $g->current = $finally;
            $l->statements($node->finally->stmts);
            $g->end($g->terminators[$g->current] ?? new Terminator('resume'));
        }
        $g->handlerDepth--;
        $g->current = $after;
    }
}
