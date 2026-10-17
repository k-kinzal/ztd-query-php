<?php

declare(strict_types=1);

namespace Deriver\Session;

use Deriver\Analysis\Selection\CallObservations;
use Deriver\AnalysisSession;
use Deriver\Exception\InvalidInputException;
use Deriver\Model\Provider\ObservationProvider;
use Deriver\Model\Registration\ProviderExpansion;
use Deriver\Model\Registration\Registry;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\ProjectSnapshot;
use Deriver\Query\Query;
use Deriver\Reference\Observation;
use Deriver\Reference\ResultRef;
use Deriver\Result\Candidates\CandidateCollection;
use Deriver\Result\Explanation;
use Deriver\Result\ResultSet;
use Deriver\Result\Serialization\QueryEncoding;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Source\Lowering\LoweringCache;
use Deriver\Source\Parsing\ParseCache;
use JsonException;
use Override;
use WeakMap;
use WeakReference;

/**
 * Coordinates snapshot-local query evaluation and result explanations.
 * @visibility root
 */
final class Session implements AnalysisSession
{
    /**
     * Selects an expression by its exact byte range and syntactic role.
     * @throws InvalidInputException If the range does not select one captured expression
     */
    public function expression(string $path, int $start, int $end, string $role = 'value'): \Deriver\Reference\ExpressionRef
    {
        return (new \Deriver\Analysis\Selection\ExpressionResolution())->expression($this->program, $path, $start, $end, $role);
    }
    /**
     * @return \Deriver\Model\Metadata\DeclarationLookup Captured declaration facts
     */
    #[Override]
    public function declarations(): \Deriver\Model\Metadata\DeclarationLookup
    {
        return new \Deriver\Model\Registration\Declarations($this->program);
    }

    /**
     * Captured declaration and lazy graph index.
     */
    public readonly ProjectIndex $program;

    /**
     * @param string $symbol Captured declaration
     * @return list<\Deriver\Reference\SourceComment> Source comments
     */
    #[Override]
    public function comments(string $symbol): array
    {
        return (new \Deriver\Source\Declaration\Comments())->within($this->program, $symbol);
    }
    /**
     * Explicit model selection.
     */
    public readonly Registry $models;
    /**
     * Captured manifest.
     */
    public readonly ProjectSnapshot $manifest;
    /**
     * @var array<string, WeakReference<CandidateCollection>> Live session results.
     */
    public array $results = [];
    /**
     * @var array<string, WeakReference<CandidateCollection>> Live complete query results by semantic options.
     */
    public array $cache = [];
    /**
     * @var WeakMap<CandidateCollection, true> Caller-owned instances, including repeated results with the same identity
     */
    private readonly WeakMap $liveResults;
    /**
     * Bounded strong ownership of recent small results.
     */
    private readonly ResultRetention $retention;
    /**
     * Closed isolated function results shared by this immutable session.
     */
    /**
     * @var array<string, list<Observation>> Cached source call inventories
     */
    private array $calls = [];
    private readonly \Deriver\Candidate\Index\DefinitionIndex $candidateIndex;
    private readonly \Deriver\Candidate\Cache $candidateCache;
    /**
     * Captured provider contributions.
     */
    public readonly ProviderExpansion $providers;
    /**
     * Frozen semantic assumptions.
     */
    public readonly Configuration $configuration;

    /**
     * Assembled project stages shared by every query of this session.
     */
    public readonly SessionAssembly $assembly;

    /**
     * @param ProjectInput $input Captured sources
     * @param Configuration $configuration Explicit assumptions
     * @param ParseCache $parsing Reusable parsed files
     * @param LoweringCache $lowering Reusable lowered bodies
     * @param bool $validateSource Whether malformed captured PHP rejects the session
     * @throws InvalidInputException If captured PHP is malformed
     * @throws JsonException If data cannot be represented in JSON
     */
    public function __construct(ProjectInput $input, Configuration $configuration, ParseCache $parsing = new ParseCache(), LoweringCache $lowering = new LoweringCache(), bool $validateSource = true)
    {
        $this->assembly = new SessionAssembly($input, $configuration, $parsing, $lowering, $validateSource);
        $this->liveResults = new WeakMap();
        $this->retention = new ResultRetention($this->assembly->configuration->retainedResults);
        $this->providers = $this->assembly->providers;
        $this->configuration = $this->assembly->configuration;
        $this->models = $this->assembly->models;
        $this->program = $this->assembly->program;
        $this->candidateIndex = $this->assembly->index;
        $this->candidateCache = $this->assembly->cache;
        $this->manifest = $this->assembly->snapshot;
    }

    /**
     * Derives the candidate set of one query, reusing a complete result of the same query within this session.
     * @throws JsonException If query metadata cannot be encoded
     */
    #[Override]
    public function derive(Query $query): CandidateCollection
    {
        $key = (new QueryEncoding())->key($query);
        $cached = ($this->cache[$key] ?? null)?->get();
        if ($cached !== null && $this->configuration->resources->cancellation?->isRequested() !== true) {
            return $cached;
        }
        $result = (new \Deriver\Analysis\Candidates\QueryExecution($this->candidateIndex, $this->configuration, $this->models, $this->manifest, $this->candidateCache))->derive($query);
        $this->remember($result);
        if (!$result->interrupted) {
            $this->cache[$key] = WeakReference::create($result);
        }
        return $result;
    }

    /**
     * Indexes live results without owning their graphs indefinitely.
     * @param CandidateCollection $result Completed or interrupted result
     */
    public function remember(CandidateCollection $result): void
    {
        $this->liveResults[$result] = true;
        $this->retention->remember($result);
        $this->results = array_filter($this->results, static fn (WeakReference $reference): bool => $reference->get() !== null);
        $this->cache = array_filter($this->cache, static fn (WeakReference $reference): bool => $reference->get() !== null);
        $this->results[$result->reference->id] = WeakReference::create($result);
    }

    /**
     * Shares declaration and graph compilation across independent requests.
     * @param list<Query> $queries Requested queries
     * @return ResultSet Independent results
     * @throws JsonException If captured query metadata cannot be encoded
     */
    #[Override]
    public function deriveMany(array $queries): ResultSet
    {
        return new ResultSet(array_map(fn (Query $query): CandidateCollection => $this->derive($query), $queries));
    }

    /**
     * Shares candidate dependencies across independent observations.
     * @param list<Query> $queries Observations with their own bounds
     * @return ResultSet Candidate sets in request order
     * @throws JsonException If query metadata cannot be encoded
     */
    #[Override]
    public function deriveTogether(array $queries): ResultSet
    {
        return $this->deriveMany($queries);
    }

    /**
     * Releases session-owned results and dependency evaluations; caller-owned results remain valid.
     */
    #[Override]
    public function release(): void
    {
        $this->candidateCache->clear();
        $this->retention->clear();
        $this->cache = [];
        $this->results = [];
    }

    /**
     * Looks up a result's explanation.
     * @param ResultRef $result Session result reference
     * @return list<\Deriver\Result\Evidence\Alternative> Candidate derivations
     * @throws InvalidInputException If the result is absent from this session
     */
    #[Override]
    public function explain(ResultRef $result): array
    {
        $derived = ($this->results[$result->id] ?? null)?->get();
        if ($derived === null) {
            foreach ($this->liveResults as $live => $_) {
                if ($live->reference->id === $result->id) {
                    $derived = $live;
                    break;
                }
            }
        }
        $derived ??= throw new InvalidInputException('Unknown or released result reference for this session; retain the CandidateCollection while using explain().');
        return array_merge(...array_map(static fn ($candidate): array => $candidate->evidence, $derived->candidates));
    }

    /**

     * @return ProjectSnapshot Immutable snapshot manifest.

     */
    #[Override]
    public function snapshot(): ProjectSnapshot
    {
        return $this->manifest;
    }

    /**
     * Selects source call and object creation sites using evaluated IR argument references.
     * @param string $symbol Function or method name, `Class::__construct`, or `*`
     * @return list<Observation> Deterministically ordered observations
     */
    #[Override]
    public function callsTo(string $symbol): array
    {
        return $this->calls[$symbol] ??= (new CallObservations($this->program, $this->models))->find($symbol);
    }
    /**
     * Returns entries explicitly contributed by registered providers.
     * @return list<\Deriver\Project\EntryPoint> Captured application entries
     */
    #[Override]
    public function entrypoints(): array
    {
        return $this->providers->entries;
    }

    /**
     * Generates named observations using the ordinary query API.
     * @return array<string, Query> Provider-produced queries
     * @throws InvalidInputException If observation names conflict
     */
    #[Override]
    public function observations(): array
    {
        $result = [];
        foreach ($this->configuration->providers as $provider) {
            if (!$provider instanceof ObservationProvider) {
                continue;
            }
            foreach ($provider->queries($this) as $name => $query) {
                if (isset($result[$name])) {
                    throw new InvalidInputException('MODEL_CONFLICT: repeated observation name ' . $name);
                }
                $result[$name] = $query;
            }
        }
        ksort($result);
        return $result;
    }
}
