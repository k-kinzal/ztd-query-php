<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

/**
 * Exposes the supported language contract for one SQL dialect.
 */
interface SupportedLanguage
{
    /**
     * Returns the SQL dialect served by this subject.
     */
    public function dialect(): string;

    /**
     * Returns the normalized grammar snapshot inspected by contract checks.
     */
    public function grammarSnapshot(): GrammarSnapshot;

    /**
     * @return list<FamilyDefinition>
     */
    public function familyCatalog(): array;

    /**
     * Returns one named family definition from the dialect's family catalog.
     */
    public function family(string $familyId): FamilyDefinition;

    /**
     * Generates one witness SQL statement for the requested family and parameters.
     */
    public function generateWitness(FamilyRequest $request): SqlWitness;
}
