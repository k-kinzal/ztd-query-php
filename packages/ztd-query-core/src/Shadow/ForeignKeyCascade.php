<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow;

use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Schema\Key\ForeignKeyDefinition;
use ZtdQuery\Schema\Key\ReferentialAction;
use ZtdQuery\Schema\TableDefinition;
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
 * @phpstan-import-type Row from TableDefinition
 * @phpstan-import-type RowValue from TableDefinition
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

        $constraint = new FollowedConstraint($childTable, $constraintName, $foreignKey, $sql);
        $referencedColumns = $this->ends->referencedColumns($foreignKey);
        $children = new CascadedChildren($store->get($childTable));
        foreach ($parent->updated as $parentChange) {
            $this->carryUpdate($children, $store, $constraint, $referencedColumns, $parentChange);
        }
        foreach ($parent->deleted as $parentRow) {
            $this->carryDelete($children, $store, $constraint, $referencedColumns, $parentRow);
        }
        if ($children->areUnchanged()) {
            return null;
        }

        $store->set($childTable, $children->rows());

        return new TableTransition($childTable, $children->deleted(), $children->updated());
    }

    /**
     * Carries a parent row that moved to the children that were holding it.
     *
     * A key the parent table still holds up somewhere else has not moved as
     * far as the child is concerned, so nothing is carried for it.
     *
     * @param CascadedChildren $children Child rows as the cascade has left them
     * @param ShadowStore $store Shadow the parent rows live in
     * @param FollowedConstraint $constraint Constraint being followed
     * @param list<string> $referencedColumns Parent columns the key points at
     * @param RowChange $parentChange What happened to the one parent row
     *
     * @throws ForeignKeyViolationException When the action forbids the statement
     */
    public function carryUpdate(
        CascadedChildren $children,
        ShadowStore $store,
        FollowedConstraint $constraint,
        array $referencedColumns,
        RowChange $parentChange,
    ): void {
        $foreignKey = $constraint->foreignKey;
        $oldValues = $this->rows->valuesOf($parentChange->before, $referencedColumns);
        $newValues = $this->rows->valuesOf($parentChange->after, $referencedColumns);
        if ($oldValues === null
            || $newValues === null
            || $this->parents->exists($store, $foreignKey->referencedTable, $referencedColumns, $oldValues)
        ) {
            return;
        }

        foreach ($children->rows() as $index => $row) {
            if (!$this->rows->carries($row, $foreignKey->columns, $oldValues)) {
                continue;
            }
            $children->replace($index, $this->applyAction($row, $newValues, $foreignKey->onUpdate, $constraint));
        }
    }

    /**
     * Carries a parent row that went to the children that were holding it.
     *
     * @param CascadedChildren $children Child rows as the cascade has left them
     * @param ShadowStore $store Shadow the parent rows live in
     * @param FollowedConstraint $constraint Constraint being followed
     * @param list<string> $referencedColumns Parent columns the key points at
     * @param Row $parentRow The parent row that went
     *
     * @throws ForeignKeyViolationException When the action forbids the statement
     */
    public function carryDelete(
        CascadedChildren $children,
        ShadowStore $store,
        FollowedConstraint $constraint,
        array $referencedColumns,
        array $parentRow,
    ): void {
        $foreignKey = $constraint->foreignKey;
        $oldValues = $this->rows->valuesOf($parentRow, $referencedColumns);
        if ($oldValues === null
            || $this->parents->exists($store, $foreignKey->referencedTable, $referencedColumns, $oldValues)
        ) {
            return;
        }

        $gone = [];
        foreach ($children->rows() as $index => $row) {
            if (!$this->rows->carries($row, $foreignKey->columns, $oldValues)) {
                continue;
            }
            if ($foreignKey->onDelete === ReferentialAction::Cascade) {
                $gone[] = $index;
                continue;
            }
            $children->replace($index, $this->applyAction($row, [], $foreignKey->onDelete, $constraint));
        }
        $children->remove($gone);
    }

    /**
     * Writes into a child row what the declared action says should be there.
     *
     * Only CASCADE and SET NULL let the statement stand. Every other action —
     * RESTRICT, NO ACTION, SET DEFAULT — means the parent was not free to move,
     * so the statement is refused rather than quietly rewritten.
     *
     * @param Row $row Child row to rewrite
     * @param list<RowValue> $values Values to carry over, empty when the parent went
     * @param ReferentialAction $action Action the constraint declares
     * @param FollowedConstraint $constraint Constraint being followed, for the refusal
     *
     * @return Row The child row as it should now be
     *
     * @throws ForeignKeyViolationException When the action forbids the statement
     */
    public function applyAction(
        array $row,
        array $values,
        ReferentialAction $action,
        FollowedConstraint $constraint,
    ): array {
        if ($action !== ReferentialAction::Cascade && $action !== ReferentialAction::SetNull) {
            throw ForeignKeyViolationException::of(
                $constraint->sql,
                $constraint->childTable,
                $constraint->constraintName,
                $constraint->foreignKey->referencedTable,
                $this->ends->referencedColumns($constraint->foreignKey),
            );
        }

        foreach ($constraint->foreignKey->columns as $index => $column) {
            $row[$column] = $action === ReferentialAction::SetNull ? null : ($values[$index] ?? null);
        }

        return $row;
    }
}
