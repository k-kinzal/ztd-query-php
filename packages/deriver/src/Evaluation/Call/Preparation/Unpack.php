<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call\Preparation;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Control\StateJoin;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Value\Term;

/**
 * Freezes unpacked values and prepares only the reference elements required by the signature.
 * @visibility root
 */
final class Unpack
{
    /**
     * @param Machine $machine Shared argument and resource semantics
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Expands a known array at the argument's evaluation point.
     * @param CallableGraph $caller Executing graph
     * @param Instruction $instruction Unpacked argument
     * @param State $state Evaluated array argument
     * @param Target $target Selected signature
     * @param int|null $position Next positional index
     * @return list<State> Prepared arrays or inclusive unresolved unpack paths
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state, Target $target, ?int $position): array
    {
        $array = $state->value($instruction->result);
        if ($array->kind === 'constant' || $array->kind === 'closure' || $array->kind === 'enum') {
            $state->completion = new Completion('throw', new Term('throwable', 'TypeError'));
            return [$state];
        }
        if ($array->kind !== 'array' || ($array->attributes['open'] ?? false) === true) {
            return $this->boundary($state, $instruction);
        }
        $source = $this->source($instruction, $state, $array);
        $prepared = $state->memory->allocate(Term::array([]));
        $state->addresses[$instruction->result] = $prepared;
        unset($state->properties[$instruction->result]);
        $paths = [$state];
        foreach (array_keys($array->operands) as $key) {
            if (!$this->machine->context->admit($instruction->source)) {
                return $this->boundary($state, $instruction);
            }
            $mode = (new Modes($this->machine))->select($target, $position, is_string($key) ? $key : null);
            $next = [];
            foreach ($paths as $path) {
                foreach ($mode === null ? [false, true] : [$mode] as $reference) {
                    $next[] = $this->element($path->fork(), $source, $prepared, $key, $reference);
                }
            }
            $paths = (new StateJoin($this->machine->context))->limit($next, $caller);
            $position = is_int($key) && $position !== null ? $position + 1 : $position;
        }
        foreach ($paths as $path) {
            $path->registers[$instruction->result] = $path->memory->read($prepared);
        }
        return $paths;
    }

    /**
     * Copies a value or connects the original element's reference cell.
     * @param State $state Independent candidate path
     * @param Location $source Original variable array or temporary array value
     * @param Location $prepared Frozen argument array
     * @param int|string $key Array key and optional parameter name
     * @param bool $reference Whether this element is sent by reference
     * @return State Updated prepared array
     */
    public function element(State $state, Location $source, Location $prepared, int|string $key, bool $reference): State
    {
        $address = new Location($source->root, [...$source->path, $key]);
        $value = $reference ? new Term('cell', $state->memory->reference($address)) : $state->memory->read($address);
        $state->memory->write(new Location($prepared->root, [$key]), $value);
        return $state;
    }

    /**
     * Preserves iterator effects and exceptions when unpack cannot be expanded exactly.
     * @param State $state State after preceding argument effects
     * @param Instruction $instruction Unresolved unpack origin
     * @return list<State> Normal and exceptional residual paths
     */
    public function boundary(State $state, Instruction $instruction): array
    {
        (new Havoc())->all($state, 'UNKNOWN_ARGUMENT_UNPACK');
        $state->registers[$instruction->result] = $this->machine->context->frontier('UNSUPPORTED_LANGUAGE_FEATURE', $instruction->source, 'unknown-argument-unpack');
        $exception = $state->fork();
        $exception->completion = new Completion('throw', new Term('throwable', 'Throwable', attributes: ['uncertain' => true]));
        return [$state, $exception];
    }
    /**
     * Retains references returned by calls and arrays stored in plain variables.
     * @param Instruction $instruction Unpack argument
     * @param State $state Current memory
     * @param Term $array Evaluated array
     * @return Location Shared source or a temporary array for other expressions
     */
    public function source(Instruction $instruction, State $state, Term $array): Location
    {
        $source = ($instruction->attributes['unpack-variable'] ?? false) === true ? ($state->addresses[$instruction->result] ?? null) : null;
        $raw = $state->registers[$instruction->operands[1]] ?? null;
        $source = $raw?->kind === 'cell' && is_string($raw->literal) ? new Location($raw->literal) : $source;
        return $source ?? $state->memory->allocate($array);
    }
}
