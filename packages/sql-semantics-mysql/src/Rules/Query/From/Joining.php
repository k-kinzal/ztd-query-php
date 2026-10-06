<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\From;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Resolution\ImplicitSlot;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Combines the two operands of a join: merged columns, the order `*` selects and NULL extension.
 *
 * Rule: MYSQL-JOIN-COLUMNS-001. A LEFT join can extend the rows of its
 * right operand with NULLs and a RIGHT join those of its left operand; the
 * columns of an extended operand can be NULL in the occurrence and keep
 * their declaration. Each column named by USING, and for a NATURAL join each
 * column name both operands select with `*`, is merged: an unqualified name
 * and `*` see one column, the column of the left operand, or of the right
 * operand for a RIGHT join, which is the value COALESCE of both yields
 * there; a qualified name still reaches the column of either operand. `*`
 * selects the merged columns first, in the order of the operand they are
 * taken from, then the other columns of that operand, then those of the
 * other operand (a RIGHT join is read as the LEFT join of its swapped
 * operands). A USING column that an operand certainly lacks, or selects
 * twice, is reported. The common columns of a NATURAL join are not known
 * while an operand has undeclared columns, so nothing is merged then.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/join.html ("Natural joins
 * and joins with USING, including outer join variants, are processed
 * according to the SQL:2003 standard"). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Joining
{
    /**
     * Joins the right operand to the left one.
     */
    public function join(Derivation $derivation, JoinedInput $left, JoinedInput $right, JoinedTable $join): JoinedInput
    {
        $offset = count($left->visible);
        $visible = [...$left->visible, ...$right->visible];
        $first = $left->star;
        $second = [];
        foreach ($right->star as [$relation, $position]) {
            $second[] = [$relation + $offset, $position];
        }
        if ($join->operator->keepsRight()) {
            [$first, $second] = [$second, $first];
        }
        $merged = [];
        foreach ($this->names($derivation, $join, $left, $right) as $name) {
            $kept = $this->locate($derivation, $visible, $first, $name, $join->operator->keepsRight() ? $right : $left);
            $dropped = $this->locate($derivation, $visible, $second, $name, $join->operator->keepsRight() ? $left : $right);
            if ($kept === null || $dropped === null) {
                continue;
            }
            $merged[] = $first[$kept];
            $relation = $visible[$second[$dropped][0]];
            $visible[$second[$dropped][0]] = new VisibleRelation($relation->relation, $relation->shape, $relation->alias, $relation->name, [...$relation->hidden, $second[$dropped][1]], $relation->implicit);
            unset($first[$kept], $second[$dropped]);
            $first = array_values($first);
            $second = array_values($second);
        }
        $extended = [];
        foreach ($visible as $index => $relation) {
            $outer = $index < $offset ? $join->operator->keepsRight() : $join->operator->keepsLeft();
            $extended[] = $outer ? $this->extend($relation) : $relation;
        }

        return JoinedInput::of($extended, [...$merged, ...$first, ...$second]);
    }

    /**
     * Answers the names of the merged columns: the USING list, or for a natural join the names both operands select, in the order of the operand they are taken from.
     *
     * @return list<Name>
     */
    public function names(Derivation $derivation, JoinedTable $join, JoinedInput $left, JoinedInput $right): array
    {
        if (!$join->operator->natural()) {
            return $join->using;
        }
        if (!$left->complete() || !$right->complete()) {
            return [];
        }
        $names = [];
        $columns = $derivation->context->columnNames;
        [$first, $second] = $join->operator->keepsRight() ? [$right, $left] : [$left, $right];
        foreach ($first->star as $entry) {
            $name = $first->slot($entry)->name;
            if ($name === null) {
                continue;
            }
            foreach ($second->star as $other) {
                $candidate = $second->slot($other)->name;
                if ($candidate !== null && $columns->equal($candidate->value, $name->value)) {
                    $names[] = $name;
                    break;
                }
            }
        }

        return $names;
    }

    /**
     * Finds the star entry of an operand that a merged name denotes, reporting a name that is missing or selected twice.
     *
     * @param array<int, VisibleRelation> $visible
     * @param list<array{int, int}> $star The star entries of the operand
     */
    public function locate(Derivation $derivation, array $visible, array $star, Name $name, JoinedInput $operand): ?int
    {
        $found = [];
        foreach ($star as $index => [$relation, $position]) {
            $slot = $visible[$relation]->shape->slots[$position];
            if ($slot->name !== null && $derivation->context->columnNames->equal($slot->name->value, $name->value)) {
                $found[] = $index;
            }
        }
        if (count($found) > 1) {
            $derivation->report(new Misuse(MisuseRule::AmbiguousJoinColumn));
        }
        if ($found === [] && $operand->complete()) {
            $derivation->report(new MissingColumn($name));
        }

        return count($found) === 1 ? $found[0] : null;
    }

    /**
     * Makes every column of a relation able to be NULL in the occurrence, keeping the slot it extends as its origin.
     */
    public function extend(VisibleRelation $relation): VisibleRelation
    {
        $slots = [];
        foreach ($relation->shape->slots as $slot) {
            $slots[] = $this->nullable($slot);
        }
        $implicit = [];
        foreach ($relation->implicit as $slot) {
            $implicit[] = new ImplicitSlot($slot->names, $this->nullable($slot->slot));
        }

        return new VisibleRelation($relation->relation, new RowShape($slots, $relation->shape->missing), $relation->alias, $relation->name, $relation->hidden, $implicit);
    }

    /**
     * Answers a slot that can be NULL, extending the given one.
     */
    public function nullable(OutputSlot $slot): OutputSlot
    {
        return $slot->nullability === Nullability::Nullable ? $slot : new OutputSlot($slot->name, $slot->type, Nullability::Nullable, null, $slot, $slot->unnamed);
    }
}
