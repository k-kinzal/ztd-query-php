<?php

declare(strict_types=1);

namespace Deriver\Query;

use Deriver\Exception\InvalidInputException;

/**
 * Logical limits that preserve residual behavior when exhausted.
 *
 * @visibility public
 * @example Setting a reproducible small budget
 *     (new \Deriver\Query\Budget(transfers: 100))->transfers // => 100
 */
final class Budget
{
    /**
     * @param int $transfers Maximum instruction transfers
     * @param int $partitions Maximum live correlated paths
     * @param int $iterations Maximum precise visits to a loop header
     * @param int $recursion Maximum active specializations of a recursive callable
     * @param int $nodes Maximum materialized value and dependency nodes
     * @param int $symbolicRecursion Maximum active specializations of a recursive callable entered with symbolic inputs;
     *     each level of such recursion multiplies the paths to explore, unlike recursion over concrete values
     * @param int $maxDepth Maximum reference-to-origin expansions on each dependency branch; zero retains the observed reference
     * @throws InvalidInputException If a work budget is not positive or maxDepth is negative
     */
    public function __construct(
        public readonly int $transfers = 100000,
        public readonly int $partitions = 32,
        public readonly int $iterations = 16,
        public readonly int $recursion = 64,
        public readonly int $nodes = 20000,
        public readonly int $symbolicRecursion = 4,
        public readonly int $maxDepth = 64,
    ) {
        if (min($transfers, $partitions, $iterations, $recursion, $nodes, $symbolicRecursion) < 1 || $maxDepth < 0) {
            throw new InvalidInputException('Every logical budget must be positive.');
        }
    }
}
