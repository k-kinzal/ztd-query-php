<?php

declare(strict_types=1);

namespace Deriver\Result\Execution;

/**
 * Independent query results; use TupleQuery to retain correlations.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Result\Execution\ExecutionResultSet([]))->results // => []
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
