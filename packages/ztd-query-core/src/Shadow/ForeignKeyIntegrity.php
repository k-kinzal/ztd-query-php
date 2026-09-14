<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Row\RowMatch;

/**
 * Refuses a shadow in which some child references a parent that is not there.
 *
 * This is the check that runs once the cascades have finished, so that a
 * statement whose consequences left the shadow inconsistent is refused rather
 * than read back. A key with a null in it references nothing and is left
 * alone, which is what SQL says a partly-null key means.
 */
final class ForeignKeyIntegrity
{
    /**
     * @param TableDefinitionRegistry $registry Answers what a table declares
     * @param ForeignKeyEnds $ends Answers which parent columns a key points at
     * @param ParentKeyLookup $parents Reports whether a referenced row is there
     * @param RowMatch $rows Finds a row among rows
     */
    public function __construct(
        private readonly TableDefinitionRegistry $registry,
        private readonly ForeignKeyEnds $ends,
        private readonly ParentKeyLookup $parents = new ParentKeyLookup(),
        private readonly RowMatch $rows = new RowMatch(),
    ) {
    }

    /**
     * Refuses the shadow where a declared constraint no longer holds.
     *
     * @param ShadowStore $store Shadow to check
     * @param string $sql Statement being simulated, for the refusal
     *
     * @throws ForeignKeyViolationException When a child references a row that is not there
     */
    public function assertHolds(ShadowStore $store, string $sql): void
    {
        foreach ($this->registry->getAll() as $childTable => $definition) {
            foreach ($definition->foreignKeys as $constraintName => $foreignKey) {
                if (!$this->ends->areBalanced($foreignKey)) {
                    continue;
                }
                $referencedColumns = $this->ends->referencedColumns($foreignKey);

                foreach ($store->get($childTable) as $row) {
                    $values = $this->rows->valuesOf($row, $foreignKey->columns);
                    if ($values === null || in_array(null, $values, true)) {
                        continue;
                    }
                    if (!$this->parents->exists($store, $foreignKey->referencedTable, $referencedColumns, $values)) {
                        throw ForeignKeyViolationException::of(
                            $sql,
                            $childTable,
                            $constraintName,
                            $foreignKey->referencedTable,
                            $referencedColumns,
                        );
                    }
                }
            }
        }
    }
}
