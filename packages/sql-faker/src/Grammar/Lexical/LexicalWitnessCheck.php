<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Lexical;

use SqlFaker\Grammar\LexicalCatalogException;

/**
 * Checks that the catalog's witnesses and its coverage agree with each other.
 *
 * The two halves of the catalog are written by the same generator but read
 * independently, so nothing in the file itself guarantees they still describe
 * the same lexer. These checks are what turn "both halves parsed" into "both
 * halves refer to each other": every witness identifier is unique, every unit a
 * witness claims exists, and every witnessed unit is claimed back by the
 * witness the coverage names.
 *
 * @phpstan-type Witness array{id: string, sql: string, tokens: list<string>, units: list<string>, context_sql?: string}
 * @phpstan-type Coverage array{units: list<string>, witnessed: array<string, string>, excluded: array<string, string>}
 *
 * @visibility parent
 */
final class LexicalWitnessCheck
{
    /**
     * Checks the witnesses of a catalog against its coverage.
     *
     * @param array<string, list<Witness>> $terminals Witnesses by terminal name
     * @param array<string, string> $exclusions Reasons by excluded terminal name
     * @param Coverage $coverage Coverage section of the catalog
     *
     * @throws LexicalCatalogException When the two halves disagree
     */
    public function verify(array $terminals, array $exclusions, array $coverage): void
    {
        $identifiers = $this->identifiersOf($terminals, $exclusions, $coverage['units']);
        $this->verifyCoverageNamesRealWitnesses($terminals, $coverage['witnessed'], $identifiers);
    }

    /**
     * Collects every witness identifier, checking each witness as it goes.
     *
     * @param array<string, list<Witness>> $terminals Witnesses by terminal name
     * @param array<string, string> $exclusions Reasons by excluded terminal name
     * @param list<string> $units Units the upstream lexer is made of
     *
     * @return array<string, true> The identifiers, as a set
     *
     * @throws LexicalCatalogException When a terminal is empty or excluded, or an identifier or unit is wrong
     */
    public function identifiersOf(array $terminals, array $exclusions, array $units): array
    {
        $known = array_fill_keys($units, true);
        $identifiers = [];

        foreach ($terminals as $terminal => $witnesses) {
            if (isset($exclusions[$terminal])) {
                throw LexicalCatalogException::terminalIsAlsoExcluded();
            }
            if ($witnesses === []) {
                throw LexicalCatalogException::emptyTerminal($terminal);
            }

            foreach ($witnesses as $witness) {
                if (isset($identifiers[$witness['id']])) {
                    throw LexicalCatalogException::duplicateWitnessId($witness['id']);
                }
                $identifiers[$witness['id']] = true;

                foreach ($witness['units'] as $unit) {
                    if (!isset($known[$unit])) {
                        throw LexicalCatalogException::unknownCoverageUnit($unit);
                    }
                }
            }
        }

        return $identifiers;
    }

    /**
     * Checks that each witnessed unit is claimed back by the witness naming it.
     *
     * @param array<string, list<Witness>> $terminals Witnesses by terminal name
     * @param array<string, string> $witnessed Witness identifiers by unit name
     * @param array<string, true> $identifiers Every identifier the witnesses carry
     *
     * @throws LexicalCatalogException When a named witness is missing or does not cover the unit
     */
    public function verifyCoverageNamesRealWitnesses(array $terminals, array $witnessed, array $identifiers): void
    {
        foreach ($witnessed as $unit => $id) {
            if (!isset($identifiers[$id])) {
                throw LexicalCatalogException::unknownWitness($unit);
            }
            if (!$this->covers($terminals, $id, $unit)) {
                throw LexicalCatalogException::witnessDoesNotCoverItsUnit($unit);
            }
        }
    }

    /**
     * Reports whether the named witness lists the unit among the ones it covers.
     *
     * @param array<string, list<Witness>> $terminals Witnesses by terminal name
     * @param string $id Identifier of the witness to look for
     * @param string $unit Unit the witness is expected to cover
     *
     * @return bool True when the witness exists and claims the unit
     */
    public function covers(array $terminals, string $id, string $unit): bool
    {
        foreach ($terminals as $witnesses) {
            foreach ($witnesses as $witness) {
                if ($witness['id'] === $id && in_array($unit, $witness['units'], true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
