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
        $depth = $g->handlerDepth++;
        $protectedBlocks = $this->scoped("region:$region:try", 'try', $depth, $node->stmts);
        $g->end($g->terminators[$g->current] ?? new Terminator('leave-try'));
        $catchBlocks = [];
        foreach ($node->catches as $index => $catch) {
            $g->current = $catches[$index]->block;
            array_push($catchBlocks, ...$this->scoped("region:$region:catch:$index", 'catch', $depth, $catch->stmts));
            $g->end($g->terminators[$g->current] ?? new Terminator('leave-try'));
        }
        $finallyBlocks = [];
        if ($node->finally !== null && $finally !== null) {
            $g->current = $finally;
            $finallyBlocks = $this->scoped("region:$region:finally", 'finally', $depth, $node->finally->stmts);
            $g->end($g->terminators[$g->current] ?? new Terminator('resume'));
        }
        $g->handlerDepth--;
        $g->regions[$region] = new ExceptionRegion($catches, $finally, $after, $protectedBlocks, $catchBlocks, $finallyBlocks);
        $g->current = $after;
    }

    /**
     * Lowers one try, catch, or finally body inside its lexical goto scope.
     * @param string $key Scope identity
     * @param string $kind Exception region part
     * @param int $depth Handler depth outside the region
     * @param array<Stmt> $statements Body statements
     * @return list<int> Blocks created within this exception scope
     */
    public function scoped(string $key, string $kind, int $depth, array $statements): array
    {
        $g = $this->lowering->graph;
        $start = $g->current;
        $before = count($g->instructions);
        $g->scopes[] = ['key' => $key, 'kind' => $kind, 'iterator' => '', 'depth' => $depth];
        $this->lowering->statements($statements);
        array_pop($g->scopes);
        return [$start, ...array_slice(array_keys($g->instructions), $before)];
    }
}
