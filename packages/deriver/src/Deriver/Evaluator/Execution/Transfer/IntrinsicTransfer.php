<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Transfer;

use Deriver\Deriver\Evaluator\Builtin\ScalarFunctions;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\UnknownCall;
use Deriver\Deriver\Evaluator\Execution\Control\CollectionCalls;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\Operation\CallablePredicate;
use Deriver\Deriver\Evaluator\Execution\Operation\CountableCalls;
use Deriver\Deriver\Evaluator\Execution\Operation\PartialFormatting;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Executes declared pure intrinsics and synchronous collection callback semantics.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class IntrinsicTransfer
{
    /**
     * @param Evaluator $machine Shared evaluator
     */
    public function __construct(public readonly Evaluator $machine)
    {
    }

    /**
     * Evaluates one intrinsic with already bound and evaluated inputs.
     * @param CallableGraph $caller Evaluator graph
     * @param Instruction $instruction Intrinsic expression
     * @param State $state Bound evaluator state
     * @return list<State> Result paths
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state): array
    {
        $values = array_map(fn (string $register): Term => $state->value($register), $instruction->operands);
        foreach ($this->machine->context->evaluators->extensions->intrinsics as $operation => $intrinsic) {
            if ($operation === $instruction->name) {
                $result = (new \Deriver\Deriver\Evaluator\Intrinsic\IntrinsicEvaluation())->evaluate($intrinsic, $values, $this->machine->context->configuration->target);
                $state->registers[$instruction->result] = $result;
                return [$state];
            }
        }
        if ($instruction->name === 'define') {
            return (new \Deriver\Deriver\Evaluator\Execution\Constant\Definitions($this->machine->context))->apply($instruction, $state, $values);
        }
        if ($instruction->name === 'count' && ($values[0]->kind ?? null) === \Deriver\Deriver\Evaluator\TermKind::Object) {
            return (new CountableCalls($this->machine))->apply($caller, $instruction, $state, $values);
        }
        if ($instruction->name === 'is_callable') {
            return (new CallablePredicate($this->machine->context))->apply($instruction, $state, $values);
        }
        if (in_array($instruction->name, ['implode', 'join'], true)) {
            $joined = (new \Deriver\Deriver\Evaluator\Execution\Operation\StringJoining($this->machine))->apply($caller, $instruction, $state, $values);
            if ($joined !== null) {
                return $joined;
            }
        }
        if (in_array($instruction->name, ['sprintf', 'vsprintf'], true)) {
            $formatted = (new PartialFormatting($this->machine->context))->apply($instruction, $state, $values);
            if ($formatted !== null) {
                return $formatted;
            }
        }
        if (in_array($instruction->name, ['array_map', 'array_filter', 'array_reduce'], true)) {
            return (new CollectionCalls($this->machine))->apply($caller, $instruction, $state, $values);
        }
        if (in_array($instruction->name, ['time', 'microtime', 'random_int', 'rand', 'mt_rand', 'getenv'], true)) {
            return (new ExternalTransfer($this->machine->context))->apply($instruction, $state, $values);
        }
        $result = (new ScalarFunctions($this->machine->context->configuration->target->floatPrecision))->apply($instruction->name, $values);
        if ($result->kind === \Deriver\Deriver\Evaluator\TermKind::Opaque) {
            $arguments = [];
            foreach ($caller->parameters as $parameter) {
                $location = $state->locals[$parameter->name] ?? null;
                $arguments[] = new PassedArgument($location === null ? Term::opaque('UNKNOWN_ARGUMENT') : $state->memory->read($location), location: $parameter->byReference ? $location : null);
            }
            return (new UnknownCall($this->machine->context))->apply($state, $instruction, $arguments, null, 'UNSUPPORTED_EVALUATOR_CASE');
        }
        $state->registers[$instruction->result] = $result;
        return [$state];
    }
}
