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
     */
    public function __construct(public readonly string $name, public readonly KeyKind $kind, public readonly array $columns, public readonly array $prefixes = [])
    {
    }

    /**
     * Tells whether the key refuses duplicates.
     */
    public function unique(): bool
    {
        return $this->kind === KeyKind::Primary || $this->kind === KeyKind::Unique;
    }
}
