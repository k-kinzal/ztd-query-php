<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call\Preparation;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Value\Term;

/**
 * Freezes value arguments and exposes reference cells at their source evaluation point.
 * @visibility root
 */
final class Arguments
{
    /**
     * @param Machine $machine Shared property, offset, and reference semantics
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Evaluates an actual under the selected signature's passing mode.
     * @param CallableGraph $caller Executing graph
     * @param Instruction $instruction Argument expression after its operand effects
     * @param State $state State before evaluating the next argument
     * @return list<State> Argument results and any immediate reference errors
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state): array
    {
        $target = $state->callTargets[$instruction->operands[0]] ?? new Target();
        $modes = new Modes($this->machine);
        $position = $modes->position($instruction, $state);
        if (($instruction->attributes['unpack'] ?? false) === true) {
            $result = [];
            foreach ($this->read($caller, $instruction, $state, false) as $path) {
                array_push($result, ...($path->completion->kind === 'normal' ? (new Unpack($this->machine))->apply($caller, $instruction, $path, $target, $position) : [$path]));
            }
            return $result;
        }
        $name = $instruction->attributes['argument-name'] ?? null;
        $mode = $modes->select($target, $position, is_string($name) ? $name : null);
        if ($mode === null) {
            return [...$this->read($caller, $instruction, $state->fork(), false), ...$this->read($caller, $instruction, $state->fork(), true)];
        }
        return $this->read($caller, $instruction, $state, $mode);
    }

    /**
     * Applies the ordinary access checks to a value read or reference exposure.
     * @param CallableGraph $caller Calling scope
     * @param Instruction $instruction Argument origin
     * @param State $state Current memory
     * @param bool $reference Whether the signature requires a reference
     * @return list<State> Evaluated argument paths
     */
    public function read(CallableGraph $caller, Instruction $instruction, State $state, bool $reference): array
    {
        $operand = $instruction->operands[1];
        if (($instruction->attributes['address'] ?? false) !== true) {
            return $this->computed($instruction, $state, $reference);
        }
        $probe = new Instruction($instruction->id . ':access', $reference ? 'reference' : 'read', $instruction->source, $instruction->result, [$operand]);
        $paths = (new InstructionTransfer($this->machine))->apply($caller, $probe, $state);
        foreach ($paths as $path) {
            $raw = $path->registers[$instruction->result] ?? Term::opaque('UNCOMPUTED_ARGUMENT');
            if ($raw->kind === 'throwable') {
                $path->completion = new Completion('throw', $raw);
            }
            if ($path->completion->kind !== 'normal') {
                continue;
            }
            if ($reference && $raw->kind === 'cell' && is_string($raw->literal)) {
                $path->addresses[$instruction->result] = new Location($raw->literal);
            } else {
                $path->addresses[$instruction->result] = $path->addresses[$operand] ?? new Location('unknown-argument', unknown: true);
                $path->registers[$instruction->result] = $path->value($instruction->result);
                if (isset($path->properties[$operand])) {
                    $path->properties[$instruction->result] = $path->properties[$operand];
                }
            }
        }
        return $paths;
    }

    /**
     * Distinguishes returned references, allowed function-result temporaries, and literal errors.
     * @param Instruction $instruction Computed argument
     * @param State $state State after computing its value
     * @param bool $reference Whether the selected parameter requires a reference
     * @return list<State> Frozen value, returned cell, or immediate error
     */
    public function computed(Instruction $instruction, State $state, bool $reference): array
    {
        $raw = $state->registers[$instruction->operands[1]] ?? Term::opaque('UNCOMPUTED_ARGUMENT');
        if (!$reference) {
            $state->registers[$instruction->result] = $state->memory->dereference($raw);
            return [$state];
        }
        if ($raw->kind === 'cell' && is_string($raw->literal)) {
            $location = new Location($raw->literal);
        } elseif (($instruction->attributes['temporary'] ?? false) === true) {
            $this->machine->context->frontier('PHP_WARNING', $instruction->source, 'temporary-reference-argument');
            $location = $state->memory->allocate($raw);
        } else {
            $state->completion = new Completion('throw', new Term('throwable', 'Error'));
            return [$state];
        }
        $state->addresses[$instruction->result] = $location;
        $state->registers[$instruction->result] = new Term('cell', $location->root);
        return [$state];
    }
}
