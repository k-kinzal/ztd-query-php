<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Call\Model;

use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Call\PassedArgument;
use Deriver\Internal\Solver\Call\UnknownCall;
use Deriver\Internal\Solver\Context;
use Deriver\Internal\Solver\State;

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
     * @param CallableIR $callable Signature-only callable
     * @param Instruction $instruction Body boundary
     * @param State $state Bound parameters and receiver
     * @return list<State> Inclusive completions without treating a stub as an empty implementation
     */
    public function apply(CallableIR $callable, Instruction $instruction, State $state): array
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
