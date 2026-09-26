<?php

declare(strict_types=1);

namespace Deriver\Internal\Api;

use Deriver\Api\AnalysisSession;
use Deriver\Api\InvalidInputException;
use Deriver\Api\Project\Configuration;
use Deriver\Api\Project\ProjectInput;
use Deriver\Api\Project\ProjectSnapshot;
use Deriver\Api\Query\Query;
use Deriver\Api\Reference\Observation;
use Deriver\Api\Reference\ResultRef;
use Deriver\Api\Result\DerivationResult;
use Deriver\Api\Result\Explanation;
use Deriver\Api\Result\ResultSet;
use Deriver\Internal\Frontend\Php\ProjectIndex;
use Deriver\Internal\Model\Registry;
use Deriver\Internal\Value\Identity;
use Deriver\Report\QueryEncoding;
use JsonException;
use Override;

/**
 * Coordinates snapshot-local query evaluation and result explanations.
 * @visibility root
 */
final class Session implements AnalysisSession
{
    /**
     * Captured declaration and lazy graph index.
     */
    public readonly ProjectIndex $program;
    /**
     * Explicit model selection.
     */
    public readonly Registry $models;
    /**
     * Captured manifest.
     */
    public readonly ProjectSnapshot $manifest;
    /**
     * @var array<string, DerivationResult> Session results.
     */
    public array $results = [];
    /**
     * @var array<string, DerivationResult> Complete query results by semantic options.
     */
    public array $cache = [];
    /**
     * Captured provider contributions.
     */
    public readonly \Deriver\Internal\Model\ProviderInputs $providerInputs;
    /**
     * Frozen semantic assumptions.
     */
    public readonly Configuration $configuration;

    /**
     * @param ProjectInput $input Captured sources
     * @param Configuration $configuration Explicit assumptions
     * @param \Deriver\Internal\Frontend\Php\Cache\SyntaxCache $syntax Reusable captured source parsing
     * @param \Deriver\Internal\Frontend\Php\Cache\GraphCache $lowered Reusable source graph templates
     * @throws JsonException If data cannot be represented in JSON
     */
    public function __construct(ProjectInput $input, Configuration $configuration, \Deriver\Internal\Frontend\Php\Cache\SyntaxCache $syntax = new \Deriver\Internal\Frontend\Php\Cache\SyntaxCache(), \Deriver\Internal\Frontend\Php\Cache\GraphCache $lowered = new \Deriver\Internal\Frontend\Php\Cache\GraphCache())
    {
        $this->providerInputs = new \Deriver\Internal\Model\ProviderInputs($input, $configuration);
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
            $models['model:' . $descriptor->id] = hash('sha256', serialize([$descriptor->version, $descriptor->symbol, $descriptor->priority, $descriptor->replaces, $descriptor->replaceSource, (new \Deriver\Internal\Model\SignatureIdentity())->key($descriptor->signature)]));
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
        $id = hash('sha256', json_encode((new \Deriver\Report\JsonText())->tree($identity), JSON_THROW_ON_ERROR));
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
        if (isset($this->cache[$key]) && $this->configuration->resources->cancellation?->isRequested() !== true) {
            return $this->cache[$key];
        }
        $result = (new QueryExecution($this->program, $this->configuration, $this->models, $this->manifest))->derive($query);
        $this->results[$result->reference->id] = $result;
        foreach ($result->frontiers as $frontier) {
            if (in_array($frontier->code, ['CANCELLED', 'MEMORY_LIMIT', 'TIME_LIMIT', 'STACK_LIMIT'], true)) {
                return $result;
            }
        }
        return $this->cache[$key] = $result;
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
     * Looks up a result's explanation.
     * @param ResultRef $result Session result reference
     * @return Explanation Explanation graph
     * @throws InvalidInputException If the result is absent from this session
     */
    #[Override]
    public function explain(ResultRef $result): Explanation
    {
        $derived = $this->results[$result->id] ?? throw new InvalidInputException('Unknown result reference for this session.');
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
     * Selects source call sites using evaluated IR argument references.
     * @param string $symbol Function or method name
     * @return list<Observation> Deterministically ordered observations
     */
    #[Override]
    public function callsTo(string $symbol): array
    {
        return (new CallObservations($this->program))->find($symbol);
    }
    /**
     * Returns entries explicitly contributed by registered providers.
     * @return list<\Deriver\Api\Project\EntryPoint> Captured application entries
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
            if (!$provider instanceof \Deriver\Model\Provider\ObservationProvider) {
                continue;
            }
            foreach ((new \Deriver\Internal\Model\ModelBoundary())->queries($provider, $this) as $name => $query) {
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
