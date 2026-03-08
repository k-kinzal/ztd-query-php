<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

/**
 * Captures the normalized supported grammar exposed by the public contract.
 */
final class GrammarSnapshot
{
    /**
     * @param list<string> $entryRules
     * @param array<string, GrammarRuleSnapshot> $rules
     * @param array<string, list<string>> $familyAnchors
     */
    public function __construct(
        public readonly string $dialect,
        public readonly string $startRule,
        public readonly array $entryRules,
        public readonly array $rules,
        public readonly array $familyAnchors,
    ) {
    }
}
