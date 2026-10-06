<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Mutation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Definition\RelationKinds;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnResolver;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertInto;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Upsert;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\KindRefusal;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;

/**
 * Derives the facts of an INSERT statement.
 *
 * Rule: SQLITE-INSERT-001. The source query is derived where the common
 * tables of the statement are visible; it does not see the written table.
 * The source must supply one value per column of the column list, or per
 * column of the table when no list is written; another count is reported
 * when both counts are known. In an ON CONFLICT clause the conflict target
 * sees the written table; DO UPDATE sees the written table and, under the
 * qualifier `excluded`, the row that could not be inserted. An ON CONFLICT
 * clause on a view is reported (SQLITE-RELATION-KIND-001). RETURNING
 * follows SQLITE-MUTATION-SCOPE-001.
 * Source: https://sqlite.org/lang_insert.html, https://sqlite.org/lang_upsert.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class InsertFacts
{
    /**
     * Derives every part of an INSERT and answers the rows it returns, or null when it returns none.
     *
     * @param Query|null $source The query that supplies the rows; null for DEFAULT VALUES
     * @param list<Upsert> $upserts
     * @param list<ResultColumn|Star|TableStar> $returning
     */
    public function derive(InsertInto $into, ?Query $source, array $upserts, array $returning, Derivation $derivation, Environment $outer): ?QueryFact
    {
        $scope = new MutationScope();
        [$base, $target, $written] = $scope->open($into->target, $into->with, $derivation, $outer);
        if ($upserts !== []) {
            (new RelationKinds())->refuse($written, KindRefusal::Upsert, $derivation);
        }
        $scope->names($into->columns, $target, $derivation);
        if ($source !== null) {
            $rows = $derivation->query($source, $base);
            $expected = $into->columns !== [] ? count($into->columns) : ($target->shape->complete() ? count($target->shape->slots) : null);
            if ($expected !== null && $rows->shape->complete() && count($rows->shape->slots) !== $expected) {
                $derivation->report(new ArityMismatch(ArityRule::InsertedValues, $expected, count($rows->shape->slots)));
            }
        }
        $excluded = new VisibleRelation($into->target, $target->shape, new Name('excluded'), null, [ColumnResolver::QUALIFIED_ONLY], $target->implicit);
        foreach ($upserts as $upsert) {
            $environment = new Environment($derivation->context, $base, [$target]);
            foreach ($upsert->target === null ? [] : $upsert->target->terms as $term) {
                $derivation->scalar($term->expression, $environment);
            }
            if ($upsert->target?->where !== null) {
                $derivation->scalar($upsert->target->where, $environment);
            }
            $update = new Environment($derivation->context, $base, [$target, $excluded]);
            $scope->assign($upsert->assignments, $target, $derivation, $update);
            if ($upsert->where !== null) {
                $derivation->scalar($upsert->where, $update);
            }
        }

        return $scope->returning($returning, $target, $derivation, $base);
    }
}
