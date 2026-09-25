<?php

declare(strict_types=1);

namespace SqlCatalog\Evaluation;

use Override;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

/**
 * An array literal the analyzer reconstructed element by element.
 *
 * Bound parameters reach the database as an array, so keeping element order and
 * keys is what lets the catalog line placeholders up with the values they take.
 *
 * @visibility root
 */
final class ArrayTerm implements Term
{
    private ?string $signature = null;

    /**
     * @param list<ArrayEntry> $entries Elements in source order
     * @param bool $complete Whether every element of the runtime array is present
     */
    public function __construct(
        public readonly array $entries,
        public readonly bool $complete = true,
    ) {
    }

    /**
     * The elements that take successive integer keys, in order.
     *
     * @return list<Domain>
     */
    public function positional(): array
    {
        $values = [];
        foreach ($this->entries as $entry) {
            if ($entry->key === null) {
                $values[] = $entry->value;
                continue;
            }
            if (is_int($entry->scalarKey())) {
                $values[] = $entry->value;
            }
        }

        return $values;
    }

    /**
     * The elements reached by a string key.
     *
     * @return array<string, Domain>
     */
    public function named(): array
    {
        $values = [];
        foreach ($this->entries as $entry) {
            $key = $entry->scalarKey();
            if (is_string($key)) {
                $values[$key] = $entry->value;
            }
        }

        return $values;
    }

    /**
     * The element stored under a key, or null when the array does not hold one.
     */
    public function element(string|int|float|bool|null $key): ?Domain
    {
        if (is_bool($key) || $key === null) {
            return null;
        }
        $wanted = (string) $key;
        $position = 0;
        foreach ($this->entries as $entry) {
            $entryKey = $entry->key === null ? $position++ : $entry->scalarKey();
            if ($entryKey !== null && (string) $entryKey === $wanted) {
                return $entry->value;
            }
        }

        return null;
    }

    /**
     * Whatever one element can be, or null when the array holds none.
     *
     * The union of every element covers the value whichever key selects it,
     * which is what turns `self::TABLES[$kind]` with an unknown `$kind` into
     * one statement per table instead of a gap. An array known only in part
     * may hold elements the walk never saw, so the union keeps a gap for them.
     */
    public function anyValue(Origin $origin, ?string $expression = null): ?Domain
    {
        $values = null;
        foreach ($this->entries as $entry) {
            $values = $values === null ? $entry->value : $values->union($entry->value);
        }
        if ($values === null || $this->complete) {
            return $values;
        }

        return $values->union(Domain::opaque(TypeShape::unknown(), $origin, $expression));
    }

    /**
     * An array never resolves to text, so it becomes a gap.
     */
    #[Override]
    public function toPattern(): TextPattern
    {
        return TextPattern::fromHole(new TextHole(Origin::Unresolved, TypeShape::of(['array'])));
    }

    /**
     * Always `array`.
     */
    #[Override]
    public function type(): TypeShape
    {
        return TypeShape::of(['array']);
    }

    /**
     * The elements, in order, tagged as complete or partial.
     */
    #[Override]
    public function signature(): string
    {
        if ($this->signature !== null) {
            return $this->signature;
        }
        $parts = [];
        foreach ($this->entries as $entry) {
            $parts[] = $entry->signature();
        }

        return $this->signature = 'array:' . ($this->complete ? 'all' : 'some') . ':' . implode(',', $parts);
    }
}
