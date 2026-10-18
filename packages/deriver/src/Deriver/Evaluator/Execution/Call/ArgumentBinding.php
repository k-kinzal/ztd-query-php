<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call;

use Deriver\Deriver\Evaluator\Execution\Call\Contract\NativeArguments;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Binds positional, named, variadic, unpacked, default, and reference arguments.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 * @visibility Deriver\Deriver\Execution
 */
final class ArgumentBinding
{
    /**
     * @param Evaluator $machine Shared evaluator for defaults
     */
    public function __construct(public readonly Evaluator $machine)
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
            if ($value->kind !== \Deriver\Deriver\Evaluator\TermKind::Array || ($value->attributes['open'] ?? false) === true) {
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
            $entry->locals[$name] = $value->kind === \Deriver\Deriver\Evaluator\TermKind::Cell && is_string($value->literal) ? new Location($value->literal) : $entry->memory->allocate($value);
        }
        if ($receiver !== null) {
            $entry->memory->write($entry->local('this'), $receiver);
        }
        $normalized = (new ArgumentOrder())->normalize($callable, $arguments);
        if (is_string($normalized)) {
            $entry->completion = new Completion(\Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw, new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, $normalized));
            return [$entry];
        }
        $states = [$entry];
        foreach ($callable->parameters as $parameter) {
            $next = [];
            foreach ($states as $state) {
                if ($state->completion->kind !== \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
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
