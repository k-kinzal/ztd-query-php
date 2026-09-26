<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver;

use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Call\CallExecutor;
use Deriver\Internal\Solver\Control\Handler;
use Deriver\Internal\Solver\Control\IterationStep;
use Deriver\Internal\Solver\Transfer\CallableTransfer;
use Deriver\Internal\Solver\Transfer\MemoryStep;
use Deriver\Internal\Solver\Transfer\PureStep;
use Deriver\Internal\Value\Arrays;
use Deriver\Value\Term;

/**
 * Connects shared instruction semantics to the abstract machine.
 * @visibility root
 */
final class InstructionTransfer
{
    /**
     * @param Machine $machine Calling evaluator
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Dispatches one instruction to its semantic operation.
     * @param CallableIR $callable Current callable
     * @param Instruction $instruction Instruction
     * @param State $state Input path
     * @return list<State> Result paths
     */
    public function apply(CallableIR $callable, Instruction $instruction, State $state): array
    {
        $op = $instruction->operation;
        $prepared = $this->preparation($callable, $instruction, $state);
        if ($prepared !== null) {
            return $prepared;
        }
        if ($op === 'compound') {
            return (new Transfer\CompoundAssignment($this->machine))->apply($callable, $instruction, $state);
        }
        if (in_array($op, ['unsupported', 'symbol-table-boundary', 'uncertain-order'], true)) {
            return $this->boundary($callable, $instruction, $state);
        }
        $storage = $this->storage($callable, $instruction, $state);
        if ($storage !== null) {
            return $storage;
        }
        $conversion = (new Operation\Conversions($this->machine))->apply($callable, $instruction, $state);
        if ($conversion !== null) {
            return $conversion;
        }
        $context = $this->machine->context;
        if ($op === 'array-read') {
            return $this->offsetRead($callable, $instruction, $state);
        }
        if (in_array($op, ['constant-fetch', 'class-constant'], true)) {
            return (new Transfer\ConstantTransfer($this->machine))->apply($callable, $instruction, $state);
        }
        if (in_array($op, ['invoke', 'invoke-method', 'invoke-static', 'new', 'clone', 'intrinsic'], true)) {
            return (new CallExecutor($this->machine))->instruction($callable, $instruction, $state);
        }
        if (in_array($op, ['local', 'element-address', 'field-address', 'static-address', 'unsupported-address', 'returned-address', 'read', 'read-silent', 'write', 'alias', 'reference', 'increment', 'unset', 'global', 'static-local', 'static-initialized'], true)) {
            $value = (new MemoryStep($context))->evaluate($callable, $instruction, $state);
        } elseif (in_array($op, ['iterator', 'iterate', 'iterator-key', 'iterator-value', 'iterator-address', 'iterator-release'], true)) {
            $value = (new IterationStep($context))->evaluate($instruction, $state);
        } elseif ($op === 'array-unpack') {
            return $this->unpack($instruction, $state);
        } elseif ($op === 'array-set') {
            $array = $state->value($instruction->operands[0]);
            $key = $instruction->operands[1] === '' ? null : $state->value($instruction->operands[1]);
            $item = $state->registers[$instruction->operands[2]] ?? Term::opaque('UNCOMPUTED_REGISTER');
            $value = (new Arrays())->set($array, $key, $item);
        } elseif (in_array($op, ['closure', 'callable', 'callable-method', 'class-constant', 'instanceof'], true)) {
            $value = (new CallableTransfer($context))->evaluate($callable, $instruction, $state);
        } else {
            $value = $this->other($callable, $instruction, $state);
        }
        $state->registers[$instruction->result] = $value;
        return (new Operation\ScalarErrors($context))->paths($instruction, $state);
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
        if ((new \Deriver\Standard\TypePredicates())->apply('is_array', $item)->literal === true) {
            $state->registers[$instruction->result] = (new Arrays())->merge($array, $item);
            if ($state->registers[$instruction->result]->kind === 'array-merge') {
                $this->machine->context->frontier('WIDENED', $instruction->source, 'symbolic-unpack-append', [$array, $item]);
                $exception = $state->fork();
                $exception->completion = new Completion('throw', new Term('throwable', 'Error'));
                return [$state, $exception];
            }
            return [$state];
        }
        if ($item->kind === 'constant' || $item->kind === 'closure') {
            $state->completion = new Completion('throw', new Term('throwable', $item->kind === 'closure' ? 'TypeError' : 'Error'));
            return [$state];
        }
        return (new Call\UnknownCall($this->machine->context))->apply($state, $instruction, [new Call\PassedArgument($item)], null, 'UNSUPPORTED_LANGUAGE_FEATURE');
    }

    /**
     * Resolves call preparation and model state instructions before ordinary value transfer.
     * @param CallableIR $callable Executing graph
     * @param Instruction $instruction Current instruction
     * @param State $state Current path
     * @return list<State>|null Specialized result, or null for ordinary instructions
     */
    public function preparation(CallableIR $callable, Instruction $instruction, State $state): ?array
    {
        $op = $instruction->operation;
        if ($op === 'external-body') {
            return (new Call\Model\ExternalBody($this->machine->context))->apply($callable, $instruction, $state);
        }
        if ($op === 'model-havoc') {
            return (new Model\Effects($this->machine->context))->apply($instruction, $state);
        }
        if ($op === 'model-state-address') {
            $state->registers[$instruction->result] = (new Model\StateStorage($this->machine->context))->address($instruction, $state);
            return [$state];
        }
        if ($op === 'call-prepare') {
            return (new Call\Preparation\Transfer($this->machine))->apply($callable, $instruction, $state);
        }
        if ($op === 'callable' || $op === 'callable-method') {
            return (new Call\Closure\Capture($this->machine))->apply($callable, $instruction, $state);
        }
        if ($op === 'argument') {
            return (new Call\Preparation\Arguments($this->machine))->apply($callable, $instruction, $state);
        }
        return null;
    }

    /**
     * Applies property, offset, and shared-reference checks before ordinary storage operations.
     * @param CallableIR $callable Executing callable
     * @param Instruction $instruction Pending operation
     * @param State $state Input path
     * @return list<State>|null Checked paths or null when ordinary transfer may proceed
     */
    public function storage(CallableIR $callable, Instruction $instruction, State $state): ?array
    {
        $op = $instruction->operation;
        $slot = (new Model\SlotReference($this->machine->context))->apply($callable, $instruction, $state);
        if ($slot !== null) {
            return $slot;
        }
        $source = $instruction->operands[1] ?? '';
        if ($op === 'alias' && ($instruction->attributes['source-prepared'] ?? false) !== true && (isset($state->properties[$source]) || isset($state->offsets[$source]))) {
            return (new Transfer\PropertyReference($this->machine))->alias($callable, $instruction, $state);
        }
        $property = $state->properties[$instruction->operands[0] ?? ''] ?? null;
        if ($property !== null && in_array($op, ['read', 'read-silent', 'write', 'increment', 'reference', 'alias', 'unset'], true)) {
            return (new Transfer\PropertyTransfer($this->machine))->apply($callable, $instruction, $state, $property);
        }
        if (isset($state->offsets[$instruction->operands[0] ?? '']) && in_array($op, ['read', 'read-silent', 'write', 'increment', 'reference', 'alias', 'unset'], true)) {
            return (new Offset\Transfer($this->machine))->apply($callable, $instruction, $state);
        }
        if ($op === 'reference' && ($state->addresses[$instruction->operands[0] ?? '']->unknown ?? true)) {
            return $this->boundary($callable, new Instruction($instruction->id, 'unsupported', $instruction->source, $instruction->result, name: 'unknown-reference-location'), $state);
        }
        $referenceWrite = (new Transfer\ReferenceAssignment($this->machine->context))->apply($callable, $instruction, $state);
        if ($referenceWrite !== null) {
            return $referenceWrite;
        }
        return null;
    }

    /**
     * Reads offsets on computed values, including silent tests and ArrayAccess results.
     * @param CallableIR $callable Executing graph
     * @param Instruction $instruction Computed offset read
     * @param State $state Evaluated container and key
     * @return list<State> Read results and exceptions
     */
    public function offsetRead(CallableIR $callable, Instruction $instruction, State $state): array
    {
        $container = $state->value($instruction->operands[0]);
        $key = $state->value($instruction->operands[1]);
        $reader = new Offset\Reader($this->machine->context);
        $silent = ($instruction->attributes['silent'] ?? false) === true;
        if ($container->kind === 'object' && !$reader->plainObject($container)) {
            $read = new Instruction($instruction->id, $silent ? 'read-silent' : 'array-read', $instruction->source, $instruction->result, $instruction->operands, attributes: $instruction->attributes);
            return (new Offset\Protocol($this->machine))->apply($callable, $read, $state, new Offset\ProtocolAccess($container, $key));
        }
        return (new Offset\Transfer($this->machine))->result($state, $instruction, $reader->read($container, $key, $instruction, $state, $silent));
    }

    /**
     * Preserves normal and exceptional exits when arbitrary boundary effects are possible.
     * @param CallableIR $callable Executing callable
     * @param Instruction $instruction Unsupported or unordered operation
     * @param State $state Input path
     * @return list<State> Inclusive normal and unknown-throwable paths after conservative effects
     */
    public function boundary(CallableIR $callable, Instruction $instruction, State $state): array
    {
        $state->registers[$instruction->result] = $this->other($callable, $instruction, $state);
        $exception = $state->fork();
        $exception->completion = new Completion('throw', new Term('throwable', 'Throwable', attributes: ['uncertain' => true]));
        return [$state, $exception];
    }

    /**
     * Applies exception and boundary instructions before pure evaluation.
     * @param CallableIR $callable Current callable
     * @param Instruction $instruction Instruction
     * @param State $state Current path
     * @return Term Result value
     */
    public function other(CallableIR $callable, Instruction $instruction, State $state): Term
    {
        $context = $this->machine->context;
        if ($instruction->operation === 'enter-try') {
            $region = $instruction->attributes['region'] ?? 0;
            if (is_int($region) && isset($callable->regions[$region])) {
                $state->handlers[] = new Handler($callable->regions[$region]);
            }
            return Term::constant(null);
        }
        if ($instruction->operation === 'throw') {
            $value = $state->value($instruction->operands[0]);
            $state->completion = new Completion('throw', $value);
            return $value;
        }
        if (in_array($instruction->operation, ['unsupported', 'symbol-table-boundary', 'uncertain-order'], true)) {
            $reason = $instruction->operation === 'uncertain-order' ? 'UNSPECIFIED_EVALUATION_ORDER' : ($instruction->operation === 'symbol-table-boundary' ? $instruction->name : 'UNSUPPORTED_LANGUAGE_FEATURE');
            (new Havoc())->all($state, $reason);
            return $context->frontier($reason, $instruction->source, $instruction->name);
        }
        return (new PureStep($context))->evaluate($callable, $instruction, $state);
    }
}
