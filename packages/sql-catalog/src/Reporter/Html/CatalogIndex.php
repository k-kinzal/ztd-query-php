<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Catalog\Catalog;
use SqlCatalog\Catalog\CatalogEntry;
use SqlCatalog\Catalog\Severity;

/**
 * The catalog grouped along every route a reader can take to a statement.
 *
 * A reader rarely wants the whole catalog. They want the statements on one
 * table, the ones a class issues, the ones in a file, or the ones a function
 * writes with values it does not control. Each of those is a grouping of the
 * same entries, and the groupings are built once here so every page addresses
 * the same statement through the same keys.
 *
 * @visibility root
 */
final class CatalogIndex
{
    /**
     * @var list<CatalogEntry>
     */
    private array $entries;

    /**
     * @var array<string, list<CatalogEntry>>
     */
    private array $unreadFiles = [];

    /**
     * Groups a catalog, in the order the report lists statements.
     */
    public function __construct(Catalog $catalog)
    {
        $this->entries = $catalog->sorted()->entries();
        foreach ($catalog->problems() as $problem) {
            if ($catalog->source($problem->file) !== null) {
                $this->unreadFiles[$problem->file] = [];
            }
        }
    }

    /**
     * The statements, in reporting order.
     *
     * @return list<CatalogEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * The statements naming each table, most named first.
     *
     * @return array<string, list<CatalogEntry>>
     */
    public function byTable(): array
    {
        $grouped = [];
        foreach ($this->entries as $entry) {
            foreach ($entry->tables as $table) {
                $grouped[$table][] = $entry;
            }
        }
        $counts = array_map('count', $grouped);
        uksort($grouped, static fn (string $left, string $right): int
            => [$counts[$right] ?? 0, $left] <=> [$counts[$left] ?? 0, $right]);

        return $grouped;
    }

    /**
     * The statements each function issues, in the order the functions are met.
     *
     * @return array<string, list<CatalogEntry>>
     */
    public function byFunction(): array
    {
        $grouped = [];
        foreach ($this->entries as $entry) {
            $grouped[$entry->site->function][] = $entry;
        }

        return $grouped;
    }

    /**
     * The statements each class issues through its methods, in name order.
     *
     * @return array<string, list<CatalogEntry>>
     */
    public function byClass(): array
    {
        $grouped = [];
        foreach ($this->entries as $entry) {
            $class = Scope::of($entry->site->function)->class;
            if ($class !== null) {
                $grouped[$class][] = $entry;
            }
        }
        ksort($grouped, SORT_STRING | SORT_FLAG_CASE);

        return $grouped;
    }

    /**
     * The statements issued under each namespace, the global one first.
     *
     * @return array<string, list<CatalogEntry>>
     */
    public function byNamespace(): array
    {
        $grouped = [];
        foreach ($this->entries as $entry) {
            $grouped[Scope::of($entry->site->function)->namespace][] = $entry;
        }
        ksort($grouped, SORT_STRING | SORT_FLAG_CASE);

        return $grouped;
    }

    /**
     * The statements written in each file, in path order.
     *
     * @return array<string, list<CatalogEntry>>
     */
    public function byFile(): array
    {
        $grouped = $this->unreadFiles;
        foreach ($this->entries as $entry) {
            $grouped[$entry->site->file][] = $entry;
        }
        ksort($grouped, SORT_STRING);

        return $grouped;
    }

    /**
     * The files under each directory, in path order.
     *
     * @return array<string, list<string>>
     */
    public function byDirectory(): array
    {
        $grouped = [];
        foreach (array_keys($this->byFile()) as $file) {
            $slash = strrpos($file, '/');
            $grouped[$slash === false ? '' : substr($file, 0, $slash)][] = $file;
        }

        return $grouped;
    }

    /**
     * The statements each rule reported on, most reported first.
     *
     * @return array<string, list<CatalogEntry>>
     */
    public function byRule(): array
    {
        $grouped = [];
        foreach ($this->entries as $entry) {
            foreach ($entry->findings as $finding) {
                $grouped[$finding->rule->value][] = $entry;
            }
        }
        $counts = array_map('count', $grouped);
        uksort($grouped, static fn (string $left, string $right): int
            => [$counts[$right] ?? 0, $left] <=> [$counts[$left] ?? 0, $right]);

        return $grouped;
    }

    /**
     * How a set of statements uses what it names.
     *
     * @param list<CatalogEntry> $entries
     * @return array{reads: int, writes: int, schema: int, other: int, attention: int}
     */
    public function usage(array $entries): array
    {
        $usage = ['reads' => 0, 'writes' => 0, 'schema' => 0, 'other' => 0, 'attention' => 0];
        foreach ($entries as $entry) {
            $usage[$this->usageOf($entry)]++;
            $usage['attention'] += $entry->severity()->atLeast(Severity::Medium) && $entry->findings !== [] ? 1 : 0;
        }

        return $usage;
    }

    /**
     * Which use a statement is counted as.
     *
     * @return 'reads'|'writes'|'schema'|'other'
     */
    public function usageOf(CatalogEntry $entry): string
    {
        if ($entry->kind->isWrite() && !$entry->kind->isSchema()) {
            return 'writes';
        }
        if ($entry->kind->isSchema()) {
            return 'schema';
        }

        return $entry->kind->value === 'select' ? 'reads' : 'other';
    }

    /**
     * How often each table is named by a set of statements, most named first.
     *
     * @param list<CatalogEntry> $entries
     * @return array<string, int>
     */
    public function tablesOf(array $entries): array
    {
        $counts = [];
        foreach ($entries as $entry) {
            foreach ($entry->tables as $table) {
                $counts[$table] = ($counts[$table] ?? 0) + 1;
            }
        }

        return $this->mostFirst($counts);
    }

    /**
     * How many statements each function issues among a set, most first.
     *
     * @param list<CatalogEntry> $entries
     * @return array<string, int>
     */
    public function functionsOf(array $entries): array
    {
        $counts = [];
        foreach ($entries as $entry) {
            $counts[$entry->site->function] = ($counts[$entry->site->function] ?? 0) + 1;
        }

        return $this->mostFirst($counts);
    }

    /**
     * The tables named alongside one table, most often first.
     *
     * @return array<string, int>
     */
    public function alongside(string $table): array
    {
        $counts = [];
        foreach ($this->byTable()[$table] ?? [] as $entry) {
            foreach ($entry->tables as $other) {
                if ($other !== $table) {
                    $counts[$other] = ($counts[$other] ?? 0) + 1;
                }
            }
        }

        return $this->mostFirst($counts);
    }

    /**
     * The functions issuing statements that need attention, worst first.
     *
     * @return list<array{function: string, file: string, high: int, medium: int}>
     */
    public function hotspots(): array
    {
        $spots = [];
        foreach ($this->entries as $entry) {
            $severity = $entry->severity();
            if ($entry->findings === [] || !$severity->atLeast(Severity::Medium)) {
                continue;
            }
            $spot = $spots[$entry->site->function] ?? ['function' => $entry->site->function, 'file' => $entry->site->file, 'high' => 0, 'medium' => 0];
            $spot[$severity === Severity::High ? 'high' : 'medium']++;
            $spots[$entry->site->function] = $spot;
        }
        usort($spots, static fn (array $left, array $right): int
            => [$right['high'], $right['medium'], $left['function']] <=> [$left['high'], $left['medium'], $right['function']]);

        return $spots;
    }

    /**
     * Counts sorted highest first, ties in name order.
     *
     * @param array<string, int> $counts
     * @return array<string, int>
     */
    public function mostFirst(array $counts): array
    {
        uksort($counts, static fn (string $left, string $right): int
            => [$counts[$right], $left] <=> [$counts[$left], $right]);

        return $counts;
    }
}
