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
