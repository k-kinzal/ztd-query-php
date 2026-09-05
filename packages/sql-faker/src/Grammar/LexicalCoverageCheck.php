<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

/**
 * Checks that the catalog accounts for every unit of the upstream lexer.
 *
 * Coverage is a partition: each unit the upstream lexer is made of is either
 * witnessed by an example or excluded with a reason, never both and never
 * neither. That is what makes the catalog a claim about the whole lexer rather
 * than about whichever parts someone happened to write down.
 *
 * @phpstan-type Coverage array{units: list<string>, witnessed: array<string, string>, excluded: array<string, string>}
 *
 * @visibility root
 */
final class LexicalCoverageCheck
{
    /**
     * Checks the coverage partition of a catalog.
     *
     * @param Coverage $coverage Coverage section of the catalog
     *
     * @throws LexicalCatalogException When a unit is duplicated, doubly classified, or unclassified
     */
    public function verify(array $coverage): void
    {
        $this->verifyUnitsAreUnique($coverage['units']);
        $this->verifyClassificationsDoNotOverlap($coverage['witnessed'], $coverage['excluded']);
        $this->verifyEveryUnitIsClassified($coverage);
    }

    /**
     * Checks that no unit is listed twice.
     *
     * @param list<string> $units Units the upstream lexer is made of
     *
     * @throws LexicalCatalogException When a unit appears more than once
     */
    public function verifyUnitsAreUnique(array $units): void
    {
        if (count($units) !== count(array_unique($units))) {
            throw LexicalCatalogException::duplicateCoverageUnits();
        }
    }

    /**
     * Checks that no unit is both witnessed and excluded.
     *
     * @param array<string, string> $witnessed Witness identifiers by unit name
     * @param array<string, string> $excluded Reasons by unit name
     *
     * @throws LexicalCatalogException When a unit carries both classifications
     */
    public function verifyClassificationsDoNotOverlap(array $witnessed, array $excluded): void
    {
        if (array_intersect_key($witnessed, $excluded) !== []) {
            throw LexicalCatalogException::overlappingClassification();
        }
    }

    /**
     * Checks that the two classifications together name exactly the listed units.
     *
     * @param Coverage $coverage Coverage section of the catalog
     *
     * @throws LexicalCatalogException When a unit is left out or an unlisted one is classified
     */
    public function verifyEveryUnitIsClassified(array $coverage): void
    {
        $units = $coverage['units'];
        sort($units);

        $classified = [
            ...array_keys($coverage['witnessed']),
            ...array_keys($coverage['excluded']),
        ];
        sort($classified);

        if ($units !== $classified) {
            throw LexicalCatalogException::incompleteClassification();
        }
    }
}
