<?php

declare(strict_types=1);

namespace Deriver\Evaluation;

use Deriver\ControlFlow\Program;
use Deriver\Evaluation\Control\Resources;
use Deriver\Evaluation\Demand\Table;
use Deriver\Model\Registration\Registry;
use Deriver\Project\Configuration;
use Deriver\Query\Query;
use Deriver\Reference\SourceRef;
use Deriver\Result\Alternative;
use Deriver\Result\Derivation;
use Deriver\Result\Exceptional;
use Deriver\Result\Frontier;
use Deriver\Value\Identity;
use Deriver\Value\Term;
use WeakMap;

/**
 * Query-local work budget, dependency evidence, observations, and model registry.
 * @visibility root
 */
final class Context
{
    /**
     * Dynamic call dependencies and completed specializations for this query.
     */
    public readonly Table $summaries;
    /**
     * Runtime resource checks, independent of semantic transfer budgets.
     */
    public readonly Resources $resources;
    /**
     * Structural term keys memoized across this query's states, which share term subgraphs.
     */
    public readonly Identity $identity;
    /**
     * Permanent resource interruption reason, when applicable; STACK_LIMIT only seals the refused call.
     */
    public ?string $stopReason = null;
    /**
     * Explicit observations sharing this execution and its resource budget.
     */
    public ?BatchObservations $batch = null;

    /**
     * @var array<string, true> Graphs evaluated by this query.
     */
    public array $graphs = [];
    /**
     * @var WeakMap<\Deriver\ControlFlow\CallableGraph, array<string, true>> Backward dependencies per immutable graph.
     */
    public WeakMap $demands;
    /**
     * @var WeakMap<\Deriver\ControlFlow\CallableGraph, true> Graphs supplied by built-in PHP models.
     */
    public WeakMap $nativeCalls;
    /**
     * @var WeakMap<Term, bool> Immutable value graphs proven free of storage identities
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
     * @var list<Exceptional> Exceptional observations.
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
     * @var list<string> Applied model and world assumptions.
     */
    public array $assumptions = [];

    /**
     * @param Program $program Immutable declaration world
     * @param Query $query Normalized query
     * @param Configuration $configuration Explicit semantic assumptions
     * @param Registry $models Trusted model selection
     * @param Summary\SharedSummaries $shared Session cache of closed isolated completions
     * @param Resources|null $resources Shared runtime policy for observation-only contexts
     */
    public function __construct(public readonly Program $program, public readonly Query $query, public readonly Configuration $configuration, public readonly Registry $models, public readonly Summary\SharedSummaries $shared = new Summary\SharedSummaries(), ?Resources $resources = null)
    {
        $this->summaries = new Table();
        $this->demands = new WeakMap();
        $this->nativeCalls = new WeakMap();
        $this->plainValues = new WeakMap();
        $this->resources = $resources ?? new Resources($configuration->resources);
        $this->identity = new Identity();
        $this->assumptions = ['target:' . $configuration->target->id(), 'scope:' . $query->scope()->mode, 'world:' . ($configuration->closedWorld ? 'closed' : 'open'), 'environment:' . $configuration->environmentVersion];
        foreach ($configuration->providers as $provider) {
            [$id, $version] = [$provider->id(), $provider->version()];
            $this->assumptions[] = 'provider:' . $id . '@' . $version;
        }
    }

    /**
     * Adds a root cause and an explicit residual without silently dropping a path.
     * @param string $code Reason code
     * @param SourceRef $source Source position
     * @param string $operation Affected operation
     * @param list<Term> $dependencies Known dependencies
     * @param string $type Justified residual type bound
     * @param list<string> $knownDependencies Named inputs, such as `global:name` environment keys, that would resolve the frontier
     * @return Term Residual expression
     */
    public function frontier(string $code, SourceRef $source, string $operation, array $dependencies = [], string $type = 'mixed', array $knownDependencies = []): Term
    {
        $id = $source->id() . ':' . $code . ':' . $operation;
        $value = Term::opaque($code, $type, $dependencies);
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
        if (!$this->available($source)) {
            return false;
        }
        if ($this->sealed || $this->transfers >= $this->query->budget()->transfers || count($this->evidence) >= $this->query->budget()->nodes) {
            $this->sealed = true;
            $this->frontier('BUDGET_EXCEEDED', $source, 'logical-work');
            return false;
        }
        $this->transfers++;
        return true;
    }
    /**
     * Checks cancellation and resource limits even for callables without instructions.
     * @param SourceRef $source Next semantic operation
     * @param int $additionalBytes Anticipated allocation before executing the operation
     * @param bool $call Whether a new callable will add host stack frames
     * @return bool Whether runtime resources permit more work; false for a call refused by STACK_LIMIT leaves later work admitted
     */
    public function available(SourceRef $source, int $additionalBytes = 0, bool $call = false): bool
    {
        if ($this->stopReason !== null) {
            return false;
        }
        $reason = $this->resources->reason($additionalBytes, $call);
        if ($reason !== null) {
            if ($reason !== 'STACK_LIMIT') {
                $this->stopReason = $reason;
                $this->sealed = true;
            }
            $this->frontier($reason, $source, 'runtime-resources');
            return false;
        }
        return true;
    }
}
