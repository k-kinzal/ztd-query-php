<?php

declare(strict_types=1);

namespace Deriver\Deriver\Candidate;

/**
 * Independent query results; use TupleQuery to retain correlations.
 *
 * @visibility root
 * @example Inspecting the contract
 *     (new \Deriver\Deriver\Candidate\ResultSet([]))->results // => []
 */
final class ResultSet
{
    /**
     * @param list<CandidateCollection> $results results
     */
    public function __construct(
        public readonly array $results,
    ) {
    }
}
