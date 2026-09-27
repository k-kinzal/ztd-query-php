<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Transfer;

use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\Offset\Transfer;
use Deriver\Evaluation\State;
use Deriver\Value\Term;

/**
 * Connects inaccessible property reads and writes to ordinary source magic methods.
 * @visibility root
 */
final class PropertyMagic
{
    /**
     * @param Machine $machine Shared call evaluator
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Invokes an applicable magic method with evaluated receiver and property name.
     * @param CallableGraph $caller Executing callable
     * @param Instruction $instruction Storage instruction
     * @param State $state Input path
     * @param PropertySlot $slot Address context
     * @return list<State>|null Magic results or no applicable method
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state, PropertySlot $slot): ?array
    {
        $class = $slot->receiver->attributes['class'] ?? '';
        if ($slot->static || !is_string($class)) {
            return null;
        }
        if (isset($state->offsets[$instruction->operands[0]]) && (new Dispatch($this->machine->context->program))->method($class, '__get') !== null) {
            return (new Transfer($this->machine))->boundary($state, $instruction);
        }
        if ($instruction->operation === 'read-silent') {
            return $this->silent($caller, $instruction, $state, $slot, $class);
        }
        $method = match ($instruction->operation) {
            'read' => '__get', 'read-silent' => '__isset', 'write' => '__set', 'unset' => '__unset', default => '',
        };
        $symbol = (new Dispatch($this->machine->context->program))->method($class, $method);
        if ($symbol === null || strcasecmp($caller->symbol, $symbol) === 0) {
            return null;
        }
        $arguments = [new PassedArgument(Term::constant($slot->name))];
        $assigned = $state->value($instruction->operands[1] ?? '');
        if ($method === '__set') {
            $arguments[] = new PassedArgument($assigned);
        }
        $paths = (new CallExecutor($this->machine))->symbol($symbol, $arguments, $state, $instruction, $slot->receiver, strict: $caller->strict);
        foreach ($paths as $path) {
            if ($path->completion->kind === 'normal' && $method === '__set') {
                $path->registers[$instruction->result] = $assigned;
            }
        }
        return $paths;
    }

    /**
     * Evaluates __isset before an optional __get, retaining both guarded paths.
     * @param CallableGraph $caller Executing callable
     * @param Instruction $instruction Silent property read
     * @param State $state Input path
     * @param PropertySlot $slot Property address context
     * @param string $class Runtime receiver class
     * @return list<State>|null Silent read alternatives
     */
    public function silent(CallableGraph $caller, Instruction $instruction, State $state, PropertySlot $slot, string $class): ?array
    {
        $dispatch = new Dispatch($this->machine->context->program);
        $exists = $dispatch->method($class, '__isset');
        $getter = $dispatch->method($class, '__get');
        $probe = ($instruction->attributes['existence'] ?? false) === true;
        $arguments = [new PassedArgument(Term::constant($slot->name))];
        if ($exists === null) {
            if (!$probe && $getter !== null) {
                return (new CallExecutor($this->machine))->symbol($getter, $arguments, $state, $instruction, $slot->receiver, strict: $caller->strict);
            }
            return null;
        }
        $results = [];
        $paths = (new CallExecutor($this->machine))->symbol($exists, $arguments, $state, $instruction, $slot->receiver, strict: $caller->strict);
        foreach ($paths as $path) {
            if ($path->completion->kind !== 'normal') {
                $results[] = $path;
                continue;
            }
            foreach ([true, false] as $truth) {
                $next = $path->fork();
                if (!(new Constraints($this->machine->context))->assume($next, $path->value($instruction->result), $truth)) {
                    continue;
                }
                if ($truth && !$probe && $getter !== null) {
                    array_push($results, ...(new CallExecutor($this->machine))->symbol($getter, $arguments, $next, $instruction, $slot->receiver, strict: $caller->strict));
                } else {
                    $next->registers[$instruction->result] = $truth ? Term::constant(true) : new Term('uninitialized');
                    $results[] = $next;
                }
            }
        }
        return $results;
    }
}
