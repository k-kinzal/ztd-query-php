<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

/**
 * The checked-in terminal catalog derived from an upstream lexer source.
 *
 * The catalog records, for each terminal the grammar can produce, SQL that the
 * server's own lexer turns into that terminal. It is generated from an upstream
 * lexer and committed, so it is read rather than trusted: a catalog that cannot
 * be shown to describe a whole lexer is refused at construction rather than
 * producing wrong answers later.
 *
 * @phpstan-type Witness array{id: string, sql: string, tokens: list<string>, units: list<string>, context_sql?: string}
 * @phpstan-type Catalog array{
 *     source: array{engine: string, entrypoint: string},
 *     terminals: array<string, list<Witness>>,
 *     terminal_exclusions: array<string, string>,
 *     coverage: array{units: list<string>, witnessed: array<string, string>, excluded: array<string, string>}
 * }
 */
final class LexicalCatalog
{
    /** @var Catalog */
    private readonly array $catalog;

    /**
     * @param array<string, mixed> $catalog Catalog as decoded from its resource file
     * @param LexicalCatalogShape|null $shape Reads the file into the shape below
     * @param LexicalCoverageCheck|null $coverage Checks that every lexer unit is accounted for
     * @param LexicalWitnessCheck|null $witnesses Checks that witnesses and coverage agree
     *
     * @throws LexicalCatalogException When the catalog is malformed or self-contradictory
     */
    public function __construct(
        array $catalog,
        ?LexicalCatalogShape $shape = null,
        ?LexicalCoverageCheck $coverage = null,
        ?LexicalWitnessCheck $witnesses = null,
    ) {
        $read = ($shape ?? new LexicalCatalogShape())->of($catalog);

        ($coverage ?? new LexicalCoverageCheck())->verify($read['coverage']);
        ($witnesses ?? new LexicalWitnessCheck())->verify(
            $read['terminals'],
            $read['terminal_exclusions'],
            $read['coverage'],
        );

        $this->catalog = $read;
    }

    /**
     * Names the lexer the catalog was generated from.
     *
     * @return string Engine name, e.g. "official"
     */
    public function sourceEngine(): string
    {
        return $this->catalog['source']['engine'];
    }

    /**
     * Names the entry point of the lexer the catalog was generated from.
     *
     * @return string Entry point name
     */
    public function sourceEntrypoint(): string
    {
        return $this->catalog['source']['entrypoint'];
    }

    /**
     * Reports whether the catalog can produce SQL for a terminal.
     *
     * @param string $terminal Grammar terminal to look for
     *
     * @return bool True when at least one witness is catalogued
     */
    public function supports(string $terminal): bool
    {
        return isset($this->catalog['terminals'][$terminal]);
    }

    /**
     * Reports whether the catalog leaves a terminal out on purpose.
     *
     * @param string $terminal Grammar terminal to look for
     *
     * @return bool True when the terminal is excluded with a reason
     */
    public function excludes(string $terminal): bool
    {
        return isset($this->catalog['terminal_exclusions'][$terminal]);
    }

    /**
     * Reports the lexing examples catalogued for a terminal.
     *
     * @param string $terminal Grammar terminal to look for
     *
     * @return list<Witness> The witnesses, empty when the terminal is not catalogued
     */
    public function witnesses(string $terminal): array
    {
        return $this->catalog['terminals'][$terminal] ?? [];
    }

    /**
     * Checks that the catalog classifies every terminal a grammar can produce.
     *
     * @param list<string> $terminals Terminals the grammar declares
     *
     * @throws LexicalCatalogException When a terminal is neither witnessed nor excluded
     */
    public function assertTerminalsCovered(array $terminals): void
    {
        $missing = [];
        foreach ($terminals as $terminal) {
            if (!$this->supports($terminal) && !$this->excludes($terminal)) {
                $missing[] = $terminal;
            }
        }

        if ($missing === []) {
            return;
        }

        sort($missing);

        throw LexicalCatalogException::missingTerminals($missing);
    }
}
