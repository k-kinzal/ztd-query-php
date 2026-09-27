<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\UnknownCall;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Value\Term;

/**
 * Connects finite collection callbacks to the ordinary call and exception evaluator.
 * @visibility root
 */
final class CollectionCalls
{
    /**
     * @param Machine $machine Shared evaluator
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Applies a synchronous map, filter, or reduce with ordered callback effects.
     * @param CallableGraph $caller Model graph
     * @param Instruction $instruction Intrinsic call
     * @param State $state Initial collection state
     * @param list<Term> $values Bound arguments
     * @return list<State> Normal and exceptional callback alternatives
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state, array $values): array
    {
        $map = $instruction->name === 'array_map';
        $array = $values[$map ? 1 : 0] ?? Term::constant(null);
        $callback = $values[$map ? 0 : 1] ?? Term::constant(null);
        if (!$this->knownCallback($callback) || !$this->knownMode($instruction->name, $values)) {
            return (new UnknownCall($this->machine->context))->apply($state, $instruction, [new PassedArgument($array)], $callback, 'UNSUPPORTED_MODEL_CASE');
        }
        if ($array->kind !== 'array' || ($array->attributes['open'] ?? false) === true) {
            return (new UnknownCall($this->machine->context))->apply($state, $instruction, [new PassedArgument($array)], $callback, 'UNSUPPORTED_MODEL_CASE');
        }
        if ($map && ($values[2]->operands ?? []) !== []) {
            return (new UnknownCall($this->machine->context))->apply($state, $instruction, [new PassedArgument($array)], $callback, 'UNSUPPORTED_MODEL_CASE');
        }
        $resultKey = $instruction->id . ':collection';
        $state->registers[$resultKey] = $instruction->name === 'array_reduce' ? ($values[2] ?? Term::constant(null)) : Term::array([]);
        $paths = [$state];
        foreach ($array->operands as $key => $element) {
            $next = [];
            foreach ($paths as $path) {
                if ($path->completion->kind !== 'normal') {
                    $next[] = $path;
                    continue;
                }
                array_push($next, ...$this->element($caller, $instruction, $path, $callback, $element, $key, $resultKey, $values));
            }
            $paths = (new StateJoin($this->machine->context))->limit($next, $caller);
        }
        return $this->complete($instruction, $paths, $values, $resultKey);
    }

    /**
     * Publishes completed collections without overwriting exceptional callback exits.
     * @param Instruction $instruction Collection result destination
     * @param list<State> $paths Completed callback paths
     * @param list<Term> $values Bound inputs whose confidentiality the result inherits
     * @param string $resultKey Private accumulator register
     * @return list<State> Published normal results and unchanged exceptional completions
     */
    public function complete(Instruction $instruction, array $paths, array $values, string $resultKey): array
    {
        $secret = array_filter($values, static fn (Term $value): bool => $value->isSecret()) !== [];
        foreach ($paths as $path) {
            if ($path->completion->kind !== 'normal') {
                continue;
            }
            $result = $path->registers[$resultKey];
            $path->registers[$instruction->result] = $secret ? new Term($result->kind, $result->literal, $result->operands, $result->attributes, true) : $result;
            unset($path->registers[$resultKey]);
        }
        return $paths;
    }

    /**
     * Calls and accumulates one collection element.
     * @param CallableGraph $caller Model graph
     * @param Instruction $instruction Intrinsic site
     * @param State $state Path before callback
     * @param Term $callback Callback identity
     * @param Term $element Current element
     * @param int|string $key Original key
     * @param string $resultKey Accumulator register
     * @param list<Term> $values Bound arguments
     * @return list<State> Accumulated alternatives
     */
    public function element(CallableGraph $caller, Instruction $instruction, State $state, Term $callback, Term $element, int|string $key, string $resultKey, array $values): array
    {
        $name = $instruction->name;
        $secret = $values[$name === 'array_map' ? 1 : 0]->secret ?? false;
        $element = $secret ? new Term($element->kind, $element->literal, $element->operands, $element->attributes, true) : $element;
        if ($callback->kind === 'constant' && $callback->literal === null) {
            $state->registers[$instruction->result] = $element;
            $paths = [$state];
        } else {
            $arguments = [new PassedArgument($element)];
            if ($name === 'array_reduce') {
                array_unshift($arguments, new PassedArgument($state->registers[$resultKey]));
            } elseif ($name === 'array_filter' && ($values[2]->literal ?? 0) === 2) {
                $arguments = [new PassedArgument(Term::constant($key, $secret))];
            } elseif ($name === 'array_filter' && ($values[2]->literal ?? 0) === 1) {
                $arguments[] = new PassedArgument(Term::constant($key, $secret));
            }
            $paths = (new CallExecutor($this->machine))->invoke($callback, $arguments, $state, $instruction, $caller);
        }
        $result = [];
        foreach ($paths as $path) {
            $value = $name === 'array_map' && $callback->kind === 'constant' && $callback->literal === null ? $element : $path->value($instruction->result);
            if ($path->completion->kind === 'throw') {
                $result[] = $path;
            } elseif ($name === 'array_reduce') {
                $path->registers[$resultKey] = $value;
                $result[] = $path;
            } elseif ($name === 'array_map') {
                $entries = $path->registers[$resultKey]->operands;
                $entries[$key] = $value;
                $path->registers[$resultKey] = Term::array($entries);
                $result[] = $path;
            } else {
                array_push($result, ...$this->filter($path, $value, $element, $key, $resultKey));
            }
        }
        return $result;
    }

    /**
     * Preserves filter membership as a correlated branch.
     * @param State $state Post-callback state
     * @param Term $predicate Callback result
     * @param Term $element Original element
     * @param int|string $key Original key
     * @param string $resultKey Accumulator register
     * @return list<State> Filtered alternatives
     */
    public function filter(State $state, Term $predicate, Term $element, int|string $key, string $resultKey): array
    {
        $result = [];
        foreach ([true, false] as $truth) {
            $path = $state->fork();
            if (!(new Constraints($this->machine->context))->assume($path, $predicate, $truth)) {
                continue;
            }
            $entries = $path->registers[$resultKey]->operands;
            if ($truth) {
                $entries[$key] = $element;
            }
            $path->registers[$resultKey] = new Term('array', operands: $entries, attributes: ['open' => false], secret: $path->registers[$resultKey]->secret || $predicate->isSecret());
            $result[] = $path;
        }
        return $result;
    }

    /**
     * Recognizes the nullable callback form and callbacks whose validation needs no opaque protocol.
     * @param Term $callback Bound callback
     * @return bool Whether collection evaluation can use the ordinary callable rules
     */
    public function knownCallback(Term $callback): bool
    {
        return $callback->kind === 'constant' && $callback->literal === null || (new CallableCheck($this->machine->context))->evaluate($callback)->kind === 'constant';
    }
    /**
     * Requires a known filter argument mode before selecting callback arguments.
     * @param string $function Standard collection function
     * @param list<Term> $values Bound arguments
     * @return bool Whether argument selection is determined
     */
    public function knownMode(string $function, array $values): bool
    {
        return $function !== 'array_filter' || ($values[2]->kind ?? 'constant') === 'constant';
    }
}
