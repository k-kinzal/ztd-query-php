<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution;

use Deriver\Deriver\Evaluator\Builtin\TypePredicates;
use Deriver\Deriver\Evaluator\Execution\Call\CallExecutor;
use Deriver\Deriver\Evaluator\Execution\Call\Closure\Capture;
use Deriver\Deriver\Evaluator\Execution\Call\Contract\Effects;
use Deriver\Deriver\Evaluator\Execution\Call\Contract\ExternalBody;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Arguments;
use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Transfer;
use Deriver\Deriver\Evaluator\Execution\Call\UnknownCall;
use Deriver\Deriver\Evaluator\Execution\Control\Handler;
use Deriver\Deriver\Evaluator\Execution\Control\IterationStep;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\SlotReference;
use Deriver\Deriver\Evaluator\Execution\Memory\Slots\StateStorage;
use Deriver\Deriver\Evaluator\Execution\Offset\Protocol;
use Deriver\Deriver\Evaluator\Execution\Offset\ProtocolAccess;
use Deriver\Deriver\Evaluator\Execution\Offset\Reader;
use Deriver\Deriver\Evaluator\Execution\Operation\Conversions;
use Deriver\Deriver\Evaluator\Execution\Operation\ScalarErrors;
use Deriver\Deriver\Evaluator\Execution\Transfer\CallableTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\CompoundAssignment;
use Deriver\Deriver\Evaluator\Execution\Transfer\ConstantTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\MemoryStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyReference;
use Deriver\Deriver\Evaluator\Execution\Transfer\PropertyTransfer;
use Deriver\Deriver\Evaluator\Execution\Transfer\PureStep;
use Deriver\Deriver\Evaluator\Execution\Transfer\ReferenceAssignment;
use Deriver\Deriver\Evaluator\Scalar\Arrays;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Connects shared instruction semantics to the abstract machine.
 * @visibility namespace
 */
final class InstructionTransfer
{
    /**
     * @param Evaluator $machine Calling evaluator
     */
    public function __construct(public readonly Evaluator $machine)
    {
    }

    /**
     * Dispatches one instruction to its semantic operation.
     * @param CallableGraph $callable Current callable
     * @param Instruction $instruction \Deriver\Deriver\Lowerer\Graph\Instruction
     * @param State $state Input path
     * @return list<State> Result paths
     */
    public function apply(CallableGraph $callable, Instruction $instruction, State $state): array
    {
        $op = $instruction->operation;
        $prepared = $this->preparation($callable, $instruction, $state);
        if ($prepared !== null) {
            return $prepared;
        }
        if ($op === 'compound') {
            return (new CompoundAssignment($this->machine))->apply($callable, $instruction, $state);
        }
        if (in_array($op, ['unsupported', 'symbol-table-boundary', 'uncertain-order'], true)) {
            return $this->boundary($callable, $instruction, $state);
        }
        $storage = $this->storage($callable, $instruction, $state);
        if ($storage !== null) {
            return $storage;
        }
        $conversion = (new Conversions($this->machine))->apply($callable, $instruction, $state);
        if ($conversion !== null) {
            return $conversion;
        }
        $context = $this->machine->context;
        if ($op === 'array-read') {
            return $this->offsetRead($callable, $instruction, $state);
        }
        if (in_array($op, ['constant-fetch', 'class-constant'], true)) {
            return (new ConstantTransfer($this->machine))->apply($callable, $instruction, $state);
        }
        if (in_array($op, ['invoke', 'invoke-method', 'invoke-static', 'new', 'clone', 'intrinsic'], true)) {
            return (new CallExecutor($this->machine))->instruction($callable, $instruction, $state);
        }
        if (in_array($op, ['local', 'dynamic-local', 'element-address', 'field-address', 'static-address', 'unsupported-address', 'returned-address', 'read', 'read-silent', 'write', 'alias', 'reference', 'increment', 'unset', 'global', 'static-local', 'static-initialized'], true)) {
            $value = (new MemoryStep($context))->evaluate($callable, $instruction, $state);
        } elseif (in_array($op, ['iterator', 'iterate', 'iterator-key', 'iterator-value', 'iterator-address', 'iterator-release'], true)) {
            $value = (new IterationStep($context))->evaluate($instruction, $state);
        } elseif ($op === 'array-unpack') {
            return $this->unpack($instruction, $state);
        } elseif ($op === 'array-set') {
            $array = $state->value($instruction->operands[0]);
            $key = $instruction->operands[1] === '' ? null : (new Reader($context))->key($state->value($instruction->operands[1]), $instruction);
            $item = $state->registers[$instruction->operands[2]] ?? \Deriver\Deriver\Evaluator\Term::opaque('UNCOMPUTED_REGISTER');
            $value = (new Arrays())->set($array, $key, $item);
        } elseif (in_array($op, ['closure', 'callable', 'callable-method', 'instanceof'], true)) {
            $value = (new CallableTransfer($context))->evaluate($callable, $instruction, $state);
        } else {
            $value = $this->other($callable, $instruction, $state);
        }
        $state->registers[$instruction->result] = $value;
        return (new ScalarErrors($context))->paths($instruction, $state);
    }

    /**
     * Unpacks arrays while preserving invalid-type errors and unknown iterator effects.
     * @param Instruction $instruction Array construction step
     * @param State $state Current path
     * @return list<State> Normal and possible exceptional paths
     */
    public function unpack(Instruction $instruction, State $state): array
    {
        $array = $state->value($instruction->operands[0]);
        $item = $state->value($instruction->operands[2]);
        if ((new TypePredicates())->apply('is_array', $item)->literal === true) {
            $state->registers[$instruction->result] = (new Arrays())->merge($array, $item);
            if ($state->registers[$instruction->result]->kind === \Deriver\Deriver\Evaluator\TermKind::ArrayMerge && !(new Arrays())->appendable($array)) {
                $this->machine->context->frontier('WIDENED', $instruction->source, 'symbolic-unpack-append', [$array, $item]);
                $exception = $state->fork();
                $exception->completion = new Completion\Completion(Completion\CompletionKind::Throw, new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error'));
                return [$state, $exception];
            }
            return [$state];
        }
        if ($item->kind === \Deriver\Deriver\Evaluator\TermKind::Constant || $item->kind === \Deriver\Deriver\Evaluator\TermKind::Closure) {
            $state->completion = new Completion\Completion(Completion\CompletionKind::Throw, new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, $item->kind === \Deriver\Deriver\Evaluator\TermKind::Closure ? 'TypeError' : 'Error'));
            return [$state];
        }
        return (new UnknownCall($this->machine->context))->apply($state, $instruction, [new PassedArgument($item)], null, 'UNSUPPORTED_LANGUAGE_FEATURE');
    }

    /**
     * Resolves call preparation and evaluator state instructions before ordinary value transfer.
     * @param CallableGraph $callable Executing graph
     * @param Instruction $instruction Current instruction
     * @param State $state Current path
     * @return list<State>|null Specialized result, or null for ordinary instructions
     */
    public function preparation(CallableGraph $callable, Instruction $instruction, State $state): ?array
    {
        $op = $instruction->operation;
        if ($op === 'iterator-value') {
            return (new IterationStep($this->machine->context))->candidates($instruction, $state);
        }
        if ($op === 'external-body') {
            return (new ExternalBody($this->machine->context))->apply($callable, $instruction, $state);
        }
        if ($op === 'evaluator-havoc') {
            return (new Effects($this->machine->context))->apply($instruction, $state);
        }
        if ($op === 'evaluator-state-address') {
            $state->registers[$instruction->result] = (new StateStorage($this->machine->context))->address($instruction, $state);
            return [$state];
        }
        if ($op === 'call-prepare') {
            return (new Transfer($this->machine))->apply($callable, $instruction, $state);
        }
        if ($op === 'callable' || $op === 'callable-method') {
            return (new Capture($this->machine))->apply($callable, $instruction, $state);
        }
        if ($op === 'argument') {
            return (new Arguments($this->machine))->apply($callable, $instruction, $state);
        }
        return null;
    }

    /**
     * Applies property, offset, and shared-reference checks before ordinary storage operations.
     * @param CallableGraph $callable Executing callable
     * @param Instruction $instruction Pending operation
     * @param State $state Input path
     * @return list<State>|null Checked paths or null when ordinary transfer may proceed
     */
    public function storage(CallableGraph $callable, Instruction $instruction, State $state): ?array
    {
        $op = $instruction->operation;
        $slot = (new SlotReference($this->machine->context))->apply($callable, $instruction, $state);
        if ($slot !== null) {
            return $slot;
        }
        $source = $instruction->operands[1] ?? '';
        if ($op === 'alias' && ($instruction->attributes['source-prepared'] ?? false) !== true && (isset($state->properties[$source]) || isset($state->offsets[$source]))) {
            return (new PropertyReference($this->machine))->alias($callable, $instruction, $state);
        }
        $property = $state->properties[$instruction->operands[0] ?? ''] ?? null;
        if ($property !== null && in_array($op, ['read', 'read-silent', 'write', 'increment', 'reference', 'alias', 'unset'], true)) {
            return (new PropertyTransfer($this->machine))->apply($callable, $instruction, $state, $property);
        }
        if (isset($state->offsets[$instruction->operands[0] ?? '']) && in_array($op, ['read', 'read-silent', 'write', 'increment', 'reference', 'alias', 'unset'], true)) {
            return (new Offset\Transfer($this->machine))->apply($callable, $instruction, $state);
        }
        if ($op === 'reference' && ($state->addresses[$instruction->operands[0] ?? '']->unknown ?? true)) {
            return $this->boundary($callable, new \Deriver\Deriver\Evaluator\Call\BoundInstruction($instruction->id, 'unsupported', $instruction->source, $instruction->result, name: 'unknown-reference-location'), $state);
        }
        $referenceWrite = (new ReferenceAssignment($this->machine->context))->apply($callable, $instruction, $state);
        if ($referenceWrite !== null) {
            return $referenceWrite;
        }
        return null;
    }

    /**
     * Reads offsets on computed values, including silent tests and ArrayAccess results.
     * @param CallableGraph $callable Executing graph
     * @param Instruction $instruction Computed offset read
     * @param State $state Evaluated container and key
     * @return list<State> Read results and exceptions
     */
    public function offsetRead(CallableGraph $callable, Instruction $instruction, State $state): array
    {
        $container = $state->value($instruction->operands[0]);
        $key = $state->value($instruction->operands[1]);
        $reader = new Reader($this->machine->context);
        $silent = ($instruction->attributes['silent'] ?? false) === true;
        if ($container->kind === \Deriver\Deriver\Evaluator\TermKind::Object && !$reader->plainObject($container)) {
            $read = new \Deriver\Deriver\Evaluator\Call\BoundInstruction($instruction->id, $silent ? 'read-silent' : 'array-read', $instruction->source, $instruction->result, $instruction->operands, attributes: $instruction->attributes);
            return (new Protocol($this->machine))->apply($callable, $read, $state, new ProtocolAccess($container, $key));
        }
        return (new Offset\Transfer($this->machine))->result($state, $instruction, $reader->read($container, $key, $instruction, $state, $silent));
    }

    /**
     * Preserves normal and exceptional exits when arbitrary boundary effects are possible.
     * @param CallableGraph $callable Executing callable
     * @param Instruction $instruction Unsupported or unordered operation
     * @param State $state Input path
     * @return list<State> Inclusive normal and unknown-throwable paths after conservative effects
     */
    public function boundary(CallableGraph $callable, Instruction $instruction, State $state): array
    {
        $state->registers[$instruction->result] = $this->other($callable, $instruction, $state);
        $exception = $state->fork();
        $exception->completion = new Completion\Completion(Completion\CompletionKind::Throw, new \Deriver\Deriver\Evaluator\Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Throwable', attributes: ['uncertain' => true]));
        return [$state, $exception];
    }

    /**
     * Applies exception and boundary instructions before pure evaluation.
     * @param CallableGraph $callable Current callable
     * @param Instruction $instruction \Deriver\Deriver\Lowerer\Graph\Instruction
     * @param State $state Current path
     * @return \Deriver\Deriver\Evaluator\Term Result value
     */
    public function other(CallableGraph $callable, Instruction $instruction, State $state): \Deriver\Deriver\Evaluator\Term
    {
        $context = $this->machine->context;
        if ($instruction->operation === 'enter-try') {
            $region = $instruction->attributes['region'] ?? 0;
            if (is_int($region) && isset($callable->regions[$region])) {
                $state->handlers[] = new Handler($callable->regions[$region]);
            }
            return \Deriver\Deriver\Evaluator\Term::constant(null);
        }
        if ($instruction->operation === 'throw') {
            $value = $state->value($instruction->operands[0]);
            $state->completion = new Completion\Completion(Completion\CompletionKind::Throw, $value);
            return $value;
        }
        if (in_array($instruction->operation, ['unsupported', 'symbol-table-boundary', 'uncertain-order'], true)) {
            $reason = $instruction->operation === 'uncertain-order' ? 'UNSPECIFIED_EVALUATION_ORDER' : ($instruction->operation === 'symbol-table-boundary' ? $instruction->name : 'UNSUPPORTED_LANGUAGE_FEATURE');
            $values = array_map($state->value(...), $instruction->operands);
            if (($instruction->attributes['effects'] ?? '') === 'reachable') {
                (new Havoc())->call($state, $values, [], $reason);
            } else {
                (new Havoc())->all($state, $reason);
            }
            return $context->frontier($reason, $instruction->source, $instruction->name, $values, (string) ($instruction->attributes['type'] ?? 'mixed'));
        }
        return (new PureStep($context))->evaluate($callable, $instruction, $state);
    }
}
