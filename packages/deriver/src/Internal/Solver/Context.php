<?php

declare(strict_types=1);

namespace Deriver\Internal\Solver;

use Deriver\Api\Project\Configuration;
use Deriver\Api\Query\Query;
use Deriver\Api\Reference\SourceRef;
use Deriver\Api\Result\Alternative;
use Deriver\Api\Result\Derivation;
use Deriver\Api\Result\Exceptional;
use Deriver\Api\Result\Frontier;
use Deriver\Internal\IR\Program;
use Deriver\Internal\Model\Registry;
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
    public readonly Demand\Table $summaries;
    /**
     * Runtime resource checks, independent of semantic transfer budgets.
     */
    public readonly Control\Resources $resources;
    /**
     * Permanent resource interruption reason, when applicable.
     */
    public ?string $stopReason = null;

    /**
     * @var array<string, true> Graphs evaluated by this query.
     */
    public array $graphs = [];
    /**
     * @var WeakMap<\Deriver\Internal\IR\CallableIR, array<string, true>> Backward dependencies per immutable graph.
     */
    public WeakMap $demands;
    /**
     * @var WeakMap<\Deriver\Internal\IR\CallableIR, true> Graphs supplied by built-in PHP models.
     */
    public WeakMap $nativeCalls;
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
     */
    public function __construct(public readonly Program $program, public readonly Query $query, public readonly Configuration $configuration, public readonly Registry $models)
    {
        $this->summaries = new Demand\Table();
        $this->demands = new WeakMap();
        $this->nativeCalls = new WeakMap();
        $this->resources = new Control\Resources($configuration->resources);
        $this->assumptions = ['target:' . $configuration->target->id(), 'scope:' . $query->scope()->mode, 'world:' . ($configuration->closedWorld ? 'closed' : 'open'), 'environment:' . $configuration->environmentVersion];
        foreach ($configuration->providers as $provider) {
            [$id, $version] = (new \Deriver\Internal\Model\ModelBoundary())->providerRegistration($provider);
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
     * @return Term Residual expression
     */
    public function frontier(string $code, SourceRef $source, string $operation, array $dependencies = [], string $type = 'mixed'): Term
    {
        $id = $source->id() . ':' . $code . ':' . $operation;
        $value = Term::opaque($code, $type, $dependencies);
        $this->frontiers[$id] = new Frontier($code, $source, $operation, ['value', 'state'], residual: $value, missingCapability: $operation);
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
     * @return bool Whether runtime resources permit more work
     */
    public function available(SourceRef $source, int $additionalBytes = 0, bool $call = false): bool
    {
        if ($this->stopReason !== null) {
            return false;
        }
        $reason = $this->resources->reason($additionalBytes, $call);
        if ($reason !== null) {
            $this->stopReason = $reason;
            $this->sealed = true;
            $this->frontier($reason, $source, 'runtime-resources');
            return false;
        }
        return true;
    }
}
