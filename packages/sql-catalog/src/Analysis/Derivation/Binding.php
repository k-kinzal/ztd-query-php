<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Derivation;

use SqlCatalog\Evaluation\Environment;

/**
 * What the names a path still needs are bound to at the start of its body, for one way in.
 *
 * @visibility root
 */
final class Binding
{
    /**
     * @param Environment $environment The names, bound
     * @param list<string> $through The bodies the way in passes through, outermost first, ending with this body
     * @param bool $truncated Whether a bound cut short the search for ways in
     * @param bool $combined Whether values that are worked out separately were paired, so the pairing may never occur
     */
    public function __construct(
        public readonly Environment $environment,
        public readonly array $through,
        public readonly bool $truncated = false,
        public readonly bool $combined = false,
    ) {
    }
}
