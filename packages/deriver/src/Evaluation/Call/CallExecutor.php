<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\Closure\Binding;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\IntrinsicTransfer;
use Deriver\Value\Term;

/**
 * Resolves and evaluates source bodies and semantic plans through one machine.
 * @visibility root
 */
final class CallExecutor
{
    /**
     * @param Machine $machine Shared abstract evaluator
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Dispatches invocation, allocation, clone, or a registered intrinsic.
     * @param CallableGraph $caller Calling graph
     * @param Instruction $instruction Invocation instruction
     * @param State $state State after argument evaluation
     * @return list<State> Return and exceptional states
     */
    public function instruction(CallableGraph $caller, Instruction $instruction, State $state): array
    {
        $arguments = (new ArgumentBinding($this->machine))->actuals($instruction, $state);
        if ($instruction->operation === 'new' || $instruction->operation === 'clone') {
            return (new Allocation($this->machine))->apply($caller, $instruction, $state, $arguments);
        }
        if ($instruction->operation === 'intrinsic') {
            return (new IntrinsicTransfer($this->machine))->apply($caller, $instruction, $state);
        }
        $target = $state->value($instruction->operands[0] ?? '');
        if ($instruction->operation === 'invoke') {
            return $this->invoke($target, $arguments, $state, $instruction, $caller);
        }
        return (new MethodInvocation($this->machine))->apply($caller, $instruction, $state, $arguments);
    }

    /**
     * Invokes a known function, closure, or first-class callable.
     * @param Term $target Callable value
     * @param list<PassedArgument> $arguments Evaluated actuals
     * @param State $state Calling path
     * @param Instruction $instruction Call site
     * @param CallableGraph $caller Calling graph
     * @return list<State> Result paths
     */
    public function invoke(Term $target, array $arguments, State $state, Instruction $instruction, CallableGraph $caller): array
    {
        if ($target->kind === 'closure' && ($target->attributes['first-class'] ?? false) === true) {
            return (new Binding($this->machine))->invoke($target, $arguments, $state, $instruction, $caller);
        }
        if ($target->kind === 'callable' && isset($target->operands[0])) {
            return $this->invoke($target->operands[0], $arguments, $state, $instruction, $caller);
        }
        $objectCall = $this->object($target, $arguments, $state, $instruction, $caller);
        if ($objectCall !== null) {
            return $objectCall;
        }
        if ($target->kind === 'closure' && is_string($target->literal)) {
            $captures = [];
            foreach ($target->operands as $name => $value) {
                if (is_string($name)) {
                    $captures[$name] = $value;
                }
            }
            $calledClass = $target->attributes['late-static'] ?? null;
            return $this->symbol($target->literal, $arguments, $state, $instruction, $target->operands['this'] ?? null, $captures, $caller->strict, is_string($calledClass) ? $calledClass : null);
        }
        if ($target->kind === 'constant' && is_string($target->literal)) {
            $symbol = (new CallResolution($this->machine->context))->name($target->literal, $instruction);
            if (strtolower(ltrim($symbol, '\\')) === 'extract' && $this->machine->context->program->callable($symbol) === null && !isset($this->machine->context->models->models['extract'])) {
                (new \Deriver\Evaluation\Havoc())->symbols($state, 'DYNAMIC_VARIABLE_WRITE');
                return (new UnknownCall($this->machine->context))->apply($state, $instruction, $arguments, null, 'DYNAMIC_VARIABLE_WRITE');
            }
            return $this->symbol($symbol, $arguments, $state, $instruction, strict: $caller->strict);
        }
        return (new UnknownCall($this->machine->context))->apply($state, $instruction, $arguments, $target, 'OPEN_DISPATCH');
    }

    /**
     * Selects source semantics or an explicitly applicable model.
     * @param string $symbol Candidate callable
     * @param list<PassedArgument> $arguments Actual arguments
     * @param State $state Caller state
     * @param Instruction $instruction Call site
     * @param Term|null $receiver Receiver identity
     * @param array<string, Term> $captures Lexical capture bindings
     * @param bool $strict Calling-file scalar coercion mode
     * @param string|null $calledClass Explicit late static binding for this invocation
     * @return list<State> Completed invocations
     */
    public function symbol(string $symbol, array $arguments, State $state, Instruction $instruction, ?Term $receiver = null, array $captures = [], bool $strict = false, ?string $calledClass = null): array
    {
        $context = $this->machine->context;
        $receiverType = $calledClass ?? $receiver?->attributes['class'] ?? $receiver?->attributes['type'] ?? '';
        $body = (new CallResolution($context))->body($symbol, $instruction, $arguments, $state, is_string($receiverType) ? $receiverType : '');
        if ($body === null) {
            return (new UnknownCall($context))->apply($state, $instruction, $arguments, $receiver, $context->callFailures[$instruction->id] ?? 'MISSING_CALL_MODEL');
        }
        if ($body->className !== '' && $body->static) {
            $receiver = null;
        }
        $binding = $state->fork();
        $binding->lateStaticClass = $calledClass ?? $state->lateStaticClass;
        $result = [];
        foreach ((new ArgumentBinding($this->machine))->bind($body, $binding, $arguments, $receiver, $captures, strict: $strict) as $entry) {
            $context->summaries->history[] = $instruction->id;
            $exits = $entry->completion->kind === 'normal' ? $this->machine->run($body, $entry) : [$entry];
            array_pop($context->summaries->history);
            foreach ($exits as $exit) {
                $next = $state->fork();
                $next->memory = $exit->memory;
                $next->observed = $exit->observed;
                $next->evidence = array_values(array_unique([...$state->evidence, ...$exit->evidence]));
                $next->controls = array_values(array_unique([...$state->controls, ...$exit->controls]));
                $next->guard = $exit->guard;
                $next->constraints = $exit->constraints;
                $next->registers[$instruction->result] = $exit->completion->value ?? Term::constant(null);
                $next->completion = $exit->completion->kind === 'return' ? new Completion() : $exit->completion;
                $result[] = $next;
            }
        }
        return $result;
    }

    /**
     * Resolves evaluated object and array callables before ordinary function names.
     * @param Term $target Evaluated callable
     * @param list<PassedArgument> $arguments Actual arguments
     * @param State $state Caller state
     * @param Instruction $instruction Invocation site
     * @param CallableGraph $caller Lexical caller
     * @return list<State>|null Object invocation or no matching callable form
     */
    public function object(Term $target, array $arguments, State $state, Instruction $instruction, CallableGraph $caller): ?array
    {
        if ($target->kind === 'constant' && is_string($target->literal) && str_contains($target->literal, '::')) {
            [$class, $method] = explode('::', $target->literal, 2);
            $target = new Term('array', operands: [Term::constant($class), Term::constant($method)]);
        }
        if ((new CallableCheck($this->machine->context))->pair($target)) {
            $receiver = $state->memory->dereference($target->operands[0]);
            $method = $state->memory->dereference($target->operands[1]);
            $state->registers[$instruction->id . ':receiver'] = $receiver;
            $state->registers[$instruction->id . ':method'] = $method;
            $call = new Instruction($instruction->id, $receiver->kind === 'constant' ? 'invoke-static' : 'invoke-method', $instruction->source, $instruction->result, [$instruction->id . ':receiver', $instruction->id . ':method'], attributes: $target->attributes);
            return (new MethodInvocation($this->machine))->apply($caller, $call, $state, $arguments);
        }
        if ($target->kind === 'object') {
            $class = $target->attributes['class'] ?? '';
            if (is_string($class)) {
                $symbol = (new Dispatch($this->machine->context->program))->method($class, '__invoke');
                if ($symbol !== null) {
                    return $this->symbol($symbol, $arguments, $state, $instruction, $target, strict: $caller->strict);
                }
            }
        }
        return null;
    }
}
