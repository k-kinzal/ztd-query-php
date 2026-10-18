<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Control;

use Deriver\Deriver\Evaluator\Execution\Call\Native\Signatures;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Program;

/**
 * Appends exceptions displaced by finally using PHP's previous-chain identity rules.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
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
        if ($handler->phase !== HandlerPhase::Finally || $handler->saved?->kind !== \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw || $state->completion->kind !== \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw) {
            return;
        }
        $unwinding = new Unwinding($this->program);
        $exception = $unwinding->capture($state, $state->completion->value ?? Term::opaque('EXCEPTION', 'Throwable'));
        $previous = $unwinding->capture($state, $handler->saved->value ?? Term::opaque('EXCEPTION', 'Throwable'));
        $this->append($state, $exception, $previous);
        $state->completion = new Completion(\Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw, $exception);
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
        while ($exception->kind === \Deriver\Deriver\Evaluator\TermKind::Object && is_string($exception->literal)) {
            if (isset($ancestors[$exception->literal]) || isset($seen[$exception->literal])) {
                return;
            }
            $seen[$exception->literal] = true;
            $location = $this->location($exception);
            if ($location === null) {
                return;
            }
            $next = $state->memory->read($location);
            if ($next->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && $next->literal === null) {
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
        while ($exception->kind === \Deriver\Deriver\Evaluator\TermKind::Object && is_string($exception->literal) && !isset($seen[$exception->literal])) {
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
