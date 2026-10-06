<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\FromScope;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Relation;

/**
 * Derives the FROM items of CREATE STATISTICS and the scope of its columns and expressions.
 *
 * Rule: PG-STATISTICS-SCOPE-001. The FROM items are derived as the FROM
 * items of a query (PG-FROM-SCOPE-001), each seeing the ones before it; the
 * columns and expressions see the relations the items make visible. The
 * server accepts a single table and reports anything else. Source:
 * https://www.postgresql.org/docs/17/sql-createstatistics.html. Termination:
 * one pass over the items. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class StatisticsScope
{
    /**
     * Derives the FROM items and answers the scope of the key terms.
     *
     * @param list<Relation> $from
     */
    public function derive(array $from, Derivation $derivation): Environment
    {
        $visible = [];
        $scope = new FromScope();
        foreach ($from as $item) {
            array_push($visible, ...$scope->open($item, $derivation, $derivation->environment(), $visible)->visible);
        }

        return new Environment($derivation->context, null, $visible);
    }
}
