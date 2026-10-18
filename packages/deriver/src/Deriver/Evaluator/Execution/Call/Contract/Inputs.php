<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call\Contract;

use Deriver\Deriver\Evaluator\Call\ArgumentBindings;
use Deriver\Deriver\Evaluator\Call\BoundArgument;
use Deriver\Deriver\Evaluator\Call\CallDescriptor;
use Deriver\Deriver\Evaluator\Call\Plan\Location\LocationRef;
use Deriver\Deriver\Evaluator\Call\Plan\PlanCompiler;
use Deriver\Deriver\Evaluator\Call\Plan\SemanticPlan;
use Deriver\Deriver\Evaluator\Declaration\Parameter;
use Deriver\Deriver\Evaluator\Execution\Call\ArgumentOrder;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Exposes evaluated call metadata without allowing evaluators to access mutable solver state.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Inputs
{
    /**
     * Uses ordinary name, variadic, and unpack normalization for evaluator metadata.
     * @param CallDescriptor $descriptor Candidate evaluator signature
     * @param Instruction $instruction Invocation provenance
     * @param list<PassedArgument> $arguments Evaluated arguments
     * @param State $state \Deriver\Deriver\Evaluator\Execution\State after argument evaluation
     * @return ArgumentBindings Immutable metadata; core binding still enforces types and defaults
     */
    public function bindings(CallDescriptor $descriptor, Instruction $instruction, array $arguments, State $state): ArgumentBindings
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
     * @param Parameter $parameter Selected passing mode
     * @param PassedArgument|null $actual Normalized argument, or omitted
     * @param State $state \Deriver\Deriver\Evaluator\Execution\State after all argument effects
     * @return Term Abstract input before parameter coercion
     */
    public function value(Parameter $parameter, ?PassedArgument $actual, State $state): Term
    {
        if ($actual === null) {
            return $parameter->default ?? ($parameter->variadic ? Term::array([]) : new Term(\Deriver\Deriver\Evaluator\TermKind::Omitted));
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
