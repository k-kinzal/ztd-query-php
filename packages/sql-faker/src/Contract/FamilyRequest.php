<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

/**
 * Describes a request to generate one witness from a supported SQL family.
 */
final class FamilyRequest
{
    /**
     * @param array<string, scalar> $parameters
     */
    public function __construct(
        public readonly string $familyId,
        public readonly array $parameters = [],
    ) {
    }
}
