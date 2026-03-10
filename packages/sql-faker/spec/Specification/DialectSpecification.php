<?php

declare(strict_types=1);

namespace Spec\Specification;

use Spec\Subject\FamilyDefinition;
use Spec\Subject\FamilyRequest;
use Spec\Subject\SqlWitness;
use SqlFaker\Contract\Runtime;

interface DialectSpecification
{
    /**
     * @return list<string>
     */
    public function entryRules(): array;

    /**
     * @return list<FamilyDefinition>
     */
    public function familyCatalog(): array;

    public function family(string $familyId): FamilyDefinition;

    public function generateWitness(Runtime $runtime, FamilyRequest $request): SqlWitness;
}
