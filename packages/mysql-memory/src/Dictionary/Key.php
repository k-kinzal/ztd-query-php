<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;

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
     * @param bool $generated Whether the server added the index for a foreign key, which an index another foreign key needs replaces
     * @param bool $visible Whether the optimizer can use the index without use_invisible_indexes
     * @param IndexAlgorithm|null $algorithm The explicit index structure the engine retained
     */
    public function __construct(public readonly string $name, public readonly KeyKind $kind, public readonly array $columns, public readonly array $prefixes = [], public readonly array $descending = [], public readonly bool $generated = false, public readonly bool $visible = true, public readonly ?IndexAlgorithm $algorithm = null)
    {
    }

    /**
     * Resolves the structure: MEMORY defaults to HASH, other engines use BTREE.
     * A legacy HASH declaration on InnoDB or MyISAM still uses BTREE.
     */
    public function access(string $engine): IndexAlgorithm
    {
        $memory = in_array(strtoupper($engine), ['MEMORY', 'HEAP'], true);
        if (!$memory && $this->algorithm === IndexAlgorithm::Hash) {
            return IndexAlgorithm::Btree;
        }

        return $this->algorithm ?? ($memory ? IndexAlgorithm::Hash : IndexAlgorithm::Btree);
    }

    /**
     * Tells whether another key indexes the same columns as this one in the same way: of the same kind, with the same prefixes and the same order.
     */
    public function duplicates(self $other, string $engine = 'InnoDB'): bool
    {
        if ($this->kind !== $other->kind || $this->columns !== $other->columns || $this->access($engine) !== $other->access($engine)) {
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
