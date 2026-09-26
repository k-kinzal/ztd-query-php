<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Transfer;

use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Control\CollectionCalls;
use Deriver\Internal\Solver\Machine;
use Deriver\Internal\Solver\State;
use Deriver\Standard\ScalarFunctions;
use Deriver\Value\Term;

/**
 * Executes declared pure intrinsics and synchronous collection callback semantics.
 * @visibility root
 */
final class IntrinsicTransfer
{
    /**
     * @param Machine $machine Shared evaluator
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Evaluates one intrinsic with already bound and evaluated inputs.
     * @param CallableIR $caller Model graph
     * @param Instruction $instruction Intrinsic expression
     * @param State $state Bound model state
     * @return list<State> Result paths
     */
    public function apply(CallableIR $caller, Instruction $instruction, State $state): array
    {
        $values = array_map(fn (string $register): Term => $state->value($register), $instruction->operands);
        foreach ($this->machine->context->models->extensions->intrinsics as $operation => $intrinsic) {
            if ($operation === $instruction->name) {
                $result = (new \Deriver\Internal\Model\ModelBoundary())->evaluate($intrinsic, $values, $this->machine->context->configuration->target);
                if ($result->kind === 'opaque' && $result->literal === 'MODEL_CONTRACT_VIOLATION') {
                    $this->machine->context->frontier('MODEL_CONTRACT_VIOLATION', $instruction->source, $instruction->name, $values);
                }
                $state->registers[$instruction->result] = $result;
                return [$state];
            }
        }
        if ($instruction->name === 'count' && ($values[0]->kind ?? '') === 'object') {
            return (new \Deriver\Internal\Solver\Operation\CountableCalls($this->machine))->apply($caller, $instruction, $state, $values);
        }
        if ($instruction->name === 'is_callable') {
            return (new \Deriver\Internal\Solver\Operation\CallablePredicate($this->machine->context))->apply($instruction, $state, $values);
        }
        if (in_array($instruction->name, ['array_map', 'array_filter', 'array_reduce'], true)) {
            return (new CollectionCalls($this->machine))->apply($caller, $instruction, $state, $values);
        }
        if (in_array($instruction->name, ['time', 'microtime', 'random_int', 'rand', 'mt_rand', 'getenv'], true)) {
            return (new ExternalTransfer($this->machine->context))->apply($instruction, $state, $values);
        }
        $result = (new ScalarFunctions())->apply($instruction->name, $values);
        if ($result->kind === 'opaque') {
            $arguments = [];
            foreach ($caller->parameters as $parameter) {
                $location = $state->locals[$parameter->name] ?? null;
                $arguments[] = new \Deriver\Internal\Solver\Call\PassedArgument($location === null ? Term::opaque('UNKNOWN_ARGUMENT') : $state->memory->read($location), location: $parameter->byReference ? $location : null);
            }
            return (new \Deriver\Internal\Solver\Call\UnknownCall($this->machine->context))->apply($state, $instruction, $arguments, null, 'UNSUPPORTED_MODEL_CASE');
        }
        $secret = array_filter($values, static fn (Term $value): bool => $value->isSecret()) !== [];
        $state->registers[$instruction->result] = $secret ? new Term($result->kind, $result->literal, $result->operands, $result->attributes, true) : $result;
        return [$state];
    }
}
