<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Transfer;

use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Solver\Call\CallExecutor;
use Deriver\Internal\Solver\Call\Dispatch;
use Deriver\Internal\Solver\Call\PassedArgument;
use Deriver\Internal\Solver\Machine;
use Deriver\Internal\Solver\State;
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
     * @param CallableIR $caller Executing callable
     * @param Instruction $instruction Storage instruction
     * @param State $state Input path
     * @param PropertySlot $slot Address context
     * @return list<State>|null Magic results or no applicable method
     */
    public function apply(CallableIR $caller, Instruction $instruction, State $state, PropertySlot $slot): ?array
    {
        $class = $slot->receiver->attributes['class'] ?? '';
        if ($slot->static || !is_string($class)) {
            return null;
        }
        if (isset($state->offsets[$instruction->operands[0]]) && (new Dispatch($this->machine->context->program))->method($class, '__get') !== null) {
            return (new \Deriver\Internal\Solver\Offset\Transfer($this->machine))->boundary($state, $instruction);
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
     * @param CallableIR $caller Executing callable
     * @param Instruction $instruction Silent property read
     * @param State $state Input path
     * @param PropertySlot $slot Property address context
     * @param string $class Runtime receiver class
     * @return list<State>|null Silent read alternatives
     */
    public function silent(CallableIR $caller, Instruction $instruction, State $state, PropertySlot $slot, string $class): ?array
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
                if (!(new \Deriver\Internal\Constraint\Constraints($this->machine->context))->assume($next, $path->value($instruction->result), $truth)) {
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
