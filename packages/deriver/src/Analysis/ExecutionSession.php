<?php

declare(strict_types=1);

namespace Deriver\Analysis;

use Deriver\Exception\InvalidInputException;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Query\Query;
use Deriver\Reference\ResultRef;
use Deriver\Result\DerivationResult;
use Deriver\Result\Explanation;
use Deriver\Result\Serialization\QueryEncoding;
use JsonException;
use WeakMap;
use WeakReference;

/**
 * Isolated migration API for execution analysis. Candidate expansion never calls it.
 * @example Explicit execution analysis
 *     $session = new \Deriver\Analysis\ExecutionSession(new \Deriver\Project\ProjectInput([]));
 *     $session->callsTo("missing") // => []
 * @visibility public
 */
final class ExecutionSession
{
    /**
     * Captured source inventory shared with the candidate query API.
     */
    public readonly Session $snapshotSession;
    /**
     * Captured source index.
     */
    public readonly \Deriver\Source\Declaration\ProjectIndex $program;
    /**
     * Registered trusted execution models.
     */
    public readonly \Deriver\Model\Registration\Registry $models;
    /**
     * Immutable source and model identities.
     */
    public readonly \Deriver\Project\ProjectSnapshot $manifest;
    /**
     * Captured semantic configuration.
     */
    public readonly Configuration $configuration;
    /**
     * @var array<string, WeakReference<DerivationResult>>
     */
    public array $results = [];
    /**
     * @var array<string, WeakReference<DerivationResult>>
     */
    public array $cache = [];
    /**
     * @var WeakMap<DerivationResult, true>
     */
    private readonly WeakMap $liveResults;
    private readonly ResultRetention $retention;
    private \Deriver\Evaluation\Summary\SharedSummaries $shared;

    /**
     * Captures an isolated execution-analysis session.
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function __construct(ProjectInput $input, Configuration $configuration = new Configuration())
    {
        $this->snapshotSession = new Session($input, $configuration, validateSource: false);
        $this->program = $this->snapshotSession->program;
        $this->models = $this->snapshotSession->models;
        $this->manifest = $this->snapshotSession->manifest;
        $this->configuration = $this->snapshotSession->configuration;
        $this->liveResults = new WeakMap();
        $this->retention = new ResultRetention($configuration->retainedResults);
        $this->shared = new \Deriver\Evaluation\Summary\SharedSummaries();
    }

    /**
     * Evaluates an explicit execution request.
     * @throws JsonException If query metadata cannot be encoded
     */
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
     * Returns retained execution provenance.
     * @throws InvalidInputException If the result reference is unknown
     */
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
     * Releases session-owned execution caches.
     */
    public function release(): void
    {
        $this->snapshotSession->release();
        $this->shared = new \Deriver\Evaluation\Summary\SharedSummaries();
        $this->retention->clear();
        $this->cache = [];
        $this->results = [];
    }

    /**
     * Finds captured call observations.
     * @return list<\Deriver\Reference\Observation>
     */
    public function callsTo(string $symbol): array
    {
        return $this->snapshotSession->callsTo($symbol);
    }
    /**
     * Returns the immutable source and model manifest.
     */
    public function snapshot(): \Deriver\Project\ProjectSnapshot
    {
        return $this->manifest;
    }
    /**
     * Returns captured declaration metadata.
     */
    public function declarations(): \Deriver\Model\Metadata\DeclarationLookup
    {
        return $this->snapshotSession->declarations();
    }
    /**
     * Returns source comments for a declaration.
     * @return list<\Deriver\Reference\SourceComment>
     */
    public function comments(string $symbol): array
    {
        return $this->snapshotSession->comments($symbol);
    }
    /**
     * Returns entries contributed by providers.
     * @return list<\Deriver\Project\EntryPoint>
     */
    public function entrypoints(): array
    {
        return $this->snapshotSession->entrypoints();
    }
    /**
     * Builds provider-defined observations.
     * @return array<string, Query>
     */
    public function observations(): array
    {
        return $this->snapshotSession->observations();
    }
    /**
     * Evaluates independent execution requests.
     * @param list<Query> $queries Ordered requests
     * @throws JsonException If query metadata cannot be encoded
     */
    public function deriveMany(array $queries): \Deriver\Result\ExecutionResultSet
    {
        return new \Deriver\Result\ExecutionResultSet(array_map(fn (Query $query): DerivationResult => $this->derive($query), $queries));
    }
    /**
     * Shares execution prefixes across compatible requests.
     * @param list<Query> $queries Observations at compatible program points
     * @throws JsonException If query metadata cannot be encoded
     */
    public function deriveTogether(array $queries): \Deriver\Result\ExecutionResultSet
    {
        $results = (new QueryExecution($this->program, $this->configuration, $this->models, $this->manifest, $this->shared))->together($queries);
        foreach ($results->results as $result) {
            $this->remember($result);
        }
        return $results;
    }
}
