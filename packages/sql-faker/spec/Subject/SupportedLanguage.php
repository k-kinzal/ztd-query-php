<?php

declare(strict_types=1);

namespace Spec\Subject;

use SqlFaker\Contract\Grammar;

interface SupportedLanguage
{
    public function dialect(): string;

    public function supportedGrammar(): Grammar;

    /**
     * @return list<string>
     */
    public function entryRules(): array;

    /**
     * @return list<FamilyDefinition>
     */
    public function familyCatalog(): array;

    public function family(string $familyId): FamilyDefinition;

    public function generateWitness(FamilyRequest $request): SqlWitness;
}
