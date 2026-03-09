<?php

declare(strict_types=1);

namespace Spec\Subject;

final readonly class SqlWitness
{
    /**
     * @param array<string, scalar> $parameters
     * @param array<string, scalar> $properties
     */
    public function __construct(
        public string $familyId,
        public int $seed,
        public string $sql,
        public array $parameters = [],
        public array $properties = [],
    ) {
    }
}
