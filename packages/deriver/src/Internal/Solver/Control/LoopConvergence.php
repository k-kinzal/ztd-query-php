<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Control;

use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\Solver\Context;
use Deriver\Internal\Solver\Havoc;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;

/**
 * Bounds precise loop expansion and retains an explicit residual state.
 * @visibility root
 */
final class LoopConvergence
{
    /**
     * @param Context $context Query context
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Widens an unbounded loop instead of reporting its first iterations as exhaustive.
     * @param CallableIR $callable Current callable
     * @param State $state Loop header entry
     * @return bool Whether the loop was replaced by a residual exit
     */
    public function widen(CallableIR $callable, State $state): bool
    {
        $header = $state->block;
        $state->visits[$header] = ($state->visits[$header] ?? 0) + 1;
        $state->loopEntries[$header] ??= $state->memory->cells;
        $state->loopGuards[$header] ??= $state->guard;
        if ($state->visits[$header] <= $this->context->query->budget()->iterations) {
            return false;
        }
        $previous = $state->approximations[$header] ?? $state->loopEntries[$header];
        $lattice = new \Deriver\Internal\Value\Lattice($this->context->models->extensions->domains);
        $structure = $this->structure($state);
        $stable = isset($state->approximations[$header]) && ($state->loopStructures[$header] ?? null) === $structure;
        $state->loopStructures[$header] = $structure;
        $next = [];
        foreach (array_unique([...array_keys($previous), ...array_keys($state->memory->cells)]) as $root) {
            $a = $previous[$root] ?? new Term('uninitialized');
            $b = $state->memory->cells[$root] ?? new Term('uninitialized');
            $stable = $stable && $lattice->contains($a, $b);
            $next[$root] = $lattice->widen($a, $b);
            if ($next[$root]->literal === 'MODEL_CONTRACT_VIOLATION' && $next[$root]->kind === 'opaque') {
                $this->context->frontier('MODEL_CONTRACT_VIOLATION', $callable->source, 'domain-widen');
            }
        }
        $state->memory->cells = $next;
        $state->approximations[$header] = $next;
        $state->guard = $state->loopGuards[$header];
        $state->constraints = [];
        $this->context->frontier('WIDENED', $callable->source, 'loop-fixed-point');
        if ($stable) {
            $state->stableHeader = $header;
        }
        if ($state->visits[$header] > $this->context->query->budget()->iterations + 64) {
            $this->context->frontier('BUDGET_EXCEEDED', $callable->source, 'loop-fixed-point');
            (new Havoc())->all($state, 'BUDGET_EXCEEDED');
            return true;
        }
        return false;
    }

    /**
     * Hashes loop alias and cursor structure without expanding shared value subgraphs.
     * @param State $state Loop header state
     * @return string Alias and iteration identity
     */
    public function structure(State $state): string
    {
        $identity = new \Deriver\Internal\Value\Identity();
        $iterators = [];
        foreach ($state->iterators as $id => $iterator) {
            $iterators[$id] = [$identity->key($iterator->array), $iterator->location, $iterator->position];
        }
        return hash('sha256', serialize([$state->locals, $iterators, $state->memory->liveArrays, $state->memory->unknownShared, $state->observed]));
    }
}
