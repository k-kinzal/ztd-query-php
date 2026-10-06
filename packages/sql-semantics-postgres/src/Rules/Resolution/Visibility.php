<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Resolution;

use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Tells which relations of a query level a qualifier or a star reaches.
 *
 * Rule: PG-VISIBILITY-001. A relation the hidden list of which holds
 * QUALIFIED_ONLY is visible as a relation name but not for unqualified
 * column names and not to `*`: PostgreSQL treats the tables inside a join
 * that has no alias this way, and the alias of USING columns. Such a relation
 * also lists every slot position as hidden, so that the core column lookup
 * skips it for unqualified names; its system columns are not found by an
 * unqualified name either (`colNameToVar` skips a namespace item whose
 * columns are not visible, and a join has no system columns), so an
 * unqualified name is looked up in a view of the positions without them. A qualifier reaches a relation by its
 * alias, or, without one, by its name and the schema written; `*` reaches
 * every other relation and skips its hidden slots.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-FROM.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Visibility
{
    /**
     * The hidden-list entry that marks a relation as reachable with a qualifier only.
     */
    public const QUALIFIED_ONLY = -1;

    /**
     * Answers the relation reachable by a qualifier only.
     */
    public function qualifiedOnly(VisibleRelation $relation): VisibleRelation
    {
        if ($this->restricted($relation)) {
            return $relation;
        }

        return new VisibleRelation($relation->relation, $relation->shape, $relation->alias, $relation->name, [self::QUALIFIED_ONLY, ...array_keys($relation->shape->slots)], $relation->implicit);
    }

    /**
     * Answers the positions an unqualified column name searches: the same, without the system columns of relations reachable with a qualifier only.
     */
    public function unqualified(Environment $environment): Environment
    {
        $chain = [];
        for ($scope = $environment; $scope !== null; $scope = $scope->outer) {
            $chain[] = $scope;
        }
        $rebuilt = null;
        $changed = false;
        foreach (array_reverse($chain) as $scope) {
            $relations = [];
            foreach ($scope->relations as $relation) {
                if ($relation->implicit !== [] && $this->restricted($relation)) {
                    $relation = new VisibleRelation($relation->relation, $relation->shape, $relation->alias, $relation->name, $relation->hidden);
                    $changed = true;
                }
                $relations[] = $relation;
            }
            $rebuilt = $changed ? new Environment($scope->context, $rebuilt, $relations, $scope->commonTables, $scope->aliases) : $scope;
        }

        return $rebuilt ?? $environment;
    }

    /**
     * Tells whether a relation is reachable with a qualifier only.
     */
    public function restricted(VisibleRelation $relation): bool
    {
        return in_array(self::QUALIFIED_ONLY, $relation->hidden, true);
    }

    /**
     * Tells whether a qualifier names a relation.
     */
    public function admits(Environment $scope, VisibleRelation $relation, QualifiedName $qualifier): bool
    {
        $names = $scope->context->relationNames;
        if ($relation->alias !== null) {
            return $qualifier->schema === null && $qualifier->catalog === null && $names->equal($relation->alias->value, $qualifier->name->value);
        }
        if ($relation->name === null || !$names->equal($relation->name->name->value, $qualifier->name->value)) {
            return false;
        }
        if ($qualifier->schema === null) {
            return true;
        }

        return $relation->name->schema !== null && $names->equal($relation->name->schema->value, $qualifier->schema->value);
    }

    /**
     * Answers the row `*` selects from the relations of a level: every slot that is not hidden, of every relation that is not qualified-only.
     *
     * @param list<VisibleRelation> $relations
     */
    public function star(array $relations): RowShape
    {
        $slots = [];
        $missing = [];
        foreach ($relations as $relation) {
            if ($this->restricted($relation)) {
                continue;
            }
            array_push($slots, ...$this->unhidden($relation));
            array_push($missing, ...$relation->shape->missing);
        }

        return new RowShape($slots, $missing);
    }

    /**
     * Answers the slots of a relation that unqualified names see.
     *
     * @return list<OutputSlot>
     */
    public function unhidden(VisibleRelation $relation): array
    {
        $slots = [];
        foreach ($relation->shape->slots as $position => $slot) {
            if (!in_array($position, $relation->hidden, true)) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }
}
