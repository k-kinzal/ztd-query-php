<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Transfer;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\UnknownCall;
use Deriver\Evaluation\Control\CollectionCalls;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Operation\CallablePredicate;
use Deriver\Evaluation\Operation\CountableCalls;
use Deriver\Evaluation\State;
use Deriver\Model\Builtin\ScalarFunctions;
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
     * @param CallableGraph $caller Model graph
     * @param Instruction $instruction Intrinsic expression
     * @param State $state Bound model state
     * @return list<State> Result paths
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state): array
    {
        $values = array_map(fn (string $register): Term => $state->value($register), $instruction->operands);
        foreach ($this->machine->context->models->extensions->intrinsics as $operation => $intrinsic) {
            if ($operation === $instruction->name) {
                $result = (new \Deriver\Model\Intrinsic\IntrinsicEvaluation())->evaluate($intrinsic, $values, $this->machine->context->configuration->target);
                $state->registers[$instruction->result] = $result;
                return [$state];
            }
        }
        if ($instruction->name === 'define') {
            return (new \Deriver\Evaluation\Constant\Definitions($this->machine->context))->apply($instruction, $state, $values);
        }
        if ($instruction->name === 'count' && ($values[0]->kind ?? '') === 'object') {
            return (new CountableCalls($this->machine))->apply($caller, $instruction, $state, $values);
        }
        if ($instruction->name === 'is_callable') {
            return (new CallablePredicate($this->machine->context))->apply($instruction, $state, $values);
        }
        if (in_array($instruction->name, ['implode', 'join'], true)) {
            $joined = (new \Deriver\Evaluation\Operation\StringJoining($this->machine))->apply($caller, $instruction, $state, $values);
            if ($joined !== null) {
                return $joined;
            }
        }
        if (in_array($instruction->name, ['array_map', 'array_filter', 'array_reduce'], true)) {
            return (new CollectionCalls($this->machine))->apply($caller, $instruction, $state, $values);
        }
        if (in_array($instruction->name, ['time', 'microtime', 'random_int', 'rand', 'mt_rand', 'getenv'], true)) {
            return (new ExternalTransfer($this->machine->context))->apply($instruction, $state, $values);
        }
        $result = (new ScalarFunctions($this->machine->context->configuration->target->floatPrecision))->apply($instruction->name, $values);
        if ($result->kind === 'opaque') {
            $arguments = [];
            foreach ($caller->parameters as $parameter) {
                $location = $state->locals[$parameter->name] ?? null;
                $arguments[] = new PassedArgument($location === null ? Term::opaque('UNKNOWN_ARGUMENT') : $state->memory->read($location), location: $parameter->byReference ? $location : null);
            }
            return (new UnknownCall($this->machine->context))->apply($state, $instruction, $arguments, null, 'UNSUPPORTED_MODEL_CASE');
        }
        $secret = array_filter($values, static fn (Term $value): bool => $value->isSecret()) !== [];
        $state->registers[$instruction->result] = $secret ? new Term($result->kind, $result->literal, $result->operands, $result->attributes, true) : $result;
        return [$state];
    }
}
