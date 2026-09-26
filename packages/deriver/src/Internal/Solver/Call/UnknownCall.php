<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Call;

use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Completion;
use Deriver\Internal\Solver\Context;
use Deriver\Internal\Solver\Havoc;
use Deriver\Internal\Solver\State;
use Deriver\Value\Term;

/**
 * Preserves normal and exceptional residuals and conservatively invalidates effects.
 * @visibility root
 */
final class UnknownCall
{
    /**
     * @param Context $context Query context
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Models an unavailable callee without assuming purity or successful completion.
     * @param State $state Caller state
     * @param Instruction $instruction Call site
     * @param list<PassedArgument> $arguments Actual inputs and exposed addresses
     * @param Term|null $receiver Optional receiver or dynamic target
     * @param string $reason Diagnostic code
     * @return list<State> Normal and exceptional residuals
     */
    public function apply(State $state, Instruction $instruction, array $arguments, ?Term $receiver, string $reason): array
    {
        $values = $receiver === null ? [] : [$receiver];
        $references = [];
        foreach ($arguments as $argument) {
            $values[] = $argument->value;
            if ($argument->location !== null) {
                $references[] = $argument->location;
            }
        }
        (new Havoc())->call($state, $values, $references, $reason);
        $state->registers[$instruction->result] = $this->context->frontier($reason, $instruction->source, $instruction->name === '' ? 'call' : $instruction->name, $values);
        $exception = $state->fork();
        $exception->completion = new Completion('throw', new Term('throwable', 'Throwable', attributes: ['uncertain' => true]));
        return [$state, $exception];
    }
}
