<?php

declare(strict_types=1);

namespace Spec\Claim;

/**
 * Describes one contract or support claim checked by the spec runner.
 */
final class ClaimDefinition
{
    /**
     * @param list<array<string, scalar>> $cases
     * @param array<string, scalar> $subjectOptions
     * @param list<EvidenceDefinition> $evidence
     */
    public function __construct(
        public readonly string $id,
        public readonly string $level,
        public readonly string $dialect,
        public readonly string $statement,
        public readonly string $sourceLocation,
        public readonly string $subjectKind,
        public readonly array $subjectOptions,
        public readonly array $cases,
        public readonly array $evidence,
    ) {
    }
}
