<?php

declare(strict_types=1);

namespace Deriver;

use Deriver\Project\CapturedProject;
use Deriver\Query\Query;
use Deriver\Reference\ResultRef;
use Deriver\Result\CandidateCollection;
use Deriver\Result\ResultSet;

/**
 * An immutable source world with reusable analysis queries and explanations.
 *
 * @visibility public
 * @example Opening an empty source snapshot
 *     $session = (new \Deriver\Analyzer())->open(new \Deriver\Project\ProjectInput([]));
 *     $session->snapshot()->sources // => []
 */
interface AnalysisSession extends CapturedProject
{
    /**
     * Derives a value, state, return, or correlated tuple.
     * @param Query $query Immutable query
     * @return CandidateCollection Values and quality assessment
     */
    public function derive(Query $query): CandidateCollection;

    /**
     * Shares the snapshot across independent requests.
     * @param list<Query> $queries Independent queries
     * @return ResultSet Results in request order
     */
    public function deriveMany(array $queries): ResultSet;

    /**
     * Shares candidate dependencies across separately budgeted observations, like deriveMany().
     * Each tuple preserves its own correlation.
     * @param list<Query> $queries Queries in output order
     * @return ResultSet Results in request order
     */
    public function deriveTogether(array $queries): ResultSet;

    /**
     * Releases session-owned candidate evaluations and result retention.
     * Results still owned by callers remain immutable and usable.
     */
    public function release(): void;

    /**
     * Retrieves the explanation for a result from this session.
     * Keep the CandidateCollection alive while using its reference: by default only 32 small recent results are retained strongly;
     * older or large results may be released once the caller drops them.
     * @param ResultRef $result Result reference
     * @return list<Result\Alternative> Candidate derivations
     */
    public function explain(ResultRef $result): array;

    /**
     * Generates named queries from registered observation providers.
     * @return array<string, Query> Ordinary independent public queries
     */
    public function observations(): array;
}
