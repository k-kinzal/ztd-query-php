<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call\Preparation;

use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\InstructionTransfer;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Freezes value arguments and exposes reference cells at their source evaluation point.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Arguments
{
    /**
     * @param Evaluator $machine Shared property, offset, and reference semantics
     */
    public function __construct(public readonly Evaluator $machine)
    {
    }

    /**
     * Evaluates an actual under the selected signature's passing mode.
     * @param CallableGraph $caller Executing graph
     * @param Instruction $instruction Argument expression after its operand effects
     * @param State $state \Deriver\Deriver\Evaluator\Execution\State before evaluating the next argument
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
                array_push($result, ...($path->completion->kind === \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal ? (new Unpack($this->machine))->apply($caller, $instruction, $path, $target, $position) : [$path]));
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
        $probe = new \Deriver\Deriver\Evaluator\Call\BoundInstruction($instruction->id . ':access', $reference ? 'reference' : 'read', $instruction->source, $instruction->result, [$operand]);
        $paths = (new InstructionTransfer($this->machine))->apply($caller, $probe, $state);
        foreach ($paths as $path) {
            $raw = $path->registers[$instruction->result] ?? Term::opaque('UNCOMPUTED_ARGUMENT');
            if ($raw->kind === \Deriver\Deriver\Evaluator\TermKind::Throwable) {
                $path->completion = new Completion(\Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw, $raw);
            }
            if ($path->completion->kind !== \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
                continue;
            }
            if ($reference && $raw->kind === \Deriver\Deriver\Evaluator\TermKind::Cell && is_string($raw->literal)) {
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
     * @param State $state \Deriver\Deriver\Evaluator\Execution\State after computing its value
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
        if ($raw->kind === \Deriver\Deriver\Evaluator\TermKind::Cell && is_string($raw->literal)) {
            $location = new Location($raw->literal);
        } elseif (($instruction->attributes['temporary'] ?? false) === true) {
            $this->machine->context->frontier('PHP_WARNING', $instruction->source, 'temporary-reference-argument');
            $location = $state->memory->allocate($raw);
        } else {
            $state->completion = new Completion(\Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw, new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error'));
            return [$state];
        }
        $state->addresses[$instruction->result] = $location;
        $state->registers[$instruction->result] = new Term(\Deriver\Deriver\Evaluator\TermKind::Cell, $location->root);
        return [$state];
    }
}
