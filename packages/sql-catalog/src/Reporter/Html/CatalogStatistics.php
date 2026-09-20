<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Resolution;
use SqlCatalog\Catalog\Severity;

/**
 * The counts the report is read through.
 *
 * Every page asks the same questions of the catalog — how far the analysis got,
 * what the statements do, which tables they touch — so the counting is done
 * once, over the whole catalog, rather than once per page over whatever that
 * page happens to hold.
 *
 * @visibility root
 */
final class CatalogStatistics
{
    private Catalog $catalog;

    /**
     * Counts the catalog once.
     */
    public function __construct(Catalog $catalog)
    {
        $this->catalog = $catalog;
    }

    /**
     * How many statements the catalog holds.
     */
    public function statements(): int
    {
        return $this->catalog->count();
    }

    /**
     * How many statements have no gaps at all.
     */
    public function resolved(): int
    {
        $resolved = 0;
        foreach ($this->catalog as $entry) {
            $resolved += $entry->isExact() ? 1 : 0;
        }

        return $resolved;
    }

    /**
     * How many statements the analyzer stopped short of, one way or another.
     */
    public function open(): int
    {
        $open = 0;
        foreach ($this->catalog as $entry) {
            $open += $entry->resolution()->isClosed() ? 0 : 1;
        }

        return $open;
    }

    /**
     * How many findings the catalog holds.
     */
    public function findings(): int
    {
        $findings = 0;
        foreach ($this->catalog as $entry) {
            $findings += count($entry->findings);
        }

        return $findings;
    }

    /**
     * How many statements carry a finding of at least that severity.
     */
    public function atLeast(Severity $severity): int
    {
        $count = 0;
        foreach ($this->catalog as $entry) {
            $count += $entry->severity()->atLeast($severity) && $entry->findings !== [] ? 1 : 0;
        }

        return $count;
    }

    /**
     * How many statements each resolution accounts for, in the order they are explained.
     *
     * @return array<string, int>
     */
    public function byResolution(): array
    {
        $counts = [];
        foreach (Resolution::cases() as $resolution) {
            $counts[$resolution->value] = 0;
        }
        foreach ($this->catalog as $entry) {
            $counts[$entry->resolution()->value]++;
        }

        return $counts;
    }

    /**
     * How many statements of each kind there are, most first.
     *
     * @return array<string, int>
     */
    public function byKind(): array
    {
        $counts = [];
        foreach ($this->catalog as $entry) {
            $counts[$entry->kind->value] = ($counts[$entry->kind->value] ?? 0) + 1;
        }
        arsort($counts);

        return $counts;
    }

    /**
     * How many findings each rule accounts for, most first.
     *
     * @return array<string, int>
     */
    public function byRule(): array
    {
        $counts = [];
        foreach ($this->catalog as $entry) {
            foreach ($entry->findings as $finding) {
                $counts[$finding->rule->value] = ($counts[$finding->rule->value] ?? 0) + 1;
            }
        }
        arsort($counts);

        return $counts;
    }

    /**
     * Every table the catalog names, with how it is used, most used first.
     *
     * @return list<array{name: string, reads: int, writes: int, statements: int}>
     */
    public function tables(): array
    {
        $tables = [];
        foreach ($this->catalog as $entry) {
            foreach ($entry->tables as $table) {
                $row = $tables[$table] ?? ['name' => $table, 'reads' => 0, 'writes' => 0, 'statements' => 0];
                $row['statements']++;
                $row[$entry->kind->isWrite() ? 'writes' : 'reads']++;
                $tables[$table] = $row;
            }
        }
        uasort($tables, static fn (array $left, array $right): int
            => [$right['statements'], $left['name']] <=> [$left['statements'], $right['name']]);

        return array_values($tables);
    }

    /**
     * Every file the catalog names, with what was found in it, in path order.
     *
     * @return array<string, array{statements: int, open: int, findings: int}>
     */
    public function files(): array
    {
        $files = [];
        foreach ($this->catalog as $entry) {
            $row = $files[$entry->site->file] ?? ['statements' => 0, 'open' => 0, 'findings' => 0];
            $row['statements']++;
            $row['open'] += $entry->resolution()->isClosed() ? 0 : 1;
            $row['findings'] += count($entry->findings);
            $files[$entry->site->file] = $row;
        }
        ksort($files);

        return $files;
    }

    /**
     * Every statement carrying a finding, worst first.
     *
     * @return list<CatalogEntry>
     */
    public function flagged(): array
    {
        $flagged = [];
        foreach ($this->catalog->sorted() as $entry) {
            if ($entry->findings !== []) {
                $flagged[] = $entry;
            }
        }
        usort($flagged, static fn (CatalogEntry $left, CatalogEntry $right): int
            => $right->severity()->rank() <=> $left->severity()->rank());

        return $flagged;
    }
}
