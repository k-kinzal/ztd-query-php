<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

/**
 * Describes one named family in a supported SQL language contract.
 */
final class FamilyDefinition
{
    /**
     * @param list<string> $anchorRules
     * @param list<string> $parameterNames
     * @param list<string> $propertyNames
     */
    public function __construct(
        public readonly string $id,
        public readonly string $description,
        public readonly string $layer,
        public readonly array $anchorRules = [],
        public readonly array $parameterNames = [],
        public readonly array $propertyNames = [],
    ) {
    }
}
