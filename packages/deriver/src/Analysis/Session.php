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
use Deriver\Result\Candidates\CandidateCollection;
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
     * Selects an expression by its exact byte range and syntactic role.
     * @throws InvalidInputException If the range does not select one captured expression
     */
    public function expression(string $path, int $start, int $end, string $role = 'value'): \Deriver\Reference\ExpressionRef
    {
        return (new Candidates\Targets())->expression($this->program, $path, $start, $end, $role);
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
    private readonly \Deriver\Evaluation\Candidate\Index $candidateIndex;
    private readonly \Deriver\Evaluation\Candidate\Cache $candidateCache;
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
     * @throws InvalidInputException If captured PHP is malformed
     * @throws JsonException If data cannot be represented in JSON
     */
    public function __construct(ProjectInput $input, Configuration $configuration, SyntaxCache $syntax = new SyntaxCache(), GraphCache $lowered = new GraphCache(), bool $validateSource = true)
    {
        $this->liveResults = new WeakMap();
        $this->retention = new ResultRetention($configuration->retainedResults);
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
        foreach ($configuration->expansionRules as $rule) {
            $models['expansion:' . $rule->id] = hash('sha256', serialize([$rule->version, $rule->operation, $rule->name, $rule->priority, $rule->path, $rule->start, $rule->end]));
        }
        ksort($models);
        $environment = [];
        foreach ($configuration->environment as $key => $value) {
            $environment[$key] = (new Identity())->key($value);
        }
        ksort($environment);
        $dependencies = $configuration->dependencyVersions;
        ksort($dependencies);
        $identity = [$sourceModes, $dependencies, $sources, $models, $configuration->target->id(), $configuration->closedWorld, $configuration->environmentVersion, $environment, $configuration->standardModels, 'deriver-semantics-2'];
        $id = hash('sha256', json_encode((new JsonText())->tree($identity), JSON_THROW_ON_ERROR));
        $this->program = new ProjectIndex($id, $input, $configuration->target, $syntax, $lowered, $configuration->sourceLimits);
        if ($validateSource) {
            $this->validateSource();
        }
        $this->candidateIndex = new \Deriver\Evaluation\Candidate\Index($this->program);
        $this->candidateCache = new \Deriver\Evaluation\Candidate\Cache($configuration->candidateCacheEntries);
        $this->manifest = new ProjectSnapshot($id, $sources, $models, $configuration->target, $configuration->closedWorld, $configuration->environmentVersion, $this->program->diagnostics(), $dependencies, $sourceModes);
    }

    /**
     * Rejects malformed source at capture rather than manufacturing candidate values.
     * @throws InvalidInputException If captured PHP cannot be parsed
     */
    public function validateSource(): void
    {
        foreach ($this->program->diagnostics() as $diagnostic) {
            if ($diagnostic->code === 'INCOMPLETE_SOURCE') {
                throw new InvalidInputException('Malformed captured PHP in ' . $diagnostic->at->path . ': ' . $diagnostic->operation);
            }
        }
    }

    /**
     * Derives one normalized request from the immutable snapshot.
     * @param Query $query Requested observation
     * @return CandidateCollection Derived result
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[Override]
    public function derive(Query $query): CandidateCollection
    {
        $key = (new QueryEncoding())->key($query);
        $cached = ($this->cache[$key] ?? null)?->get();
        if ($cached !== null && $this->configuration->resources->cancellation?->isRequested() !== true) {
            return $cached;
        }
        $result = (new Candidates\QueryExecution($this->candidateIndex, $this->configuration, $this->models, $this->manifest, $this->candidateCache))->derive($query);
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
