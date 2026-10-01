<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\ControlFlow\CallableGraph;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\State;
use Deriver\Value\Term;

/**
 * Safely merges excess partitions rather than retaining only the first candidates.
 * @visibility root
 */
final class StateJoin
{
    /**
     * @param Context $context Query context
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Bounds partitions at each pending program point.
     * @param list<State> $states Pending paths
     * @param CallableGraph $callable Current callable
     * @return list<State> Bounded pending paths
     */
    public function limit(array $states, CallableGraph $callable): array
    {
        $states = $this->orders($states, $callable);
        $groups = [];
        foreach ($states as $state) {
            $groups[$state->block][] = $state;
        }
        $result = [];
        foreach ($groups as $group) {
            if (count($group) > $this->context->query->budget()->partitions) {
                $this->context->frontier('BUDGET_EXCEEDED', $callable->source, 'partition-limit');
                $this->context->frontier('CORRELATION_RELAXED', $callable->source, 'partition-limit');
                array_push($result, ...array_slice($group, 0, max(0, $this->context->query->budget()->partitions - 1)));
                $residual = $group[0]->fork();
                foreach ($group as $state) {
                    $residual->observed = $residual->observed && $state->observed;
                    $residual->memory->cells += $state->memory->cells;
                    $residual->locals += $state->locals;
                }
                $residual->guard = [];
                array_push($result, ...(new ResidualPaths($this->context))->seal($residual, $callable->source, 'partition-limit'));
            } else {
                array_push($result, ...$group);
            }
        }
        return $result;
    }

    /**
     * Coalesces operand orders only when their complete resulting states are identical.
     * @param list<State> $states States waiting at instruction blocks
     * @param CallableGraph $callable Graph containing explicit operand-order joins
     * @return list<State> Distinct semantic states
     */
    public function orders(array $states, CallableGraph $callable): array
    {
        $result = [];
        $seen = [];
        $counts = array_count_values(array_column($states, 'block'));
        foreach ($states as $state) {
            $register = $callable->blocks[$state->block]->instructions[0]->attributes['evaluation-order'] ?? null;
            if (!is_string($register) || $counts[$state->block] < 2) {
                $result[] = $state;
                continue;
            }
            $key = $this->orderKey($state, $register);
            if (isset($seen[$key])) {
                $kept = $result[$seen[$key]];
                unset($kept->guard[(new \Deriver\Value\Identity())->key($kept->value($register))]);
            } else {
                $seen[$key] = count($result);
                $result[] = $state;
            }
        }
        return $result;
    }

    /**
     * Normalizes bookkeeping while retaining all values, storage, aliases, and source guards.
     * @param State $state Candidate completed operand order
     * @param string $register Hidden order predicate
     * @return string Exact equality key, excluding only the internal order choice
     */
    public function orderKey(State $state, string $register): string
    {
        $copy = $state->fork();
        unset($copy->guard[(new \Deriver\Value\Identity())->key($copy->value($register))]);
        $copy->previous = -1;
        ksort($copy->registers);
        ksort($copy->producers);
        ksort($copy->addresses);
        ksort($copy->offsets);
        ksort($copy->callTargets);
        ksort($copy->properties);
        ksort($copy->locals);
        ksort($copy->guard);
        ksort($copy->constraints);
        $copy->evidence = array_values(array_unique($copy->evidence));
        sort($copy->evidence);
        sort($copy->controls);
        ksort($copy->memory->cells);
        ksort($copy->memory->writers);
        ksort($copy->memory->versions);
        return $this->fingerprint($copy);
    }


    /**
     * Encodes structural equality without PHP object-sharing identifiers or scalar coercion.
     * @param State $state Normalized, acyclic evaluator state
     * @return string Typed structural fingerprint
     */
    public function fingerprint(State $state): string
    {
        $encode = static function ($value) use (&$encode): string {
            if (is_object($value)) {
                return hash('sha256', get_class($value) . ':' . $encode(get_object_vars($value)));
            }
            if (is_array($value)) {
                return hash('sha256', serialize(array_map($encode, $value)));
            }
            return hash('sha256', serialize($value));
        };
        return $encode($state);
    }

    /**
     * Bounds completed paths while retaining distinct normal and exceptional effects.
     * @param list<State> $states Completed paths
     * @param CallableGraph $callable Owning graph
     * @return list<State> Inclusive completed paths
     */
    public function completed(array $states, CallableGraph $callable): array
    {
        $groups = [];
        foreach ($states as $state) {
            $groups[$state->completion->kind][] = $state;
        }
        $result = [];
        foreach ($groups as $kind => $group) {
            if (count($group) <= $this->context->query->budget()->partitions) {
                array_push($result, ...$group);
                continue;
            }
            $residual = $group[0]->fork();
            foreach ($group as $state) {
                $residual->observed = $residual->observed && $state->observed;
                $residual->memory->cells += $state->memory->cells;
                $residual->locals += $state->locals;
            }
            (new Havoc())->all($residual, 'BUDGET_EXCEEDED');
            $value = $kind === 'throw' ? new Term('throwable', 'Throwable', attributes: ['uncertain' => true]) : Term::opaque('BUDGET_EXCEEDED');
            $residual->completion = new Completion($kind, $value);
            $residual->guard = [];
            $residual->constraints = [];
            $result[] = $residual;
            (new ObservationLimit($this->context))->boundary($callable->source);
        }
        return $result;
    }
}
