<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Resolution;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Unification;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Join;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinUsing;
use SqlSemantics\Resolution\ImplicitSlot;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Combines the two sides of a join into the columns and relations the join makes visible.
 *
 * Rule: PG-JOIN-001. The columns of a side are the columns it shows to
 * unqualified names. USING names columns that each side must have exactly
 * once; NATURAL uses every column name both sides have. Each such column is
 * merged into one column, first and in USING (or left) order, followed by
 * the other columns of the left side and then of the right side. The merged
 * column has the common type of the pair (PG-UNIFICATION-001) and is the
 * left column for INNER and LEFT joins, the right column for RIGHT joins and
 * the first non-NULL of both for FULL joins. A LEFT join can extend the right
 * side with NULLs, a RIGHT join the left side, a FULL join both: the columns
 * of an extended side can be NULL in the join and keep their declaration.
 * The join itself is visible to unqualified names only; the relations inside
 * stay visible to qualified names, and a USING alias names the merged
 * columns, for qualified names only. A name a side does not have is reported
 * only when the side is completely known.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-JOIN. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Joining
{
    /**
     * Joins two derived sides.
     */
    public function join(Join $join, JoinedInput $left, JoinedInput $right, Derivation $derivation): JoinedInput
    {
        $visibility = new Visibility();
        $leftColumns = $visibility->star($left->visible);
        $rightColumns = $visibility->star($right->visible);
        $merged = [];
        $usedLeft = [];
        $usedRight = [];
        foreach ($this->names($join, $leftColumns, $rightColumns, $derivation) as $name) {
            $from = $this->locate($leftColumns, $name, $derivation, QueryMisuseRule::UsingColumnNotInLeft);
            $into = $this->locate($rightColumns, $name, $derivation, QueryMisuseRule::UsingColumnNotInRight);
            if ($from === null || $into === null) {
                $missing = [...$leftColumns->missing, ...$rightColumns->missing];
                if ($missing !== []) {
                    $merged[] = new OutputSlot($name, new Dependent($missing), Nullability::Dependent);
                }
                continue;
            }
            $usedLeft[] = $from;
            $usedRight[] = $into;
            $merged[] = $this->merged($join->kind, $leftColumns->slots[$from], $rightColumns->slots[$into], $derivation);
        }
        $slots = [...$merged];
        foreach ($leftColumns->slots as $position => $slot) {
            if (!in_array($position, $usedLeft, true)) {
                $slots[] = $join->kind->extendsLeft() ? $this->nullable($slot) : $slot;
            }
        }
        foreach ($rightColumns->slots as $position => $slot) {
            if (!in_array($position, $usedRight, true)) {
                $slots[] = $join->kind->extendsRight() ? $this->nullable($slot) : $slot;
            }
        }
        $shape = new RowShape($slots, [...$leftColumns->missing, ...$rightColumns->missing]);
        $visible = [new VisibleRelation($join, $shape)];
        if ($join->condition instanceof JoinUsing && $join->condition->alias !== null) {
            $visible[] = $visibility->qualifiedOnly(new VisibleRelation($join, new RowShape($merged), $join->condition->alias));
        }
        foreach ($left->visible as $relation) {
            $visible[] = $visibility->qualifiedOnly($join->kind->extendsLeft() ? $this->extended($relation) : $relation);
        }
        foreach ($right->visible as $relation) {
            $visible[] = $visibility->qualifiedOnly($join->kind->extendsRight() ? $this->extended($relation) : $relation);
        }

        return new JoinedInput(new RelationFact($shape), $visible);
    }

    /**
     * Answers the names of the merged columns: the USING list, or the names both sides of a NATURAL join have.
     *
     * @return list<Name>
     */
    public function names(Join $join, RowShape $left, RowShape $right, Derivation $derivation): array
    {
        $names = $derivation->context->columnNames;
        if ($join->condition instanceof JoinUsing) {
            $seen = [];
            $result = [];
            foreach ($join->condition->columns as $column) {
                $key = $names->fold($column->value);
                if (isset($seen[$key])) {
                    $derivation->report(new QueryMisuse(QueryMisuseRule::UsingColumnRepeated, $column));
                    continue;
                }
                $seen[$key] = true;
                $result[] = $column;
            }

            return $result;
        }
        if (!$join->natural) {
            return [];
        }
        $result = [];
        $seen = [];
        foreach ($left->slots as $slot) {
            if ($slot->name === null || isset($seen[$names->fold($slot->name->value)])) {
                continue;
            }
            foreach ($right->slots as $other) {
                if ($other->name !== null && $names->equal($slot->name->value, $other->name->value)) {
                    $seen[$names->fold($slot->name->value)] = true;
                    $result[] = $slot->name;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Finds the one column of a side with a name, reporting a name the side lacks or repeats when the side is completely known.
     */
    public function locate(RowShape $side, Name $name, Derivation $derivation, QueryMisuseRule $absent): ?int
    {
        $found = [];
        foreach ($side->slots as $position => $slot) {
            if ($slot->name !== null && $derivation->context->columnNames->equal($slot->name->value, $name->value)) {
                $found[] = $position;
            }
        }
        if (count($found) > 1) {
            $derivation->report(new QueryMisuse(QueryMisuseRule::RepeatedCommonColumn, $name));
        }
        if ($found === [] && $side->complete()) {
            $derivation->report(new QueryMisuse($absent, $name));
        }

        return $found[0] ?? null;
    }

    /**
     * Answers the merged column of a pair.
     */
    public function merged(JoinKind $kind, OutputSlot $left, OutputSlot $right, Derivation $derivation): OutputSlot
    {
        $type = (new Unification())->resolve($derivation->context, [$left->type, $right->type], 'JOIN/USING');
        if ($type instanceof Invalid) {
            $derivation->report($type->cause);
        }
        $kept = $kind === JoinKind::Right ? $right : $left;
        $nullability = $kind === JoinKind::Full ? (new Unification())->nullability([$left->nullability, $right->nullability]) : $kept->nullability;

        return new OutputSlot($kept->name, $type, $nullability, null, $kept);
    }

    /**
     * Answers a slot that the join can extend with NULL, referring to the slot it extends.
     */
    public function nullable(OutputSlot $slot): OutputSlot
    {
        return $slot->nullability === Nullability::Nullable ? $slot : new OutputSlot($slot->name, $slot->type, Nullability::Nullable, null, $slot);
    }

    /**
     * Answers a relation whose slots the join can extend with NULL.
     */
    public function extended(VisibleRelation $relation): VisibleRelation
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
}
