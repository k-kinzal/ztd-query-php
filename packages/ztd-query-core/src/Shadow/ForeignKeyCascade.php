<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Schema\ForeignKeyDefinition;
use ZtdQuery\Schema\ReferentialAction;
use ZtdQuery\Shadow\Row\RowChange;
use ZtdQuery\Shadow\Row\RowMatch;
use ZtdQuery\Shadow\Row\TableTransition;

/**
 * Applies to one child table what happened to its parent.
 *
 * A parent row that moved only reaches its children where nothing else holds
 * the key up, so each candidate is checked against the parent table first. An
 * update carries new values to write; a delete carries none, and the action
 * decides whether the child goes with it, is set to null, or refuses the
 * statement outright.
 *
 * What comes back is what happened to the child table, in the same shape as
 * what happened to the parent, which is what lets the caller keep following
 * the constraints outward without knowing how deep it is.
 *
 * @phpstan-import-type Row from StatementInterface
 * @phpstan-import-type RowValue from StatementInterface
 */
final class ForeignKeyCascade
{
    /**
     * @param ForeignKeyEnds $ends Answers which parent columns a key points at
     * @param ParentKeyLookup $parents Reports whether a referenced row is still there
     * @param RowMatch $rows Finds a row among rows
     */
    public function __construct(
        private readonly ForeignKeyEnds $ends,
        private readonly ParentKeyLookup $parents = new ParentKeyLookup(),
        private readonly RowMatch $rows = new RowMatch(),
    ) {
    }

    /**
     * Answers what one constraint makes of what happened to the parent.
     *
     * @param ShadowStore $store Shadow the child rows live in, written back in place
     * @param string $childTable Table holding the key
     * @param string $constraintName Constraint being followed, for the refusal
     * @param ForeignKeyDefinition $foreignKey Constraint being followed
     * @param TableTransition $parent What happened to the parent table
     * @param string $sql Statement being simulated, for the refusal
     *
     * @return TableTransition|null What happened to the child, or null when nothing did
     *
     * @throws ForeignKeyViolationException When the action forbids the statement
     */
    public function of(
        ShadowStore $store,
        string $childTable,
        string $constraintName,
        ForeignKeyDefinition $foreignKey,
        TableTransition $parent,
        string $sql,
    ): ?TableTransition {
        if (!$this->ends->areBalanced($foreignKey)) {
            return null;
        }

        $referencedColumns = $this->ends->referencedColumns($foreignKey);
        $rows = $store->get($childTable);
        $updatedChildren = [];

        foreach ($parent->updated as $parentChange) {
            $oldValues = $this->rows->valuesOf($parentChange->before, $referencedColumns);
            $newValues = $this->rows->valuesOf($parentChange->after, $referencedColumns);
            if ($oldValues === null || $newValues === null) {
                continue;
            }
            if ($this->parents->exists($store, $foreignKey->referencedTable, $referencedColumns, $oldValues)) {
                continue;
            }

            foreach ($rows as $index => $row) {
                if (!$this->rows->carries($row, $foreignKey->columns, $oldValues)) {
                    continue;
                }
                $updated = $this->applyAction(
                    $row,
                    $foreignKey->columns,
                    $newValues,
                    $foreignKey->onUpdate,
                    $childTable,
                    $constraintName,
                    $foreignKey,
                    $sql,
                );
                $rows[$index] = $updated;
                $updatedChildren[] = new RowChange($row, $updated);
            }
        }

        $deletedChildren = [];
        foreach ($parent->deleted as $parentRow) {
            $oldValues = $this->rows->valuesOf($parentRow, $referencedColumns);
            if ($oldValues === null
                || $this->parents->exists($store, $foreignKey->referencedTable, $referencedColumns, $oldValues)
            ) {
                continue;
            }

            $remaining = [];
            foreach ($rows as $row) {
                if (!$this->rows->carries($row, $foreignKey->columns, $oldValues)) {
                    $remaining[] = $row;
                    continue;
                }
                if ($foreignKey->onDelete === ReferentialAction::Cascade) {
                    $deletedChildren[] = $row;
                    continue;
                }
                $updated = $this->applyAction(
                    $row,
                    $foreignKey->columns,
                    [],
                    $foreignKey->onDelete,
                    $childTable,
                    $constraintName,
                    $foreignKey,
                    $sql,
                );
                $remaining[] = $updated;
                $updatedChildren[] = new RowChange($row, $updated);
            }
            $rows = $remaining;
        }

        if ($deletedChildren === [] && $updatedChildren === []) {
            return null;
        }

        $store->set($childTable, $rows);

        return new TableTransition($childTable, $deletedChildren, $updatedChildren);
    }

    /**
     * Writes into a child row what the declared action says should be there.
     *
     * Only CASCADE and SET NULL let the statement stand. Every other action —
     * RESTRICT, NO ACTION, SET DEFAULT — means the parent was not free to move,
     * so the statement is refused rather than quietly rewritten.
     *
     * @param Row $row Child row to rewrite
     * @param non-empty-list<string> $columns Child columns holding the key
     * @param list<RowValue> $values Values to carry over, empty when the parent went
     * @param ReferentialAction $action Action the constraint declares
     * @param string $childTable Table holding the key, for the refusal
     * @param string $constraintName Constraint being followed, for the refusal
     * @param ForeignKeyDefinition $foreignKey Constraint being followed, for the refusal
     * @param string $sql Statement being simulated, for the refusal
     *
     * @return Row The child row as it should now be
     *
     * @throws ForeignKeyViolationException When the action forbids the statement
     */
    public function applyAction(
        array $row,
        array $columns,
        array $values,
        ReferentialAction $action,
        string $childTable,
        string $constraintName,
        ForeignKeyDefinition $foreignKey,
        string $sql,
    ): array {
        if ($action !== ReferentialAction::Cascade && $action !== ReferentialAction::SetNull) {
            throw ForeignKeyViolationException::of(
                $sql,
                $childTable,
                $constraintName,
                $foreignKey->referencedTable,
                $this->ends->referencedColumns($foreignKey),
            );
        }

        foreach ($columns as $index => $column) {
            $row[$column] = $action === ReferentialAction::SetNull ? null : ($values[$index] ?? null);
        }

        return $row;
    }
}
