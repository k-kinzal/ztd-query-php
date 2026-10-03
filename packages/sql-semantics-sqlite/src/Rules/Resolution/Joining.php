<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Resolution;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Storages;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinStep;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinUsing;
use SqlSemantics\Resolution\ImplicitSlot;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Combines the visible relations of the two sides of a join.
 *
 * Rule: SQLITE-JOIN-001. A LEFT join can extend the rows of its right side
 * with NULLs, a RIGHT join those of everything to its left, a FULL join
 * both; the columns of an extended side can be NULL in the occurrence and
 * keep their declaration. For each column named by USING, or common to both
 * sides of a NATURAL join, the column of the right side is merged into the
 * leftmost column of that name: an unqualified name and the star see the
 * merged column once. Under a RIGHT or FULL join the merged column is the
 * first non-NULL of both, so its type is the choice over both. A USING
 * column that one side certainly lacks is reported.
 * Source: https://sqlite.org/lang_select.html#the_from_clause. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class Joining
{
    /**
     * Joins the relations of a step to the relations to its left.
     *
     * @param list<VisibleRelation> $left The relations to the left, in written order
     * @param list<VisibleRelation> $right The relations of the joined term
     * @return list<VisibleRelation>
     */
    public function join(Derivation $derivation, array $left, array $right, JoinStep $step): array
    {
        $merged = $step->constraint instanceof JoinUsing ? $step->constraint->columns : [];
        if ($step->operator->natural()) {
            foreach ($right as $relation) {
                foreach ($relation->shape->slots as $position => $slot) {
                    if ($slot->name !== null && !in_array($position, $relation->hidden, true) && $this->locate($left, $slot->name->value, $derivation) !== null) {
                        $merged[] = $slot->name;
                    }
                }
            }
        }
        $outer = $step->operator->right();
        foreach ($merged as $column) {
            $from = $this->locate($left, $column->value, $derivation);
            $into = $this->locate($right, $column->value, $derivation);
            if ($from === null || $into === null) {
                if ($this->closed($from === null ? $left : $right)) {
                    $derivation->report(new MissingColumn($column));
                }
                continue;
            }
            $hidden = $right[$into[0]];
            $right = $this->replaced($right, $into[0], new VisibleRelation($hidden->relation, $hidden->shape, $hidden->alias, $hidden->name, [...$hidden->hidden, $into[1]], $hidden->implicit));
            if ($outer) {
                $left = $this->replaced($left, $from[0], $this->coalesced($left[$from[0]], $from[1], $hidden->shape->slots[$into[1]]));
            }
        }

        return [...($outer ? $this->extend($left) : $left), ...($step->operator->left() ? $this->extend($right) : $right)];
    }

    /**
     * Answers the relations with the one at a position replaced.
     *
     * @param list<VisibleRelation> $relations
     * @return list<VisibleRelation>
     */
    public function replaced(array $relations, int $position, VisibleRelation $relation): array
    {
        $result = [];
        foreach ($relations as $index => $item) {
            $result[] = $index === $position ? $relation : $item;
        }

        return $result;
    }

    /**
     * Answers a relation whose merged column takes the first non-NULL of its own slot and the slot merged into it.
     */
    public function coalesced(VisibleRelation $kept, int $position, OutputSlot $other): VisibleRelation
    {
        $slots = [];
        foreach ($kept->shape->slots as $index => $slot) {
            $slots[] = $index === $position ? new OutputSlot($slot->name, (new Storages())->either([$slot->type, $other->type]), $slot->nullability->propagate($other->nullability), null, $slot) : $slot;
        }

        return new VisibleRelation($kept->relation, new RowShape($slots, $kept->shape->missing), $kept->alias, $kept->name, $kept->hidden, $kept->implicit);
    }

    /**
     * Finds the leftmost column an unqualified name denotes among relations: the relation index and the slot position, or null.
     *
     * @param list<VisibleRelation> $relations
     * @return array{int, int}|null
     */
    public function locate(array $relations, string $column, Derivation $derivation): ?array
    {
        foreach ($relations as $index => $relation) {
            if (in_array(ColumnResolver::QUALIFIED_ONLY, $relation->hidden, true)) {
                continue;
            }
            foreach ($relation->shape->slots as $position => $slot) {
                if ($slot->name !== null && !in_array($position, $relation->hidden, true) && $derivation->context->columnNames->equal($slot->name->value, $column)) {
                    return [$index, $position];
                }
            }
        }

        return null;
    }

    /**
     * Tells whether every column of the relations is known by name.
     *
     * @param list<VisibleRelation> $relations
     */
    public function closed(array $relations): bool
    {
        foreach ($relations as $relation) {
            if (!$relation->shape->complete()) {
                return false;
            }
            foreach ($relation->shape->slots as $slot) {
                if ($slot->name === null) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Makes every column of the relations able to be NULL in the occurrence, keeping the slot it extends as its origin.
     *
     * @param list<VisibleRelation> $relations
     * @return list<VisibleRelation>
     */
    public function extend(array $relations): array
    {
        $extended = [];
        foreach ($relations as $relation) {
            $slots = [];
            foreach ($relation->shape->slots as $slot) {
                $slots[] = $slot->nullability === Nullability::Nullable ? $slot : new OutputSlot($slot->name, $slot->type, Nullability::Nullable, null, $slot);
            }
            $implicit = [];
            foreach ($relation->implicit as $slot) {
                $implicit[] = new ImplicitSlot($slot->names, new OutputSlot($slot->slot->name, $slot->slot->type, Nullability::Nullable, null, $slot->slot));
            }
            $extended[] = new VisibleRelation($relation->relation, new RowShape($slots, $relation->shape->missing), $relation->alias, $relation->name, $relation->hidden, $implicit);
        }

        return $extended;
    }
}
