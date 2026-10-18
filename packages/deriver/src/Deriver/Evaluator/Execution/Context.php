<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution;

use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Evidence\Frontier;
use Deriver\Deriver\Evaluator\Execution\Completion\Alternative;
use Deriver\Deriver\Evaluator\Execution\Completion\Exceptional;
use Deriver\Deriver\Evaluator\Execution\Demand\Table;
use Deriver\Deriver\Evaluator\Execution\Trace\Derivation;
use Deriver\Deriver\Evaluator\Request\Query;
use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Lowerer\Graph\Program;
use Deriver\Deriver\Sources\SourceRef;
use WeakMap;

/**
 * Query-local work budget, dependency evidence, observations, and evaluator registry.
 * @visibility namespace
 * @visibility Deriver\Deriver\Execution
 */
final class Context
{
    /**
     * Dynamic call dependencies and completed specializations for this query.
     */
    public readonly Table $summaries;
    /**
     * Structural term keys memoized across this query's states, which share term subgraphs.
     */
    public readonly Identity $identity;
    /**
     * Explicit observations sharing this execution and its exploration budget.
     */
    public ?BatchObservations $batch = null;

    /**
     * @var array<string, true> Graphs evaluated by this query.
     */
    public array $graphs = [];
    /**
     * @var WeakMap<\Deriver\Deriver\Lowerer\Graph\CallableGraph, array<string, true>> Backward dependencies per immutable graph.
     */
    public WeakMap $demands;
    /**
     * @var WeakMap<\Deriver\Deriver\Lowerer\Graph\CallableGraph, true> Graphs supplied by built-in PHP evaluators.
     */
    public WeakMap $nativeCalls;
    /**
     * @var WeakMap<\Deriver\Deriver\Evaluator\Term, bool> Immutable value graphs proven free of storage identities
     */
    public WeakMap $plainValues;
    /**
     * @var array<string, string> Unresolved call reasons by instruction.
     */
    public array $callFailures = [];

    /**
     * Number of logical instruction transfers.
     */
    public int $transfers = 0;
    /**
     * Whether a structural budget forced an unexplored residual.
     */
    public bool $sealed = false;
    /**
     * @var array<string, Frontier> Root causes, deduplicated by source and code.
     */
    public array $frontiers = [];
    /**
     * @var array<string, Derivation> Explanation graph.
     */
    public array $evidence = [];
    /**
     * @var list<Alternative> Correlated normal observations.
     */
    public array $normal = [];
    /**
     * @var list<Exceptional> \Deriver\Deriver\Evaluator\Execution\Completion\Exceptional observations.
     */
    public array $exceptional = [];
    /**
     * @var array<string, int> Active callable specializations.
     */
    public array $active = [];
    /**
     * Entry whose escaping pre-observation exceptions belong to the current query.
     */
    public string $entrySymbol = '';
    /**
     * @var list<string> Applied evaluator and world assumptions.
     */
    public array $assumptions = [];

    /**
     * @param Program $program Immutable declaration world
     * @param Query $query Normalized query
     * @param EvaluatorConfig $configuration Explicit semantic assumptions
     * @param Evaluators $evaluators Trusted evaluator selection
     */
    public function __construct(public readonly Program $program, public readonly Query $query, public readonly EvaluatorConfig $configuration, public readonly Evaluators $evaluators)
    {
        $this->summaries = new Table();
        $this->demands = new WeakMap();
        $this->nativeCalls = new WeakMap();
        $this->plainValues = new WeakMap();
        $this->identity = new Identity();
        $this->assumptions = ['target:' . $configuration->target->id(), 'scope:' . $query->scope()->mode->value];
        foreach ($evaluators->contributions->providers as $provider) {
            [$id, $version] = [$provider->id(), $provider->version()];
            $this->assumptions[] = 'provider:' . $id . '@' . $version;
        }
    }

    /**
     * Adds a root cause and an explicit residual without silently dropping a path.
     * @param string $code Reason code
     * @param SourceRef $source Source position
     * @param string $operation Affected operation
     * @param list<\Deriver\Deriver\Evaluator\Term> $dependencies Known dependencies
     * @param string $type Justified residual type bound
     * @param list<string> $knownDependencies Named inputs, such as `global:name` environment keys, that would resolve the frontier
     * @return \Deriver\Deriver\Evaluator\Term Residual expression
     */
    public function frontier(string $code, SourceRef $source, string $operation, array $dependencies = [], string $type = 'mixed', array $knownDependencies = []): \Deriver\Deriver\Evaluator\Term
    {
        $id = $source->id() . ':' . $code . ':' . $operation;
        $value = \Deriver\Deriver\Evaluator\Term::opaque($code, $type, $dependencies);
        $names = array_values(array_unique([...$this->frontiers[$id]->knownDependencies ?? [], ...$knownDependencies]));
        $this->frontiers[$id] = new Frontier($code, $source, $operation, ['value', 'state'], $names, $value, $operation);
        return $value;
    }

    /**
     * Checks logical work limits before admitting more semantic work.
     * @param SourceRef $source Next source position
     * @return bool Whether work may continue
     */
    public function admit(SourceRef $source): bool
    {
        if ($this->sealed || $this->transfers >= $this->query->budget()->transfers || count($this->evidence) >= $this->query->budget()->nodes) {
            $this->sealed = true;
            $this->frontier('BUDGET_EXCEEDED', $source, 'logical-work');
            return false;
        }
        $this->transfers++;
        return true;
    }
}
