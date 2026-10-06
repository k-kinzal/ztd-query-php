<?php

declare(strict_types=1);

namespace Deriver\Result;

/**
 * Independent query results; use TupleQuery to retain correlations.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Result\ResultSet([]))->results // => []
 */
final class ResultSet
{
    /**
     * @param list<Candidates\CandidateCollection> $results results
     */
    public function __construct(
        public readonly array $results,
    ) {
    }
}
