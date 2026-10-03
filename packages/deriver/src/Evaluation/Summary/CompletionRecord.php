<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Summary;

use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\State;
use Deriver\Value\Identity;

/**
 * Keeps one correlated isolated return or exception with its final local state.
 * @visibility root
 */
final class CompletionRecord
{
    /**
     * @param State $state Frozen completed path, never exposed directly
     * @param int $allocations Allocation events consumed after parameter binding
     * @param bool $havoc Whether an interrupted computation invalidated caller state
     */
    public function __construct(public readonly State $state, public readonly int $allocations, public readonly bool $havoc = false)
    {
    }

    /**
     * Reconstructs a completed invocation without reusing another invocation's cells.
     * @param State $entry Fresh bound invocation
     * @return State Independently writable completed path
     */
    public function instantiate(State $entry): State
    {
        $next = $entry->fork();
        $sequence = $entry->memory->sequence + $this->allocations;
        $next->locals = [];
        foreach ($this->state->locals as $name => $location) {
            $target = $entry->locals[$name] ?? $next->memory->allocate($this->state->memory->read($location));
            $next->memory->write($target, $this->state->memory->read($location));
            $next->locals[$name] = $target;
        }
        $next->memory->sequence = max($sequence, $next->memory->sequence);
        if ($this->havoc) {
            (new Havoc())->symbols($next, 'BUDGET_EXCEEDED');
        }
        $next->completion = new Completion($this->state->completion->kind, $this->state->completion->value);
        $next->guard = $this->state->guard;
        $next->constraints = $this->state->constraints;
        $next->evidence = $this->state->evidence;
        $next->controls = array_values(array_unique([...$entry->controls, ...$this->state->controls]));
        return $next;
    }

    /**
     * Identifies semantic outcomes independently of temporary local cell identities.
     * @return string Correlated outcome identity
     */
    public function id(): string
    {
        $identity = new Identity();
        $locals = [];
        foreach ($this->state->locals as $name => $location) {
            $locals[$name] = $identity->key($this->state->memory->read($location));
        }
        ksort($locals);
        $guard = $this->state->guard;
        ksort($guard);
        return hash('sha256', serialize([$this->state->completion->kind, $this->state->completion->value === null ? null : $identity->key($this->state->completion->value), $locals, $guard, $this->havoc]));
    }
}
