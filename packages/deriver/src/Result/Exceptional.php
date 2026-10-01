<?php

declare(strict_types=1);

namespace Deriver\Result;

use Deriver\Value\Term;

/**
 * An exceptional completion with the state reached before it was thrown.
 *
 * @visibility public
 * @example Inspecting the contract
 *     $e = new \Deriver\Result\Exceptional(\Deriver\Value\Term::opaque('EXCEPTION', 'Throwable'));
 *     $e->guard // => []
 */
final class Exceptional
{
    /**
     * @param Term $exception exception
     * @param array<string, bool> $guard guard
     * @param array<string, Term> $state Materialized local values
     * @param list<string> $evidence Derivation node identifiers
     * @param StorageSnapshot $storage Reachable raw storage for this alternative
     */
    public function __construct(
        public readonly Term $exception,
        public readonly array $guard = [],
        public readonly array $state = [],
        public readonly array $evidence = [],
        public readonly StorageSnapshot $storage = new StorageSnapshot(),
    ) {
    }
}
