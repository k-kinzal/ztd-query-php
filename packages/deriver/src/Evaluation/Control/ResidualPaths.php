<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\State;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;

/**
 * Seals unexplored execution with inclusive normal and exceptional completions.
 * @visibility root
 */
final class ResidualPaths
{
    /**
     * @param Context $context Query budget and frontier records
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Seals an invocation that is not entered, havocking only what the callee can reach, as for an unknown call.
     * @param State $entry Bound callee entry, whose locals hold the arguments, captures, and receiver
     * @param SourceRef $source Callee source
     * @param string $operation Limit that refused the invocation
     * @param string|null $reason Frontier code, defaulting to the permanent interruption or BUDGET_EXCEEDED
     * @return list<State> Normal and exceptional residuals
     */
    public function invocation(State $entry, SourceRef $source, string $operation, ?string $reason = null): array
    {
        $this->context->available($source);
        $reason ??= $this->context->stopReason ?? 'BUDGET_EXCEEDED';
        $value = $this->context->frontier($reason, $source, $operation);
        (new Havoc())->call($entry, [], array_values($entry->locals), $reason);
        return $this->complete($entry, $value);
    }

    /**
     * Keeps unexplored effects and exceptions when a logical limit interrupts execution.
     * @param State $state Interrupted execution path
     * @param SourceRef $source Interrupted source operation
     * @param string $operation Limit or capability that stopped progress
     * @param string|null $reason Frontier code, defaulting to the permanent interruption or BUDGET_EXCEEDED
     * @return list<State> Normal and exceptional residuals
     */
    public function seal(State $state, SourceRef $source, string $operation, ?string $reason = null): array
    {
        $this->context->available($source);
        $reason ??= $this->context->stopReason ?? 'BUDGET_EXCEEDED';
        $value = $this->context->frontier($reason, $source, $operation);
        (new Havoc())->symbols($state, $reason);
        return $this->complete($state, $value);
    }

    /**
     * Completes an already havocked path with a residual return and an unknown throwable.
     * @param State $state Interrupted execution path
     * @param Term $value Residual return value
     * @return list<State> Normal and exceptional residuals
     */
    public function complete(State $state, Term $value): array
    {
        $state->constraints = [];
        $state->completion = new Completion('return', $value);
        $exception = $state->fork();
        $exception->completion = new Completion('throw', new Term('throwable', 'Throwable', attributes: ['uncertain' => true]));
        return [$state, $exception];
    }
}
