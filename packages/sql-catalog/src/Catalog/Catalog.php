<?php

declare(strict_types=1);

namespace SqlCatalog\Catalog;

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
 * @example Reading a catalog produced from source
 *     $analyzer = new \SqlCatalog\Analyzer();
 *     $catalog = $analyzer->analyzeSource(['app.php' => '<?php $pdo = new PDO("sqlite::memory:"); $pdo->query("SELECT 1");']);
 *     $catalog->count() // => 1
 *     $catalog->entries()[0]->sql() // => 'SELECT 1'
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
     * @param list<CatalogEntry> $entries The statements found, in reporting order
     * @param list<AnalysisProblem> $problems The files that could not be analyzed
     */
    public function __construct(array $entries = [], array $problems = [])
    {
        $this->entries = $entries;
        $this->problems = $problems;
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
        return new self(array_values(array_filter($this->entries, $keep)), $this->problems);
    }

    /**
     * A catalog holding these statements and the given ones.
     */
    public function merge(self $other): self
    {
        return new self(
            array_merge($this->entries, $other->entries),
            array_merge($this->problems, $other->problems),
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

        return new self($entries, $problems);
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
