<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call;

use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\Havoc;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Preserves normal and exceptional residuals and conservatively invalidates effects.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
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
     * Evaluators an unavailable callee without assuming purity or successful completion.
     * @param State $state Caller state
     * @param Instruction $instruction Call site
     * @param list<PassedArgument> $arguments Actual inputs and exposed addresses
     * @param Term|null $receiver Optional receiver or dynamic target
     * @param string $reason Diagnostic code
     * @param string $type Justified type bound of a normal result
     * @return list<State> Normal and exceptional residuals
     */
    public function apply(State $state, Instruction $instruction, array $arguments, ?Term $receiver, string $reason, string $type = 'mixed'): array
    {
        $values = $receiver === null ? [] : [$receiver];
        $references = [];
        foreach ($arguments as $argument) {
            $values[] = $argument->value;
            if ($argument->location !== null) {
                $references[] = $argument->location;
            }
        }
        (new Havoc())->call($state, $values, $references, $reason, shared: false);
        $state->registers[$instruction->result] = $this->context->frontier($reason, $instruction->source, $instruction->name === '' ? 'call' : $instruction->name, $values, $type);
        $exception = $state->fork();
        $exception->completion = new Completion(\Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw, new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Throwable', attributes: ['uncertain' => true]));
        return [$state, $exception];
    }
}
