<?php

declare(strict_types=1);

namespace Spec\Subject;

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
