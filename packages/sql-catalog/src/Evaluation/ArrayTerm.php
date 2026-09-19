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
        $parts = [];
        foreach ($this->entries as $entry) {
            $parts[] = $entry->signature();
        }

        return 'array:' . ($this->complete ? 'all' : 'some') . ':' . implode(',', $parts);
    }
}
