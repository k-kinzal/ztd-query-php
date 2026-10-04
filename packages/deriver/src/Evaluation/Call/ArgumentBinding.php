<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\Model\NativeArguments;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Memory\Location;
use Deriver\Value\Term;

/**
 * Binds positional, named, variadic, unpacked, default, and reference arguments.
 * @visibility root
 */
final class ArgumentBinding
{
    /**
     * @param Machine $machine Shared evaluator for defaults
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Expands known unpacked arrays and preserves an explicit unknown remainder.
     * @param Instruction $instruction Call instruction
     * @param State $state Evaluated argument registers
     * @return list<PassedArgument> Actual arguments
     */
    public function actuals(Instruction $instruction, State $state): array
    {
        $arguments = [];
        foreach ($instruction->arguments as $argument) {
            $value = $state->value($argument->register);
            $location = $argument->location === null ? null : ($state->addresses[$argument->location] ?? null);
            if (!$argument->unpack) {
                $arguments[] = new PassedArgument($value, $argument->name, $location, writable: ($state->properties[$argument->location ?? '']->declaration->readonly ?? false) !== true);
                continue;
            }
            if ($value->kind !== 'array' || ($value->attributes['open'] ?? false) === true) {
                $unknown = $this->machine->context->frontier('UNSUPPORTED_LANGUAGE_FEATURE', $instruction->source, 'unknown-argument-unpack', [$value]);
                $arguments[] = new PassedArgument($unknown, '*');
                continue;
            }
            foreach ($value->operands as $key => $element) {
                $address = $location === null ? null : new Location($location->root, [...$location->path, $key]);
                $arguments[] = new PassedArgument($state->memory->dereference($element), is_string($key) ? $key : null, $address, writable: ($state->properties[$argument->location ?? '']->declaration->readonly ?? false) !== true);
            }
        }
        return $arguments;
    }

    /**
     * Binds a callable entry without conflating omitted and symbolic inputs.
     * @param CallableGraph $callable Callee signature
     * @param State $caller Calling state
     * @param list<PassedArgument> $arguments Evaluated actuals
     * @param Term|null $receiver Bound receiver
     * @param array<string, Term> $captures Captured lexical values
     * @param bool $symbolic Whether all valid entry parameters are symbolic
     * @param bool $strict Calling-file scalar coercion mode
     * @return list<State> Bound entries and argument errors
     */
    public function bind(CallableGraph $callable, State $caller, array $arguments, ?Term $receiver = null, array $captures = [], bool $symbolic = false, bool $strict = false): array
    {
        $entry = new State(clone $caller->memory);
        $entry->guard = $caller->guard;
        $entry->constraints = $caller->constraints;
        $entry->controls = $caller->controls;
        $entry->observed = $caller->observed;
        $entry->observedQueries = $caller->observedQueries;
        $receiverClass = $receiver->attributes['class'] ?? null;
        $entry->lateStaticClass = is_string($receiverClass) ? $receiverClass : $caller->lateStaticClass;
        foreach ($captures as $name => $value) {
            $entry->locals[$name] = $value->kind === 'cell' && is_string($value->literal) ? new Location($value->literal) : $entry->memory->allocate($value);
        }
        if ($receiver !== null) {
            $entry->memory->write($entry->local('this'), $receiver);
        }
        $normalized = (new ArgumentOrder())->normalize($callable, $arguments);
        if (is_string($normalized)) {
            $entry->completion = new Completion('throw', new Term('throwable', $normalized));
            return [$entry];
        }
        $states = [$entry];
        foreach ($callable->parameters as $parameter) {
            $next = [];
            foreach ($states as $state) {
                if ($state->completion->kind !== 'normal') {
                    $next[] = $state;
                    continue;
                }
                $actual = $normalized[$parameter->name] ?? null;
                if ($actual !== null && !$strict && isset($this->machine->context->nativeCalls[$callable])) {
                    $actual = (new NativeArguments($this->machine->context))->coerce($parameter, $actual, $callable->source);
                }
                array_push($next, ...(new ParameterBinding($this->machine))->bind($callable, $parameter, $actual, $state, $symbolic, $strict));
            }
            $states = $next;
        }
        return $states;
    }
}
