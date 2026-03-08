<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

/**
 * Stores one generated SQL witness from the supported language contract.
 */
final class SqlWitness
{
    /**
     * @param array<string, scalar> $parameters
     * @param array<string, scalar> $properties
     */
    public function __construct(
        public readonly string $familyId,
        public readonly int $seed,
        public readonly string $sql,
        public readonly array $parameters = [],
        public readonly array $properties = [],
    ) {
    }
}
