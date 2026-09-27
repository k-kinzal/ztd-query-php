<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call\Model;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\UnknownCall;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;

/**
 * Retains declared argument binding while preserving an external implementation's unknown effects.
 * @visibility root
 */
final class ExternalBody
{
    /**
     * @param Context $context Captured declarations and model world
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
