<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

/**
 * Captures one grammar rule within a snapshot.
 */
final class GrammarRuleSnapshot
{
    /**
     * @param list<GrammarAlternativeSnapshot> $alternatives
     */
    public function __construct(
        public readonly string $name,
        public readonly array $alternatives,
    ) {
    }
}
