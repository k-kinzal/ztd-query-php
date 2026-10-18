<?php

declare(strict_types=1);

namespace Deriver\Deriver\Execution;

/**
 * Independent query results; use TupleQuery to retain correlations.
 *
 * @visibility root
 * @example Inspecting the contract
 *     (new \Deriver\Deriver\Execution\ExecutionResultSet([]))->results // => []
 */
final class ExecutionResultSet
{
    /**
     * @param list<DerivationResult> $results results
     */
    public function __construct(
        public readonly array $results,
    ) {
    }
}
