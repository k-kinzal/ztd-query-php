<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call\Contract;

use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\UnknownCall;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Retains declared argument binding while preserving an external implementation's unknown effects.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class ExternalBody
{
    /**
     * @param Context $context Captured declarations and evaluator world
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Propagates unavailable normal and exceptional behavior after ordinary parameter binding.
     * @param CallableGraph $callable Signature-only callable
     * @param Instruction $instruction Body boundary
     * @param State $state Bound parameters and receiver
     * @return list<State> Inclusive completions without treating a stub as an empty implementation
     */
    public function apply(CallableGraph $callable, Instruction $instruction, State $state): array
    {
        $arguments = [];
        foreach ($callable->parameters as $parameter) {
            $location = $state->local($parameter->name);
            $arguments[] = new PassedArgument($state->memory->read($location), location: $parameter->byReference ? $location : null);
        }
        $receiver = isset($state->locals['this']) ? $state->memory->read($state->locals['this']) : null;
        return (new UnknownCall($this->context))->apply($state, $instruction, $arguments, $receiver, 'MISSING_CALL_MODEL');
    }
}
