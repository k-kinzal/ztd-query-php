<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Lexical;

/**
 * Reads the checked-in terminal catalog into the shape the rest of the code assumes.
 *
 * The catalog arrives as a decoded resource file, so nothing about its contents
 * is known before it is inspected. Each section is read on its own and reports
 * which field was wrong, because a catalog is regenerated from an upstream
 * lexer and "the shape is invalid" is not enough to find what changed.
 *
 * @phpstan-type Witness array{id: string, sql: string, tokens: list<string>, units: list<string>, context_sql?: string}
 * @phpstan-type Catalog array{
 *     source: array{engine: string, entrypoint: string},
 *     terminals: array<string, list<Witness>>,
 *     terminal_exclusions: array<string, string>,
 *     coverage: array{units: list<string>, witnessed: array<string, string>, excluded: array<string, string>}
 * }
 */
final class LexicalCatalogShape
{
    /** @readonly */
    private LexicalWitnessShape $witnesses;

    /**
     * @param LexicalWitnessShape|null $witnesses Reads the individual lexing examples
     */
    public function __construct(?LexicalWitnessShape $witnesses = null)
    {
        $this->witnesses = $witnesses ?? new LexicalWitnessShape();
    }

    /**
     * Reads a decoded catalog file.
     *
     * @param array<string, mixed> $catalog Catalog as decoded from its resource file
     *
     * @return Catalog The catalog with every section checked
     *
     * @throws LexicalCatalogException When any section is not what it must be
     */
    public function of(array $catalog): array
    {
        return [
            'source' => $this->sourceOf($catalog),
            'terminals' => $this->terminalsOf($catalog),
            'terminal_exclusions' => $this->exclusionsOf($catalog),
            'coverage' => $this->coverageOf($catalog),
        ];
    }

    /**
     * Reads the record of which lexer the catalog was generated from.
     *
     * @param array<string, mixed> $catalog Catalog as decoded from its resource file
     *
     * @return array{engine: string, entrypoint: string} The upstream lexer's identity
     *
     * @throws LexicalCatalogException When the record is absent or mistyped
     */
    public function sourceOf(array $catalog): array
    {
        $source = $catalog['source'] ?? throw LexicalCatalogException::malformedShape('source');
        if (!is_array($source)) {
            throw LexicalCatalogException::malformedShape('source');
        }

        $engine = $source['engine'] ?? throw LexicalCatalogException::malformedShape('source.engine');
        if (!is_string($engine)) {
            throw LexicalCatalogException::malformedShape('source.engine');
        }

        $entrypoint = $source['entrypoint'] ?? throw LexicalCatalogException::malformedShape('source.entrypoint');
        if (!is_string($entrypoint)) {
            throw LexicalCatalogException::malformedShape('source.entrypoint');
        }

        return ['engine' => $engine, 'entrypoint' => $entrypoint];
    }

    /**
     * Reads the witnesses catalogued for each terminal.
     *
     * @param array<string, mixed> $catalog Catalog as decoded from its resource file
     *
     * @return array<string, list<Witness>> Witnesses by terminal name
     *
     * @throws LexicalCatalogException When the section or any witness is mistyped
     */
    public function terminalsOf(array $catalog): array
    {
        $terminals = $catalog['terminals'] ?? throw LexicalCatalogException::malformedShape('terminals');
        if (!is_array($terminals)) {
            throw LexicalCatalogException::malformedShape('terminals');
        }

        $read = [];
        foreach ($terminals as $terminal => $witnesses) {
            if (!is_string($terminal) || !is_array($witnesses)) {
                throw LexicalCatalogException::malformedTerminalCatalog();
            }

            $readWitnesses = [];
            foreach ($witnesses as $witness) {
                $readWitnesses[] = $this->witnesses->of($terminal, $witness);
            }
            $read[$terminal] = $readWitnesses;
        }

        return $read;
    }

    /**
     * Reads the terminals the catalog deliberately leaves out, with their reasons.
     *
     * @param array<string, mixed> $catalog Catalog as decoded from its resource file
     *
     * @return array<string, string> Reasons by terminal name
     *
     * @throws LexicalCatalogException When the section is mistyped or a reason is empty
     */
    public function exclusionsOf(array $catalog): array
    {
        $exclusions = $catalog['terminal_exclusions']
            ?? throw LexicalCatalogException::malformedShape('terminal_exclusions');
        if (!is_array($exclusions)) {
            throw LexicalCatalogException::malformedShape('terminal_exclusions');
        }

        $read = [];
        foreach ($exclusions as $terminal => $reason) {
            if (!is_string($terminal) || !is_string($reason) || $reason === '') {
                throw LexicalCatalogException::malformedExclusion();
            }
            $read[$terminal] = $reason;
        }

        return $read;
    }

    /**
     * Reads which units of the upstream lexer the catalog accounts for.
     *
     * @param array<string, mixed> $catalog Catalog as decoded from its resource file
     *
     * @return array{units: list<string>, witnessed: array<string, string>, excluded: array<string, string>}
     *         The units and how each is classified
     *
     * @throws LexicalCatalogException When the section is absent or mistyped
     */
    public function coverageOf(array $catalog): array
    {
        $coverage = $catalog['coverage'] ?? throw LexicalCatalogException::malformedShape('coverage');
        if (!is_array($coverage)) {
            throw LexicalCatalogException::malformedShape('coverage');
        }

        return [
            'units' => $this->unitsOf($coverage),
            'witnessed' => $this->witnessedOf($coverage),
            'excluded' => $this->excludedOf($coverage),
        ];
    }

    /**
     * Reads the list of units the upstream lexer is made of.
     *
     * @param array<mixed> $coverage Coverage section of the catalog
     *
     * @return list<string> Unit names
     *
     * @throws LexicalCatalogException When the list is absent or holds anything but names
     */
    public function unitsOf(array $coverage): array
    {
        $units = $coverage['units'] ?? throw LexicalCatalogException::malformedShape('coverage.units');
        if (!is_array($units)) {
            throw LexicalCatalogException::malformedShape('coverage.units');
        }
        if (!array_is_list($units)) {
            throw LexicalCatalogException::malformedCoverageUnits();
        }
        foreach ($units as $unit) {
            if (!is_string($unit)) {
                throw LexicalCatalogException::malformedCoverageUnits();
            }
        }

        /** @var list<string> $units */
        return $units;
    }

    /**
     * Reads which witness accounts for each covered unit.
     *
     * @param array<mixed> $coverage Coverage section of the catalog
     *
     * @return array<string, string> Witness identifiers by unit name
     *
     * @throws LexicalCatalogException When the map is absent or mistyped
     */
    public function witnessedOf(array $coverage): array
    {
        $witnessed = $coverage['witnessed'] ?? throw LexicalCatalogException::malformedShape('coverage.witnessed');
        if (!is_array($witnessed)) {
            throw LexicalCatalogException::malformedShape('coverage.witnessed');
        }

        $read = [];
        foreach ($witnessed as $unit => $id) {
            if (!is_string($unit) || !is_string($id)) {
                throw LexicalCatalogException::malformedCoverageWitness();
            }
            $read[$unit] = $id;
        }

        return $read;
    }

    /**
     * Reads which units are left uncovered on purpose, with their reasons.
     *
     * @param array<mixed> $coverage Coverage section of the catalog
     *
     * @return array<string, string> Reasons by unit name
     *
     * @throws LexicalCatalogException When the map is absent, mistyped, or a reason is empty
     */
    public function excludedOf(array $coverage): array
    {
        $excluded = $coverage['excluded'] ?? throw LexicalCatalogException::malformedShape('coverage.excluded');
        if (!is_array($excluded)) {
            throw LexicalCatalogException::malformedShape('coverage.excluded');
        }

        $read = [];
        foreach ($excluded as $unit => $reason) {
            if (!is_string($unit) || !is_string($reason) || $reason === '') {
                throw LexicalCatalogException::malformedCoverageExclusion();
            }
            $read[$unit] = $reason;
        }

        return $read;
    }
}
