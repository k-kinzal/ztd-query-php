<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\ControlFlow\CallableGraph;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\Havoc;
use Deriver\Evaluation\State;
use Deriver\Value\Identity;
use Deriver\Value\Term;
use WeakMap;

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
        $result = [];
        foreach ($states as $state) {
            if ($state->completion->kind === 'normal') {
                $groups[$state->block][] = $state;
            } else {
                $result[] = $state;
            }
        }
        $keep = max(0, $this->context->query->budget()->partitions - 1);
        foreach ($groups as $group) {
            if (count($group) <= $this->context->query->budget()->partitions) {
                array_push($result, ...$group);
                continue;
            }
            $this->context->frontier('BUDGET_EXCEEDED', $callable->source, 'partition-limit');
            $this->context->frontier('CORRELATION_RELAXED', $callable->source, 'partition-limit');
            array_push($result, ...array_slice($group, 0, $keep));
            $clusters = $this->clusters(array_slice($group, $keep));
            array_push($result, ...array_slice($clusters, 0, $this->context->query->budget()->partitions));
            $sealed = array_slice($clusters, $this->context->query->budget()->partitions);
            if ($sealed !== []) {
                array_push($result, ...$this->seal($sealed, $callable));
            }
        }
        return $result;
    }

    /**
     * Joins each path into the first compatible cluster; paths with another structure keep executing as their own cluster.
     * @param list<State> $states Excess paths waiting at one block
     * @return list<State> Joined clusters in discovery order
     */
    public function clusters(array $states): array
    {
        $join = new PathJoin($this->context);
        $clusters = [];
        $structures = [];
        foreach ($states as $state) {
            $structure = $join->structure($state);
            foreach (array_keys($structures, $structure, true) as $index) {
                $joined = $join->join([$clusters[$index], $state], shared: true);
                if ($joined !== null) {
                    $clusters[$index] = $joined;
                    continue 2;
                }
            }
            $clusters[] = $state;
            $structures[] = $structure;
        }
        return array_values($clusters);
    }

    /**
     * Seals paths that cannot be joined, keeping their storage roots and aliases in one residual.
     * @param non-empty-list<State> $states Paths waiting at one block
     * @param CallableGraph $callable Current callable
     * @return list<State> Normal and exceptional residuals
     */
    public function seal(array $states, CallableGraph $callable): array
    {
        $residual = $states[0]->fork();
        foreach ($states as $state) {
            $residual->observed = $residual->observed && $state->observed;
            $residual->memory->cells += $state->memory->cells;
            $residual->locals += $state->locals;
        }
        $residual->guard = [];
        return (new ResidualPaths($this->context))->seal($residual, $callable->source, 'partition-limit');
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
        $candidates = [];
        $counts = array_count_values(array_column($states, 'block'));
        $identity = $this->context->identity;
        foreach ($states as $state) {
            $register = $callable->blocks[$state->block]->instructions[0]->attributes['evaluation-order'] ?? null;
            if (!is_string($register) || $counts[$state->block] < 2) {
                $result[] = $state;
                continue;
            }
            $candidate = $this->candidateKey($state, $register, $identity);
            if (!isset($candidates[$candidate])) {
                $candidates[$candidate] = count($result);
                $result[] = $state;
                continue;
            }
            if (is_int($candidates[$candidate])) {
                $seen[$this->orderKey($result[$candidates[$candidate]], $register, $identity)] = $candidates[$candidate];
                $candidates[$candidate] = true;
            }
            $key = $this->orderKey($state, $register, $identity);
            if (isset($seen[$key])) {
                $kept = $result[$seen[$key]];
                unset($kept->guard[$identity->key($kept->value($register))]);
            } else {
                $seen[$key] = count($result);
                $result[] = $state;
            }
        }
        return $result;
    }

    /**
     * Summarizes the guard, registers, and storage that every coalesced operand order must share.
     * Different summaries prove different states, so complete fingerprints are only computed for collisions.
     * @param State $state Candidate completed operand order
     * @param string $register Hidden order predicate
     * @param Identity $identity Term keys shared by the compared states
     * @return string Necessary condition for equal order keys
     */
    public function candidateKey(State $state, string $register, Identity $identity): string
    {
        $guard = $state->guard;
        unset($guard[$identity->key($state->value($register))]);
        ksort($guard);
        $registers = array_map($identity->key(...), $state->registers);
        ksort($registers);
        $cells = array_map($identity->key(...), $state->memory->cells);
        ksort($cells);
        return hash('sha256', serialize([$state->block, $guard, $state->locals, $registers, $cells]));
    }

    /**
     * Normalizes bookkeeping while retaining all values, storage, aliases, and source guards.
     * @param State $state Candidate completed operand order
     * @param string $register Hidden order predicate
     * @param Identity $identity Term keys shared by the compared states
     * @return string Exact equality key, excluding only the internal order choice
     */
    public function orderKey(State $state, string $register, Identity $identity = new Identity()): string
    {
        $copy = $state->fork();
        unset($copy->guard[$identity->key($copy->value($register))]);
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
        return $this->fingerprint($copy, $identity);
    }

    /**
     * Encodes structural equality without PHP object-sharing identifiers or scalar coercion.
     * @param State $state Normalized, acyclic evaluator state
     * @param Identity $identity Structural term keys
     * @return string Typed structural fingerprint
     */
    public function fingerprint(State $state, Identity $identity = new Identity()): string
    {
        return $this->encode($state, $identity);
    }

    /**
     * Encodes an object structurally; shared terms and objects are encoded once, so the cost follows the graph rather than its tree expansion.
     * @param object $value Acyclic evaluator value
     * @param Identity $identity Structural term keys
     * @return string Typed structural fingerprint
     */
    public function encode(object $value, Identity $identity): string
    {
        /** @var WeakMap<object, string> $encoded */
        $encoded = new WeakMap();
        $encode = static function ($value) use (&$encode, $identity, $encoded): string {
            if ($value instanceof Term) {
                return $identity->key($value);
            }
            if (is_object($value)) {
                return $encoded[$value] ??= hash('sha256', get_class($value) . ':' . $encode(get_object_vars($value)));
            }
            if (is_array($value)) {
                return hash('sha256', serialize(array_map($encode, $value)));
            }
            return hash('sha256', serialize($value));
        };
        return $encode($value);
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
            $kept = array_slice($group, 0, $this->context->query->budget()->partitions - 1);
            $excess = array_slice($group, $this->context->query->budget()->partitions - 1);
            $joined = $kind === 'return' && $excess !== [] ? (new PathJoin($this->context))->join($excess, true) : null;
            if ($joined !== null) {
                array_push($result, ...$kept, ...[$joined]);
                $this->context->frontier('BUDGET_EXCEEDED', $callable->source, 'partition-limit');
                $this->context->frontier('CORRELATION_RELAXED', $callable->source, 'partition-limit');
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
