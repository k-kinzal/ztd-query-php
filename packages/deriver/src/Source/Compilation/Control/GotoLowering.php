<?php

declare(strict_types=1);

namespace Deriver\Source\Compilation\Control;

use Deriver\ControlFlow\Terminator;
use Deriver\Source\Compilation\Lowering;
use PhpParser\Node\Stmt;

/**
 * Lowers labels and goto to explicit jumps that release iterators and run finally blocks.
 * @visibility root
 */
final class GotoLowering
{
    /**
     * @param Lowering $lowering Callable lowering
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * Starts the block a goto can enter and records its lexical scopes.
     * @param Stmt\Label $node Label statement
     */
    public function label(Stmt\Label $node): void
    {
        $g = $this->lowering->graph;
        $block = $g->block();
        $g->jump($block);
        $g->current = $block;
        $name = $node->name->toString();
        $g->labels[$name] = array_key_exists($name, $g->labels) ? null : ['block' => $block, 'scopes' => $g->scopes, 'depth' => $g->handlerDepth, 'position' => $node->getStartFilePos()];
    }

    /**
     * Ends the current block until the possibly later label is known.
     * @param Stmt\Goto_ $node Goto statement
     */
    public function jump(Stmt\Goto_ $node): void
    {
        $g = $this->lowering->graph;
        $g->gotos[] = ['block' => $g->current, 'node' => $node, 'scopes' => $g->scopes];
        $g->end(new Terminator('residual'));
    }

    /**
     * Connects every goto of the callable body to its label, or seals it as an explicit boundary.
     */
    public function resolve(): void
    {
        $g = $this->lowering->graph;
        $current = $g->current;
        foreach ($g->gotos as $goto) {
            $g->current = $goto['block'];
            unset($g->terminators[$goto['block']]);
            $label = $g->labels[$goto['node']->name->toString()] ?? null;
            $exited = $label === null ? null : $this->exited($goto['scopes'], $label['scopes']);
            if ($label === null || $exited === null) {
                $g->end(new Terminator('residual', $g->emit($goto['node'], 'unsupported', name: 'Stmt_Goto')));
                continue;
            }
            if ($goto['node']->getStartFilePos() > $label['position']) {
                $g->headers[$label['block']] = true;
            }
            $this->unwind($goto['node'], $exited);
            $g->end(new Terminator('complete-jump', targets: [$label['block']], handlerDepth: $label['depth']));
        }
        $g->gotos = [];
        $g->current = $current;
    }

    /**
     * Lists the scopes a jump leaves, rejecting jumps into scopes and out of finally.
     * @param list<array{key: string, kind: string, iterator: string, depth: int}> $from Scopes enclosing the goto
     * @param list<array{key: string, kind: string, iterator: string, depth: int}> $to Scopes enclosing the label
     * @return list<array{key: string, kind: string, iterator: string, depth: int}>|null Exited scopes innermost first, or null when the jump is not modeled
     */
    public function exited(array $from, array $to): ?array
    {
        if (array_slice($from, 0, count($to)) !== $to) {
            return null;
        }
        $exited = array_reverse(array_slice($from, count($to)));
        foreach ($exited as $scope) {
            if ($scope['kind'] === 'finally') {
                return null;
            }
        }
        return $exited;
    }

    /**
     * Releases exited foreach iterators after the finally blocks nested inside them, innermost first.
     * @param Stmt\Goto_ $node Goto statement
     * @param list<array{key: string, kind: string, iterator: string, depth: int}> $exited Exited scopes innermost first
     */
    public function unwind(Stmt\Goto_ $node, array $exited): void
    {
        $g = $this->lowering->graph;
        $pending = false;
        foreach ($exited as $scope) {
            if ($scope['kind'] === 'try' || $scope['kind'] === 'catch') {
                $pending = true;
            } elseif ($scope['iterator'] !== '') {
                if ($pending) {
                    $next = $g->block();
                    $g->end(new Terminator('complete-jump', targets: [$next], handlerDepth: $scope['depth']));
                    $g->current = $next;
                    $pending = false;
                }
                $g->emit($node, 'iterator-release', [$scope['iterator']]);
            }
        }
    }
}
