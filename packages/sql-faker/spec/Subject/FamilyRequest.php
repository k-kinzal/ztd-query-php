<?php

declare(strict_types=1);

namespace Spec\Subject;

final readonly class FamilyRequest
{
    /**
     * @param array<string, scalar> $parameters
     */
    public function __construct(
        public string $familyId,
        public array $parameters = [],
    ) {
    }
}
