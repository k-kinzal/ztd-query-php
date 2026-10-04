<?php

declare(strict_types=1);

namespace Deriver\Evaluation;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\Query\Query;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;

/**
 * Collects separate observation results from one execution with one shared budget.
 * @visibility root
 */
final class BatchObservations
{
    /**
     * @var list<Context> Observation-only contexts; they share runtime resources and never execute instructions
     */
    public array $contexts = [];
    /**
     * @var array<string, list<int>> Observation indices by callable, phase, and instruction or register
     */
    private array $sites = [];
    /**
     * @var array<string, array<string, true>> Union of requested registers and points by callable
     */
    public array $demands = [];

    /**
     * @param non-empty-list<Query> $queries Validated observations with identical scope and budget
     * @param Context $execution Shared forward execution
     */
    public function __construct(public readonly array $queries, public readonly Context $execution)
    {
        foreach ($queries as $index => $query) {
            $this->contexts[] = new Context($execution->program, $query, $execution->configuration, $execution->models, $execution->shared, $execution->resources);
            if ($query instanceof ValueQuery) {
                $owner = (new CallableIdentity())->key($query->expression->callable);
                $key = $owner . ':after:' . $query->expression->register;
                $this->demands[$owner]['register:' . $query->expression->register] = true;
            } elseif ($query instanceof StateQuery || $query instanceof TupleQuery) {
                $owner = (new CallableIdentity())->key($query->point->callable);
                $key = $owner . ':' . $query->point->phase . ':' . $query->point->instruction;
                $this->demands[$owner]['instruction:' . $query->point->instruction] = true;
                foreach ($query instanceof TupleQuery ? $query->values : [] as $value) {
                    $this->demands[$owner]['register:' . $value->register] = true;
                }
            } else {
                continue;
            }
            $this->sites[$key][] = $index;
        }
    }

    /**
     * Observes each requested point without re-running its execution prefix.
     * @param CallableGraph $callable Current graph
     * @param Instruction $instruction Current transfer
     * @param State $state Current path, retaining per-observation reachability
     * @param string $phase before, invocation, or after
     */
    public function instruction(CallableGraph $callable, Instruction $instruction, State $state, string $phase): void
    {
        $prefix = (new CallableIdentity())->key($callable->symbol) . ':' . $phase . ':';
        $indices = [...$this->sites[$prefix . $instruction->result] ?? [], ...$this->sites[$prefix . $instruction->id] ?? []];
        foreach ($indices as $index) {
            $context = $this->synchronize($index);
            $state->observed = isset($state->observedQueries[$index]);
            (new ObservationCollector($context))->instruction($callable, $instruction, $state, $phase, false);
            if ($state->observed) {
                $state->observedQueries[$index] = true;
            }
            $this->execution->frontiers = $context->frontiers;
            [$context->frontiers, $context->evidence, $context->graphs] = [[], [], []];
        }
        $state->observed = count($state->observedQueries) === count($this->queries);
        if ($indices !== [] && $state->observed && !(new ObservationCollector($this->execution))->repeated($callable, $state->block)) {
            $state->completion = new Completion('observed');
        }
    }

    /**
     * Keeps exceptions before each individual observation, even if another observation already succeeded.
     * @param CallableGraph $callable Completing graph
     * @param State $state Completed path
     */
    public function completion(CallableGraph $callable, State $state): void
    {
        $observed = $state->observed;
        foreach ($this->contexts as $index => $_) {
            $context = $this->synchronize($index);
            $state->observed = isset($state->observedQueries[$index]);
            (new ObservationCollector($context))->completion($callable, $state);
            $this->execution->frontiers = $context->frontiers;
            [$context->frontiers, $context->evidence, $context->graphs] = [[], [], []];
        }
        $state->observed = $observed;
    }

    /**
     * All observations report the batch's work and frontiers; their alternatives remain separate.
     * @param int $index Observation index
     * @return Context Observation result context
     */
    public function synchronize(int $index): Context
    {
        $context = $this->contexts[$index];
        foreach (['frontiers', 'evidence', 'assumptions', 'active', 'entrySymbol', 'stopReason', 'sealed', 'transfers', 'graphs'] as $field) {
            $context->$field = $this->execution->$field;
        }
        $context->summaries->hits = $this->execution->summaries->hits;
        return $context;
    }
}
