<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver\Call\Model;

use Deriver\Internal\IR\Instruction;
use Deriver\Internal\Model\PlanCompiler;
use Deriver\Internal\Solver\Call\ArgumentOrder;
use Deriver\Internal\Solver\Call\PassedArgument;
use Deriver\Internal\Solver\State;
use Deriver\Model\Binding\ArgumentBindings;
use Deriver\Model\Binding\BoundArgument;
use Deriver\Model\Binding\LocationRef;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Value\Term;

/**
 * Exposes evaluated call metadata without allowing models to access mutable solver state.
 * @visibility root
 */
final class Inputs
{
    /**
     * Uses ordinary name, variadic, and unpack normalization for model metadata.
     * @param ModelDescriptor $descriptor Candidate model signature
     * @param Instruction $instruction Invocation provenance
     * @param list<PassedArgument> $arguments Evaluated arguments
     * @param State $state State after argument evaluation
     * @return ArgumentBindings Immutable metadata; core binding still enforces types and defaults
     */
    public function bindings(ModelDescriptor $descriptor, Instruction $instruction, array $arguments, State $state): ArgumentBindings
    {
        $signature = (new PlanCompiler($instruction->source))->compile($descriptor, new SemanticPlan([]));
        $normalized = (new ArgumentOrder())->normalize($signature, $arguments);
        if (is_string($normalized)) {
            return new ArgumentBindings(evaluated: true, error: $normalized);
        }
        $result = [];
        $remainder = null;
        foreach ($arguments as $argument) {
            if ($argument->name === '*') {
                $remainder = $argument->value;
            }
        }
        foreach ($descriptor->signature->parameters as $parameter) {
            $actual = $normalized[$parameter->name] ?? null;
            $value = $this->value($parameter, $actual, $state);
            $result[$parameter->name] = new BoundArgument($parameter->name, $state->memory->materialize($value), $parameter->byReference ? LocationRef::parameter($parameter->name) : null, $remainder === null ? $actual !== null && (!$parameter->variadic || $actual->elements !== []) : null, $parameter->variadic);
        }
        return new ArgumentBindings($result, true, remainder: $remainder);
    }
    /**
     * Observes current reference actuals while preserving frozen by-value arguments.
     * @param \Deriver\Model\Signature\Parameter $parameter Selected passing mode
     * @param PassedArgument|null $actual Normalized argument, or omitted
     * @param State $state State after all argument effects
     * @return Term Abstract input before parameter coercion
     */
    public function value(\Deriver\Model\Signature\Parameter $parameter, ?PassedArgument $actual, State $state): Term
    {
        if ($actual === null) {
            return $parameter->default ?? ($parameter->variadic ? Term::array([]) : new Term('omitted'));
        }
        if (!$parameter->byReference) {
            return $actual->value;
        }
        if ($parameter->variadic) {
            $entries = [];
            foreach ($actual->elements as $key => $element) {
                $entries[$key] = $element->location === null ? $element->value : $state->memory->read($element->location);
            }
            return Term::array($entries);
        }
        return $actual->location === null ? $actual->value : $state->memory->read($actual->location);
    }
}
