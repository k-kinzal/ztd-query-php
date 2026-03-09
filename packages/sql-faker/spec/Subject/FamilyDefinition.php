<?php

declare(strict_types=1);

namespace Spec\Subject;

final readonly class FamilyDefinition
{
    /**
     * @param list<string> $anchorRules
     * @param list<string> $parameterNames
     * @param list<string> $propertyNames
     */
    public function __construct(
        public string $id,
        public string $description,
        public string $layer,
        public array $anchorRules = [],
        public array $parameterNames = [],
        public array $propertyNames = [],
    ) {
    }
}
