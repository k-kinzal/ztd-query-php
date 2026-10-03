<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Resolution;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Relation\DerivedQuery;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinOn;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinStep;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinUsing;
use SqlSemantics\Platform\Sqlite\Statement\Relation\NestedInput;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableCall;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Derives the input relations of a FROM clause and the relations they make visible to names.
 *
 * Rule: SQLITE-FROM-SCOPE-001. A table, a table-valued function and a
 * derived query are each one visible relation under their correlation name,
 * or under their table name when they have none. The arguments of a
 * table-valued function see the relations to its left; a derived query sees
 * none of its siblings. A chain combines its terms from left to right by
 * SQLITE-JOIN-001 and derives every ON condition where all its relations are
 * visible. Parentheses around several terms keep the relations inside
 * visible and add their correlation name for the joined row; parentheses
 * around one term replace its correlation name with their own, except that
 * plain parentheses at the very start of a FROM clause change nothing.
 * Terminates: chains are walked in a loop; recursion follows parentheses.
 * Source: https://sqlite.org/lang_select.html#the_from_clause. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class FromScope
{
    /**
     * Derives one term, records its facts and answers what it makes visible.
     *
     * @param list<VisibleRelation> $left The relations to the left of the term in its chain
     * @param bool $leading Whether the term starts a FROM clause and carries no constraint
     */
    public function open(Relation $term, Derivation $derivation, Environment $outer, array $left = [], bool $leading = true): JoinedInput
    {
        if ($term instanceof JoinChain || $term instanceof NestedInput) {
            $input = $this->enter($term, $derivation, $outer, $left, $leading);
            $derivation->target($term, $input->fact);

            return $input;
        }
        $fact = $derivation->relation($term, $term instanceof TableCall ? new Environment($derivation->context, $outer, $left) : $outer);
        $named = $term instanceof NamedRelation || $term instanceof TableCall;
        $alias = $term instanceof NamedRelation ? $term->alias() : ($term instanceof TableCall || $term instanceof DerivedQuery ? $term->alias : null);

        return new JoinedInput($fact, [new VisibleRelation($term, $fact->shape, $alias, $named ? ($term instanceof NamedRelation ? $term->name() : $term->name) : null, [], (new TableShapes())->implicit($fact))]);
    }

    /**
     * Derives the terms of a chain or of parentheses without recording the facts of the node itself.
     *
     * @param list<VisibleRelation> $left The relations to the left of the node in its chain
     */
    public function enter(JoinChain|NestedInput $node, Derivation $derivation, Environment $outer, array $left, bool $leading): JoinedInput
    {
        if ($node instanceof NestedInput) {
            return $this->nested($node, $derivation, $outer, $leading);
        }
        $visible = $this->open($node->first, $derivation, $outer, $left, $leading && $node->constraint === null)->visible;
        foreach ($node->steps as $step) {
            $term = $this->open($step->relation, $derivation, $outer, [...$left, ...$visible], false);
            if (!$step->operator->valid()) {
                $derivation->report(new Misuse(MisuseRule::UnknownJoinType));
            }
            if ($step->operator->natural() && $step->constraint !== null) {
                $derivation->report(new Misuse(MisuseRule::NaturalJoinWithConstraint));
            }
            $visible = (new Joining())->join($derivation, $visible, $term->visible, $step);
        }
        $environment = new Environment($derivation->context, $outer, [...$left, ...$visible]);
        if ($node->constraint !== null) {
            $derivation->report(new Misuse($node->constraint instanceof JoinOn ? MisuseRule::OnWithoutJoin : MisuseRule::UsingWithoutJoin));
        }
        foreach ([$node->constraint, ...array_map(static fn (JoinStep $step): JoinOn|JoinUsing|null => $step->constraint, $node->steps)] as $constraint) {
            if ($constraint instanceof JoinOn) {
                $derivation->scalar($constraint->condition, $environment);
            }
        }

        return new JoinedInput(new RelationFact($this->star($visible)), $visible);
    }

    /**
     * Derives the terms inside parentheses and answers what the parentheses make visible.
     *
     * Around one term the parentheses replace its correlation name with their
     * own, unless they are plain parentheses at the very start of a FROM
     * clause; around several terms the relations inside stay visible, and a
     * correlation name on the parentheses names the joined row, reachable
     * with that qualifier only.
     */
    public function nested(NestedInput $node, Derivation $derivation, Environment $outer, bool $leading): JoinedInput
    {
        $input = $this->open($node->relation, $derivation, $outer, [], $leading && $node->alias === null);
        $single = count($input->visible) === 1;
        $visible = [];
        foreach ($input->visible as $relation) {
            if ($single && ($node->alias !== null || !$leading)) {
                $visible[] = new VisibleRelation($relation->relation, $relation->shape, $node->alias, $relation->name, $relation->hidden, $relation->implicit);
            } elseif ($single || $leading || $node->alias !== null || !in_array(ColumnResolver::QUALIFIED_ONLY, $relation->hidden, true)) {
                $visible[] = $relation;
            }
        }
        if ($node->alias !== null && !$single) {
            $visible[] = new VisibleRelation($node, $input->fact->shape, $node->alias, null, [ColumnResolver::QUALIFIED_ONLY]);
        }

        return new JoinedInput($input->fact, $visible);
    }

    /**
     * Answers the row that `*` selects from visible relations: every column except the merged ones, without the relations reachable by qualifier only.
     *
     * @param list<VisibleRelation> $visible
     */
    public function star(array $visible): RowShape
    {
        $slots = [];
        $missing = [];
        foreach ($visible as $relation) {
            if (in_array(ColumnResolver::QUALIFIED_ONLY, $relation->hidden, true)) {
                continue;
            }
            foreach ($relation->shape->slots as $position => $slot) {
                if (!in_array($position, $relation->hidden, true)) {
                    $slots[] = $slot;
                }
            }
            array_push($missing, ...$relation->shape->missing);
        }

        return new RowShape($slots, $missing);
    }
}
