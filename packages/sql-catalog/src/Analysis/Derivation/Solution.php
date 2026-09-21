<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Derivation;

use SqlCatalog\Evaluation\Domain;

/**
 * What a set of expressions can be at a point, along one way of getting there.
 *
 * @visibility root
 */
final class Solution
{
    /**
     * @param list<Domain> $values One value for each expression asked about, in the order they were asked about
     * @param list<string> $through The bodies the way passes through, outermost first
     * @param bool $truncated Whether a bound cut the search short of every way there
     * @param bool $combined Whether values worked out separately were paired, so the pairing may never occur
     */
    public function __construct(
        public readonly array $values,
        public readonly array $through,
        public readonly bool $truncated = false,
        public readonly bool $combined = false,
    ) {
    }
}
