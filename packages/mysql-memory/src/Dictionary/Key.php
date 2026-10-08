<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

/**
 * An index of a stored table: its name, kind, and the columns it covers.
 *
 * A primary or unique key refuses two rows with equal values in all its columns; a row with
 * NULL in a column of a unique key never conflicts.
 *
 * @visibility MySqlMemory
 */
final class Key
{
    /**
     * @param string $name The index name; PRIMARY for the primary key
     * @param KeyKind $kind The kind of index
     * @param list<int> $columns The positions of the indexed columns, in index order
     * @param list<int|null> $prefixes The number of leading characters indexed of each column, or null for the whole value
     * @param list<bool> $descending Whether each column is indexed in descending order; a missing entry is ascending
     */
    public function __construct(public readonly string $name, public readonly KeyKind $kind, public readonly array $columns, public readonly array $prefixes = [], public readonly array $descending = [])
    {
    }

    /**
     * Tells whether another key indexes the same columns as this one in the same way: of the same kind, with the same prefixes and the same order.
     */
    public function duplicates(self $other): bool
    {
        if ($this->kind !== $other->kind || $this->columns !== $other->columns) {
            return false;
        }
        foreach (array_keys($this->columns) as $index) {
            if (($this->prefixes[$index] ?? null) !== ($other->prefixes[$index] ?? null) || ($this->descending[$index] ?? false) !== ($other->descending[$index] ?? false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Tells whether the key refuses duplicates.
     */
    public function unique(): bool
    {
        return $this->kind === KeyKind::Primary || $this->kind === KeyKind::Unique;
    }
}
