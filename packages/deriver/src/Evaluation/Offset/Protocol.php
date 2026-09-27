<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Offset;

use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\MemoryStep;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Value\Term;

/**
 * Executes ArrayAccess methods through source semantics and shared reference storage.
 * @visibility root
 */
final class Protocol
{
    /**
     * @param Machine $machine Shared call evaluator
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Selects the PHP offset protocol for a read, assignment, or indirect mutation.
     * @param CallableGraph $caller Executing callable
     * @param Instruction $instruction Storage operation
     * @param State $state Input path
     * @param ProtocolAccess $access Evaluated object and key
     * @return list<State> Source method results and their exceptions
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state, ProtocolAccess $access): array
    {
        if ($instruction->operation === 'read-silent') {
            return $this->silent($caller, $instruction, $state, $access);
        }
        if ($access->remaining !== [] || !in_array($instruction->operation, ['write', 'unset'], true)) {
            return $this->get($caller, $instruction, $state, $access);
        }
        $assigned = $state->value($instruction->operands[1] ?? '');
        $arguments = [new PassedArgument($access->key ?? Term::constant(null))];
        if ($instruction->operation === 'write') {
            $arguments[] = new PassedArgument($assigned);
        }
        $paths = $this->call($caller, $instruction, $state, $access, $instruction->operation === 'write' ? 'offsetSet' : 'offsetUnset', $arguments);
        foreach ($paths as $path) {
            if ($path->completion->kind === 'normal') {
                $path->registers[$instruction->result] = $instruction->operation === 'write' ? $assigned : Term::constant(null);
            }
        }
        return $paths;
    }

    /**
     * Invokes one protocol method without executing application code in the host runtime.
     * @param CallableGraph $caller Calling-file coercion mode
     * @param Instruction $instruction Offset origin
     * @param State $state Input path
     * @param ProtocolAccess $access Receiver identity
     * @param string $method Offset method
     * @param list<PassedArgument> $arguments Evaluated arguments
     * @return list<State> Normal and exceptional method results
     */
    public function call(CallableGraph $caller, Instruction $instruction, State $state, ProtocolAccess $access, string $method, array $arguments): array
    {
        $class = $access->receiver->attributes['class'] ?? '';
        $symbol = is_string($class) ? (new Dispatch($this->machine->context->program))->method($class, $method) : null;
        if ($symbol === null) {
            return (new Transfer($this->machine))->boundary($state, $instruction);
        }
        $call = new Instruction($instruction->id . ':' . $method, 'invoke-method', $instruction->source, $instruction->result);
        return (new CallExecutor($this->machine))->symbol($symbol, $arguments, $state, $call, $access->receiver, strict: $caller->strict);
    }

    /**
     * Calls offsetGet and then applies any remaining indirect operation.
     * @param CallableGraph $caller Executing graph
     * @param Instruction $instruction Original operation
     * @param State $state Input memory
     * @param ProtocolAccess $access Receiver and remaining path
     * @return list<State> Value reads or subsequent mutations
     */
    public function get(CallableGraph $caller, Instruction $instruction, State $state, ProtocolAccess $access): array
    {
        $results = [];
        foreach ($this->call($caller, $instruction, $state, $access, 'offsetGet', [new PassedArgument($access->key ?? Term::constant(null))]) as $path) {
            if ($path->completion->kind !== 'normal') {
                $results[] = $path;
            } elseif ($access->remaining === [] && in_array($instruction->operation, ['read', 'read-silent', 'array-read'], true)) {
                $path->registers[$instruction->result] = $path->value($instruction->result);
                $results[] = $path;
            } else {
                array_push($results, ...$this->indirect($caller, $instruction, $path, $access));
            }
        }
        return $results;
    }

    /**
     * Uses offsetExists before offsetGet, preserving the source method call order.
     * @param CallableGraph $caller Executing graph
     * @param Instruction $instruction Silent offset read
     * @param State $state Input memory
     * @param ProtocolAccess $access Receiver, key, and nested path
     * @return list<State> Guarded existence or read results
     */
    public function silent(CallableGraph $caller, Instruction $instruction, State $state, ProtocolAccess $access): array
    {
        $results = [];
        foreach ($this->call($caller, $instruction, $state, $access, 'offsetExists', [new PassedArgument($access->key ?? Term::constant(null))]) as $path) {
            if ($path->completion->kind !== 'normal') {
                $results[] = $path;
                continue;
            }
            foreach ([true, false] as $exists) {
                $next = $path->fork();
                if (!(new Constraints($this->machine->context))->assume($next, $path->value($instruction->result), $exists)) {
                    continue;
                }
                if ($exists && ($access->remaining !== [] || ($instruction->attributes['existence'] ?? false) !== true)) {
                    array_push($results, ...$this->get($caller, $instruction, $next, $access));
                } else {
                    $next->registers[$instruction->result] = $exists ? Term::constant(true) : new Term('uninitialized');
                    $results[] = $next;
                }
            }
        }
        return $results;
    }

    /**
     * Preserves reference returns; value returns provide temporary indirect storage.
     * @param CallableGraph $caller Executing callable
     * @param Instruction $instruction Pending indirect operation
     * @param State $state State after offsetGet
     * @param ProtocolAccess $access Remaining nested offsets
     * @return list<State> Subsequent storage operation results
     */
    public function indirect(CallableGraph $caller, Instruction $instruction, State $state, ProtocolAccess $access): array
    {
        $raw = $state->registers[$instruction->result];
        $value = $state->value($instruction->result);
        $reference = $raw->kind === 'cell' && is_string($raw->literal);
        $read = in_array($instruction->operation, ['read', 'read-silent', 'array-read'], true) && ($instruction->attributes['compound'] ?? false) !== true;
        if (!$read && !$reference && $value->kind !== 'object') {
            (new Strings($this->machine->context))->warning($instruction);
        }
        if ($instruction->operation === 'alias' && $access->remaining === []) {
            return (new Transfer($this->machine))->result($state, $instruction, new Term('throwable', 'Error'));
        }
        $location = $reference ? new Location($raw->literal) : $state->memory->allocate($value);
        if ($access->remaining !== []) {
            return $this->nested($caller, $instruction, $state, $access, $location);
        }
        $state->addresses[$instruction->operands[0]] = $location;
        unset($state->offsets[$instruction->operands[0]]);
        $checked = (new ReferenceAssignment($this->machine->context))->apply($caller, $instruction, $state);
        return $checked ?? (new Transfer($this->machine))->result($state, $instruction, (new MemoryStep($this->machine->context))->evaluate($caller, $instruction, $state));
    }

    /**
     * Continues a nested offset chain from the cell returned by offsetGet.
     * @param CallableGraph $caller Executing graph
     * @param Instruction $instruction Original operation
     * @param State $state Path after the implicit call
     * @param ProtocolAccess $access Remaining offset registers
     * @param Location $location Returned reference or temporary value storage
     * @return list<State> Nested transfer results
     */
    public function nested(CallableGraph $caller, Instruction $instruction, State $state, ProtocolAccess $access, Location $location): array
    {
        $base = $instruction->id . ':offset-result';
        $state->addresses[$base] = $location;
        $first = $access->remaining[0];
        $state->offsets[$first] = new Address($base, $state->offsets[$first]->key);
        return (new Transfer($this->machine))->apply($caller, $instruction, $state);
    }
}
