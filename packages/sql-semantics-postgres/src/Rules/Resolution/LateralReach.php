<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Resolution;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\WholeRows;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\DerivedTable;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\AmbiguousColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Tells which FROM items a lateral item on the right of a join may reference.
 *
 * Rule: PG-LATERAL-JOIN-001. A lateral item (a function call, XMLTABLE,
 * JSON_TABLE or a LATERAL subquery) on the right of a join sees the items on
 * the left of the join. When the join is RIGHT or FULL, those items are still
 * visible, but referencing one of them is an error: `transformFromClauseItem`
 * marks them `p_lateral_ok = false` and `check_lateral_ref_ok` reports
 * "invalid reference to FROM-clause entry for table" as soon as a column, a
 * qualifier or a whole row reaches one. Such a relation carries the BARRED
 * entry in its hidden list for the lateral item only. The name reported is
 * the alias, the relation name, `unnamed_subquery` or `unnamed_join`.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-LATERAL.
 * Termination: one pass over the relations of each level. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class LateralReach
{
    /**
     * The hidden-list entry that marks a relation a lateral item sees but must not reference.
     */
    public const BARRED = -2;

    /**
     * Answers the relations as the right side of a RIGHT or FULL join sees them: visible, but barred.
     *
     * @param list<VisibleRelation> $relations
     * @return list<VisibleRelation>
     */
    public function bar(array $relations): array
    {
        $barred = [];
        foreach ($relations as $relation) {
            $barred[] = $this->barred($relation) ? $relation : new VisibleRelation($relation->relation, $relation->shape, $relation->alias, $relation->name, [self::BARRED, ...$relation->hidden], $relation->implicit);
        }

        return $barred;
    }

    /**
     * Tells whether a relation must not be referenced at this position.
     */
    public function barred(VisibleRelation $relation): bool
    {
        return in_array(self::BARRED, $relation->hidden, true);
    }

    /**
     * Answers the barred relation a column resolution or a qualifier reaches, or null when it reaches none.
     */
    public function reached(Environment $environment, Resolution $resolution, ?QualifiedName $qualifier): ?VisibleRelation
    {
        if ($qualifier !== null) {
            foreach ((new WholeRows())->find($environment, $qualifier) as $relation) {
                if ($this->barred($relation)) {
                    return $relation;
                }
            }

            return null;
        }
        $found = match (true) {
            $resolution instanceof ResolvedColumn => [$resolution],
            $resolution instanceof AmbiguousColumn => $resolution->candidates,
            default => [],
        };
        foreach ($found as $column) {
            $scope = $environment;
            for ($depth = $column->depth; $depth > 0 && $scope !== null; $depth--) {
                $scope = $scope->outer;
            }
            foreach ($scope === null ? [] : $scope->relations as $relation) {
                if ($relation->relation === $column->relation && $this->barred($relation)) {
                    return $relation;
                }
            }
        }

        return null;
    }

    /**
     * Reports the reference to a barred relation and answers the fact of the reference.
     */
    public function report(Derivation $derivation, VisibleRelation $relation): ScalarFact
    {
        $name = $relation->alias ?? $relation->name?->name ?? new Name($relation->relation instanceof DerivedTable ? 'unnamed_subquery' : 'unnamed_join');
        $problem = new QueryMisuse(QueryMisuseRule::LateralJoinType, $name);
        $derivation->report($problem);

        return new ScalarFact(new Invalid($problem), Nullability::Dependent);
    }
}
