<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Manipulation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\FromScope;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\TargetTable;
use SqlSemantics\Platform\PostgreSql\Statement\Query\CommonTables;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Target;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Relation;

/**
 * Derives the parts that INSERT, UPDATE, DELETE and MERGE share: common tables, the target, other inputs and RETURNING.
 *
 * Rule: PG-MODIFICATION-SCOPE-001. The common tables of the statement are
 * bound first, in the outer environment, and every query of the statement
 * sees them. The target follows PG-TARGET-TABLE-001. The FROM items of
 * UPDATE, the USING items of DELETE and the source of MERGE follow
 * PG-FROM-SCOPE-001 in the environment of the common tables; they do not
 * see the target. A FROM item that the same name qualifies as the target is
 * reported, unless neither has a correlation name and the two names differ.
 * RETURNING sees the target and the other inputs; its items follow
 * PG-TARGET-001 and PG-STAR-001 and are the rows of the statement, which
 * has no columns without RETURNING.
 * Source: https://www.postgresql.org/docs/17/sql-update.html, https://www.postgresql.org/docs/17/sql-delete.html,
 * https://www.postgresql.org/docs/17/dml-returning.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ModificationScope
{
    /**
     * Binds the common tables and derives the target.
     *
     * @return array{Environment, VisibleRelation} The environment of the common tables, and the target as a visible relation
     */
    public function open(?CommonTables $with, TargetTable $target, Derivation $derivation, Environment $outer): array
    {
        $base = $with === null ? $outer : $with->deriveCommonTables($derivation, $outer);
        $fact = $derivation->relation($target, $base);

        return [$base, (new Targets())->visible($target, $fact)];
    }

    /**
     * Derives the other inputs of the statement and answers the relations they make visible.
     *
     * @return list<VisibleRelation>
     */
    public function inputs(?Relation $items, VisibleRelation $target, Derivation $derivation, Environment $base): array
    {
        if ($items === null) {
            return [];
        }
        $visible = (new FromScope())->open($items, $derivation, $base, [])->visible;
        $this->conflicts($target, $visible, $derivation);

        return $visible;
    }

    /**
     * Reports the visible relations that the name of the target also qualifies.
     *
     * @param list<VisibleRelation> $others
     */
    public function conflicts(VisibleRelation $target, array $others, Derivation $derivation): void
    {
        $names = $derivation->context->relationNames;
        $own = $target->alias ?? $target->name?->name;
        foreach ($others as $other) {
            $name = $other->alias ?? $other->name?->name;
            if ($own === null || $name === null || !$names->equal($own->value, $name->value)) {
                continue;
            }
            $unaliased = $target->alias === null && $other->alias === null;
            $schemas = [$target->name?->schema?->value, $other->name?->schema?->value];
            if ($unaliased && ($schemas[0] === null) !== ($schemas[1] === null)) {
                continue;
            }
            if ($unaliased && $schemas[0] !== null && $schemas[1] !== null && !$names->equal($schemas[0], $schemas[1])) {
                continue;
            }
            $derivation->report(new ManipulationMisuse(ManipulationMisuseRule::RepeatedRelationName, $own->value));
        }
    }

    /**
     * Derives RETURNING and answers the rows of the statement.
     *
     * @param list<Target> $returning
     * @param list<VisibleRelation> $visible
     */
    public function returning(array $returning, array $visible, Derivation $derivation, Environment $base): QueryFact
    {
        $environment = new Environment($derivation->context, $base, $visible);
        $items = [];
        foreach ($returning as $target) {
            array_push($items, ...$target->project($derivation, $environment, count($items)));
        }

        return new QueryFact($items, $derivation->context->columnNames);
    }
}
