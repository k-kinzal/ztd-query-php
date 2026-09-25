<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Catalog;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/**
 * Every statement the analyzed application can issue, and what stopped the analyzer.
 *
 * @implements IteratorAggregate<int, CatalogEntry>
 *
 * @visibility public
 * @example Reading an empty catalog
 *     $catalog = new \SqlCatalog\Core\Catalog\Catalog();
 *     $catalog->count() // => 0
 *     $catalog->entries() // => []
 */
final class Catalog implements Countable, IteratorAggregate
{
    /**
     * @var list<CatalogEntry>
     */
    private array $entries;

    /**
     * @var list<AnalysisProblem>
     */
    private array $problems;

    /**
     * @var array<string, string>
     */
    private array $sources;

    /**
     * @param list<CatalogEntry> $entries The statements found, in reporting order
     * @param list<AnalysisProblem> $problems The files that could not be analyzed
     * @param array<string, string> $sources Source snapshots keyed by the reported file path
     */
    public function __construct(array $entries = [], array $problems = [], array $sources = [])
    {
        $this->entries = $entries;
        $this->problems = $problems;
        $this->sources = $sources;
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
     * The files that could not be analyzed.
     *
     * @return list<AnalysisProblem>
     */
    public function problems(): array
    {
        return $this->problems;
    }

    /**
     * The source as it was analyzed, or null when no snapshot was supplied.
     */
    public function source(string $file): ?string
    {
        return $this->sources[$file] ?? null;
    }

    /**
     * The statement of that identifier, or null when the catalog has none.
     */
    public function find(string $id): ?CatalogEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->id === $id) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * A catalog holding only the statements the given test keeps.
     *
     * @param callable(CatalogEntry): bool $keep
     */
    public function filter(callable $keep): self
    {
        return new self(array_values(array_filter($this->entries, $keep)), $this->problems, $this->sources);
    }

    /**
     * A catalog holding these statements and the given ones.
     */
    public function merge(self $other): self
    {
        return new self(
            array_merge($this->entries, $other->entries),
            array_merge($this->problems, $other->problems),
            array_replace($this->sources, $other->sources),
        );
    }

    /**
     * The statements sorted by where they are issued, so two runs compare cleanly.
     */
    public function sorted(): self
    {
        $entries = $this->entries;
        usort($entries, static function (CatalogEntry $left, CatalogEntry $right): int {
            return [$left->site->file, $left->site->line, $left->id]
                <=> [$right->site->file, $right->site->line, $right->id];
        });

        $problems = $this->problems;
        usort($problems, static fn (AnalysisProblem $left, AnalysisProblem $right): int => $left->file <=> $right->file);

        return new self($entries, $problems, $this->sources);
    }

    /**
     * How many statements the catalog holds.
     */
    #[Override]
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * The statements, so a catalog can be walked directly.
     *
     * @return Traversable<int, CatalogEntry>
     */
    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->entries);
    }
}
