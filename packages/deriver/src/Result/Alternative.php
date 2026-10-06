<?php

declare(strict_types=1);

namespace Deriver\Result;

use Deriver\Value\Term;

/**
 * Correlated values and state under a shared path guard.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Result\Alternative([]))->guard // => []
 */
final class Alternative
{
    /**
     * @param array<string, Term> $values values
     * @param array<string, bool> $guard guard
     * @param array<string, Term> $state Materialized local values
     * @param list<string> $evidence evidence
     * @param StorageSnapshot $storage Reachable raw storage for this alternative
     */
    public function __construct(
        public readonly array $values,
        public readonly array $guard = [],
        public readonly array $state = [],
        public readonly array $evidence = [],
        public readonly StorageSnapshot $storage = new StorageSnapshot(),
    ) {
    }
}
