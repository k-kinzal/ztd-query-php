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
     * Keeps unexplored effects and exceptions when a logical limit interrupts execution.
     * @param State $state Interrupted execution path
     * @param SourceRef $source Interrupted source operation
     * @param string $operation Limit or capability that stopped progress
     * @return list<State> Normal and exceptional residuals
     */
    public function seal(State $state, SourceRef $source, string $operation): array
    {
        $reason = $this->context->stopReason ?? 'BUDGET_EXCEEDED';
        $value = $this->context->frontier($reason, $source, $operation);
        (new Havoc())->all($state, $reason);
        $state->constraints = [];
        $state->completion = new Completion('return', $value);
        $exception = $state->fork();
        $exception->completion = new Completion('throw', new Term('throwable', 'Throwable', attributes: ['uncertain' => true]));
        return [$state, $exception];
    }
}
