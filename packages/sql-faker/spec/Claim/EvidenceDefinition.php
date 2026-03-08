<?php

declare(strict_types=1);

namespace Spec\Claim;

/**
 * Describes one evidence requirement attached to a spec claim.
 */
final class EvidenceDefinition
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public readonly string $kind,
        public readonly array $options = [],
    ) {
    }
}
