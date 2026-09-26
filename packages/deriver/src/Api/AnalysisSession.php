<?php

declare(strict_types=1);

namespace Deriver\Api;

use Deriver\Api\Project\ProjectSnapshot;
use Deriver\Api\Query\Query;
use Deriver\Api\Reference\Observation;
use Deriver\Api\Reference\ResultRef;
use Deriver\Api\Result\DerivationResult;
use Deriver\Api\Result\Explanation;
use Deriver\Api\Result\ResultSet;

/**
 * An immutable source world with reusable analysis queries and explanations.
 *
 * @visibility public
 * @example Opening an empty source snapshot
 *     $session = (new \Deriver\Analyzer())->open(new \Deriver\Api\Project\ProjectInput([]));
 *     $session->snapshot()->sources // => []
 */
interface AnalysisSession
{
    /**
     * Derives a value, state, return, or correlated tuple.
     * @param Query $query Immutable query
     * @return DerivationResult Values and quality assessment
     */
    public function derive(Query $query): DerivationResult;

    /**
     * Shares the snapshot across independent requests.
     * @param list<Query> $queries Independent queries
     * @return ResultSet Results in request order
     */
    public function deriveMany(array $queries): ResultSet;

    /**
     * Retrieves the explanation for a result from this session.
     * @param ResultRef $result Result reference
     * @return Explanation Derivations, assumptions, and boundaries
     */
    public function explain(ResultRef $result): Explanation;

    /**
     * Returns the immutable source and configuration manifest.
     * @return ProjectSnapshot Snapshot metadata
     */
    public function snapshot(): ProjectSnapshot;

    /**
     * Finds source call observations without running the application.
     * @param string $symbol Function or method name
     * @return list<Observation> Source-ordered call observations
     */
    public function callsTo(string $symbol): array;
    /**
     * Returns entries explicitly supplied by registered providers.
     * @return list<Project\EntryPoint> Captured application entries
     */
    public function entrypoints(): array;

    /**
     * Generates named queries from registered observation providers.
     * @return array<string, Query> Ordinary independent public queries
     */
    public function observations(): array;
}
