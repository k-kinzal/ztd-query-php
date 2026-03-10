<?php

declare(strict_types=1);

namespace Spec\Subject;

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
