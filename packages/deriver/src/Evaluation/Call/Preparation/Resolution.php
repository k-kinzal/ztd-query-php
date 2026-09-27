<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call\Preparation;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallResolution;
use Deriver\Evaluation\Call\Closure\Binding;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Value\Term;

/**
 * Resolves argument reference modes without invoking the selected body.
 * @visibility root
 */
final class Resolution
{
    /**
     * @param Machine $machine Captured source and model world
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Resolves function, method, and constructor signatures before arguments.
     * @param CallableGraph $caller Lexical caller
     * @param Instruction $instruction Call prototype with evaluated target operands
     * @param State $state Target evaluation state
     * @return Target Signature or early failure
     */
    public function resolve(CallableGraph $caller, Instruction $instruction, State $state): Target
    {
        if ($instruction->operation === 'new') {
            return (new Creation($this->machine))->resolve($caller, $instruction, $state);
        }
        $target = $state->value($instruction->operands[0]);
        if ($instruction->operation !== 'invoke') {
            return (new Methods($this->machine))->resolve($caller, $instruction, $state, $target, $state->value($instruction->operands[1]), $instruction->operation === 'invoke-static');
        }
        return $this->function($caller, $instruction, $state, $target);
    }

    /**
     * Resolves callable values while retaining actual closure and object identities.
     * @param CallableGraph $caller Lexical caller
     * @param Instruction $instruction Function invocation
     * @param State $state Evaluated callable state
     * @param Term $target Callable value
     * @return Target Signature or invalid callable error
     */
    public function function(CallableGraph $caller, Instruction $instruction, State $state, Term $target): Target
    {
        if ($target->kind === 'closure' && ($target->attributes['first-class'] ?? false) === true) {
            return (new Binding($this->machine))->signature($target, $caller, $instruction, $state);
        }
        if ($target->kind === 'callable' && isset($target->operands[0])) {
            return $this->function($caller, $instruction, $state, $target->operands[0]);
        }
        if ((new CallableCheck($this->machine->context))->pair($target)) {
            $receiver = $state->memory->dereference($target->operands[0]);
            return (new Methods($this->machine))->resolve($caller, $instruction, $state, $receiver, $state->memory->dereference($target->operands[1]), $receiver->kind === 'constant');
        }
        if ($target->kind === 'closure' && is_string($target->literal)) {
            return new Target($this->machine->context->program->callable($target->literal));
        }
        if ($target->kind === 'object') {
            return (new Methods($this->machine))->resolve($caller, $instruction, $state, $target, Term::constant('__invoke'), false);
        }
        if ($target->kind === 'constant' && is_string($target->literal)) {
            return $this->named($caller, $instruction, $state, $target->literal);
        }
        return in_array($target->kind, ['constant', 'array', 'enum'], true) && ($target->attributes['open'] ?? false) !== true ? new Target(error: 'Error') : new Target();
    }

    /**
     * Applies namespace fallback and class-method callable string rules.
     * @param CallableGraph $caller Executing graph
     * @param Instruction $instruction Call origin
     * @param State $state Lexical receiver bindings
     * @param string $symbol Evaluated callable name
     * @return Target Selected signature
     */
    public function named(CallableGraph $caller, Instruction $instruction, State $state, string $symbol): Target
    {
        if (str_contains($symbol, '::')) {
            [$class, $method] = explode('::', $symbol, 2);
            return (new Methods($this->machine))->resolve($caller, $instruction, $state, Term::constant($class), Term::constant($method), true);
        }
        $resolution = new CallResolution($this->machine->context);
        return new Target($resolution->body($resolution->name($symbol, $instruction), $instruction));
    }
}
