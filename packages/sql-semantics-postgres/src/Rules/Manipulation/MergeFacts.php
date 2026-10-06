<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\DefaultRequest;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeInsert;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeMatch;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeUpdate;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeWhen;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the facts of MERGE.
 *
 * Rule: PG-MERGE-001. The common tables, the target and the source follow
 * PG-MODIFICATION-SCOPE-001. The join condition sees the target and the
 * source. A WHEN MATCHED clause sees both; a WHEN NOT MATCHED BY SOURCE
 * clause sees the target only; a WHEN NOT MATCHED [BY TARGET] clause sees
 * the source only. UPDATE SET follows PG-ASSIGNMENT-001; INSERT follows the
 * column and value rules of PG-INSERT-001 for one VALUES row. A WHEN clause
 * after a clause of the same kind without condition can never apply and is
 * reported. RETURNING (PostgreSQL 17) sees the target and the source;
 * with a WHEN NOT MATCHED BY SOURCE clause the source columns can be NULL.
 * MERGE_ACTION() is admitted in RETURNING only (PG-PLACEMENT-001).
 * Source: https://www.postgresql.org/docs/17/sql-merge.html,
 * https://www.postgresql.org/docs/17/functions-merge-support.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class MergeFacts
{
    /**
     * Derives a MERGE and answers its rows.
     */
    public function derive(Merge $merge, Derivation $derivation, Environment $outer): QueryFact
    {
        $scope = new ModificationScope();
        [$base, $target] = $scope->open($merge->with, $merge->target, $derivation, $outer, true);
        $source = $scope->inputs($merge->source, $target, $derivation, $base);
        $derivation->scalar($merge->condition, new Environment($derivation->context, $base, [$target, ...$source]));
        $defaults = [];
        $unconditional = [];
        foreach ($merge->clauses as $clause) {
            if (isset($unconditional[$clause->match->name])) {
                $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::UnreachableWhen));
            }
            if ($clause->condition === null) {
                $unconditional[$clause->match->name] = true;
            }
            $visible = match ($clause->match) {
                MergeMatch::Matched => [$target, ...$source],
                MergeMatch::NotMatchedBySource => [$target],
                MergeMatch::NotMatched => $source,
            };
            array_push($defaults, ...$this->clause($clause, $target, $derivation, new Environment($derivation->context, $base, $visible)));
        }
        if ($this->bySource($merge)) {
            $source = $this->nullable($source);
        }
        (new Placement())->values($merge, $defaults, $merge->returning, $derivation);

        return $scope->returning($merge->returning, [$target, ...$source], $derivation, $base);
    }

    /**
     * Derives one WHEN clause and answers the DEFAULT values it admits.
     *
     * @return list<DefaultRequest>
     */
    public function clause(MergeWhen $clause, VisibleRelation $target, Derivation $derivation, Environment $environment): array
    {
        if ($clause->condition !== null) {
            $derivation->scalar($clause->condition, $environment);
        }
        $action = $clause->action;
        $assignments = new Assignments();
        if ($action instanceof MergeUpdate) {
            $assignments->assign($action->assignments, $target, $derivation, $environment);

            return $assignments->defaults($action->assignments);
        }
        if (!$action instanceof MergeInsert) {
            return [];
        }
        $facts = new InsertFacts();
        $slots = $facts->columns($action->columns, $target, $derivation, $environment);
        $defaults = [];
        foreach ($action->values as $position => $value) {
            $assignments->value($slots[$position] ?? null, $value, $derivation->scalar($value, $environment)->type, $derivation);
            if ($value instanceof DefaultRequest) {
                $defaults[] = $value;
            }
        }
        $facts->arity($action->columns, $target, count($action->values), $derivation);

        return $defaults;
    }

    /**
     * Tells whether a MERGE has a WHEN NOT MATCHED BY SOURCE clause.
     */
    public function bySource(Merge $merge): bool
    {
        foreach ($merge->clauses as $clause) {
            if ($clause->match === MergeMatch::NotMatchedBySource) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the relations with every column able to be NULL.
     *
     * @param list<VisibleRelation> $relations
     *
     * @return list<VisibleRelation>
     */
    public function nullable(array $relations): array
    {
        $result = [];
        foreach ($relations as $relation) {
            $slots = [];
            foreach ($relation->shape->slots as $slot) {
                $slots[] = new OutputSlot($slot->name, $slot->type, Nullability::Nullable, null, $slot);
            }
            $result[] = new VisibleRelation($relation->relation, new RowShape($slots, $relation->shape->missing), $relation->alias, $relation->name, $relation->hidden, $relation->implicit);
        }

        return $result;
    }
}
