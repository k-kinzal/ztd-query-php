<?php

declare(strict_types=1);

namespace Deriver\Analysis;

use Deriver\AnalysisSession;
use Deriver\Exception\InvalidInputException;
use Deriver\Model\Provider\ObservationProvider;
use Deriver\Model\Registration\ProviderInputs;
use Deriver\Model\Registration\Registry;
use Deriver\Model\Registration\SignatureIdentity;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\ProjectSnapshot;
use Deriver\Query\Query;
use Deriver\Reference\Observation;
use Deriver\Reference\ResultRef;
use Deriver\Result\DerivationResult;
use Deriver\Result\Explanation;
use Deriver\Result\ResultSet;
use Deriver\Result\Serialization\JsonText;
use Deriver\Result\Serialization\QueryEncoding;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Declaration\ProjectIndex;
use Deriver\Value\Identity;
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
     * @var array<string, WeakReference<DerivationResult>> Live session results.
     */
    public array $results = [];
    /**
     * @var array<string, WeakReference<DerivationResult>> Live complete query results by semantic options.
     */
    public array $cache = [];
    /**
     * @var WeakMap<DerivationResult, true> Caller-owned instances, including repeated results with the same identity
     */
    private readonly WeakMap $liveResults;
    /**
     * Bounded strong ownership of recent small results.
     */
    private readonly ResultRetention $retention;
    /**
     * Closed isolated function results shared by this immutable session.
     */
    private \Deriver\Evaluation\Summary\SharedSummaries $shared;
    /**
     * @var array<string, list<Observation>> Cached source call inventories
     */
    private array $calls = [];
    /**
     * Captured provider contributions.
     */
    public readonly ProviderInputs $providerInputs;
    /**
     * Frozen semantic assumptions.
     */
    public readonly Configuration $configuration;

    /**
     * @param ProjectInput $input Captured sources
     * @param Configuration $configuration Explicit assumptions
     * @param SyntaxCache $syntax Reusable captured source parsing
     * @param GraphCache $lowered Reusable source graph templates
     * @throws JsonException If data cannot be represented in JSON
     */
    public function __construct(ProjectInput $input, Configuration $configuration, SyntaxCache $syntax = new SyntaxCache(), GraphCache $lowered = new GraphCache())
    {
        $this->liveResults = new WeakMap();
        $this->retention = new ResultRetention();
        $this->shared = new \Deriver\Evaluation\Summary\SharedSummaries();
        $this->providerInputs = new ProviderInputs($input, $configuration);
        $input = $this->providerInputs->input;
        $configuration = $this->providerInputs->configuration;
        $this->configuration = $configuration;
        $configuration->sourceLimits->check($input);
        $this->models = new Registry($configuration);
        $sources = [];
        $sourceModes = [];
        foreach ($input->files as $file) {
            $path = ProjectInput::normalize($file->path);
            $sources[$path] = hash('sha256', $file->contents);
            if ($file->declarationsOnly) {
                $sourceModes[$path] = 'declarations';
            }
        }
        ksort($sources);
        ksort($sourceModes);
        $models = [];
        foreach ($this->models->manifest as $descriptor) {
            $models['model:' . $descriptor->id] = hash('sha256', serialize([$descriptor->version, $descriptor->symbol, $descriptor->priority, $descriptor->replaces, $descriptor->replaceSource, $descriptor->useSourceSignature, (new SignatureIdentity())->key($descriptor->signature)]));
        }
        $models = [...$models, ...$this->models->extensions->manifest, ...$this->models->state->manifest, ...$this->providerInputs->manifest];
        ksort($models);
        $environment = [];
        foreach ($configuration->environment as $key => $value) {
            $environment[$key] = (new Identity())->key($value);
        }
        ksort($environment);
        $dependencies = $configuration->dependencyVersions;
        ksort($dependencies);
        $identity = [$sourceModes, $dependencies, $sources, $models, $configuration->target->id(), $configuration->closedWorld, $configuration->environmentVersion, $environment, $configuration->standardModels, 'deriver-semantics-1'];
        $id = hash('sha256', json_encode((new JsonText())->tree($identity), JSON_THROW_ON_ERROR));
        $this->program = new ProjectIndex($id, $input, $configuration->target, $syntax, $lowered, $configuration->sourceLimits);
        $this->manifest = new ProjectSnapshot($id, $sources, $models, $configuration->target, $configuration->closedWorld, $configuration->environmentVersion, $this->program->diagnostics(), $dependencies, $sourceModes);
    }

    /**
     * Derives one normalized request from the immutable snapshot.
     * @param Query $query Requested observation
     * @return DerivationResult Derived result
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[Override]
    public function derive(Query $query): DerivationResult
    {
        $key = (new QueryEncoding())->key($query);
        $cached = ($this->cache[$key] ?? null)?->get();
        if ($cached !== null && $this->configuration->resources->cancellation?->isRequested() !== true) {
            return $cached;
        }
        $result = (new QueryExecution($this->program, $this->configuration, $this->models, $this->manifest, $this->shared))->derive($query);
        $this->remember($result);
        foreach ($result->frontiers as $frontier) {
            if (in_array($frontier->code, ['CANCELLED', 'MEMORY_LIMIT', 'TIME_LIMIT', 'STACK_LIMIT'], true)) {
                return $result;
            }
        }
        $this->cache[$key] = WeakReference::create($result);
        return $result;
    }

    /**
     * Indexes live results without owning their graphs indefinitely.
     * @param DerivationResult $result Completed or interrupted result
     */
    public function remember(DerivationResult $result): void
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
        return new ResultSet(array_map(fn (Query $query): DerivationResult => $this->derive($query), $queries));
    }

    /**
     * Shares one symbolic execution across observations in the same callable.
     * @param list<Query> $queries Observations with identical budgets
     * @return ResultSet Results in request order, sharing execution costs and frontiers
     * @throws JsonException If query metadata cannot be encoded
     * @throws InvalidInputException If observation owners, scopes, or budgets differ
     */
    #[Override]
    public function deriveTogether(array $queries): ResultSet
    {
        $results = (new QueryExecution($this->program, $this->configuration, $this->models, $this->manifest, $this->shared))->together($queries);
        foreach ($results->results as $result) {
            $this->remember($result);
        }
        return $results;
    }

    /**
     * Looks up a result's explanation.
     * @param ResultRef $result Session result reference
     * @return Explanation Explanation graph
     * @throws InvalidInputException If the result is absent from this session
     */
    #[Override]
    public function explain(ResultRef $result): Explanation
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
        $derived ??= throw new InvalidInputException('Unknown or released result reference for this session; retain the DerivationResult while using explain().');
        return new Explanation($derived->evidence, $derived->frontiers, $derived->assumptions);
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
        return $this->providerInputs->entries;
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
