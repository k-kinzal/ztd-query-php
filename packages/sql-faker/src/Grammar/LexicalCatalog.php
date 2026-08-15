<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use RuntimeException;

/**
 * Validates the checked-in terminal catalog derived from an upstream lexer source.
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
    private array $catalog;

    /**
     * @param array<string, mixed> $catalog
     */
    public function __construct(array $catalog)
    {
        $this->catalog = $this->validateShape($catalog);
        $this->validateCoveragePartition();
        $this->validateWitnesses();
    }

    public function sourceEngine(): string
    {
        return $this->catalog['source']['engine'];
    }

    public function sourceEntrypoint(): string
    {
        return $this->catalog['source']['entrypoint'];
    }

    public function supports(string $terminal): bool
    {
        return isset($this->catalog['terminals'][$terminal]);
    }

    public function excludes(string $terminal): bool
    {
        return isset($this->catalog['terminal_exclusions'][$terminal]);
    }

    /**
     * @return list<array{id: string, sql: string, tokens: list<string>, units: list<string>, context_sql?: string}>
     */
    public function witnesses(string $terminal): array
    {
        return $this->catalog['terminals'][$terminal] ?? [];
    }

    /**
     * @param list<string> $terminals
     */
    public function assertTerminalsCovered(array $terminals): void
    {
        $missing = [];
        foreach ($terminals as $terminal) {
            if (!$this->supports($terminal) && !$this->excludes($terminal)) {
                $missing[] = $terminal;
            }
        }
        sort($missing);
        if ($missing !== []) {
            throw new RuntimeException(sprintf(
                'Upstream lexer catalog is missing grammar terminals: %s',
                implode(', ', $missing),
            ));
        }
    }

    /**
     * @param array<string, mixed> $catalog
     * @return Catalog
     */
    private function validateShape(array $catalog): array
    {
        if (!isset($catalog['source'])) {
            throw new RuntimeException('Invalid upstream lexical catalog shape.');
        }
        if (!is_array($catalog['source'])) {
            throw new RuntimeException('Invalid upstream lexical catalog shape.');
        }
        if (!isset($catalog['source']['engine'], $catalog['source']['entrypoint'])) {
            throw new RuntimeException('Invalid upstream lexical catalog shape.');
        }
        if (!is_string($catalog['source']['engine'])) {
            throw new RuntimeException('Invalid upstream lexical catalog shape.');
        }
        if (!is_string($catalog['source']['entrypoint'])) {
            throw new RuntimeException('Invalid upstream lexical catalog shape.');
        }
        if (!isset($catalog['terminals'])) {
            throw new RuntimeException('Invalid upstream lexical catalog shape.');
        }
        if (!is_array($catalog['terminals'])) {
            throw new RuntimeException('Invalid upstream lexical catalog shape.');
        }
        if (!isset($catalog['terminal_exclusions'])) {
            throw new RuntimeException('Invalid upstream lexical catalog shape.');
        }
        if (!is_array($catalog['terminal_exclusions'])) {
            throw new RuntimeException('Invalid upstream lexical catalog shape.');
        }
        if (!isset($catalog['coverage'])) {
            throw new RuntimeException('Invalid upstream lexical catalog shape.');
        }
        if (!is_array($catalog['coverage'])) {
            throw new RuntimeException('Invalid upstream lexical catalog shape.');
        }
        if (!isset($catalog['coverage']['units'], $catalog['coverage']['witnessed'], $catalog['coverage']['excluded'])) {
            throw new RuntimeException('Invalid upstream lexical catalog shape.');
        }
        if (!is_array($catalog['coverage']['units'])) {
            throw new RuntimeException('Invalid upstream lexical catalog shape.');
        }
        if (!is_array($catalog['coverage']['witnessed'])) {
            throw new RuntimeException('Invalid upstream lexical catalog shape.');
        }
        if (!is_array($catalog['coverage']['excluded'])) {
            throw new RuntimeException('Invalid upstream lexical catalog shape.');
        }
        $normalizedTerminals = [];
        foreach ($catalog['terminals'] as $terminal => $witnesses) {
            if (!is_string($terminal) || !is_array($witnesses)) {
                throw new RuntimeException('Invalid upstream lexical terminal catalog.');
            }
            $normalizedTerminals[$terminal] = [];
            foreach ($witnesses as $witness) {
                if (is_array($witness) && array_is_list($witness) && in_array(count($witness), [4, 5], true)) {
                    $witness = [
                        'id' => $witness[0],
                        'sql' => $witness[1],
                        'tokens' => $witness[2],
                        'units' => $witness[3],
                        ...isset($witness[4]) ? ['context_sql' => $witness[4]] : [],
                    ];
                }
                if (!is_array($witness)
                    || !isset($witness['id'], $witness['sql'], $witness['tokens'], $witness['units'])
                    || !is_string($witness['id']) || !is_string($witness['sql'])
                    || !is_array($witness['tokens']) || !array_is_list($witness['tokens'])
                    || !is_array($witness['units']) || !array_is_list($witness['units'])
                    || (isset($witness['context_sql']) && !is_string($witness['context_sql']))
                    || array_filter($witness['tokens'], static fn (mixed $token): bool => !is_string($token)) !== []
                    || array_filter($witness['units'], static fn (mixed $unit): bool => !is_string($unit)) !== []
                ) {
                    throw new RuntimeException("Invalid terminal witness: {$terminal}");
                }
                $normalizedTerminals[$terminal][] = $witness;
            }
        }
        $catalog['terminals'] = $normalizedTerminals;
        foreach ($catalog['terminal_exclusions'] as $terminal => $reason) {
            if (!is_string($terminal) || !is_string($reason) || $reason === '') {
                throw new RuntimeException('Terminal exclusions require string terminals and nonempty reasons.');
            }
        }
        if (!array_is_list($catalog['coverage']['units'])
            || array_filter(
                $catalog['coverage']['units'],
                static fn (mixed $unit): bool => !is_string($unit),
            ) !== []
        ) {
            throw new RuntimeException('Coverage units must be a list of strings.');
        }
        foreach ($catalog['coverage']['witnessed'] as $unit => $id) {
            if (!is_string($unit) || !is_string($id)) {
                throw new RuntimeException('Coverage witnesses require string units and identifiers.');
            }
        }
        foreach ($catalog['coverage']['excluded'] as $unit => $reason) {
            if (!is_string($unit) || !is_string($reason) || $reason === '') {
                throw new RuntimeException('Coverage exclusions require string units and nonempty reasons.');
            }
        }

        /** @var Catalog $catalog */
        return $catalog;
    }

    private function validateCoveragePartition(): void
    {
        if (count($this->catalog['coverage']['units']) !== count(array_unique($this->catalog['coverage']['units']))) {
            throw new RuntimeException('Upstream lexer coverage units must be unique.');
        }
        $overlap = array_intersect_key(
            $this->catalog['coverage']['witnessed'],
            $this->catalog['coverage']['excluded'],
        );
        if ($overlap !== []) {
            throw new RuntimeException('Upstream lexer coverage units cannot be both witnessed and excluded.');
        }

        $units = $this->catalog['coverage']['units'];
        sort($units);
        $classified = [
            ...array_keys($this->catalog['coverage']['witnessed']),
            ...array_keys($this->catalog['coverage']['excluded']),
        ];
        sort($classified);
        if ($units !== $classified) {
            throw new RuntimeException('Upstream lexer coverage units are not completely classified.');
        }
    }

    private function validateWitnesses(): void
    {
        $coverageUnits = array_fill_keys($this->catalog['coverage']['units'], true);
        $ids = [];
        foreach ($this->catalog['terminals'] as $terminal => $witnesses) {
            if (isset($this->catalog['terminal_exclusions'][$terminal])) {
                throw new RuntimeException('Terminal catalog and exclusions must be disjoint string keys.');
            }
            if ($witnesses === []) {
                throw new RuntimeException("Terminal catalog is empty: {$terminal}");
            }
            foreach ($witnesses as $witness) {
                if (isset($ids[$witness['id']])) {
                    throw new RuntimeException("Duplicate terminal witness identifier: {$witness['id']}");
                }
                $ids[$witness['id']] = $witness['id'];
                foreach ($witness['units'] as $unit) {
                    if (!isset($coverageUnits[$unit])) {
                        throw new RuntimeException("Terminal witness references an unknown coverage unit: {$unit}");
                    }
                }
            }
        }
        foreach ($this->catalog['coverage']['witnessed'] as $unit => $id) {
            if (!isset($ids[$id])) {
                throw new RuntimeException("Coverage unit references an unknown witness: {$unit}");
            }
            $found = false;
            foreach ($this->catalog['terminals'] as $witnesses) {
                foreach ($witnesses as $witness) {
                    if ($witness['id'] === $id && in_array($unit, $witness['units'], true)) {
                        $found = true;
                    }
                }
            }
            if (!$found) {
                throw new RuntimeException("Coverage witness does not reference its unit: {$unit}");
            }
        }
    }
}
