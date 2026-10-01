<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\ControlFlow\Program;
use Deriver\Evaluation\Call\Native\Signatures;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Value\Term;

/**
 * Appends exceptions displaced by finally using PHP's previous-chain identity rules.
 * @visibility root
 */
final class ExceptionChain
{
    /**
     * @param Program $program Captured declaration hierarchy
     */
    public function __construct(public readonly Program $program)
    {
    }

    /**
     * Links a saved throw when a finally block replaces it with another throw.
     * @param State $state Completing finally path
     * @param Handler $handler Region being unwound
     */
    public function unwind(State $state, Handler $handler): void
    {
        if ($handler->phase !== 'finally' || $handler->saved?->kind !== 'throw' || $state->completion->kind !== 'throw') {
            return;
        }
        $unwinding = new Unwinding($this->program);
        $exception = $unwinding->capture($state, $state->completion->value ?? Term::opaque('EXCEPTION', 'Throwable'));
        $previous = $unwinding->capture($state, $handler->saved->value ?? Term::opaque('EXCEPTION', 'Throwable'));
        $this->append($state, $exception, $previous);
        $state->completion = new Completion('throw', $exception);
    }

    /**
     * Preserves existing previous links and prevents duplicate or cyclic chains.
     * @param State $state Object heap
     * @param Term $exception New throwable identity
     * @param Term $previous Displaced throwable identity
     */
    public function append(State $state, Term $exception, Term $previous): void
    {
        $ancestors = $this->ancestors($state, $previous);
        $seen = [];
        while ($exception->kind === 'object' && is_string($exception->literal)) {
            if (isset($ancestors[$exception->literal]) || isset($seen[$exception->literal])) {
                return;
            }
            $seen[$exception->literal] = true;
            $location = $this->location($exception);
            if ($location === null) {
                return;
            }
            $next = $state->memory->read($location);
            if ($next->kind === 'constant' && $next->literal === null) {
                $state->memory->write($location, $previous);
                return;
            }
            $exception = $next;
        }
    }

    /**
     * Collects the displaced chain to prevent links back into its ancestors.
     * @param State $state Object heap
     * @param Term $exception Displaced exception
     * @return array<string, true> Existing identities
     */
    public function ancestors(State $state, Term $exception): array
    {
        $seen = [];
        while ($exception->kind === 'object' && is_string($exception->literal) && !isset($seen[$exception->literal])) {
            $seen[$exception->literal] = true;
            $location = $this->location($exception);
            if ($location === null) {
                break;
            }
            $exception = $state->memory->read($location);
        }
        return $seen;
    }

    /**
     * Resolves the inherited private slot used by both native exception families.
     * @param Term $exception Throwable identity
     * @return Location|null Known previous slot
     */
    public function location(Term $exception): ?Location
    {
        $class = $exception->attributes['class'] ?? '';
        $family = is_string($class) ? (new Signatures($this->program))->family($class) : '';
        return $family === '' ? null : new Location('object:' . $exception->literal, [($family === 'Error' ? 'Error' : 'Exception') . '::previous']);
    }
}
