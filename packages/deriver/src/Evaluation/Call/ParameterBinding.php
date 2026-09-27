<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Parameter;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\ReferenceAssignment;
use Deriver\Memory\Location;
use Deriver\Memory\ReferenceConstraint;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;

/**
 * Evaluates defaults only on omission and connects reference cells at entry.
 * @visibility root
 */
final class ParameterBinding
{
    /**
     * @param Machine $machine Shared evaluator
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Binds one normalized parameter.
     * @param CallableGraph $callable Callee
     * @param Parameter $parameter Signature parameter
     * @param PassedArgument|null $actual Supplied actual, or omitted
     * @param State $state Partially bound entry
     * @param bool $symbolic Whether this is an arbitrary valid callable entry
     * @param bool $strict Calling-file scalar coercion mode
     * @return list<State> Bound paths
     */
    public function bind(CallableGraph $callable, Parameter $parameter, ?PassedArgument $actual, State $state, bool $symbolic, bool $strict = false): array
    {
        if ($symbolic) {
            $type = (new TypeBinding($this->machine->context))->declared($parameter->type, $callable, $state);
            $value = Term::parameter($parameter->name, $parameter->variadic ? 'array' : $type);
            $state->memory->write($state->local($parameter->name), $value);
            return [$state];
        }
        if ($actual === null && $parameter->default !== null) {
            return $this->defaultArgument($callable, $parameter, $parameter->default, $state, $strict);
        }
        if ($actual === null && !$parameter->variadic) {
            $state->completion = new Completion('throw', new Term('throwable', 'ArgumentCountError'));
            return [$state];
        }
        $actual ??= new PassedArgument(Term::array([]));
        if ($parameter->variadic) {
            return $this->variadic($parameter, $actual, $state, $strict, (new TypeBinding($this->machine->context))->declared($parameter->type, $callable, $state), $callable->source);
        }
        if ($parameter->byReference && ($actual->location === null || !$actual->writable)) {
            $state->completion = new Completion('throw', new Term('throwable', 'Error'));
            return [$state];
        }
        $type = (new TypeBinding($this->machine->context))->declared($parameter->type, $callable, $state);
        $types = $parameter->byReference ? (new ReferenceConstraint())->find($state->memory, $actual->location) : [];
        $check = (new ReferenceAssignment($this->machine->context))->check($this->value($parameter, $actual, $state), [$type, ...$types], $strict);
        (new TypeBinding($this->machine->context))->report($check, $callable->source, $state);
        if ($check->mustFail) {
            $state->completion = new Completion('throw', new Term('throwable', 'TypeError'));
            return [$state];
        }
        $exception = $state->fork();
        $exception->completion = new Completion('throw', $check->exception());
        if ($parameter->byReference) {
            $state->locals[$parameter->name] = new Location($state->memory->reference($actual->location));
            $state->memory->write($state->locals[$parameter->name], $check->value);
        } else {
            $state->memory->write($state->local($parameter->name), $check->value);
        }
        if ($check->mayFail) {
            return [$state, $exception];
        }
        return [$state];
    }
    /**
     * Checks every variadic element and retains reference addresses independently.
     * @param Parameter $parameter Variadic signature
     * @param PassedArgument $actual Normalized variadic arguments
     * @param State $state Partially bound entry
     * @param bool $strict Calling-file scalar mode
     * @param string|null $type Resolved declaration-relative type, when available
     * @param SourceRef|null $source Origin of the variadic binding
     * @return list<State> Bound normal and exceptional entries
     */
    public function variadic(Parameter $parameter, PassedArgument $actual, State $state, bool $strict, ?string $type = null, ?SourceRef $source = null): array
    {
        $entries = [];
        $mayFail = false;
        $failure = new Term('throwable', 'TypeError');
        foreach ($actual->elements as $key => $element) {
            $types = $parameter->byReference && $element->location !== null ? (new ReferenceConstraint())->find($state->memory, $element->location) : [];
            $value = $this->value($parameter, $element, $state);
            $check = (new ReferenceAssignment($this->machine->context))->check($value, [$type ?? $parameter->type, ...$types], $strict);
            if ($source !== null) {
                (new TypeBinding($this->machine->context))->report($check, $source, $state);
            }
            if ($check->mustFail || $parameter->byReference && ($element->location === null || !$element->writable)) {
                $state->completion = new Completion('throw', new Term('throwable', $check->mustFail ? 'TypeError' : 'Error'));
                return [$state];
            }
            $mayFail = $mayFail || $check->mayFail;
            $failure = $check->coercion === null ? $failure : $check->exception();
            if ($parameter->byReference) {
                $cell = $state->memory->reference($element->location);
                $state->memory->write(new Location($cell), $check->value);
                $entries[$key] = new Term('cell', $cell);
            } else {
                $entries[$key] = $check->value;
            }
        }
        $state->memory->write($state->local($parameter->name), Term::array($entries));
        if (!$mayFail) {
            return [$state];
        }
        $exception = $state->fork();
        $exception->completion = new Completion('throw', $failure);
        return [$state, $exception];
    }
    /**
     * Evaluates an omitted argument once and then applies ordinary binding rules.
     * @param CallableGraph $callable Declaring callable
     * @param Parameter $parameter Omitted parameter
     * @param CallableGraph $default Captured default expression
     * @param State $state Partially bound entry
     * @param bool $strict Calling-file scalar mode
     * @return list<State> Bound entries or initializer exceptions
     */
    public function defaultArgument(CallableGraph $callable, Parameter $parameter, CallableGraph $default, State $state, bool $strict): array
    {
        $entries = $this->machine->run($default, new State(clone $state->memory));
        $result = [];
        foreach ($entries as $entry) {
            $next = $state->fork();
            $next->memory = $entry->memory;
            if ($entry->completion->kind === 'throw') {
                $next->completion = $entry->completion;
            } else {
                $value = $entry->completion->value ?? Term::constant(null);
                if ($value->kind === 'omitted') {
                    $next->memory->write($next->local($parameter->name), $value);
                    $result[] = $next;
                    continue;
                }
                $location = $parameter->byReference ? $next->local($parameter->name) : null;
                if ($location !== null) {
                    $next->memory->write($location, $value);
                }
                array_push($result, ...$this->bind($callable, $parameter, new PassedArgument($value, location: $location), $next, false, $strict));
                continue;
            }
            $result[] = $next;
        }
        return $result;
    }

    /**
     * Reads reference actuals after preceding parameter coercions have updated shared cells.
     * @param Parameter $parameter Selected passing mode
     * @param PassedArgument $actual Frozen value or shared location
     * @param State $state Current entry memory
     * @return Term Current cell value or the original value snapshot
     */
    public function value(Parameter $parameter, PassedArgument $actual, State $state): Term
    {
        return $parameter->byReference && $actual->location !== null ? $state->memory->read($actual->location) : $actual->value;
    }
}
