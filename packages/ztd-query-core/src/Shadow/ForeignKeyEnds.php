<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Schema\Key\ForeignKeyDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;

/**
 * Answers which columns of the parent a foreign key points at.
 *
 * A key may name them, or leave them out and mean the parent's primary key, so
 * the two spellings are resolved to one answer here rather than at every place
 * that has to follow the key.
 */
final class ForeignKeyEnds
{
    /**
     * @param TableDefinitionRegistry $registry Answers what a table declares
     */
    public function __construct(private readonly TableDefinitionRegistry $registry)
    {
    }

    /**
     * Answers the parent columns a key points at.
     *
     * @param ForeignKeyDefinition $foreignKey Key to follow
     *
     * @return list<string> The columns, or nothing when the parent is unknown
     */
    public function referencedColumns(ForeignKeyDefinition $foreignKey): array
    {
        if ($foreignKey->referencedColumns !== []) {
            return $foreignKey->referencedColumns;
        }

        $referencedTable = $this->registry->get($foreignKey->referencedTable);
        if ($referencedTable === null) {
            return [];
        }

        return $referencedTable->primaryKeys;
    }

    /**
     * Reports whether a key names as many parent columns as child columns.
     *
     * A key that does not cannot be followed at all: there is no pairing to
     * read the child values against.
     *
     * @param ForeignKeyDefinition $foreignKey Key to test
     *
     * @return bool True when the two ends have the same number of columns
     */
    public function areBalanced(ForeignKeyDefinition $foreignKey): bool
    {
        return count($foreignKey->columns) === count($this->referencedColumns($foreignKey));
    }
}
